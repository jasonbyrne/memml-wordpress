<?php
/**
 * Settings unit tests.
 *
 * @package Memml
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests display-default migration and sanitization.
 */
final class Memml_Settings_Test extends TestCase {

	/**
	 * Saved option returned by the WordPress stub.
	 *
	 * @var array
	 */
	private $saved_options;

	/**
	 * Sets up Brain Monkey and shared WordPress functions.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->saved_options = array(
			'organization_key' => 'river-city-neighbors',
			'base_url'         => Memml_Feed_Client::DEFAULT_BASE_URL,
		);

		Functions\when( 'get_option' )->alias(
			function ( $name, $default_value = false ) {
				return Memml_Settings::OPTION_NAME === $name ? $this->saved_options : $default_value;
			}
		);
		Functions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( $value ) {
				return trim( (string) $value );
			}
		);
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'untrailingslashit' )->alias(
			static function ( $value ) {
				return rtrim( (string) $value, '/' );
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
	 * Older saved options gain the new display defaults without a migration.
	 *
	 * @return void
	 */
	public function test_legacy_options_receive_complete_display_defaults() {
		$options = Memml_Settings::get_options();

		$this->assertSame( 'events', $options['default_calendar'] );
		$this->assertSame( 'list', $options['default_view'] );
		$this->assertSame( 'upcoming', $options['default_period'] );
		$this->assertSame( 'grid', $options['default_list_style'] );
		$this->assertSame( 0, $options['default_limit'] );
		$this->assertTrue( $options['calendar_switcher'] );
		$this->assertTrue( $options['layout_switcher'] );
		$this->assertTrue( $options['period_switcher'] );
		$this->assertTrue( $options['subscribe_links'] );

		foreach ( Memml_Renderer::VISIBILITY_PREFERENCES as $option_name => $attributes ) {
			unset( $attributes );
			$this->assertTrue( $options[ $option_name ], $option_name . ' should preserve existing output for legacy settings.' );
		}
	}

	/**
	 * Every display preference accepts and preserves its documented value.
	 *
	 * @return void
	 */
	public function test_sanitize_accepts_complete_display_defaults() {
		$settings = new Memml_Settings();
		$result   = $settings->sanitize(
			array(
				'organization_key'            => 'river-city-neighbors',
				'base_url'                    => Memml_Feed_Client::DEFAULT_BASE_URL,
				'default_calendar'            => 'volunteers',
				'default_view'                => 'month',
				'default_period'              => 'past',
				'default_list_style'          => 'rows',
				'default_limit'               => '12',
				'calendar_switcher'           => '1',
				'layout_switcher'             => '1',
				'period_switcher'             => '1',
				'subscribe_links'             => '1',
				'show_images'                 => '1',
				'show_descriptions'           => '1',
				'show_item_count'             => '1',
				'show_details'                => '1',
				'show_venue_cost'             => '1',
				'show_volunteer_availability' => '1',
				'show_cancelled_events'       => '1',
				'show_registration'           => '1',
				'show_online'                 => '1',
				'show_volunteer_signup'       => '1',
				'show_add_to_calendar'        => '1',
			)
		);

		$this->assertSame( 'volunteers', $result['default_calendar'] );
		$this->assertSame( 'month', $result['default_view'] );
		$this->assertSame( 'past', $result['default_period'] );
		$this->assertSame( 'rows', $result['default_list_style'] );
		$this->assertSame( 12, $result['default_limit'] );
		$this->assertTrue( $result['calendar_switcher'] );
		$this->assertTrue( $result['layout_switcher'] );
		$this->assertTrue( $result['period_switcher'] );
		$this->assertTrue( $result['subscribe_links'] );

		foreach ( Memml_Renderer::VISIBILITY_PREFERENCES as $option_name => $attributes ) {
			unset( $attributes );
			$this->assertTrue( $result[ $option_name ] );
		}
	}

	/**
	 * Invalid values fall back to safe defaults and limits cannot be negative.
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_invalid_display_defaults() {
		$settings = new Memml_Settings();
		$result   = $settings->sanitize(
			array(
				'organization_key'   => 'river-city-neighbors',
				'base_url'           => Memml_Feed_Client::DEFAULT_BASE_URL,
				'default_calendar'   => 'other',
				'default_view'       => 'agenda',
				'default_period'     => 'today',
				'default_list_style' => 'table',
				'default_limit'      => '-4',
			)
		);

		$this->assertSame( 'events', $result['default_calendar'] );
		$this->assertSame( 'list', $result['default_view'] );
		$this->assertSame( 'upcoming', $result['default_period'] );
		$this->assertSame( 'grid', $result['default_list_style'] );
		$this->assertSame( 0, $result['default_limit'] );
		$this->assertFalse( $result['calendar_switcher'] );
		$this->assertFalse( $result['layout_switcher'] );
		$this->assertFalse( $result['period_switcher'] );
		$this->assertFalse( $result['subscribe_links'] );

		foreach ( Memml_Renderer::VISIBILITY_PREFERENCES as $option_name => $attributes ) {
			unset( $attributes );
			$this->assertFalse( $result[ $option_name ] );
		}
	}
}
