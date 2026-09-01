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
		$_GET = array();

		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'sanitize_key' )->alias(
			static function ( $value ) {
				return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) );
			}
		);
		Functions\when( 'wp_unslash' )->returnArg();
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
		Functions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( $defaults, (array) $args );
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
		$_GET = array();
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
	 * Visitor controls inherit site defaults and accept block and shortcode spellings.
	 *
	 * @return void
	 */
	public function test_visitor_controls_parse_inherited_and_explicit_values() {
		$this->assertTrue( $this->call_renderer( 'get_control_from_attributes', array(), 'layoutSwitcher', 'layout_switcher', 'layout_switcher' ) );
		$this->assertFalse( $this->call_renderer( 'get_control_from_attributes', array( 'layoutSwitcher' => 'no' ), 'layoutSwitcher', 'layout_switcher', 'layout_switcher' ) );
		$this->assertFalse( $this->call_renderer( 'get_control_from_attributes', array( 'layout_switcher' => 'false' ), 'layoutSwitcher', 'layout_switcher', 'layout_switcher' ) );
		$this->assertTrue( $this->call_renderer( 'get_control_from_attributes', array( 'layoutSwitcher' => 'yes' ), 'layoutSwitcher', 'layout_switcher', 'layout_switcher' ) );
	}

	/**
	 * Content and action preferences inherit site defaults and accept both APIs.
	 *
	 * @return void
	 */
	public function test_visibility_preferences_parse_block_and_shortcode_attributes() {
		$block     = $this->call_renderer(
			'get_visibility_from_attributes',
			array(
				'showImages'       => 'no',
				'showRegistration' => 'yes',
			)
		);
		$shortcode = $this->call_renderer(
			'get_visibility_from_attributes',
			array(
				'show_descriptions' => 'false',
				'show_online'       => '1',
			)
		);

		$this->assertFalse( $block['show_images'] );
		$this->assertTrue( $block['show_registration'] );
		$this->assertFalse( $shortcode['show_descriptions'] );
		$this->assertTrue( $shortcode['show_online'] );
		$this->assertTrue( $shortcode['show_details'] );
	}

	/**
	 * Unset attributes fall back to the saved site-wide display defaults.
	 *
	 * @return void
	 */
	public function test_unset_attributes_follow_admin_display_defaults() {
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default_value = false ) {
				if ( Memml_Settings::OPTION_NAME === $name ) {
					return array(
						'default_calendar'            => 'volunteers',
						'default_view'                => 'month',
						'default_period'              => 'past',
						'default_list_style'          => 'rows',
						'default_limit'               => 6,
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
					);
				}

				return $default_value;
			}
		);

		$this->assertSame( 'volunteers', $this->call_renderer( 'resolve_calendar', '' ) );
		$this->assertSame( 'month', $this->call_renderer( 'resolve_layout', '' ) );
		$this->assertSame( 'past', $this->call_renderer( 'get_period_from_attributes', array() ) );
		$this->assertSame( 'rows', $this->call_renderer( 'get_list_style_from_attributes', array() ) );
		$this->assertSame( 6, $this->call_renderer( 'get_limit_from_attributes', array() ) );
		$this->assertFalse( $this->call_renderer( 'get_control_from_attributes', array(), 'calendarSwitcher', 'calendar_switcher', 'calendar_switcher' ) );
		$this->assertFalse( $this->call_renderer( 'get_control_from_attributes', array(), 'layoutSwitcher', 'layout_switcher', 'layout_switcher' ) );
		$this->assertFalse( $this->call_renderer( 'get_control_from_attributes', array(), 'periodSwitcher', 'period_switcher', 'period_switcher' ) );
		$this->assertFalse( $this->call_renderer( 'get_subscribe_from_attributes', array() ) );

		foreach ( $this->call_renderer( 'get_visibility_from_attributes', array() ) as $is_visible ) {
			$this->assertFalse( $is_visible );
		}

		// Explicit block or shortcode values still win over the site default.
		$this->assertSame( 'events', $this->call_renderer( 'resolve_calendar', 'events' ) );
		$this->assertSame( 'list', $this->call_renderer( 'resolve_layout', 'list' ) );
		$this->assertSame( 'upcoming', $this->call_renderer( 'get_period_from_attributes', array( 'period' => 'upcoming' ) ) );
		$this->assertSame( 'grid', $this->call_renderer( 'get_list_style_from_attributes', array( 'listStyle' => 'grid' ) ) );
		$this->assertSame( 0, $this->call_renderer( 'get_limit_from_attributes', array( 'limit' => 0 ) ) );
		$this->assertSame( 3, $this->call_renderer( 'get_limit_from_attributes', array( 'limit' => '3' ) ) );
		$this->assertTrue( $this->call_renderer( 'get_control_from_attributes', array( 'calendarSwitcher' => 'yes' ), 'calendarSwitcher', 'calendar_switcher', 'calendar_switcher' ) );
		$this->assertTrue( $this->call_renderer( 'get_control_from_attributes', array( 'layoutSwitcher' => 'yes' ), 'layoutSwitcher', 'layout_switcher', 'layout_switcher' ) );
		$this->assertTrue( $this->call_renderer( 'get_control_from_attributes', array( 'periodSwitcher' => 'yes' ), 'periodSwitcher', 'period_switcher', 'period_switcher' ) );
		$this->assertTrue( $this->call_renderer( 'get_subscribe_from_attributes', array( 'subscribe' => 'yes' ) ) );
		$this->assertTrue( $this->call_renderer( 'get_visibility_from_attributes', array( 'showImages' => 'yes' ) )['show_images'] );
		$this->assertTrue( $this->call_renderer( 'get_visibility_from_attributes', array( 'show_online' => 'yes' ) )['show_online'] );
	}

	/**
	 * Valid visitor URL state takes precedence over site display defaults.
	 *
	 * @return void
	 */
	public function test_visitor_state_wins_over_calendar_layout_and_period_defaults() {
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default_value = false ) {
				if ( Memml_Settings::OPTION_NAME === $name ) {
					return array(
						'default_calendar' => 'volunteers',
						'default_view'     => 'month',
						'default_period'   => 'past',
					);
				}

				return $default_value;
			}
		);

		$_GET['memml_main_calendar'] = 'events';
		$_GET['memml_main_view']     = 'list';
		$_GET['memml_main_period']   = 'upcoming';

		$this->assertSame( 'events', $this->call_renderer( 'get_initial_calendar', '', 'memml_main_' ) );
		$this->assertSame( 'list', $this->call_renderer( 'get_initial_layout', '', 'memml_main_' ) );
		$this->assertSame( 'upcoming', $this->call_renderer( 'get_initial_period', '', 'memml_main_' ) );
	}

	/**
	 * Hidden controls keep their configured state even when an old share URL disagrees.
	 *
	 * @return void
	 */
	public function test_hidden_controls_ignore_visitor_url_state() {
		$_GET['memml_fixed_calendar'] = 'volunteers';
		$_GET['memml_fixed_view']     = 'month';
		$_GET['memml_fixed_period']   = 'past';

		$this->assertSame( 'events', $this->call_renderer( 'get_initial_calendar', 'events', 'memml_fixed_', false ) );
		$this->assertSame( 'list', $this->call_renderer( 'get_initial_layout', 'list', 'memml_fixed_', false ) );
		$this->assertSame( 'upcoming', $this->call_renderer( 'get_initial_period', 'upcoming', 'memml_fixed_', false ) );
	}

	/**
	 * Every block exposes a distinct inherited state for period and list limit.
	 *
	 * @return void
	 */
	public function test_block_metadata_uses_inherited_defaults() {
		$root = dirname( __DIR__, 2 );

		foreach ( array( 'calendar', 'events', 'volunteers' ) as $block ) {
			$metadata = json_decode( file_get_contents( $root . '/blocks/' . $block . '/block.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.

			$this->assertSame( '', $metadata['attributes']['period']['default'] );
			$this->assertSame( -1, $metadata['attributes']['limit']['default'] );
			$this->assertSame( '', $metadata['attributes']['layoutSwitcher']['default'] );
			$this->assertSame( '', $metadata['attributes']['periodSwitcher']['default'] );

			foreach ( array( 'showImages', 'showDescriptions', 'showItemCount', 'showDetails', 'showVenueCost', 'showVolunteerSignup' ) as $attribute ) {
				$this->assertSame( '', $metadata['attributes'][ $attribute ]['default'] );
			}
		}

		$calendar = json_decode( file_get_contents( $root . '/blocks/calendar/block.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.
		$this->assertSame( '', $calendar['attributes']['calendar']['default'] );
		$this->assertSame( '', $calendar['attributes']['calendarSwitcher']['default'] );
		$this->assertSame( '', $calendar['attributes']['showVolunteerAvailability']['default'] );
		$this->assertSame( '', $calendar['attributes']['showCancelledEvents']['default'] );
		$this->assertSame( '', $calendar['attributes']['showRegistration']['default'] );
		$this->assertSame( '', $calendar['attributes']['showOnline']['default'] );
		$this->assertSame( '', $calendar['attributes']['showAddToCalendar']['default'] );
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
	 * Visitor action links disappear once the event or opportunity date has passed.
	 *
	 * @return void
	 */
	public function test_expired_action_links_are_hidden_by_date() {
		$timezone = new DateTimeZone( 'America/New_York' );
		$today    = new DateTimeImmutable( 'today', $timezone );
		$past     = array(
			'title'              => 'Completed cleanup',
			'eventDate'          => '2020-01-01',
			'publicEventUrl'     => 'https://events.example/completed-cleanup',
			'ctaLabel'           => 'Register',
			'meetingUrl'         => 'https://meet.example/completed-cleanup',
			'url'                => 'https://memml.com/volunteer/completed-cleanup',
			'volunteerSignupUrl' => 'https://memml.com/events/completed-cleanup/volunteer',
		);
		$future   = array(
			'title'              => 'Future cleanup',
			'eventDate'          => '2099-01-01',
			'publicEventUrl'     => 'https://events.example/future-cleanup',
			'ctaLabel'           => 'Register',
			'meetingUrl'         => 'https://meet.example/future-cleanup',
			'url'                => 'https://memml.com/volunteer/future-cleanup',
			'volunteerSignupUrl' => 'https://memml.com/events/future-cleanup/volunteer',
		);
		$current  = array(
			'title'      => 'Cleanup in progress',
			'eventDate'  => $today->format( 'Y-m-d' ),
			'meetingUrl' => 'https://meet.example/current-cleanup',
		);
		$undated  = array(
			'title'      => 'Undated cleanup',
			'meetingUrl' => 'https://meet.example/undated-cleanup',
		);

		$this->assertSame( '', $this->call_renderer( 'render_volunteer_actions', $past, $timezone ) );
		$this->assertSame( '', $this->call_renderer( 'render_event_actions', $past, $timezone ) );
		$this->assertSame( '', $this->call_renderer( 'render_event_actions', $undated, $timezone ) );
		$this->assertStringContainsString( 'Volunteer', $this->call_renderer( 'render_volunteer_actions', $future, $timezone ) );
		$future_actions  = $this->call_renderer( 'render_event_actions', $future, $timezone );
		$current_actions = $this->call_renderer( 'render_event_actions', $current, $timezone );

		$this->assertStringContainsString( 'Register', $future_actions );
		$this->assertStringContainsString( 'href="https://meet.example/future-cleanup"', $future_actions );
		$this->assertStringContainsString( 'Join online', $future_actions );
		$this->assertStringContainsString( 'Volunteer', $future_actions );
		$this->assertStringContainsString( 'href="https://meet.example/current-cleanup"', $current_actions );
	}

	/**
	 * Each event action can be hidden independently without affecting the others.
	 *
	 * @return void
	 */
	public function test_event_action_visibility_is_independent() {
		$event   = array(
			'title'              => 'Future cleanup',
			'eventDate'          => '2099-01-01',
			'publicEventUrl'     => 'https://events.example/register',
			'ctaLabel'           => 'Register',
			'meetingUrl'         => 'https://meet.example/cleanup',
			'volunteerSignupUrl' => 'https://events.example/volunteer',
			'icsUrl'             => 'https://events.example/cleanup.ics',
		);
		$context = array(
			'show_registration'     => false,
			'show_online'           => true,
			'show_volunteer_signup' => false,
			'show_add_to_calendar'  => false,
		);
		$actions = $this->call_renderer( 'render_event_actions', $event, new DateTimeZone( 'UTC' ), $context );

		$this->assertStringNotContainsString( 'Register', $actions );
		$this->assertStringContainsString( 'Join online', $actions );
		$this->assertStringNotContainsString( '>Volunteer<', $actions );
		$this->assertStringNotContainsString( 'Add to calendar:', $actions );
	}

	/**
	 * Card-level content preferences suppress summaries while leaving the title.
	 *
	 * @return void
	 */
	public function test_card_content_visibility_can_hide_optional_output() {
		$context = array(
			'show_images'                 => false,
			'show_descriptions'           => false,
			'show_details'                => false,
			'show_venue_cost'             => false,
			'show_volunteer_availability' => false,
			'show_volunteer_signup'       => false,
		);
		$card    = $this->call_renderer(
			'render_volunteer_card',
			array(
				'title'          => 'Future cleanup',
				'description'    => 'Bring gloves.',
				'location'       => 'River Park',
				'eventDate'      => '2099-01-01',
				'spotsRemaining' => 4,
				'needsMore'      => true,
				'url'            => 'https://events.example/volunteer',
				'imageUrl'       => 'https://images.example/cleanup.jpg',
			),
			new DateTimeZone( 'UTC' ),
			false,
			$context
		);

		$this->assertStringContainsString( 'Future cleanup', $card );
		$this->assertStringNotContainsString( 'Bring gloves.', $card );
		$this->assertStringNotContainsString( 'River Park', $card );
		$this->assertStringNotContainsString( 'spot', $card );
		$this->assertStringNotContainsString( 'Volunteers needed', $card );
		$this->assertStringNotContainsString( '<img', $card );
		$this->assertStringNotContainsString( 'data-memml-details', $card );
		$this->assertStringNotContainsString( 'events.example/volunteer', $card );
	}

	/**
	 * Structured venue records render richer details and a map destination.
	 *
	 * @return void
	 */
	public function test_structured_event_venue_renders_enhanced_variant() {
		$event = array(
			'location' => 'Longwood Historic Civic Center — 135 W Church Ave, Longwood FL 32750, US',
			'venues'   => array(
				array(
					'name'                => 'Longwood Historic Civic Center',
					'description'         => 'A restored civic building.',
					'streetAddress'       => '135 W Church Ave',
					'streetAddress2'      => 'Second floor',
					'city'                => 'Longwood',
					'stateCode'           => 'FL',
					'postalCode'          => '32750',
					'countryCode'         => 'US',
					'websiteUrl'          => 'https://historiclongwood.example/venue',
					'phone'               => '(407) 555-0123',
					'parkingInformation'  => 'Use the lot behind the building.',
					'arrivalInstructions' => 'Enter through the west doors.',
				),
			),
		);

		$compact = $this->call_renderer( 'render_event_location', $event );
		$full    = $this->call_renderer( 'render_event_location', $event, true );
		$card    = $this->call_renderer(
			'render_event_card',
			array_merge(
				$event,
				array(
					'title'    => 'September 2026 Car Show',
					'status'   => 'scheduled',
					'startsAt' => '2026-09-12T20:00:00.000Z',
					'endsAt'   => '2026-09-13T00:00:00.000Z',
				)
			),
			new DateTimeZone( 'America/New_York' ),
			false
		);

		$this->assertStringContainsString( 'memml-calendar__venue--enhanced', $compact );
		$this->assertStringContainsString( 'Longwood Historic Civic Center', $compact );
		$this->assertStringContainsString( '135 W Church Ave, Second floor, Longwood, FL 32750, US', $compact );
		$this->assertStringContainsString( 'https://www.google.com/maps/search/?api=1&query=Longwood%20Historic%20Civic%20Center%2C%20135%20W%20Church%20Ave%2C%20Second%20floor%2C%20Longwood%2C%20FL%2032750%2C%20US', $compact );
		$this->assertStringNotContainsString( 'Use the lot behind the building.', $compact );
		$this->assertStringContainsString( 'A restored civic building.', $full );
		$this->assertStringContainsString( 'href="tel:4075550123"', $full );
		$this->assertStringContainsString( 'https://historiclongwood.example/venue', $full );
		$this->assertStringContainsString( '<strong>Parking:</strong> Use the lot behind the building.', $full );
		$this->assertStringContainsString( '<strong>Arrival:</strong> Enter through the west doors.', $full );
		$this->assertStringContainsString( 'data-memml-details hidden', $card );
		$this->assertStringContainsString( '<strong>Parking:</strong> Use the lot behind the building.', $card );
	}

	/**
	 * Legacy and incomplete venue records do not gain misleading map links.
	 *
	 * @return void
	 */
	public function test_event_venue_falls_back_and_requires_full_address_for_maps() {
		$legacy     = $this->call_renderer(
			'render_event_location',
			array(
				'location' => 'Historic Civic Center',
				'venues'   => array(),
			)
		);
		$incomplete = $this->call_renderer(
			'render_event_location',
			array(
				'location' => 'Longwood Historic Civic Center',
				'venues'   => array(
					array(
						'name'          => 'Longwood Historic Civic Center',
						'streetAddress' => '135 W Church Ave',
						'city'          => 'Longwood',
					),
				),
			)
		);

		$this->assertSame( '<span class="memml-calendar__location">Historic Civic Center</span>', $legacy );
		$this->assertStringContainsString( 'memml-calendar__venue--enhanced', $incomplete );
		$this->assertStringNotContainsString( 'google.com/maps', $incomplete );
	}

	/**
	 * The rows list style renders chip, body, and aside columns.
	 *
	 * @return void
	 */
	public function test_volunteer_row_renders_chip_body_and_aside() {
		$timezone = new DateTimeZone( 'America/New_York' );
		$row      = $this->call_renderer(
			'render_volunteer_row',
			array(
				'title'          => 'September 2026 Car Show',
				'description'    => 'Join us for the monthly car show.',
				'location'       => 'Historic Civic Center',
				'spotsRemaining' => 10,
				'needsMore'      => 5,
				'startsAt'       => '2026-09-12T20:00:00.000Z',
				'endsAt'         => '2026-09-13T00:00:00.000Z',
				'url'            => 'https://memml.com/volunteer/org/v-1',
			),
			$timezone,
			false
		);

		$this->assertStringContainsString( '<article class="memml-calendar__row memml-calendar__card--volunteer" data-memml-item>', $row );
		$this->assertStringContainsString( '<span aria-hidden="true" class="memml-calendar__date-chip"><span>Sep</span><strong>12</strong></span><div class="memml-calendar__row-body">', $row );
		$this->assertStringContainsString( 'memml-calendar__meta memml-calendar__meta--inline', $row );
		$this->assertStringContainsString( 'Saturday · 4:00 PM–8:00 PM', $row );
		$this->assertStringContainsString( '<div class="memml-calendar__row-aside"><span class="memml-calendar__status memml-calendar__status--needed">', $row );
		$this->assertStringContainsString( 'https://memml.com/volunteer/org/v-1', $row );
		$this->assertStringContainsString( 'data-memml-details hidden', $row );
		// The chip lives at row level, so the visible meta line must not
		// repeat it; the hidden details panel keeps its own for the dialog.
		$body_start = strpos( $row, 'memml-calendar__row-body' );
		$body       = substr( $row, $body_start, strpos( $row, 'memml-calendar__row-aside' ) - $body_start );

		$this->assertStringNotContainsString( 'memml-calendar__date-chip', $body );
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
