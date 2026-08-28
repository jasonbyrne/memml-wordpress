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
		'September 2026',
		'October 2026',
		'data-month="2026-08" data-month-label="August 2026"',
		'Riverside Cleanup',
		'Community Dinner',
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

	$second_html = do_shortcode( '[memml_volunteers url_key="sidebar"]' );

	if ( false === strpos( $second_html, 'data-memml-url-prefix="memml_sidebar_"' ) || false !== strpos( $second_html, 'data-layout="month"' ) ) {
		throw new RuntimeException( 'A second calendar did not retain its independently scoped initial state.' );
	}

	WP_CLI::success( 'Memml Calendar activation, connection, URL state, upcoming/past filtering, sorting, toggles, timezone, and month view smoke test passed.' );
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
