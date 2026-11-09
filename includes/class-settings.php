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
	 * Feed-refresh admin-post action.
	 *
	 * @var string
	 */
	public const FLUSH_ACTION = 'memml_refresh_cache';

	/**
	 * Settings-preview admin-post action.
	 *
	 * @var string
	 */
	public const PREVIEW_ACTION = 'memml_preview';

	/**
	 * Preference names in each resettable settings section.
	 *
	 * @var array
	 */
	public const PREFERENCE_SECTIONS = array(
		'display'    => array(
			'default_calendar',
			'default_view',
			'default_period',
			'default_limit',
			'calendar_switcher',
			'layout_switcher',
			'period_switcher',
			'subscribe_links',
		),
		'content'    => array(
			'show_images',
			'show_descriptions',
			'show_item_count',
			'show_details',
			'show_venue_cost',
			'show_volunteer_availability',
			'show_cancelled_events',
			'show_rsvp',
			'show_registration',
			'show_online',
			'show_volunteer_signup',
			'show_add_to_calendar',
			'show_event_page',
		),
		'empty'      => array(
			'empty_events_mode',
			'empty_events_message',
			'empty_events_link_url',
			'empty_events_link_label',
			'empty_volunteers_mode',
			'empty_volunteers_message',
			'empty_volunteers_link_url',
			'empty_volunteers_link_label',
		),
		'appearance' => array(
			'default_list_style',
			'accent_color',
			'appearance_palette',
			'appearance_spacing',
			'appearance_corners',
			'image_size',
		),
	);

	/**
	 * Appearance preset choices; the first choice is the default.
	 *
	 * @var array
	 */
	public const APPEARANCE_CHOICES = array(
		'appearance_palette' => array( 'light', 'neutral', 'theme' ),
		'appearance_spacing' => array( 'comfortable', 'compact' ),
		'appearance_corners' => array( 'rounded', 'square' ),
		'image_size'         => array( 'wide', 'compact' ),
	);

	/**
	 * What a calendar shows when it has nothing upcoming; the first is the default.
	 *
	 * @var array
	 */
	public const EMPTY_STATE_MODES = array( 'standard', 'custom', 'hide' );

	/**
	 * Feeds with their own empty-calendar preferences.
	 *
	 * @var array
	 */
	public const EMPTY_STATE_FEEDS = array( 'events', 'volunteers' );

	/**
	 * Longest accepted empty-calendar message, in characters.
	 *
	 * @var int
	 */
	public const EMPTY_MESSAGE_MAX_LENGTH = 500;

	/**
	 * Longest accepted empty-calendar link text, in characters.
	 *
	 * @var int
	 */
	public const EMPTY_LINK_LABEL_MAX_LENGTH = 80;

	/**
	 * The calendar's built-in accent color, shown when no custom color is set.
	 *
	 * @var string
	 */
	public const DEFAULT_ACCENT = '#2563eb';

	/**
	 * Calendars the settings preview can show, mapped to their shortcodes.
	 *
	 * @var array
	 */
	public const PREVIEW_CALENDARS = array(
		'calendar'   => 'memml_calendar',
		'events'     => 'memml_events',
		'volunteers' => 'memml_volunteers',
	);

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
		add_action( 'admin_post_' . self::FLUSH_ACTION, array( $this, 'refresh_feeds' ) );
		add_action( 'admin_post_' . self::PREVIEW_ACTION, array( $this, 'render_preview' ) );
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
	 * Checks Memml for one feed or every listed feed right away, then clears
	 * the cached pages that show them.
	 *
	 * A failed check keeps the saved copy, so visitors never lose a working
	 * calendar because of a refresh.
	 *
	 * @return void
	 */
	public function refresh_feeds() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to manage these settings.', 'memml' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::FLUSH_ACTION );

		$options   = self::get_options();
		$requested = isset( $_POST['feed'] ) ? sanitize_key( wp_unslash( $_POST['feed'] ) ) : 'all';
		$refreshed = array();
		$failed    = array();

		if ( '' !== $options['organization_key'] ) {
			$client   = new Memml_Feed_Client( $options['base_url'] );
			$statuses = self::get_feed_statuses( $client, $options['organization_key'] );

			foreach ( self::get_refresh_feeds( $requested, $statuses ) as $feed ) {
				$result = $client->refresh_feed( $options['organization_key'], $feed );

				if ( is_array( $result ) && empty( $result['is_stale'] ) ) {
					$refreshed[] = $feed;
				} else {
					$failed[] = $feed;
				}
			}
		}

		Memml_Page_Cache::purge();

		wp_safe_redirect(
			add_query_arg(
				array(
					'memml-refreshed'      => implode( ',', $refreshed ),
					'memml-refresh-failed' => implode( ',', $failed ),
				),
				self::get_page_url()
			) . '#memml-section-status'
		);
		exit;
	}

	/**
	 * Gets the plain-language name of each feed.
	 *
	 * @return array
	 */
	public static function get_feed_labels() {
		return array(
			'events'     => __( 'Events', 'memml' ),
			'volunteers' => __( 'Volunteer opportunities', 'memml' ),
			'committees' => __( 'Committees', 'memml' ),
		);
	}

	/**
	 * Gets the status of each feed listed in the Status section.
	 *
	 * Events and volunteer opportunities are always listed. Committees are
	 * listed once a page has shown them, so sites without a committee roster
	 * are not asked about a feed they do not use.
	 *
	 * @param Memml_Feed_Client $client           Feed client.
	 * @param string            $organization_key Memml organization key.
	 * @return array Statuses keyed by feed.
	 */
	public static function get_feed_statuses( $client, $organization_key ) {
		$statuses = array();

		foreach ( array_keys( Memml_Feed_Client::FEEDS ) as $feed ) {
			$status = $client->get_feed_status( $organization_key, $feed );

			if ( 'committees' !== $feed || 'not_loaded' !== $status['state'] ) {
				$statuses[ $feed ] = $status;
			}
		}

		return $statuses;
	}

	/**
	 * Gets the feeds a refresh request covers.
	 *
	 * @param string $requested A feed identifier, or "all".
	 * @param array  $statuses  Listed feed statuses keyed by feed.
	 * @return array Feed identifiers.
	 */
	public static function get_refresh_feeds( $requested, $statuses ) {
		if ( 'all' === $requested ) {
			return array_keys( $statuses );
		}

		return isset( Memml_Feed_Client::FEEDS[ $requested ] ) ? array( $requested ) : array();
	}

	/**
	 * Describes a feed status in plain language.
	 *
	 * @param array $status Feed status from Memml_Feed_Client::get_feed_status().
	 * @param int   $now    Current timestamp, for tests.
	 * @return array Short label and one-sentence summary.
	 */
	public static function describe_feed_status( $status, $now = 0 ) {
		$now     = $now > 0 ? (int) $now : time();
		$updated = $status['fetched_at'] > 0 ? human_time_diff( $status['fetched_at'], $now ) : '';

		switch ( $status['state'] ) {
			case 'fresh':
				return array(
					'label'   => __( 'Up to date', 'memml' ),
					/* translators: %s: Time since Memml was last checked, such as "3 mins". */
					'summary' => sprintf( __( 'Visitors see the latest copy from Memml, checked %s ago.', 'memml' ), $updated ),
				);
			case 'cached':
				return array(
					'label'   => __( 'Due for a check', 'memml' ),
					/* translators: %s: Age of the saved copy, such as "2 hours". */
					'summary' => sprintf( __( 'Visitors see the copy from %s ago. The next page view checks Memml for changes.', 'memml' ), $updated ),
				);
			case 'stale':
				return array(
					'label'   => __( 'Showing an older copy', 'memml' ),
					/* translators: %s: Age of the saved copy, such as "2 hours". */
					'summary' => sprintf( __( 'The latest check with Memml did not succeed, so visitors see the copy from %s ago. The plugin tries again shortly.', 'memml' ), $updated ),
				);
			case 'error':
				return array(
					'label'   => __( 'Not available', 'memml' ),
					'summary' => __( 'The latest check with Memml did not succeed and there is no copy to show, so calendars show a message instead of items. The plugin tries again shortly.', 'memml' ),
				);
			default:
				return array(
					'label'   => __( 'Not loaded yet', 'memml' ),
					'summary' => __( 'No page has shown this feed since it was last loaded. Select Refresh to load it now.', 'memml' ),
				);
		}
	}

	/**
	 * Formats a timestamp in the site's date and time format.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private static function format_timestamp( $timestamp ) {
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Gets a readable list of feed names from a query-string value.
	 *
	 * @param string $value Comma-separated feed identifiers.
	 * @return string
	 */
	private static function get_feed_names( $value ) {
		$labels = self::get_feed_labels();
		$names  = array();

		foreach ( explode( ',', $value ) as $feed ) {
			if ( isset( $labels[ $feed ] ) ) {
				$names[] = $labels[ $feed ];
			}
		}

		return function_exists( 'wp_sprintf_l' ) ? wp_sprintf_l( '%l', $names ) : implode( ', ', $names );
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

		return array_merge(
			array(
				'organization_key' => $organization_key,
				'base_url'         => $base_url,
			),
			self::sanitize_preferences( $input )
		);
	}

	/**
	 * Sanitizes the display, content, action, and appearance preferences.
	 *
	 * Has no side effects, so the settings preview can use it for unsaved
	 * form values. Unchecked checkboxes are absent from a submission and
	 * resolve to false.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array
	 */
	public static function sanitize_preferences( $input ) {
		$input              = is_array( $input ) ? $input : array();
		$default_calendar   = isset( $input['default_calendar'] ) ? $input['default_calendar'] : '';
		$default_view       = isset( $input['default_view'] ) ? $input['default_view'] : '';
		$default_period     = isset( $input['default_period'] ) ? $input['default_period'] : '';
		$default_list_style = isset( $input['default_list_style'] ) ? $input['default_list_style'] : '';
		$default_limit      = isset( $input['default_limit'] ) ? max( 0, (int) $input['default_limit'] ) : 0;
		$accent_color       = isset( $input['accent_color'] ) && is_string( $input['accent_color'] ) ? strtolower( trim( $input['accent_color'] ) ) : '';
		$appearance         = array();

		foreach ( self::APPEARANCE_CHOICES as $name => $choices ) {
			$value               = isset( $input[ $name ] ) ? $input[ $name ] : '';
			$appearance[ $name ] = in_array( $value, $choices, true ) ? $value : $choices[0];
		}

		// The color field always has a value, so the checkbox decides whether
		// it is used; an unchecked box keeps the calendar's own accent.
		if ( empty( $input['accent_custom'] ) || ! preg_match( '/^#[0-9a-f]{6}$/', $accent_color ) ) {
			$accent_color = '';
		}

		return $appearance + self::sanitize_empty_states( $input ) + array(
			'accent_color'                => $accent_color,
			'default_calendar'            => 'volunteers' === $default_calendar ? 'volunteers' : 'events',
			'default_view'                => 'month' === $default_view ? 'month' : 'list',
			'default_period'              => 'past' === $default_period ? 'past' : 'upcoming',
			'default_list_style'          => 'rows' === $default_list_style ? 'rows' : 'grid',
			'default_limit'               => $default_limit,
			'calendar_switcher'           => ! empty( $input['calendar_switcher'] ),
			'layout_switcher'             => ! empty( $input['layout_switcher'] ),
			'period_switcher'             => ! empty( $input['period_switcher'] ),
			'subscribe_links'             => ! empty( $input['subscribe_links'] ),
			'show_images'                 => ! empty( $input['show_images'] ),
			'show_descriptions'           => ! empty( $input['show_descriptions'] ),
			'show_item_count'             => ! empty( $input['show_item_count'] ),
			'show_details'                => ! empty( $input['show_details'] ),
			'show_venue_cost'             => ! empty( $input['show_venue_cost'] ),
			'show_volunteer_availability' => ! empty( $input['show_volunteer_availability'] ),
			'show_cancelled_events'       => ! empty( $input['show_cancelled_events'] ),
			'show_rsvp'                   => ! empty( $input['show_rsvp'] ),
			'show_registration'           => ! empty( $input['show_registration'] ),
			'show_online'                 => ! empty( $input['show_online'] ),
			'show_volunteer_signup'       => ! empty( $input['show_volunteer_signup'] ),
			'show_add_to_calendar'        => ! empty( $input['show_add_to_calendar'] ),
			'show_event_page'             => ! empty( $input['show_event_page'] ),
		);
	}

	/**
	 * Sanitizes each feed's empty-calendar preferences.
	 *
	 * @param array $input Submitted settings.
	 * @return array
	 */
	private static function sanitize_empty_states( $input ) {
		$states = array();

		foreach ( self::EMPTY_STATE_FEEDS as $feed ) {
			$prefix = 'empty_' . $feed . '_';
			$mode   = isset( $input[ $prefix . 'mode' ] ) ? $input[ $prefix . 'mode' ] : '';

			$states[ $prefix . 'mode' ]       = in_array( $mode, self::EMPTY_STATE_MODES, true ) ? $mode : self::EMPTY_STATE_MODES[0];
			$states[ $prefix . 'message' ]    = self::sanitize_empty_text( isset( $input[ $prefix . 'message' ] ) ? $input[ $prefix . 'message' ] : '', self::EMPTY_MESSAGE_MAX_LENGTH );
			$states[ $prefix . 'link_url' ]   = self::sanitize_link_url( isset( $input[ $prefix . 'link_url' ] ) ? $input[ $prefix . 'link_url' ] : '' );
			$states[ $prefix . 'link_label' ] = self::sanitize_empty_text( isset( $input[ $prefix . 'link_label' ] ) ? $input[ $prefix . 'link_label' ] : '', self::EMPTY_LINK_LABEL_MAX_LENGTH );
		}

		return $states;
	}

	/**
	 * Sanitizes plain empty-calendar text to one line of limited length.
	 *
	 * @param mixed $value      Submitted text.
	 * @param int   $max_length Longest accepted length, in characters.
	 * @return string
	 */
	public static function sanitize_empty_text( $value, $max_length ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = sanitize_text_field( $value );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length ) : substr( $value, 0, $max_length );
	}

	/**
	 * Sanitizes an empty-calendar link address.
	 *
	 * Accepts web addresses, email addresses, and paths on this site. An
	 * address typed without https:// gets it, since administrators often
	 * paste "example.org/join", and a bare email address becomes a mailto link.
	 *
	 * @param mixed $value Submitted address.
	 * @return string Safe address, or '' when unusable.
	 */
	public static function sanitize_link_url( $value ) {
		$url = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $url ) {
			return '';
		}

		if ( '/' === $url[0] ) {
			// A path on this site; "//host" would leave the site.
			return '/' === substr( $url, 1, 1 ) ? '' : esc_url_raw( $url );
		}

		if ( preg_match( '/^[^\s@:\/]+@[^\s@:\/]+\.[^\s@:\/]+$/', $url ) ) {
			$url = 'mailto:' . $url;
		} elseif ( ! preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) ) {
			$url = 'https://' . $url;
		}

		if ( preg_match( '/^mailto:[^\s@]+@[^\s@]+$/i', $url ) ) {
			return esc_url_raw( $url, array( 'mailto' ) );
		}

		return preg_match( '#^https?://[^/\s]#i', $url ) ? esc_url_raw( $url, array( 'http', 'https' ) ) : '';
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only results set by an already-verified redirect.
		$refreshed_names = isset( $_GET['memml-refreshed'] ) ? self::get_feed_names( sanitize_text_field( wp_unslash( $_GET['memml-refreshed'] ) ) ) : '';
		$failed_names    = isset( $_GET['memml-refresh-failed'] ) ? self::get_feed_names( sanitize_text_field( wp_unslash( $_GET['memml-refresh-failed'] ) ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$is_ready            = '' !== $options['organization_key'];
		$feed_client         = new Memml_Feed_Client( $options['base_url'] );
		$feed_urls           = $is_ready ? $feed_client->get_feed_urls( $options['organization_key'] ) : array();
		$feed_statuses       = $is_ready ? self::get_feed_statuses( $feed_client, $options['organization_key'] ) : array();
		$feed_labels         = self::get_feed_labels();
		$content_preferences = array(
			'show_images'                 => __( 'Images', 'memml' ),
			'show_descriptions'           => __( 'Descriptions', 'memml' ),
			'show_item_count'             => __( 'Item count above lists', 'memml' ),
			'show_details'                => __( 'Clickable details dialog', 'memml' ),
			'show_venue_cost'             => __( 'Venue, location, and cost', 'memml' ),
			'show_volunteer_availability' => __( 'Volunteer availability and “Volunteers needed” status', 'memml' ),
			'show_cancelled_events'       => __( 'Cancelled events', 'memml' ),
		);
		$action_preferences  = array(
			'show_rsvp'             => __( 'RSVP actions', 'memml' ),
			'show_registration'     => __( 'Registration actions', 'memml' ),
			'show_online'           => __( 'Join online actions', 'memml' ),
			'show_volunteer_signup' => __( 'Volunteer signup actions', 'memml' ),
			'show_add_to_calendar'  => __( 'Add-to-calendar actions', 'memml' ),
			'show_event_page'       => __( 'View event page links', 'memml' ),
		);
		$toolbar_preferences = array(
			'calendar_switcher' => __( 'Let visitors switch between Events and Volunteer Opportunities on the combined calendar.', 'memml' ),
			'layout_switcher'   => __( 'Let visitors switch between List and Month.', 'memml' ),
			'period_switcher'   => __( 'Let visitors switch between Upcoming and Past in List view.', 'memml' ),
			'subscribe_links'   => __( 'Show the subscription row with Google Calendar, Apple / Outlook, and RSS links.', 'memml' ),
		);
		?>
		<div class="wrap memml-settings">
			<h1><?php echo esc_html__( 'Memml Calendar Settings', 'memml' ); ?></h1>
			<?php if ( '' !== $refreshed_names ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %s: List of feed names, such as "Events and Volunteer opportunities". */
							esc_html__( 'Refreshed from Memml: %s. Your pages now show the latest information.', 'memml' ),
							esc_html( $refreshed_names )
						);
						?>
					</p>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $failed_names ) : ?>
				<div class="notice notice-error is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %s: List of feed names, such as "Events and Volunteer opportunities". */
							esc_html__( 'Could not refresh from Memml: %s. Visitors keep seeing the last copy that loaded, if there is one. See Status for details.', 'memml' ),
							esc_html( $failed_names )
						);
						?>
					</p>
				</div>
			<?php endif; ?>
			<p class="memml-settings__intro">
				<?php echo esc_html__( 'These are site-wide settings. Every Memml block set to “Use site setting” and every shortcode without its own properties follows them. Blocks and shortcodes that make their own choices keep those choices.', 'memml' ); ?>
			</p>
			<div class="memml-settings__layout">
				<div class="memml-settings__main">
					<form action="options.php" id="memml-settings-form" method="post">
						<?php settings_fields( 'memml' ); ?>

						<section aria-labelledby="memml-section-connection" class="memml-settings__section">
							<?php $this->render_section_heading( 'connection', __( 'Connection', 'memml' ) ); ?>
							<p class="description"><?php echo esc_html__( 'Connect WordPress to your organization’s unauthenticated Memml public feeds.', 'memml' ); ?></p>
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
										<p class="memml-settings__test">
											<button class="button" id="memml-test-connection" type="button">
												<?php echo esc_html__( 'Test connection', 'memml' ); ?>
											</button>
											<span aria-live="polite" class="memml-connection-result" id="memml-connection-result"></span>
										</p>
										<p class="description">
											<?php echo esc_html__( 'Test connection checks the values in this section without saving them.', 'memml' ); ?>
										</p>
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
						</section>

						<section aria-labelledby="memml-section-display" class="memml-settings__section">
							<?php $this->render_section_heading( 'display', __( 'Calendar display', 'memml' ) ); ?>
							<p class="description"><?php echo esc_html__( 'What visitors see first, and which controls they can use to change it.', 'memml' ); ?></p>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row">
										<label for="memml-default-calendar"><?php echo esc_html__( 'Initial calendar', 'memml' ); ?></label>
									</th>
									<td>
										<?php
										$this->render_select(
											'default_calendar',
											$options['default_calendar'],
											array(
												'events' => __( 'Events', 'memml' ),
												'volunteers' => __( 'Volunteer Opportunities', 'memml' ),
											)
										);
										?>
										<p class="description"><?php echo esc_html__( 'Used by the combined Memml Calendar until a visitor chooses another calendar.', 'memml' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="memml-default-view"><?php echo esc_html__( 'Initial view', 'memml' ); ?></label>
									</th>
									<td>
										<?php
										$this->render_select(
											'default_view',
											$options['default_view'],
											array(
												'list'  => __( 'List', 'memml' ),
												'month' => __( 'Month', 'memml' ),
											)
										);
										?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="memml-default-period"><?php echo esc_html__( 'Initial list filter', 'memml' ); ?></label>
									</th>
									<td>
										<?php
										$this->render_select(
											'default_period',
											$options['default_period'],
											array(
												'upcoming' => __( 'Upcoming', 'memml' ),
												'past'     => __( 'Past', 'memml' ),
											)
										);
										?>
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
									<th scope="row"><?php echo esc_html__( 'Visitor toolbar', 'memml' ); ?></th>
									<td>
										<?php $this->render_checkboxes( __( 'Visitor toolbar', 'memml' ), $toolbar_preferences, $options ); ?>
										<p class="description"><?php echo esc_html__( 'When a switcher is hidden, the initial calendar, view, or list filter above stays fixed.', 'memml' ); ?></p>
									</td>
								</tr>
							</table>
						</section>

						<section aria-labelledby="memml-section-content" class="memml-settings__section">
							<?php $this->render_section_heading( 'content', __( 'Content and actions', 'memml' ) ); ?>
							<p class="description"><?php echo esc_html__( 'Choose the information and actions calendars show.', 'memml' ); ?></p>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php echo esc_html__( 'Content', 'memml' ); ?></th>
									<td><?php $this->render_checkboxes( __( 'Visible calendar content', 'memml' ), $content_preferences, $options ); ?></td>
								</tr>
								<tr>
									<th scope="row"><?php echo esc_html__( 'Actions', 'memml' ); ?></th>
									<td><?php $this->render_checkboxes( __( 'Visible calendar actions', 'memml' ), $action_preferences, $options ); ?></td>
								</tr>
							</table>
						</section>

						<section aria-labelledby="memml-section-empty" class="memml-settings__section">
							<?php $this->render_section_heading( 'empty', __( 'Empty calendars', 'memml' ) ); ?>
							<p class="description"><?php echo esc_html__( 'What visitors see when a calendar has nothing upcoming. A calendar fixed to the Past list uses its past items instead. If Memml cannot be reached and no earlier copy is saved, visitors always see a short “temporarily unavailable” note, never your message, so they know to check back.', 'memml' ); ?></p>
							<table class="form-table" role="presentation">
								<?php foreach ( self::get_empty_state_labels() as $feed => $feed_label ) : ?>
									<?php $this->render_empty_state_row( $feed, $feed_label, $options ); ?>
								<?php endforeach; ?>
							</table>
						</section>

						<section aria-labelledby="memml-section-appearance" class="memml-settings__section">
							<?php $this->render_section_heading( 'appearance', __( 'Appearance', 'memml' ) ); ?>
							<p class="description"><?php echo esc_html__( 'Calendars use your theme’s fonts. These presets adjust colors, spacing, and shapes, and your theme’s CSS can still change anything further.', 'memml' ); ?></p>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><?php echo esc_html__( 'Accent color', 'memml' ); ?></th>
									<td>
										<fieldset class="memml-settings__accent">
											<legend class="screen-reader-text"><?php echo esc_html__( 'Accent color', 'memml' ); ?></legend>
											<label for="memml-accent-custom">
												<input
													id="memml-accent-custom"
													name="<?php echo esc_attr( self::OPTION_NAME ); ?>[accent_custom]"
													type="checkbox"
													value="1"
													<?php checked( '' !== $options['accent_color'] ); ?>
												/>
												<?php echo esc_html__( 'Use a custom accent color', 'memml' ); ?>
											</label>
											<label for="memml-accent-color">
												<span class="screen-reader-text"><?php echo esc_html__( 'Custom accent color', 'memml' ); ?></span>
												<input
													id="memml-accent-color"
													name="<?php echo esc_attr( self::OPTION_NAME ); ?>[accent_color]"
													type="color"
													value="<?php echo esc_attr( '' !== $options['accent_color'] ? $options['accent_color'] : self::DEFAULT_ACCENT ); ?>"
												/>
											</label>
										</fieldset>
										<p class="description"><?php echo esc_html__( 'Used for buttons, highlights, and the current day. Button text switches between white and dark automatically so it stays readable.', 'memml' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="memml-appearance-palette"><?php echo esc_html__( 'Palette', 'memml' ); ?></label>
									</th>
									<td>
										<?php
										$this->render_select(
											'appearance_palette',
											$options['appearance_palette'],
											array(
												'light'   => __( 'Light', 'memml' ),
												'neutral' => __( 'Neutral', 'memml' ),
												'theme'   => __( 'Theme colors', 'memml' ),
											)
										);
										?>
										<p class="description"><?php echo esc_html__( 'Light uses white cards. Neutral uses soft gray cards. Theme colors follows your theme’s text color with lightly tinted cards, which suits dark themes.', 'memml' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="memml-appearance-spacing"><?php echo esc_html__( 'Spacing', 'memml' ); ?></label>
									</th>
									<td>
										<?php
										$this->render_select(
											'appearance_spacing',
											$options['appearance_spacing'],
											array(
												'comfortable' => __( 'Comfortable', 'memml' ),
												'compact' => __( 'Compact', 'memml' ),
											)
										);
										?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="memml-appearance-corners"><?php echo esc_html__( 'Corners', 'memml' ); ?></label>
									</th>
									<td>
										<?php
										$this->render_select(
											'appearance_corners',
											$options['appearance_corners'],
											array(
												'rounded' => __( 'Rounded', 'memml' ),
												'square'  => __( 'Square', 'memml' ),
											)
										);
										?>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="memml-image-size"><?php echo esc_html__( 'Card images', 'memml' ); ?></label>
									</th>
									<td>
										<?php
										$this->render_select(
											'image_size',
											$options['image_size'],
											array(
												'wide'    => __( 'Wide', 'memml' ),
												'compact' => __( 'Compact', 'memml' ),
											)
										);
										?>
										<p class="description"><?php echo esc_html__( 'Compact images are shorter banners. To hide images, turn off Images under Content and actions.', 'memml' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="memml-default-list-style"><?php echo esc_html__( 'List style', 'memml' ); ?></label>
									</th>
									<td>
										<?php
										$this->render_select(
											'default_list_style',
											$options['default_list_style'],
											array(
												'grid' => __( 'Cards', 'memml' ),
												'rows' => __( 'Compact rows', 'memml' ),
											)
										);
										?>
										<p class="description"><?php echo esc_html__( 'Cards arrange items in columns. Compact rows stack them full width, which suits a narrow column or long descriptions.', 'memml' ); ?></p>
									</td>
								</tr>
							</table>
						</section>

						<?php submit_button(); ?>
					</form>

					<section aria-labelledby="memml-section-status" class="memml-settings__section">
						<?php $this->render_section_heading( 'status', __( 'Status', 'memml' ) ); ?>
						<?php if ( $is_ready ) : ?>
							<p>
								<?php
								printf(
									/* translators: %d: Number of minutes between checks for Memml changes. */
									esc_html( _n( 'Your site checks Memml for changes about every %d minute.', 'Your site checks Memml for changes about every %d minutes.', (int) round( Memml_Feed_Client::DEFAULT_CACHE_TTL / 60 ), 'memml' ) ),
									(int) round( Memml_Feed_Client::DEFAULT_CACHE_TTL / 60 )
								);
								echo ' ';
								echo esc_html__( 'Refresh to show a Memml change on your site right away. If Memml cannot be reached, visitors keep seeing the last copy that loaded.', 'memml' );
								?>
							</p>
							<p class="description">
								<?php echo esc_html__( 'If your site uses LiteSpeed Cache, pages that show Memml content follow the same schedule, and refreshing here or saving these settings clears those pages too. If your site uses a different page cache, clear that cache as well.', 'memml' ); ?>
							</p>
							<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="memml-feed-status" method="post">
								<input name="action" type="hidden" value="<?php echo esc_attr( self::FLUSH_ACTION ); ?>" />
								<?php wp_nonce_field( self::FLUSH_ACTION ); ?>
								<div class="memml-feed-status__cards">
									<?php foreach ( $feed_statuses as $feed => $status ) : ?>
										<?php $this->render_feed_card( $feed, $feed_labels[ $feed ], $status, $feed_urls[ $feed ] ); ?>
									<?php endforeach; ?>
								</div>
								<p>
									<button class="button button-secondary" name="feed" type="submit" value="all">
										<?php echo esc_html__( 'Refresh all', 'memml' ); ?>
									</button>
								</p>
							</form>
						<?php else : ?>
							<p><?php echo esc_html__( 'Save an organization key to see the feeds this site will read.', 'memml' ); ?></p>
						<?php endif; ?>
					</section>

					<section aria-labelledby="memml-section-usage" class="memml-settings__section">
						<?php $this->render_section_heading( 'usage', __( 'Adding a calendar to a page', 'memml' ) ); ?>
						<p><?php echo esc_html__( 'Add the Memml Calendar, Memml Events, or Memml Volunteers block to any page, or use the matching shortcode:', 'memml' ); ?></p>
						<ul class="memml-shortcodes">
							<li><code>[memml_calendar]</code> — <?php echo esc_html__( 'events and volunteer opportunities, with a switcher.', 'memml' ); ?></li>
							<li><code>[memml_events]</code> — <?php echo esc_html__( 'events only.', 'memml' ); ?></li>
							<li><code>[memml_volunteers]</code> — <?php echo esc_html__( 'volunteer opportunities only.', 'memml' ); ?></li>
						</ul>
					</section>
				</div>

				<aside aria-labelledby="memml-preview-heading" class="memml-settings__preview">
					<div class="memml-settings__preview-header">
						<h2 id="memml-preview-heading"><?php echo esc_html__( 'Preview', 'memml' ); ?></h2>
						<?php if ( $is_ready ) : ?>
							<label for="memml-preview-calendar">
								<span class="screen-reader-text"><?php echo esc_html__( 'Calendar to preview', 'memml' ); ?></span>
								<select id="memml-preview-calendar">
									<option value="calendar"><?php echo esc_html__( 'Memml Calendar', 'memml' ); ?></option>
									<option value="events"><?php echo esc_html__( 'Memml Events', 'memml' ); ?></option>
									<option value="volunteers"><?php echo esc_html__( 'Memml Volunteers', 'memml' ); ?></option>
								</select>
							</label>
						<?php endif; ?>
					</div>
					<?php if ( $is_ready ) : ?>
						<p class="memml-settings__preview-option">
							<label for="memml-preview-empty">
								<input id="memml-preview-empty" type="checkbox" value="1" />
								<?php echo esc_html__( 'Show with nothing upcoming', 'memml' ); ?>
							</label>
						</p>
						<p class="description">
							<?php echo esc_html__( 'Shows the choices on this page before you save them, using your saved organization key. Your site’s pages may differ slightly where your theme styles them.', 'memml' ); ?>
						</p>
						<iframe
							class="memml-settings__preview-frame"
							id="memml-preview-frame"
							src="<?php echo esc_url( self::get_preview_url() ); ?>"
							title="<?php echo esc_attr__( 'Calendar preview', 'memml' ); ?>"
						></iframe>
					<?php else : ?>
						<p><?php echo esc_html__( 'Save an organization key to preview your calendar.', 'memml' ); ?></p>
					<?php endif; ?>
				</aside>
			</div>
		</div>
		<?php
	}

	/**
	 * Gets the feeds with empty-calendar preferences, mapped to their names.
	 *
	 * @return array
	 */
	public static function get_empty_state_labels() {
		return array(
			'events'     => __( 'Events', 'memml' ),
			'volunteers' => __( 'Volunteer opportunities', 'memml' ),
		);
	}

	/**
	 * Renders one feed's empty-calendar choice, message, and link fields.
	 *
	 * @param string $feed       Feed identifier.
	 * @param string $feed_label Feed name.
	 * @param array  $options    Current settings.
	 * @return void
	 */
	private function render_empty_state_row( $feed, $feed_label, $options ) {
		$prefix  = 'empty_' . $feed . '_';
		$id_base = 'memml-empty-' . $feed . '-';
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $id_base . 'mode' ); ?>"><?php echo esc_html( $feed_label ); ?></label>
			</th>
			<td>
				<?php
				$this->render_select(
					$prefix . 'mode',
					$options[ $prefix . 'mode' ],
					array(
						'standard' => __( 'Show the standard message', 'memml' ),
						'custom'   => __( 'Show my message', 'memml' ),
						'hide'     => __( 'Hide the calendar', 'memml' ),
					)
				);
				?>
				<p class="description">
					<?php
					echo esc_html(
						'volunteers' === $feed
							? __( 'The standard message is “No upcoming volunteer opportunities are currently available.” Hiding leaves the Volunteer Opportunities choice off the combined calendar until something is upcoming.', 'memml' )
							: __( 'The standard message is “No upcoming events are currently available.” Hiding leaves the Events choice off the combined calendar until something is upcoming.', 'memml' )
					);
					?>
				</p>
				<div class="memml-settings__empty-fields">
					<p>
						<label for="<?php echo esc_attr( $id_base . 'message' ); ?>"><?php echo esc_html__( 'Your message', 'memml' ); ?></label><br />
						<input
							class="large-text"
							id="<?php echo esc_attr( $id_base . 'message' ); ?>"
							maxlength="<?php echo esc_attr( self::EMPTY_MESSAGE_MAX_LENGTH ); ?>"
							name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $prefix . 'message' ); ?>]"
							type="text"
							value="<?php echo esc_attr( $options[ $prefix . 'message' ] ); ?>"
						/>
					</p>
					<p>
						<label for="<?php echo esc_attr( $id_base . 'link-url' ); ?>"><?php echo esc_html__( 'Link address (optional)', 'memml' ); ?></label><br />
						<input
							class="regular-text code"
							id="<?php echo esc_attr( $id_base . 'link-url' ); ?>"
							name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $prefix . 'link_url' ); ?>]"
							placeholder="https://"
							type="text"
							value="<?php echo esc_attr( $options[ $prefix . 'link_url' ] ); ?>"
						/>
					</p>
					<p>
						<label for="<?php echo esc_attr( $id_base . 'link-label' ); ?>"><?php echo esc_html__( 'Link text', 'memml' ); ?></label><br />
						<input
							class="regular-text"
							id="<?php echo esc_attr( $id_base . 'link-label' ); ?>"
							maxlength="<?php echo esc_attr( self::EMPTY_LINK_LABEL_MAX_LENGTH ); ?>"
							name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $prefix . 'link_label' ); ?>]"
							placeholder="<?php echo esc_attr__( 'Learn more', 'memml' ); ?>"
							type="text"
							value="<?php echo esc_attr( $options[ $prefix . 'link_label' ] ); ?>"
						/>
					</p>
					<p class="description"><?php echo esc_html__( 'Used with “Show my message.” Leave the message blank to keep the standard wording with your link. The link can be a web address, an email address, or a page on this site such as /get-involved.', 'memml' ); ?></p>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders one feed's status card with its refresh button.
	 *
	 * @param string $feed     Feed identifier.
	 * @param string $label    Feed name.
	 * @param array  $status   Feed status.
	 * @param string $feed_url Feed URL.
	 * @return void
	 */
	private function render_feed_card( $feed, $label, $status, $feed_url ) {
		$description = self::describe_feed_status( $status );
		$heading_id  = 'memml-feed-' . $feed;
		?>
		<section aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" class="memml-feed-card is-<?php echo esc_attr( str_replace( '_', '-', $status['state'] ) ); ?>">
			<div class="memml-feed-card__header">
				<h3 id="<?php echo esc_attr( $heading_id ); ?>"><?php echo esc_html( $label ); ?></h3>
				<span class="memml-feed-card__state"><?php echo esc_html( $description['label'] ); ?></span>
			</div>
			<p class="memml-feed-card__summary"><?php echo esc_html( $description['summary'] ); ?></p>
			<dl class="memml-feed-card__details">
				<?php if ( '' !== $status['organization'] ) : ?>
					<dt><?php echo esc_html__( 'Organization', 'memml' ); ?></dt>
					<dd><?php echo esc_html( $status['organization'] ); ?></dd>
				<?php endif; ?>
				<?php if ( '' !== $status['timezone'] ) : ?>
					<dt><?php echo esc_html__( 'Timezone', 'memml' ); ?></dt>
					<dd><?php echo esc_html( $status['timezone'] ); ?></dd>
				<?php endif; ?>
				<?php if ( null !== $status['item_count'] ) : ?>
					<dt><?php echo esc_html__( 'Items', 'memml' ); ?></dt>
					<dd><?php echo esc_html( number_format_i18n( $status['item_count'] ) ); ?></dd>
				<?php endif; ?>
				<dt><?php echo esc_html__( 'Last updated from Memml', 'memml' ); ?></dt>
				<dd><?php echo esc_html( $status['fetched_at'] > 0 ? self::format_timestamp( $status['fetched_at'] ) : __( 'Not yet', 'memml' ) ); ?></dd>
				<?php if ( null !== $status['error'] ) : ?>
					<dt><?php echo esc_html__( 'Latest problem', 'memml' ); ?></dt>
					<dd>
						<?php
						echo esc_html( $status['error'] );

						if ( $status['failed_at'] > 0 ) {
							echo ' ';
							printf(
								/* translators: %s: Date and time of the failed check. */
								esc_html__( '(%s)', 'memml' ),
								esc_html( self::format_timestamp( $status['failed_at'] ) )
							);
						}
						?>
					</dd>
				<?php endif; ?>
			</dl>
			<details class="memml-feed-card__address">
				<summary><?php echo esc_html__( 'Feed address', 'memml' ); ?></summary>
				<code><?php echo esc_html( $feed_url ); ?></code>
			</details>
			<button class="button" name="feed" type="submit" value="<?php echo esc_attr( $feed ); ?>">
				<?php
				printf(
					/* translators: %s: Feed name, such as "Events". */
					esc_html__( 'Refresh %s', 'memml' ),
					esc_html( $label )
				);
				?>
			</button>
		</section>
		<?php
	}

	/**
	 * Renders a section heading, with a reset action for preference sections.
	 *
	 * The reset button only fills in the form, so it starts hidden and the
	 * settings script reveals it.
	 *
	 * @param string $section Section identifier.
	 * @param string $label   Section heading.
	 * @return void
	 */
	private function render_section_heading( $section, $label ) {
		?>
		<div class="memml-settings__section-header">
			<h2 id="memml-section-<?php echo esc_attr( $section ); ?>"><?php echo esc_html( $label ); ?></h2>
			<?php if ( isset( self::PREFERENCE_SECTIONS[ $section ] ) ) : ?>
				<button
					class="button button-small memml-settings__reset"
					data-memml-reset="<?php echo esc_attr( $section ); ?>"
					hidden
					type="button"
				>
					<?php echo esc_html__( 'Reset section to defaults', 'memml' ); ?>
				</button>
				<span aria-live="polite" class="memml-settings__reset-result" data-memml-reset-result="<?php echo esc_attr( $section ); ?>"></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders a select for one preference.
	 *
	 * @param string $name    Preference name.
	 * @param string $value   Current value.
	 * @param array  $choices Values mapped to labels.
	 * @return void
	 */
	private function render_select( $name, $value, $choices ) {
		?>
		<select
			id="memml-<?php echo esc_attr( str_replace( '_', '-', $name ) ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $name ); ?>]"
		>
			<?php foreach ( $choices as $choice => $choice_label ) : ?>
				<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $choice, $value ); ?>><?php echo esc_html( $choice_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Renders a fieldset of preference checkboxes.
	 *
	 * @param string $legend  Accessible fieldset name.
	 * @param array  $choices Preference names mapped to labels.
	 * @param array  $options Current settings.
	 * @return void
	 */
	private function render_checkboxes( $legend, $choices, $options ) {
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php echo esc_html( $legend ); ?></legend>
			<?php foreach ( $choices as $name => $label ) : ?>
				<label for="memml-<?php echo esc_attr( str_replace( '_', '-', $name ) ); ?>">
					<input
						id="memml-<?php echo esc_attr( str_replace( '_', '-', $name ) ); ?>"
						name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $name ); ?>]"
						type="checkbox"
						value="1"
						<?php checked( ! empty( $options[ $name ] ) ); ?>
					/>
					<?php echo esc_html( $label ); ?>
				</label>
				<br />
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Gets the URL of the settings preview with the saved settings.
	 *
	 * The settings script adds the calendar choice and unsaved form values.
	 *
	 * @return string
	 */
	public static function get_preview_url() {
		return add_query_arg(
			array(
				'action'   => self::PREVIEW_ACTION,
				'_wpnonce' => wp_create_nonce( self::PREVIEW_ACTION ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Gets a safe preview calendar choice.
	 *
	 * @param mixed $calendar Requested calendar.
	 * @return string
	 */
	public static function get_preview_calendar( $calendar ) {
		return is_string( $calendar ) && isset( self::PREVIEW_CALENDARS[ $calendar ] ) ? $calendar : 'calendar';
	}

	/**
	 * Gets the settings a preview renders with.
	 *
	 * The connection always comes from the saved settings. Submitted
	 * preference values replace the saved ones only for this request.
	 *
	 * @param mixed $input Unsaved form values, or null for the saved settings.
	 * @return array
	 */
	public static function get_preview_options( $input ) {
		$options = self::get_options();

		if ( ! is_array( $input ) ) {
			return $options;
		}

		return array_merge( $options, self::sanitize_preferences( $input ) );
	}

	/**
	 * Gets each preference section's default values.
	 *
	 * @return array
	 */
	public static function get_section_defaults() {
		$defaults = self::get_defaults();
		$sections = array();

		foreach ( self::PREFERENCE_SECTIONS as $section => $names ) {
			$sections[ $section ] = array_intersect_key( $defaults, array_flip( $names ) );
		}

		// The accent is two form fields: a checkbox and a color that always
		// holds a value. Resetting unchecks the box and shows the default.
		$sections['appearance']['accent_custom'] = false;
		$sections['appearance']['accent_color']  = self::DEFAULT_ACCENT;

		return $sections;
	}

	/**
	 * Outputs a standalone document previewing a calendar.
	 *
	 * Runs in the settings page's frame, so preview navigation stays out of
	 * the settings page URL. The preview settings are supplied through the
	 * option filter for this request only and are never saved.
	 *
	 * @return void
	 */
	public function render_preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to manage these settings.', 'memml' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::PREVIEW_ACTION );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verified by check_admin_referer above.
		$calendar         = self::get_preview_calendar( isset( $_GET['calendar'] ) ? sanitize_key( wp_unslash( $_GET['calendar'] ) ) : '' );
		$input            = isset( $_GET[ self::OPTION_NAME ] ) && is_array( $_GET[ self::OPTION_NAME ] )
			? map_deep( wp_unslash( $_GET[ self::OPTION_NAME ] ), 'sanitize_text_field' )
			: null;
		$without_upcoming = ! empty( $_GET['empty'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$options        = self::get_preview_options( $input );
		$preview_option = static function () use ( $options ) {
			return $options;
		};

		add_filter( 'pre_option_' . self::OPTION_NAME, $preview_option );
		Memml_Renderer::set_editor_preview( true, $without_upcoming );
		$markup = do_shortcode( '[' . self::PREVIEW_CALENDARS[ $calendar ] . ' url_key="preview"]' );
		Memml_Renderer::set_editor_preview( false );
		remove_filter( 'pre_option_' . self::OPTION_NAME, $preview_option );

		wp_register_style( 'memml-preview', false, array(), MEMML_VERSION );
		wp_add_inline_style(
			'memml-preview',
			'body{margin:0;padding:16px;background:#fff;color:#1e1e1e;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:15px;line-height:1.5}'
		);

		if ( function_exists( 'wp_get_global_stylesheet' ) ) {
			wp_add_inline_style( 'memml-preview', wp_get_global_stylesheet() );
		}

		// The calendar stylesheet carries the saved accent color; swap in the
		// previewed one so unsaved appearance choices show.
		$appearance_css = Memml_Renderer::get_appearance_css( $options );

		wp_styles()->add_data( Memml_Renderer::ASSET_HANDLE, 'after', false );

		if ( '' !== $appearance_css ) {
			wp_add_inline_style( Memml_Renderer::ASSET_HANDLE, $appearance_css );
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php echo esc_attr( get_option( 'blog_charset' ) ); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<meta name="robots" content="noindex" />
			<base target="_blank" />
			<title><?php echo esc_html__( 'Calendar preview', 'memml' ); ?></title>
			<?php wp_print_styles( array( 'memml-preview', Memml_Renderer::ASSET_HANDLE ) ); ?>
		</head>
		<body>
			<?php echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer output is escaped as it is built. ?>
			<?php wp_print_scripts( Memml_Renderer::ASSET_HANDLE ); ?>
		</body>
		</html>
		<?php
		exit;
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

		wp_enqueue_style(
			'memml-admin',
			MEMML_PLUGIN_URL . 'assets/admin.css',
			array(),
			MEMML_VERSION
		);
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
				'optionName'   => self::OPTION_NAME,
				'defaults'     => self::get_section_defaults(),
				'resetDone'    => __( 'Defaults restored in this section. Select Save Changes to apply them.', 'memml' ),
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
			'organization_key'            => '',
			'base_url'                    => Memml_Feed_Client::DEFAULT_BASE_URL,
			'default_calendar'            => 'events',
			'default_view'                => 'list',
			'default_period'              => 'upcoming',
			'default_list_style'          => 'grid',
			'default_limit'               => 0,
			'calendar_switcher'           => true,
			'layout_switcher'             => true,
			'period_switcher'             => true,
			'subscribe_links'             => true,
			'show_images'                 => true,
			'show_descriptions'           => true,
			'show_item_count'             => true,
			'show_details'                => true,
			'show_venue_cost'             => true,
			'show_volunteer_availability' => true,
			'show_cancelled_events'       => true,
			'show_rsvp'                   => true,
			'show_registration'           => true,
			'show_online'                 => true,
			'show_volunteer_signup'       => true,
			'show_add_to_calendar'        => true,
			'show_event_page'             => true,
			'empty_events_mode'           => 'standard',
			'empty_events_message'        => '',
			'empty_events_link_url'       => '',
			'empty_events_link_label'     => '',
			'empty_volunteers_mode'       => 'standard',
			'empty_volunteers_message'    => '',
			'empty_volunteers_link_url'   => '',
			'empty_volunteers_link_label' => '',
			'accent_color'                => '',
			'appearance_palette'          => 'light',
			'appearance_spacing'          => 'comfortable',
			'appearance_corners'          => 'rounded',
			'image_size'                  => 'wide',
		);
	}
}
