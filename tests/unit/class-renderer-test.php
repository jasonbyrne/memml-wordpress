<?php
/**
 * Renderer unit tests.
 *
 * Covers the pure formatting helpers: attribute parsing, datetime labels,
 * and the calendar link builders. Markup rendering that depends on the full
 * WordPress runtime is exercised by the wp-env smoke test instead.
 *
 * @package Memml
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests attribute parsing and calendar link formatting.
 */
final class Memml_Renderer_Test extends TestCase {

	/**
	 * Sets up Brain Monkey and shared WordPress functions.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'is_wp_error' )->alias(
			static function ( $value ) {
				return $value instanceof WP_Error;
			}
		);
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default_value = false ) {
				if ( 'date_format' === $name ) {
					return 'm/d/Y';
				}

				if ( 'time_format' === $name ) {
					return 'g:i A';
				}

				return $default_value;
			}
		);
		Functions\when( 'wp_date' )->alias(
			static function ( $format, $timestamp, $timezone = null ) {
				$date = new DateTimeImmutable( '@' . $timestamp );

				return $date->setTimezone( $timezone ? $timezone : new DateTimeZone( 'UTC' ) )->format( $format );
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				unset( $hook );
				return $value;
			}
		);
		Functions\when( '_n' )->alias(
			static function ( $single, $plural, $number ) {
				return 1 === $number ? $single : $plural;
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( $arguments, $url ) {
				$pairs = array();

				foreach ( $arguments as $name => $value ) {
					$pairs[] = $name . '=' . $value;
				}

				return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . implode( '&', $pairs );
			}
		);
	}

	/**
	 * Tears down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Invokes a private renderer method.
	 *
	 * @param string $method       Method name.
	 * @param mixed  ...$arguments Method arguments.
	 * @return mixed
	 */
	private function call_renderer( $method, ...$arguments ) {
		$reflection = new ReflectionMethod( Memml_Renderer::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invoke( new Memml_Renderer(), ...$arguments );
	}

	/**
	 * The list style accepts block and shortcode spellings and rejects junk.
	 *
	 * @return void
	 */
	public function test_list_style_accepts_both_attribute_spellings() {
		$this->assertSame( 'rows', $this->call_renderer( 'get_list_style_from_attributes', array( 'listStyle' => 'rows' ) ) );
		$this->assertSame( 'rows', $this->call_renderer( 'get_list_style_from_attributes', array( 'list_style' => 'rows' ) ) );
		$this->assertSame( 'grid', $this->call_renderer( 'get_list_style_from_attributes', array( 'listStyle' => 'bogus' ) ) );
		$this->assertSame( 'grid', $this->call_renderer( 'get_list_style_from_attributes', array() ) );
		$this->assertSame( 'grid', $this->call_renderer( 'get_list_style_from_attributes', '' ) );
	}

	/**
	 * The subscribe flag defaults on and honours boolean and string forms.
	 *
	 * @return void
	 */
	public function test_subscribe_flag_parses_boolean_and_string_forms() {
		$this->assertTrue( $this->call_renderer( 'get_subscribe_from_attributes', array() ) );
		$this->assertTrue( $this->call_renderer( 'get_subscribe_from_attributes', array( 'subscribe' => true ) ) );
		$this->assertTrue( $this->call_renderer( 'get_subscribe_from_attributes', array( 'subscribe' => 'yes' ) ) );
		$this->assertFalse( $this->call_renderer( 'get_subscribe_from_attributes', array( 'subscribe' => false ) ) );
		$this->assertFalse( $this->call_renderer( 'get_subscribe_from_attributes', array( 'subscribe' => 'no' ) ) );
		$this->assertFalse( $this->call_renderer( 'get_subscribe_from_attributes', array( 'subscribe' => 'false' ) ) );
		$this->assertFalse( $this->call_renderer( 'get_subscribe_from_attributes', array( 'subscribe' => '0' ) ) );
	}

	/**
	 * Compact datetime labels lead with the weekday; full labels add the date.
	 *
	 * @return void
	 */
	public function test_datetime_styles_render_weekday_and_full_labels() {
		$timezone = new DateTimeZone( 'America/New_York' );
		$event    = array(
			'startsAt' => '2026-09-12T20:00:00.000Z',
			'endsAt'   => '2026-09-13T00:00:00.000Z',
		);

		$compact = $this->call_renderer( 'render_datetime', $event, $timezone, 'compact' );
		$full    = $this->call_renderer( 'render_datetime', $event, $timezone, 'full' );

		$this->assertStringContainsString( 'Saturday · 4:00 PM–8:00 PM', $compact );
		$this->assertStringNotContainsString( '09/12/2026', $compact );
		$this->assertStringContainsString( 'Saturday, 09/12/2026 · 4:00 PM–8:00 PM', $full );
		$this->assertStringContainsString( '<span>Sep</span><strong>12</strong>', $compact );
		$this->assertStringContainsString( 'datetime="2026-09-12T20:00:00.000Z"', $compact );
	}

	/**
	 * All-day items label the day instead of a time range.
	 *
	 * @return void
	 */
	public function test_datetime_labels_all_day_items() {
		$timezone = new DateTimeZone( 'America/New_York' );
		$event    = array(
			'startsAt' => '2026-09-12T04:00:00.000Z',
			'allDay'   => true,
		);

		$compact = $this->call_renderer( 'render_datetime', $event, $timezone, 'compact' );
		$full    = $this->call_renderer( 'render_datetime', $event, $timezone, 'full' );

		$this->assertStringContainsString( 'Saturday · All day', $compact );
		$this->assertStringContainsString( 'Saturday, 09/12/2026', $full );
	}

	/**
	 * The Google event link matches memml.com's own template URLs.
	 *
	 * @return void
	 */
	public function test_google_event_url_encodes_timed_events() {
		$url = $this->call_renderer(
			'build_google_event_url',
			array(
				'title'       => 'September 2026 Car Show',
				'description' => 'Join us for the monthly car show. Volunteers needed!',
				'location'    => 'Historic Civic Center',
				'startsAt'    => '2026-09-12T20:00:00.000Z',
				'endsAt'      => '2026-09-13T00:00:00.000Z',
			)
		);

		$this->assertStringStartsWith( 'https://calendar.google.com/calendar/render?action=TEMPLATE', $url );
		$this->assertStringContainsString( 'text=September%202026%20Car%20Show', $url );
		$this->assertStringContainsString( 'dates=20260912T200000Z%2F20260913T000000Z', $url );
		$this->assertStringContainsString( 'location=Historic%20Civic%20Center', $url );
	}

	/**
	 * All-day events use date-only ranges with an exclusive end date.
	 *
	 * @return void
	 */
	public function test_google_event_url_encodes_all_day_events() {
		$url = $this->call_renderer(
			'build_google_event_url',
			array(
				'title'    => 'Founders Day',
				'allDay'   => true,
				'startsAt' => '2026-10-30T00:00:00.000Z',
				'endsAt'   => '2026-10-30T00:00:00.000Z',
			)
		);

		$this->assertStringContainsString( 'dates=20261030%2F20261031', $url );
	}

	/**
	 * An end before the start falls back to a one-hour event.
	 *
	 * @return void
	 */
	public function test_google_event_url_repairs_inverted_ranges() {
		$url = $this->call_renderer(
			'build_google_event_url',
			array(
				'title'    => 'Board Meeting',
				'startsAt' => '2026-09-08T23:00:00.000Z',
				'endsAt'   => '2026-09-08T22:00:00.000Z',
			)
		);

		$this->assertStringContainsString( 'dates=20260908T230000Z%2F20260909T000000Z', $url );
	}

	/**
	 * Items without a title or start date produce no link.
	 *
	 * @return void
	 */
	public function test_google_event_url_requires_title_and_start() {
		$this->assertSame( '', $this->call_renderer( 'build_google_event_url', array( 'startsAt' => '2026-09-08T23:00:00.000Z' ) ) );
		$this->assertSame( '', $this->call_renderer( 'build_google_event_url', array( 'title' => 'Untimed' ) ) );
		$this->assertSame(
			'',
			$this->call_renderer(
				'build_google_event_url',
				array(
					'title'    => 'Bad date',
					'startsAt' => 'not-a-date',
				)
			)
		);
	}

	/**
	 * Very long descriptions are capped so the URL stays reliable.
	 *
	 * @return void
	 */
	public function test_google_event_url_caps_long_descriptions() {
		$url = $this->call_renderer(
			'build_google_event_url',
			array(
				'title'       => 'Long Story',
				'description' => str_repeat( 'a', 2000 ),
				'startsAt'    => '2026-09-12T20:00:00.000Z',
			)
		);

		$this->assertSame( 1, preg_match( '/details=([^&]*)/', $url, $matches ) );
		$this->assertSame( str_repeat( 'a', 1000 ) . '…', rawurldecode( $matches[1] ) );
	}

	/**
	 * The subscribe row offers Google, webcal, and RSS links from the feed.
	 *
	 * @return void
	 */
	public function test_subscribe_row_builds_links_from_feed_envelope() {
		$result = array(
			'data' => array(
				'links' => array(
					'ics' => 'https://memml.com/api/public/v1/org/events.ics',
					'rss' => 'https://memml.com/api/public/v1/org/events.rss',
				),
			),
		);

		$row = $this->call_renderer( 'render_subscribe_row', 'events', $result );

		$this->assertStringContainsString( 'https://calendar.google.com/calendar/render?cid=https%3A%2F%2Fmemml.com%2Fapi%2Fpublic%2Fv1%2Forg%2Fevents.ics', $row );
		$this->assertStringContainsString( 'webcal://memml.com/api/public/v1/org/events.ics', $row );
		$this->assertStringContainsString( 'https://memml.com/api/public/v1/org/events.rss', $row );
		$this->assertStringContainsString( 'Subscribe to events', $row );
	}

	/**
	 * A rendered event card carries the compact summary and full details.
	 *
	 * @return void
	 */
	public function test_event_card_renders_summary_and_hidden_details() {
		$timezone = new DateTimeZone( 'America/New_York' );
		$card     = $this->call_renderer(
			'render_event_card',
			array(
				'title'       => 'September 2026 Car Show',
				'description' => 'Join us for the monthly car show.',
				'location'    => 'Historic Civic Center',
				'status'      => 'scheduled',
				'startsAt'    => '2026-09-12T20:00:00.000Z',
				'endsAt'      => '2026-09-13T00:00:00.000Z',
				'icsUrl'      => 'https://memml.com/api/public/v1/org/events/1.ics',
			),
			$timezone,
			false
		);

		$this->assertStringContainsString( 'data-memml-item', $card );
		$this->assertStringContainsString( '<h3 class="memml-calendar__title">September 2026 Car Show</h3>', $card );
		$this->assertStringContainsString( 'Saturday · 4:00 PM–8:00 PM', $card );
		$this->assertStringContainsString( '<div class="memml-calendar__details" data-memml-details hidden>', $card );
		$this->assertStringContainsString( 'Saturday, 09/12/2026 · 4:00 PM–8:00 PM', $card );
		$this->assertStringContainsString( 'Add to calendar:', $card );
		$this->assertStringContainsString( 'https://memml.com/api/public/v1/org/events/1.ics', $card );
		$this->assertStringContainsString( 'https://calendar.google.com/calendar/render?action=TEMPLATE', $card );
	}

	/**
	 * A crowded month day collapses extra items behind a disclosure.
	 *
	 * @return void
	 */
	public function test_month_panel_collapses_crowded_days() {
		$timezone = new DateTimeZone( 'America/New_York' );
		$items    = array();

		foreach ( array( 'Board Meeting', 'Garden Tour', 'Bake Sale', 'Archive Hours' ) as $title ) {
			$items[] = array(
				'title'    => $title,
				'startsAt' => '2026-09-08T23:00:00.000Z',
			);
		}

		$panel = $this->call_renderer(
			'render_month_panel',
			array(
				'first_day' => new DateTimeImmutable( '2026-09-01 00:00:00', $timezone ),
				'days'      => array( 8 => $items ),
			),
			'2026-09',
			'events',
			$timezone,
			0,
			0
		);

		$this->assertSame( 4, substr_count( $panel, '<article class="memml-calendar__month-entry"' ) );
		$this->assertStringContainsString( '<details class="memml-calendar__month-more"><summary>+2 more</summary>', $panel );
		$this->assertStringContainsString( 'Bake Sale', $panel );
		$this->assertStringContainsString( 'Archive Hours', $panel );
		$this->assertTrue(
			strpos( $panel, 'Garden Tour' ) < strpos( $panel, '<details' ),
			'The first two items stay outside the disclosure.'
		);
	}

	/**
	 * Errors and feeds without links render no subscribe row.
	 *
	 * @return void
	 */
	public function test_subscribe_row_requires_a_usable_ics_link() {
		$this->assertSame( '', $this->call_renderer( 'render_subscribe_row', 'events', new WP_Error( 'code', 'message' ) ) );
		$this->assertSame( '', $this->call_renderer( 'render_subscribe_row', 'events', array( 'data' => array() ) ) );
		$this->assertSame(
			'',
			$this->call_renderer(
				'render_subscribe_row',
				'events',
				array( 'data' => array( 'links' => array( 'ics' => 'javascript:alert(1)' ) ) )
			)
		);
	}
}
