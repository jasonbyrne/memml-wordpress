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

add_filter( 'pre_http_request', $mock_request, 10, 3 );
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

	$html       = do_shortcode( '[memml_calendar default="volunteers"]' );
	$month_html = do_shortcode( '[memml_calendar default="events" view="month"]' );

	$expectations = array(
		'data-default-view="volunteers"',
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
		'Riverside Cleanup',
		'Community Dinner',
	);

	foreach ( $month_expectations as $expectation ) {
		if ( false === strpos( $month_html, $expectation ) ) {
			throw new RuntimeException( 'Missing month output: ' . $expectation );
		}
	}

	WP_CLI::success( 'Memml Calendar activation, connection, source toggle, timezone, and month view smoke test passed.' );
} finally {
	remove_filter( 'pre_http_request', $mock_request, 10 );

	if ( $had_options ) {
		update_option( Memml_Settings::OPTION_NAME, $previous_options );
	} else {
		delete_option( Memml_Settings::OPTION_NAME );
	}
}
