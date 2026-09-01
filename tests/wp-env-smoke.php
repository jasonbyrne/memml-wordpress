<?php
/**
 * wp-env activation, feed, shortcode, and timezone smoke test.
 *
 * Run with `npm run test:wp-env` while wp-env is running.
 *
 * @package Memml
 */

defined( 'ABSPATH' ) || exit;

$events_fixture     = file_get_contents( __DIR__ . '/fixtures/events.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.
$volunteers_fixture = file_get_contents( __DIR__ . '/fixtures/volunteer-opportunities.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.
$previous_options   = get_option( Memml_Settings::OPTION_NAME, false );
$had_options        = false !== $previous_options;
$previous_query     = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve test process state.

$mock_request = static function ( $response, $args, $url ) use ( $events_fixture, $volunteers_fixture ) {
	unset( $response, $args );

	$body = false !== strpos( $url, 'volunteer-opportunities.json' )
		? $volunteers_fixture
		: $events_fixture;

	return array(
		'headers'  => array( 'etag' => '"wp-env-smoke"' ),
		'body'     => $body,
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
};

$today_filter = static function ( $today, $timezone ) {
	unset( $today );

	return new DateTimeImmutable( '2026-08-28 00:00:00', $timezone );
};

add_filter( 'pre_http_request', $mock_request, 10, 3 );
add_filter( 'memml_calendar_today', $today_filter, 10, 2 );
update_option(
	Memml_Settings::OPTION_NAME,
	array(
		'organization_key' => 'river-city-neighbors',
		'base_url'         => Memml_Feed_Client::DEFAULT_BASE_URL,
	)
);

try {
	$block_registry = WP_Block_Type_Registry::get_instance();
	$block_names    = array( 'memml/calendar', 'memml/events', 'memml/volunteers' );

	foreach ( $block_names as $block_name ) {
		if ( ! $block_registry->is_registered( $block_name ) ) {
			throw new RuntimeException( 'Block was not registered: ' . $block_name );
		}
	}

	// A block bundle whose dependencies are not all registered never loads, which
	// silently leaves the blocks with no editing interface.
	require_once ABSPATH . 'wp-admin/includes/admin.php';
	wp_scripts();
	do_action( 'wp_default_scripts', $GLOBALS['wp_scripts'] );

	$editor_asset = require dirname( __DIR__ ) . '/build/index.asset.php';

	foreach ( $editor_asset['dependencies'] as $dependency ) {
		if ( ! wp_script_is( $dependency, 'registered' ) ) {
			throw new RuntimeException(
				'The block editor bundle depends on a script this WordPress version does not register: ' . $dependency
			);
		}
	}

	$connection = ( new Memml_Feed_Client() )->get_events( 'river-city-neighbors', true );

	if ( is_wp_error( $connection ) ) {
		throw new RuntimeException( $connection->get_error_message() );
	}

	if ( 'River City Neighbors' !== $connection['data']['organization']['name'] ) {
		throw new RuntimeException( 'The connection test did not return the fixture organization name.' );
	}

	$html        = do_shortcode( '[memml_calendar calendar="volunteers" url_key="primary"]' );
	$month_html  = do_shortcode( '[memml_calendar calendar="events" view="month" url_key="monthly"]' );
	$events_html = do_shortcode( '[memml_events view="month" url_key="events"]' );

	$expectations = array(
		'data-calendar="volunteers"',
		'data-layout="list"',
		'data-period="upcoming"',
		'data-memml-url-prefix="memml_primary_"',
		'data-memml-layout="list"',
		'data-memml-layout="month"',
		'data-memml-period="upcoming"',
		'data-memml-period="past"',
		'Riverside Cleanup',
		'Food Pantry Sorters',
		'9:00 am',
	);

	foreach ( $expectations as $expectation ) {
		if ( false === strpos( $html, $expectation ) ) {
			throw new RuntimeException( 'Missing rendered output: ' . $expectation );
		}
	}

	$month_expectations = array(
		'data-layout="month"',
		'data-memml-month-calendar',
		'data-memml-month-prev href="',
		'data-memml-month-next href="',
		'September 2026',
		'October 2026',
		'data-month="2026-08" data-month-label="August 2026"',
		'Riverside Cleanup',
		'Community Dinner',
		'class="memml-calendar__month-scroll" role="region" tabindex="0"',
		'is-today',
		'aria-current="date"',
	);

	foreach ( $month_expectations as $expectation ) {
		if ( false === strpos( $month_html, $expectation ) ) {
			throw new RuntimeException( 'Missing month output: ' . $expectation );
		}
	}

	$events_expectations = array(
		'memml-calendar--events',
		'data-layout="month"',
		'data-memml-layout="list"',
		'data-memml-layout="month"',
		'data-memml-layout-panel="list"',
		'data-memml-layout-panel="month"',
	);

	foreach ( $events_expectations as $expectation ) {
		if ( false === strpos( $events_html, $expectation ) ) {
			throw new RuntimeException( 'Missing fixed-feed toggle output: ' . $expectation );
		}
	}

	$upcoming_start = strpos( $events_html, 'data-memml-period-panel="upcoming"' );
	$past_start     = strpos( $events_html, 'data-memml-period-panel="past"' );
	$month_start    = strpos( $events_html, 'data-memml-layout-panel="month"' );

	if ( false === $upcoming_start || false === $past_start || false === $month_start ) {
		throw new RuntimeException( 'Could not locate list period panels.' );
	}

	$upcoming_segment = substr( $events_html, $upcoming_start, $past_start - $upcoming_start );
	$past_segment     = substr( $events_html, $past_start, $month_start - $past_start );

	if ( false === strpos( $upcoming_segment, 'Riverside Cleanup' ) || false === strpos( $upcoming_segment, 'Community Dinner' ) ) {
		throw new RuntimeException( 'Upcoming events were not rendered in the Upcoming panel.' );
	}

	if ( false === strpos( $upcoming_segment, 'Cancelled River Walk' ) ) {
		throw new RuntimeException( 'Cancelled events were not shown by the enabled default.' );
	}

	if ( strpos( $upcoming_segment, 'Riverside Cleanup' ) > strpos( $upcoming_segment, 'Community Dinner' ) ) {
		throw new RuntimeException( 'Upcoming events were not sorted in ascending order.' );
	}

	if ( false !== strpos( $upcoming_segment, 'School Supply Drive' ) || false !== strpos( $upcoming_segment, 'Neighborhood Picnic' ) ) {
		throw new RuntimeException( 'Past events leaked into the Upcoming panel.' );
	}

	if ( false === strpos( $past_segment, 'School Supply Drive' ) || false === strpos( $past_segment, 'Neighborhood Picnic' ) ) {
		throw new RuntimeException( 'Past events were not rendered in the Past panel.' );
	}

	if ( strpos( $past_segment, 'School Supply Drive' ) > strpos( $past_segment, 'Neighborhood Picnic' ) ) {
		throw new RuntimeException( 'Past events were not sorted in descending order.' );
	}

	if ( false !== strpos( $past_segment, 'Riverside Cleanup' ) || false !== strpos( $past_segment, 'Community Dinner' ) ) {
		throw new RuntimeException( 'Upcoming events leaked into the Past panel.' );
	}

	if ( false !== strpos( $past_segment, 'register/school-supply-drive' ) || false !== strpos( $past_segment, 'events/evt_01JPASTLATER.ics' ) ) {
		throw new RuntimeException( 'Expired actions were rendered for a past event.' );
	}

	if ( false !== strpos( $past_segment, 'meet.example/school-supply-drive' ) ) {
		throw new RuntimeException( 'An expired online meeting link was rendered for a past event.' );
	}

	if ( false === strpos( $upcoming_segment, 'meet.example/riverside-cleanup' ) ) {
		throw new RuntimeException( 'An upcoming online meeting link was not rendered.' );
	}

	$_GET['memml_shared_calendar'] = 'events';
	$_GET['memml_shared_view']     = 'month';
	$_GET['memml_shared_month']    = '2026-10';
	$_GET['memml_shared_period']   = 'past';

	$query_html         = do_shortcode( '[memml_calendar calendar="volunteers" view="list" url_key="shared"]' );
	$query_expectations = array(
		'data-memml-url-prefix="memml_shared_"',
		'data-calendar="events"',
		'data-layout="month"',
		'data-period="past"',
		'data-month="2026-10" data-month-label="October 2026"><div',
	);

	foreach ( $query_expectations as $expectation ) {
		if ( false === strpos( $query_html, $expectation ) ) {
			throw new RuntimeException( 'Missing direct-link query output: ' . $expectation );
		}
	}

	$_GET['memml_edge_month'] = '2026-07';

	$edge_html = do_shortcode( '[memml_events view="month" url_key="edge"]' );

	if ( false === strpos( $edge_html, ' aria-disabled="true" aria-label="Previous month"' ) ) {
		throw new RuntimeException( 'The first month did not mark Previous month as inactive.' );
	}

	if ( false !== strpos( $edge_html, ' aria-disabled="true" aria-label="Next month"' ) ) {
		throw new RuntimeException( 'The first month incorrectly marked Next month as inactive.' );
	}

	if ( 2 !== substr_count( $edge_html, 'class="memml-calendar__month-button" data-memml-month-' ) ) {
		throw new RuntimeException( 'An inactive month link left the accessibility tree instead of keeping its href.' );
	}

	$second_html = do_shortcode( '[memml_volunteers url_key="sidebar"]' );

	if ( false === strpos( $second_html, 'data-memml-url-prefix="memml_sidebar_"' ) || false !== strpos( $second_html, 'data-layout="month"' ) ) {
		throw new RuntimeException( 'A second calendar did not retain its independently scoped initial state.' );
	}

	if ( false === strpos( $html, '<img alt="" decoding="async" loading="lazy"' ) ) {
		throw new RuntimeException( 'Card images were not rendered as decorative.' );
	}

	$link_expectations = array(
		'data-memml-view="events" href="',
		'data-memml-layout="month" href="',
		'data-memml-period="past" href="',
		'aria-current="true"',
	);

	foreach ( $link_expectations as $expectation ) {
		if ( false === strpos( $html, $expectation ) ) {
			throw new RuntimeException( 'Controls were not rendered as shareable links: ' . $expectation );
		}
	}

	if ( false !== strpos( $html, 'aria-pressed' ) ) {
		throw new RuntimeException( 'Controls still render the button-only aria-pressed state.' );
	}

	$unlimited = do_shortcode( '[memml_events url_key="all"]' );
	$limited   = do_shortcode( '[memml_events limit="1" url_key="capped"]' );

	$count_upcoming = static function ( $markup ) {
		$start = strpos( $markup, 'data-memml-period-panel="upcoming"' );
		$end   = strpos( $markup, 'data-memml-period-panel="past"' );

		return substr_count( substr( $markup, $start, $end - $start ), 'data-memml-item' );
	};

	if ( 3 !== $count_upcoming( $unlimited ) ) {
		throw new RuntimeException( 'The events fixture no longer renders three upcoming items.' );
	}

	if ( 1 !== $count_upcoming( $limited ) ) {
		throw new RuntimeException( 'The limit attribute did not cap the upcoming list.' );
	}

	if ( false === strpos( $limited, 'data-memml-layout-panel="month"' ) ) {
		throw new RuntimeException( 'The limit attribute removed the month view.' );
	}

	update_option(
		Memml_Settings::OPTION_NAME,
		array(
			'organization_key'            => 'river-city-neighbors',
			'base_url'                    => Memml_Feed_Client::DEFAULT_BASE_URL,
			'default_calendar'            => 'volunteers',
			'default_view'                => 'month',
			'default_period'              => 'past',
			'default_list_style'          => 'rows',
			'default_limit'               => 1,
			'calendar_switcher'           => false,
			'layout_switcher'             => false,
			'period_switcher'             => false,
			'subscribe_links'             => false,
			'show_images'                 => false,
			'show_descriptions'           => false,
			'show_item_count'             => false,
			'show_details'                => false,
			'show_venue_cost'             => false,
			'show_volunteer_availability' => false,
			'show_cancelled_events'       => false,
			'show_registration'           => false,
			'show_online'                 => false,
			'show_volunteer_signup'       => false,
			'show_add_to_calendar'        => false,
		)
	);

	$_GET['memml_defaults_calendar'] = 'events';
	$_GET['memml_defaults_view']     = 'list';
	$_GET['memml_defaults_period']   = 'upcoming';

	$inherited         = do_shortcode( '[memml_calendar url_key="defaults"]' );
	$inherited_list    = do_shortcode( '[memml_events view="list" url_key="fixed-list"]' );
	$hidden_events     = do_shortcode( '[memml_events view="list" period="upcoming" limit="0" url_key="hidden-events"]' );
	$hidden_volunteers = do_shortcode( '[memml_volunteers view="list" period="upcoming" limit="0" url_key="hidden-volunteers"]' );
	$explicit          = do_shortcode( '[memml_calendar calendar="events" view="list" period="upcoming" list_style="grid" limit="0" subscribe="yes" calendar_switcher="yes" layout_switcher="yes" period_switcher="yes" show_images="yes" show_descriptions="yes" show_item_count="yes" show_details="yes" show_venue_cost="yes" show_volunteer_availability="yes" show_cancelled_events="yes" show_registration="yes" show_online="yes" show_volunteer_signup="yes" show_add_to_calendar="yes" url_key="overrides"]' );

	foreach ( array( 'data-calendar="volunteers"', 'data-layout="month"', 'data-period="past"' ) as $expectation ) {
		if ( false === strpos( $inherited, $expectation ) ) {
			throw new RuntimeException( 'A bare shortcode did not inherit the display default: ' . $expectation );
		}
	}

	if (
		false !== strpos( $inherited, 'memml-calendar__subscribe' ) ||
		false !== strpos( $inherited, 'memml-calendar__toolbar' ) ||
		false !== strpos( $inherited, 'data-memml-view=' ) ||
		false !== strpos( $inherited, 'data-memml-layout=' ) ||
		false !== strpos( $inherited, 'data-memml-period=' ) ||
		false !== strpos( $inherited, 'data-memml-layout-panel="list"' ) ||
		false !== strpos( $inherited, '-events"' )
	) {
		throw new RuntimeException( 'A bare shortcode did not inherit the hidden visitor controls or fixed display state.' );
	}

	if (
		1 !== substr_count( $inherited_list, 'data-memml-item' ) ||
		false !== strpos( $inherited_list, 'data-memml-period-panel="upcoming"' ) ||
		false === strpos( $inherited_list, 'memml-calendar__grid--rows' )
	) {
		throw new RuntimeException( 'A fixed list did not inherit its selected period or list-limit default.' );
	}

	$hidden_event_output = array(
		'Cancelled River Walk',
		'Bring gloves and comfortable shoes.',
		'Riverside Park, 100 River Road',
		'memml-calendar__count',
		'data-memml-details',
		'cdn.memml.com/events/riverside-cleanup.jpg',
		'rivercityneighbors.example/register/cleanup',
		'meet.example/riverside-cleanup',
		'events/evt_01JTESTEVENT.ics',
		'volunteer/riverside-cleanup',
	);

	foreach ( $hidden_event_output as $unexpected ) {
		if ( false !== strpos( $hidden_events, $unexpected ) ) {
			throw new RuntimeException( 'An events shortcode did not inherit hidden content or actions: ' . $unexpected );
		}
	}

	$hidden_volunteer_output = array(
		'Sort and shelve weekly food donations.',
		'River City Food Pantry',
		'4 spots remaining',
		'Volunteers needed',
		'memml-calendar__count',
		'data-memml-details',
		'cdn.memml.com/volunteer/food-pantry-sorters.jpg',
		'volunteer/vol_01JTESTVOLUNTEER',
	);

	foreach ( $hidden_volunteer_output as $unexpected ) {
		if ( false !== strpos( $hidden_volunteers, $unexpected ) ) {
			throw new RuntimeException( 'A volunteers shortcode did not inherit hidden content or actions: ' . $unexpected );
		}
	}

	foreach ( array( 'data-calendar="events"', 'data-layout="list"', 'data-period="upcoming"', 'memml-calendar__subscribe', 'data-memml-view="volunteers"', 'data-memml-layout="month"', 'data-memml-period="past"' ) as $expectation ) {
		if ( false === strpos( $explicit, $expectation ) ) {
			throw new RuntimeException( 'An explicit shortcode value did not override the display default: ' . $expectation );
		}
	}

	if ( false !== strpos( $explicit, 'memml-calendar__grid--rows' ) || 3 !== $count_upcoming( $explicit ) ) {
		throw new RuntimeException( 'Explicit list style or limit values did not override the display defaults.' );
	}

	foreach ( array( 'Cancelled River Walk', 'Bring gloves and comfortable shoes.', 'Riverside Park, 100 River Road', 'memml-calendar__count', 'data-memml-details', 'cdn.memml.com/events/riverside-cleanup.jpg', 'rivercityneighbors.example/register/cleanup', 'meet.example/riverside-cleanup', 'events/evt_01JTESTEVENT.ics', 'volunteer/riverside-cleanup', 'Sort and shelve weekly food donations.', '4 spots remaining', 'Volunteers needed' ) as $expectation ) {
		if ( false === strpos( $explicit, $expectation ) ) {
			throw new RuntimeException( 'An explicit visibility override was not rendered: ' . $expectation );
		}
	}

	wp_set_current_user( 1 );

	// The editor previews blocks through the REST block renderer, so exercise
	// the same endpoint the ServerSideRender component calls.
	foreach ( $block_names as $block_name ) {
		$request          = new WP_REST_Request( 'GET', '/wp/v2/block-renderer/' . $block_name );
		$block_attributes = array(
			'view'                => 'list',
			'period'              => 'upcoming',
			'listStyle'           => 'grid',
			'urlKey'              => 'preview',
			'limit'               => 0,
			'layoutSwitcher'      => 'yes',
			'periodSwitcher'      => 'yes',
			'subscribe'           => 'yes',
			'showImages'          => 'yes',
			'showDescriptions'    => 'yes',
			'showItemCount'       => 'yes',
			'showDetails'         => 'yes',
			'showVenueCost'       => 'yes',
			'showVolunteerSignup' => 'yes',
		);

		if ( 'memml/calendar' === $block_name ) {
			$block_attributes['calendar']         = 'events';
			$block_attributes['calendarSwitcher'] = 'yes';
		}

		if ( 'memml/volunteers' !== $block_name ) {
			$block_attributes['showCancelledEvents'] = 'yes';
			$block_attributes['showRegistration']    = 'yes';
			$block_attributes['showOnline']          = 'yes';
			$block_attributes['showAddToCalendar']   = 'yes';
		}

		if ( 'memml/events' !== $block_name ) {
			$block_attributes['showVolunteerAvailability'] = 'yes';
		}

		$request->set_param( 'context', 'edit' );
		$request->set_param( 'attributes', $block_attributes );

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			throw new RuntimeException(
				'The block renderer failed for ' . $block_name . ': ' . $response->as_error()->get_error_message()
			);
		}

		$rendered = $response->get_data()['rendered'];

		foreach ( array( 'data-memml-calendar', 'data-layout="list"', 'data-period="upcoming"' ) as $expectation ) {
			if ( false === strpos( $rendered, $expectation ) ) {
				throw new RuntimeException( 'The block renderer did not preserve an explicit value for ' . $block_name . ': ' . $expectation );
			}
		}

		if ( 'memml/volunteers' !== $block_name && false === strpos( $rendered, 'memml-calendar__subscribe' ) ) {
			throw new RuntimeException( 'The block renderer did not preserve the explicit subscribe setting for ' . $block_name . '.' );
		}

		$expected_count = 'memml/volunteers' === $block_name ? 1 : 3;

		if ( false !== strpos( $rendered, 'memml-calendar__grid--rows' ) || $expected_count !== $count_upcoming( $rendered ) ) {
			throw new RuntimeException( 'The block renderer did not preserve the explicit list style or unlimited item count for ' . $block_name . '.' );
		}

		if ( 'memml/calendar' === $block_name && false === strpos( $rendered, 'data-calendar="events"' ) ) {
			throw new RuntimeException( 'The combined block did not preserve its explicit initial calendar.' );
		}

		if ( false === strpos( $rendered, 'data-memml-details' ) || false === strpos( $rendered, 'memml-calendar__count' ) ) {
			throw new RuntimeException( 'The block renderer did not preserve explicit content visibility for ' . $block_name . '.' );
		}
	}

	set_current_screen( 'settings_page_memml' );

	ob_start();
	( new Memml_Settings() )->render_page();
	$settings_html = ob_get_clean();

	$settings_expectations = array(
		'memml-organization-key',
		'memml-default-calendar',
		'memml-default-view',
		'memml-default-period',
		'memml-default-list-style',
		'memml-default-limit',
		'memml-calendar-switcher',
		'memml-layout-switcher',
		'memml-period-switcher',
		'memml-subscribe-links',
		'memml-show-images',
		'memml-show-descriptions',
		'memml-show-item-count',
		'memml-show-details',
		'memml-show-venue-cost',
		'memml-show-volunteer-availability',
		'memml-show-cancelled-events',
		'memml-show-registration',
		'memml-show-online',
		'memml-show-volunteer-signup',
		'memml-show-add-to-calendar',
		'memml-test-connection',
		'api/public/v1/river-city-neighbors/events.json',
		'api/public/v1/river-city-neighbors/volunteer-opportunities.json',
		'value="memml_refresh_cache"',
		'[memml_calendar]',
	);

	foreach ( $settings_expectations as $expectation ) {
		if ( false === strpos( $settings_html, $expectation ) ) {
			throw new RuntimeException( 'Missing settings-screen output: ' . $expectation );
		}
	}

	ob_start();
	( new Memml_Settings() )->render_setup_notice();
	$configured_notice = ob_get_clean();

	if ( '' !== trim( $configured_notice ) ) {
		throw new RuntimeException( 'The setup notice was shown for a configured site.' );
	}

	$invalid = ( new Memml_Settings() )->sanitize(
		array(
			'organization_key' => 'not a valid key!',
			'base_url'         => Memml_Feed_Client::DEFAULT_BASE_URL,
		)
	);

	if ( 'river-city-neighbors' !== $invalid['organization_key'] ) {
		throw new RuntimeException( 'An invalid organization key discarded the saved key.' );
	}

	WP_CLI::success( 'Memml Calendar activation, connection, display-default inheritance, visitor-control visibility, explicit overrides, URL state, filtering, sorting, link controls, timezone, month view, settings screen, and key-retention smoke test passed.' );
} finally {
	remove_filter( 'pre_http_request', $mock_request, 10 );
	remove_filter( 'memml_calendar_today', $today_filter, 10 );
	$_GET = $previous_query;

	if ( $had_options ) {
		update_option( Memml_Settings::OPTION_NAME, $previous_options );
	} else {
		delete_option( Memml_Settings::OPTION_NAME );
	}
}
