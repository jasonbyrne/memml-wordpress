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
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_memml_test_connection', array( $this, 'test_connection' ) );
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
		$organization_key = isset( $input['organization_key'] ) ? trim( sanitize_text_field( $input['organization_key'] ) ) : '';
		$base_url         = isset( $input['base_url'] ) ? untrailingslashit( esc_url_raw( $input['base_url'] ) ) : '';

		if ( '' === $organization_key ) {
			add_settings_error(
				self::OPTION_NAME,
				'memml_missing_organization_key',
				__( 'Enter your Memml organization key.', 'memml' )
			);
		} elseif ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $organization_key ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'memml_invalid_organization_key',
				__( 'The organization key may contain only letters, numbers, hyphens, and underscores.', 'memml' )
			);
			$organization_key = '';
		}

		if ( '' === $base_url || ! preg_match( '#^https?://#i', $base_url ) ) {
			$base_url = Memml_Feed_Client::DEFAULT_BASE_URL;
		}

		return array(
			'organization_key' => $organization_key,
			'base_url'         => $base_url,
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

		$options = self::get_options();
		?>
		<div class="wrap memml-settings">
			<h1><?php echo esc_html__( 'Memml Calendar Settings', 'memml' ); ?></h1>
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
							<p class="description"><?php echo esc_html__( 'The key used in your Memml public feed URL.', 'memml' ); ?></p>
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
				<?php submit_button(); ?>
				<button class="button" id="memml-test-connection" type="button">
					<?php echo esc_html__( 'Test connection', 'memml' ); ?>
				</button>
				<span aria-live="polite" class="memml-connection-result" id="memml-connection-result"></span>
			</form>
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
			'organization_key' => '',
			'base_url'         => Memml_Feed_Client::DEFAULT_BASE_URL,
		);
	}
}
