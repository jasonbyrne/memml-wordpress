<?php
/**
 * Page-cache compatibility unit tests.
 *
 * @package Memml
 */

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Tests page lifetimes and purges requested from full-page caches.
 */
final class Memml_Page_Cache_Test extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Sets up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Memml_Page_Cache::reset();
	}

	/**
	 * Tears down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Memml_Page_Cache::reset();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Builds a fresh feed result that expires after the given seconds.
	 *
	 * @param int  $seconds  Seconds until the feed expires.
	 * @param bool $is_stale Whether stale fallback is in use.
	 * @return array
	 */
	private function feed_result( $seconds, $is_stale = false ) {
		return array(
			'data'       => array(),
			'etag'       => '"etag"',
			'fetched_at' => time(),
			'expires_at' => time() + $seconds,
			'is_stale'   => $is_stale,
			'from_cache' => true,
			'warning'    => null,
		);
	}

	/**
	 * Matches a lifetime within a second of the expected value.
	 *
	 * @param int $expected Expected lifetime.
	 * @return \Mockery\Matcher\Closure
	 */
	private function about( $expected ) {
		return Mockery::on(
			static function ( $ttl ) use ( $expected ) {
				return is_int( $ttl ) && abs( $ttl - $expected ) <= 1;
			}
		);
	}

	/**
	 * A fresh feed limits the page to the feed's remaining lifetime and tags it.
	 *
	 * @return void
	 */
	public function test_fresh_feed_limits_page_to_remaining_feed_lifetime() {
		Actions\expectDone( 'litespeed_tag_add' )->once()->with( 'memml' );
		Actions\expectDone( 'litespeed_control_set_ttl' )->once()->with( $this->about( 540 ) );
		Actions\expectDone( 'memml_page_cache_limit' )->once()->with( $this->about( 540 ) );

		Memml_Page_Cache::limit_for_feed( 'volunteers', $this->feed_result( 540 ) );
	}

	/**
	 * Stale data, errors, and nearly expired feeds use the minimum lifetime.
	 *
	 * @return void
	 */
	public function test_unhealthy_or_expiring_feeds_use_minimum_lifetime() {
		$results = array(
			$this->feed_result( 540, true ),
			new WP_Error( 'memml_network_error', 'Memml could not be reached.' ),
			$this->feed_result( 5 ),
			array_merge( $this->feed_result( 0 ), array( 'expires_at' => 0 ) ),
		);

		Actions\expectDone( 'litespeed_control_set_ttl' )->once()->with( Memml_Page_Cache::MIN_TTL );

		foreach ( $results as $result ) {
			Memml_Page_Cache::limit_for_feed( 'events', $result );
		}
	}

	/**
	 * A later feed on the same page can shorten the lifetime but never lengthen it.
	 *
	 * @return void
	 */
	public function test_lifetime_only_decreases_within_a_request() {
		$lifetimes = array();

		Actions\expectDone( 'litespeed_control_set_ttl' )->whenHappen(
			static function ( $ttl ) use ( &$lifetimes ) {
				$lifetimes[] = (int) round( $ttl / 10 ) * 10;
			}
		);

		Memml_Page_Cache::limit_for_feed( 'events', $this->feed_result( 300 ) );
		Memml_Page_Cache::limit_for_feed( 'volunteers', $this->feed_result( 500 ) );
		Memml_Page_Cache::limit_for_feed( 'committees', $this->feed_result( 120 ) );

		$this->assertSame( array( 300, 120 ), $lifetimes );
	}

	/**
	 * A shorter LiteSpeed public lifetime configured by the site is kept.
	 *
	 * @return void
	 */
	public function test_litespeed_lifetime_never_exceeds_site_setting() {
		Filters\expectApplied( 'litespeed_conf' )->once()->with( 'cache-ttl_pub' )->andReturn( '200' );
		Actions\expectDone( 'litespeed_control_set_ttl' )->once()->with( 200 );
		Actions\expectDone( 'memml_page_cache_limit' )->once()->with( $this->about( 540 ) );

		Memml_Page_Cache::limit_for_feed( 'events', $this->feed_result( 540 ) );
	}

	/**
	 * The lifetime filter can adjust or disable the request.
	 *
	 * @return void
	 */
	public function test_lifetime_filter_adjusts_or_disables_request() {
		Filters\expectApplied( 'memml_page_cache_ttl' )->twice()->andReturn( 90, 0 );
		Actions\expectDone( 'litespeed_tag_add' )->once();
		Actions\expectDone( 'litespeed_control_set_ttl' )->once()->with( 90 );
		Actions\expectDone( 'memml_page_cache_limit' )->once()->with( 90 );

		Memml_Page_Cache::limit_for_feed( 'events', $this->feed_result( 540 ) );
		Memml_Page_Cache::reset();
		Memml_Page_Cache::limit_for_feed( 'events', $this->feed_result( 540 ) );
	}

	/**
	 * Purging clears LiteSpeed's Memml-tagged pages and notifies other caches.
	 *
	 * @return void
	 */
	public function test_purge_clears_tagged_pages() {
		Actions\expectDone( 'litespeed_purge' )->once()->with( 'memml' );
		Actions\expectDone( 'memml_page_cache_purge' )->once();

		Memml_Page_Cache::purge();
	}

	/**
	 * Saving Memml settings purges pages that show Memml content.
	 *
	 * @return void
	 */
	public function test_settings_changes_purge_pages() {
		( new Memml_Page_Cache() )->register();

		$this->assertNotFalse( has_action( 'add_option_memml_settings', array( Memml_Page_Cache::class, 'purge' ) ) );
		$this->assertNotFalse( has_action( 'update_option_memml_settings', array( Memml_Page_Cache::class, 'purge' ) ) );
	}
}
