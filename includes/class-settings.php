<?php
/**
 * Plugin settings and connection test.
 *
 * @package Memml
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages the Memml settings screen.
 */
final class Memml_Settings {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'memml_settings';

	/**
	 * Settings-page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'memml';

	/**
	 * Cache-refresh admin-post action.
	 *
	 * @var string
	 */
	public const FLUSH_ACTION = 'memml_refresh_cache';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_setup_notice' ) );
		add_action( 'wp_ajax_memml_test_connection', array( $this, 'test_connection' ) );
		add_action( 'admin_post_' . self::FLUSH_ACTION, array( $this, 'flush_cache' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( MEMML_PLUGIN_FILE ),
			array( $this, 'add_plugin_action_links' )
		);
	}

	/**
	 * Adds a Settings shortcut to the plugin's Plugins-screen row.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self::get_page_url() ),
				esc_html__( 'Settings', 'memml' )
			)
		);

		return $links;
	}

	/**
	 * Points administrators at the settings screen until a key is saved.
	 *
	 * @return void
	 */
	public function render_setup_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if (
			! current_user_can( 'manage_options' ) ||
			'' !== self::get_options()['organization_key'] ||
			( $screen && 'settings_page_' . self::PAGE_SLUG === $screen->id )
		) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Memml Calendar needs your organization key before it can display events.', 'memml' ),
			esc_url( self::get_page_url() ),
			esc_html__( 'Open Memml Calendar settings', 'memml' )
		);
	}

	/**
	 * Discards cached feed responses so the next page view refetches them.
	 *
	 * @return void
	 */
	public function flush_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to manage these settings.', 'memml' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::FLUSH_ACTION );

		$options = self::get_options();

		if ( '' !== $options['organization_key'] ) {
			$client = new Memml_Feed_Client( $options['base_url'] );
			$client->flush_cache( $options['organization_key'] );
		}

		wp_safe_redirect( add_query_arg( 'memml-refreshed', '1', self::get_page_url() ) );
		exit;
	}

	/**
	 * Gets the settings-page URL.
	 *
	 * @return string
	 */
	public static function get_page_url() {
		return admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Adds the Settings submenu.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Memml Calendar Settings', 'memml' ),
			__( 'Memml Calendar', 'memml' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers the settings option.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'memml',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => self::get_defaults(),
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitizes settings.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input            = is_array( $input ) ? $input : array();
		$saved            = self::get_options();
		$organization_key = isset( $input['organization_key'] ) ? trim( sanitize_text_field( $input['organization_key'] ) ) : '';
		$base_url         = isset( $input['base_url'] ) ? untrailingslashit( esc_url_raw( $input['base_url'] ) ) : '';

		if ( '' === $organization_key ) {
			add_settings_error(
				self::OPTION_NAME,
				'memml_missing_organization_key',
				__( 'Enter your Memml organization key. Until one is saved, Memml calendars will not display.', 'memml' ),
				'warning'
			);
		} elseif ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $organization_key ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'memml_invalid_organization_key',
				__( 'The organization key may contain only letters, numbers, hyphens, and underscores. Your previously saved key was kept.', 'memml' )
			);
			$organization_key = $saved['organization_key'];
		}

		if ( '' === $base_url || ! preg_match( '#^https?://#i', $base_url ) ) {
			$base_url = Memml_Feed_Client::DEFAULT_BASE_URL;
		}

		if ( $organization_key !== $saved['organization_key'] || $base_url !== $saved['base_url'] ) {
			$client = new Memml_Feed_Client( $saved['base_url'] );
			$client->flush_cache( $saved['organization_key'] );
		}

		$default_calendar   = isset( $input['default_calendar'] ) ? $input['default_calendar'] : '';
		$default_view       = isset( $input['default_view'] ) ? $input['default_view'] : '';
		$default_period     = isset( $input['default_period'] ) ? $input['default_period'] : '';
		$default_list_style = isset( $input['default_list_style'] ) ? $input['default_list_style'] : '';
		$default_limit      = isset( $input['default_limit'] ) ? max( 0, (int) $input['default_limit'] ) : 0;

		return array(
			'organization_key'   => $organization_key,
			'base_url'           => $base_url,
			'default_calendar'   => 'volunteers' === $default_calendar ? 'volunteers' : 'events',
			'default_view'       => 'month' === $default_view ? 'month' : 'list',
			'default_period'     => 'past' === $default_period ? 'past' : 'upcoming',
			'default_list_style' => 'rows' === $default_list_style ? 'rows' : 'grid',
			'default_limit'      => $default_limit,
			'subscribe_links'    => ! empty( $input['subscribe_links'] ),
		);
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options     = self::get_options();
		$is_refresh  = isset( $_GET['memml-refreshed'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag set by an already-verified redirect.
		$is_ready    = '' !== $options['organization_key'];
		$feed_client = new Memml_Feed_Client( $options['base_url'] );
		$feed_urls   = $is_ready ? $feed_client->get_feed_urls( $options['organization_key'] ) : array();
		?>
		<div class="wrap memml-settings">
			<h1><?php echo esc_html__( 'Memml Calendar Settings', 'memml' ); ?></h1>
			<?php if ( $is_refresh ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__( 'Cached Memml data was cleared. The next page view will load fresh data.', 'memml' ); ?></p>
				</div>
			<?php endif; ?>
			<p><?php echo esc_html__( 'Connect WordPress to your organization’s unauthenticated Memml public feeds.', 'memml' ); ?></p>
			<form action="options.php" method="post">
				<?php settings_fields( 'memml' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="memml-organization-key"><?php echo esc_html__( 'Organization key', 'memml' ); ?></label>
						</th>
						<td>
							<input
								class="regular-text"
								id="memml-organization-key"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[organization_key]"
								pattern="[A-Za-z0-9_-]+"
								required
								type="text"
								value="<?php echo esc_attr( $options['organization_key'] ); ?>"
							/>
							<p class="description">
								<?php
								printf(
									/* translators: 1: Example feed URL, 2: Organization key within that URL. */
									esc_html__( 'The key used in your Memml public feed URL. In %1$s the key is %2$s.', 'memml' ),
									'<code>/api/public/v1/river-city-neighbors/events.json</code>',
									'<code>river-city-neighbors</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>
				<h2><?php echo esc_html__( 'Display defaults', 'memml' ); ?></h2>
				<p class="description">
					<?php echo esc_html__( 'Calendars use these settings unless a block or shortcode sets its own.', 'memml' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="memml-default-calendar"><?php echo esc_html__( 'Initial calendar', 'memml' ); ?></label>
						</th>
						<td>
							<select
								id="memml-default-calendar"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_calendar]"
							>
								<option value="events" <?php selected( 'volunteers' !== $options['default_calendar'] ); ?>><?php echo esc_html__( 'Events', 'memml' ); ?></option>
								<option value="volunteers" <?php selected( 'volunteers' === $options['default_calendar'] ); ?>><?php echo esc_html__( 'Volunteer Opportunities', 'memml' ); ?></option>
							</select>
							<p class="description"><?php echo esc_html__( 'Used by the combined Memml Calendar until a visitor chooses another calendar.', 'memml' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="memml-default-view"><?php echo esc_html__( 'Initial view', 'memml' ); ?></label>
						</th>
						<td>
							<select
								id="memml-default-view"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_view]"
							>
								<option value="list" <?php selected( 'month' !== $options['default_view'] ); ?>><?php echo esc_html__( 'List', 'memml' ); ?></option>
								<option value="month" <?php selected( 'month' === $options['default_view'] ); ?>><?php echo esc_html__( 'Month', 'memml' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="memml-default-period"><?php echo esc_html__( 'Initial list filter', 'memml' ); ?></label>
						</th>
						<td>
							<select
								id="memml-default-period"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_period]"
							>
								<option value="upcoming" <?php selected( 'past' !== $options['default_period'] ); ?>><?php echo esc_html__( 'Upcoming', 'memml' ); ?></option>
								<option value="past" <?php selected( 'past' === $options['default_period'] ); ?>><?php echo esc_html__( 'Past', 'memml' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="memml-default-list-style"><?php echo esc_html__( 'List style', 'memml' ); ?></label>
						</th>
						<td>
							<select
								id="memml-default-list-style"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_list_style]"
							>
								<option value="grid" <?php selected( 'rows' !== $options['default_list_style'] ); ?>><?php echo esc_html__( 'Cards', 'memml' ); ?></option>
								<option value="rows" <?php selected( 'rows' === $options['default_list_style'] ); ?>><?php echo esc_html__( 'Compact rows', 'memml' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="memml-default-limit"><?php echo esc_html__( 'Maximum items in list view', 'memml' ); ?></label>
						</th>
						<td>
							<input
								class="small-text"
								id="memml-default-limit"
								min="0"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_limit]"
								step="1"
								type="number"
								value="<?php echo esc_attr( $options['default_limit'] ); ?>"
							/>
							<p class="description"><?php echo esc_html__( 'Enter 0 to show every item. Month view always shows every item.', 'memml' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Subscribe links', 'memml' ); ?></th>
						<td>
							<label for="memml-subscribe-links">
								<input
									id="memml-subscribe-links"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[subscribe_links]"
									type="checkbox"
									value="1"
									<?php checked( ! empty( $options['subscribe_links'] ) ); ?>
								/>
								<?php echo esc_html__( 'Offer Google Calendar, Apple / Outlook, and RSS subscription links above each calendar.', 'memml' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<details class="memml-advanced-settings">
					<summary><?php echo esc_html__( 'Advanced', 'memml' ); ?></summary>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="memml-base-url"><?php echo esc_html__( 'Memml base URL', 'memml' ); ?></label>
							</th>
							<td>
								<input
									class="regular-text code"
									id="memml-base-url"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[base_url]"
									type="url"
									value="<?php echo esc_attr( $options['base_url'] ); ?>"
								/>
								<p class="description"><?php echo esc_html__( 'Change this only when testing against another Memml environment.', 'memml' ); ?></p>
							</td>
						</tr>
					</table>
				</details>
				<p class="submit">
					<?php submit_button( null, 'primary', 'submit', false ); ?>
					<button class="button" id="memml-test-connection" type="button">
						<?php echo esc_html__( 'Test connection', 'memml' ); ?>
					</button>
					<span aria-live="polite" class="memml-connection-result" id="memml-connection-result"></span>
				</p>
				<p class="description">
					<?php echo esc_html__( 'Test connection checks the values in the fields above without saving them.', 'memml' ); ?>
				</p>
			</form>

			<h2><?php echo esc_html__( 'Cached feed data', 'memml' ); ?></h2>
			<?php if ( $is_ready ) : ?>
				<p>
					<?php
					printf(
						/* translators: %d: Number of minutes Memml responses are cached. */
						esc_html( _n( 'Memml responses are cached for about %d minute.', 'Memml responses are cached for about %d minutes.', (int) round( Memml_Feed_Client::DEFAULT_CACHE_TTL / 60 ), 'memml' ) ),
						(int) round( Memml_Feed_Client::DEFAULT_CACHE_TTL / 60 )
					);
					echo ' ';
					echo esc_html__( 'Clear the cache to publish a Memml change on your site right away.', 'memml' );
					?>
				</p>
				<p><?php echo esc_html__( 'This site reads:', 'memml' ); ?></p>
				<ul class="memml-feed-urls">
					<?php foreach ( $feed_urls as $feed_url ) : ?>
						<li><code><?php echo esc_html( $feed_url ); ?></code></li>
					<?php endforeach; ?>
				</ul>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input name="action" type="hidden" value="<?php echo esc_attr( self::FLUSH_ACTION ); ?>" />
					<?php wp_nonce_field( self::FLUSH_ACTION ); ?>
					<?php submit_button( __( 'Clear cached data', 'memml' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<p><?php echo esc_html__( 'Save an organization key to see the feeds this site will read.', 'memml' ); ?></p>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Adding a calendar to a page', 'memml' ); ?></h2>
			<p><?php echo esc_html__( 'Add the Memml Calendar, Memml Events, or Memml Volunteers block to any page, or use the matching shortcode:', 'memml' ); ?></p>
			<ul class="memml-shortcodes">
				<li><code>[memml_calendar]</code> — <?php echo esc_html__( 'events and volunteer opportunities, with a switcher.', 'memml' ); ?></li>
				<li><code>[memml_events]</code> — <?php echo esc_html__( 'events only.', 'memml' ); ?></li>
				<li><code>[memml_volunteers]</code> — <?php echo esc_html__( 'volunteer opportunities only.', 'memml' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Loads settings-page JavaScript.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'memml-admin',
			MEMML_PLUGIN_URL . 'assets/admin.js',
			array(),
			MEMML_VERSION,
			true
		);

		wp_localize_script(
			'memml-admin',
			'memmlAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'memml_test_connection' ),
				'testing'      => __( 'Testing…', 'memml' ),
				'unknownError' => __( 'The connection test could not be completed.', 'memml' ),
			)
		);
	}

	/**
	 * Handles the authenticated connection-test request.
	 *
	 * @return void
	 */
	public function test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to manage these settings.', 'memml' ) ),
				403
			);
		}

		check_ajax_referer( 'memml_test_connection', 'nonce' );

		$organization_key = isset( $_POST['organizationKey'] )
			? sanitize_text_field( wp_unslash( $_POST['organizationKey'] ) )
			: '';
		$base_url         = isset( $_POST['baseUrl'] )
			? esc_url_raw( wp_unslash( $_POST['baseUrl'] ) )
			: Memml_Feed_Client::DEFAULT_BASE_URL;
		$client           = new Memml_Feed_Client( $base_url );
		$result           = $client->get_events( $organization_key, true );

		if ( is_wp_error( $result ) ) {
			$status = 'memml_organization_not_found' === $result->get_error_code() ? 404 : 502;

			wp_send_json_error(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				$status
			);
		}

		$organization_name = $result['data']['organization']['name'];

		wp_send_json_success(
			array(
				'organization' => $organization_name,
				'message'      => sprintf(
					/* translators: %s: Memml organization name. */
					__( 'Connected to %s.', 'memml' ),
					$organization_name
				),
			)
		);
	}

	/**
	 * Gets settings with defaults applied.
	 *
	 * @return array
	 */
	public static function get_options() {
		$options = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( is_array( $options ) ? $options : array(), self::get_defaults() );
	}

	/**
	 * Gets default settings.
	 *
	 * @return array
	 */
	private static function get_defaults() {
		return array(
			'organization_key'   => '',
			'base_url'           => Memml_Feed_Client::DEFAULT_BASE_URL,
			'default_calendar'   => 'events',
			'default_view'       => 'list',
			'default_period'     => 'upcoming',
			'default_list_style' => 'grid',
			'default_limit'      => 0,
			'subscribe_links'    => true,
		);
	}
}
