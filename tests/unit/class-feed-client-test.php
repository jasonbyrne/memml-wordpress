<?php
/**
 * Feed-client unit tests.
 *
 * @package Memml
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests ETag caching and resilient error handling.
 */
final class Memml_Feed_Client_Test extends TestCase {

	/**
	 * Events fixture data.
	 *
	 * @var array
	 */
	private $events;

	/**
	 * Sets up Brain Monkey and shared WordPress functions.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->events = json_decode(
			file_get_contents( dirname( __DIR__ ) . '/fixtures/events.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.
			true
		);

		Functions\when( '__' )->returnArg();
		Functions\when( 'untrailingslashit' )->alias(
			static function ( $value ) {
				return rtrim( $value, '/' );
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				unset( $hook );
				return $value;
			}
		);
		Functions\when( 'is_wp_error' )->alias(
			static function ( $value ) {
				return $value instanceof WP_Error;
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static function ( $response ) {
				return $response['response']['code'];
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static function ( $response ) {
				return $response['body'];
			}
		);
		Functions\when( 'wp_remote_retrieve_header' )->alias(
			static function ( $response, $header ) {
				return isset( $response['headers'][ $header ] ) ? $response['headers'][ $header ] : '';
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
	 * A fresh cache hit does not make an HTTP request.
	 *
	 * @return void
	 */
	public function test_fresh_cache_hit_skips_request() {
		$cached = $this->cache_record( time() + 600 );

		Functions\expect( 'get_transient' )->once()->andReturn( $cached );
		Functions\expect( 'wp_remote_get' )->never();

		$result = ( new Memml_Feed_Client() )->get_events( 'river-city-neighbors' );

		self::assertSame( $this->events, $result['data'] );
		self::assertTrue( $result['from_cache'] );
		self::assertFalse( $result['is_stale'] );
	}

	/**
	 * A successful request is cached with its ETag.
	 *
	 * @return void
	 */
	public function test_successful_request_caches_etagged_feed() {
		Functions\expect( 'get_transient' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				'https://memml.com/api/public/v1/river-city-neighbors/events.json',
				Mockery::on(
					static function ( $args ) {
						return 'application/json' === $args['headers']['Accept'] && 10 === $args['timeout'];
					}
				)
			)
			->andReturn( $this->http_response( 200, $this->events, '"events-v1"' ) );
		Functions\expect( 'set_transient' )
			->once()
			->with(
				Mockery::pattern( '/^memml_feed_[a-f0-9]{32}$/' ),
				Mockery::on(
					static function ( $record ) {
						return '"events-v1"' === $record['etag'] && isset( $record['expires_at'] );
					}
				),
				Memml_Feed_Client::DEFAULT_STALE_TTL
			);

		$result = ( new Memml_Feed_Client() )->get_events( 'river-city-neighbors' );

		self::assertSame( '"events-v1"', $result['etag'] );
		self::assertFalse( $result['from_cache'] );
		self::assertFalse( $result['is_stale'] );
	}

	/**
	 * An expired response is conditionally revalidated.
	 *
	 * @return void
	 */
	public function test_expired_cache_sends_etag_and_uses_304_response() {
		$cached = $this->cache_record( time() - 1 );

		Functions\expect( 'get_transient' )->once()->andReturn( $cached );
		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				Mockery::type( 'string' ),
				Mockery::on(
					static function ( $args ) {
						return '"events-v1"' === $args['headers']['If-None-Match'];
					}
				)
			)
			->andReturn( $this->http_response( 304 ) );
		Functions\expect( 'set_transient' )->once();

		$result = ( new Memml_Feed_Client() )->get_events( 'river-city-neighbors' );

		self::assertSame( $this->events, $result['data'] );
		self::assertTrue( $result['from_cache'] );
		self::assertFalse( $result['is_stale'] );
	}

	/**
	 * A forced test connection revalidates even a fresh response.
	 *
	 * @return void
	 */
	public function test_force_revalidation_bypasses_fresh_cache() {
		Functions\expect( 'get_transient' )->once()->andReturn( $this->cache_record( time() + 600 ) );
		Functions\expect( 'wp_remote_get' )->once()->andReturn( $this->http_response( 304 ) );
		Functions\expect( 'set_transient' )->once();

		$result = ( new Memml_Feed_Client() )->get_events( 'river-city-neighbors', true );

		self::assertTrue( $result['from_cache'] );
	}

	/**
	 * Network failure serves the last known-good feed.
	 *
	 * @return void
	 */
	public function test_network_error_serves_stale_cache() {
		Functions\expect( 'get_transient' )->once()->andReturn( $this->cache_record( time() - 1 ) );
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( new WP_Error( 'http_request_failed', 'Connection timed out.' ) );
		Functions\expect( 'set_transient' )
			->once()
			->with(
				Mockery::type( 'string' ),
				Mockery::on(
					static function ( $record ) {
						return isset( $record['data'], $record['retry_after'] ) &&
							true === $record['error']['allow_stale'] &&
							'memml_network_error' === $record['error']['code'];
					}
				),
				Mockery::type( 'int' )
			);

		$result = ( new Memml_Feed_Client() )->get_events( 'river-city-neighbors' );

		self::assertTrue( $result['is_stale'] );
		self::assertInstanceOf( WP_Error::class, $result['warning'] );
		self::assertSame( 'memml_network_error', $result['warning']->get_error_code() );
	}

	/**
	 * Network failure without a cache remains distinguishable.
	 *
	 * @return void
	 */
	public function test_network_error_without_cache_returns_error() {
		Functions\expect( 'get_transient' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( new WP_Error( 'http_request_failed', 'Connection timed out.' ) );
		Functions\expect( 'set_transient' )->once();

		$result = ( new Memml_Feed_Client() )->get_events( 'river-city-neighbors' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'memml_network_error', $result->get_error_code() );
	}

	/**
	 * A bad organization key returns a 404-specific error, not stale data.
	 *
	 * @return void
	 */
	public function test_404_distinguishes_bad_organization_key() {
		Functions\expect( 'get_transient' )->once()->andReturn( $this->cache_record( time() - 1 ) );
		Functions\expect( 'wp_remote_get' )->once()->andReturn( $this->http_response( 404 ) );
		Functions\expect( 'set_transient' )
			->once()
			->with(
				Mockery::type( 'string' ),
				Mockery::on(
					static function ( $record ) {
						return false === $record['error']['allow_stale'];
					}
				),
				Mockery::type( 'int' )
			);

		$result = ( new Memml_Feed_Client() )->get_events( 'wrong-key' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'memml_organization_not_found', $result->get_error_code() );
		self::assertSame( 404, $result->get_error_data()['status'] );
	}

	/**
	 * Invalid JSON does not replace a valid cached response.
	 *
	 * @return void
	 */
	public function test_invalid_json_serves_stale_cache() {
		Functions\expect( 'get_transient' )->once()->andReturn( $this->cache_record( time() - 1 ) );
		Functions\expect( 'wp_remote_get' )->once()->andReturn(
			array(
				'response' => array( 'code' => 200 ),
				'headers'  => array(),
				'body'     => '{not-json',
			)
		);
		Functions\expect( 'set_transient' )->once();

		$result = ( new Memml_Feed_Client() )->get_events( 'river-city-neighbors' );

		self::assertTrue( $result['is_stale'] );
		self::assertSame( 'memml_invalid_feed', $result['warning']->get_error_code() );
	}

	/**
	 * A recent failure is replayed from cache instead of re-requesting the feed.
	 *
	 * @return void
	 */
	public function test_recent_failure_skips_request_and_serves_stale_cache() {
		$cached                = $this->cache_record( time() - 1 );
		$cached['retry_after'] = time() + 60;
		$cached['error']       = array(
			'code'        => 'memml_network_error',
			'message'     => 'Memml could not be reached. Please try again.',
			'data'        => array(),
			'allow_stale' => true,
		);

		Functions\expect( 'get_transient' )->once()->andReturn( $cached );
		Functions\expect( 'wp_remote_get' )->never();

		$result = ( new Memml_Feed_Client() )->get_events( 'river-city-neighbors' );

		self::assertSame( $this->events, $result['data'] );
		self::assertTrue( $result['is_stale'] );
		self::assertSame( 'memml_network_error', $result['warning']->get_error_code() );
	}

	/**
	 * A recent bad-key failure is replayed without falling back to stale data.
	 *
	 * @return void
	 */
	public function test_recent_not_found_failure_skips_request_and_returns_error() {
		$cached                = $this->cache_record( time() - 1 );
		$cached['retry_after'] = time() + 60;
		$cached['error']       = array(
			'code'        => 'memml_organization_not_found',
			'message'     => 'No Memml organization was found for that key.',
			'data'        => array( 'status' => 404 ),
			'allow_stale' => false,
		);

		Functions\expect( 'get_transient' )->once()->andReturn( $cached );
		Functions\expect( 'wp_remote_get' )->never();

		$result = ( new Memml_Feed_Client() )->get_events( 'wrong-key' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'memml_organization_not_found', $result->get_error_code() );
	}

	/**
	 * A connection test still reaches Memml during a backoff window.
	 *
	 * @return void
	 */
	public function test_force_revalidation_bypasses_failure_backoff() {
		$cached                = $this->cache_record( time() - 1 );
		$cached['retry_after'] = time() + 60;
		$cached['error']       = array(
			'code'        => 'memml_organization_not_found',
			'message'     => 'No Memml organization was found for that key.',
			'data'        => array( 'status' => 404 ),
			'allow_stale' => false,
		);

		Functions\expect( 'get_transient' )->once()->andReturn( $cached );
		Functions\expect( 'wp_remote_get' )->once()->andReturn( $this->http_response( 304 ) );
		Functions\expect( 'set_transient' )->once();

		$result = ( new Memml_Feed_Client() )->get_events( 'river-city-neighbors', true );

		self::assertSame( $this->events, $result['data'] );
	}

	/**
	 * A successful revalidation clears a recorded failure.
	 *
	 * @return void
	 */
	public function test_successful_revalidation_clears_recorded_failure() {
		$cached                = $this->cache_record( time() - 1 );
		$cached['retry_after'] = time() - 1;
		$cached['error']       = array(
			'code'        => 'memml_network_error',
			'message'     => 'Memml could not be reached. Please try again.',
			'data'        => array(),
			'allow_stale' => true,
		);

		Functions\expect( 'get_transient' )->once()->andReturn( $cached );
		Functions\expect( 'wp_remote_get' )->once()->andReturn( $this->http_response( 304 ) );
		Functions\expect( 'set_transient' )
			->once()
			->with(
				Mockery::type( 'string' ),
				Mockery::on(
					static function ( $record ) {
						return ! isset( $record['error'] ) && ! isset( $record['retry_after'] );
					}
				),
				Mockery::type( 'int' )
			);

		$result = ( new Memml_Feed_Client() )->get_events( 'river-city-neighbors' );

		self::assertFalse( $result['is_stale'] );
	}

	/**
	 * The volunteer method requests the contract filename.
	 *
	 * @return void
	 */
	public function test_volunteer_feed_uses_versioned_contract_url() {
		$volunteers = json_decode(
			file_get_contents( dirname( __DIR__ ) . '/fixtures/volunteer-opportunities.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.
			true
		);

		Functions\expect( 'get_transient' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				'https://memml.com/api/public/v1/river-city-neighbors/volunteer-opportunities.json',
				Mockery::type( 'array' )
			)
			->andReturn( $this->http_response( 200, $volunteers, '"volunteers-v1"' ) );
		Functions\expect( 'set_transient' )->once();

		$result = ( new Memml_Feed_Client() )->get_volunteer_opportunities( 'river-city-neighbors' );

		self::assertSame( $volunteers, $result['data'] );
	}

	/**
	 * The committees method requests the contract filename, and accepts an
	 * envelope with no organization timezone.
	 *
	 * @return void
	 */
	public function test_committees_feed_uses_versioned_contract_url() {
		$committees = json_decode(
			file_get_contents( dirname( __DIR__ ) . '/fixtures/committees.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.
			true
		);

		Functions\expect( 'get_transient' )->once()->andReturn( false );
		Functions\expect( 'wp_remote_get' )
			->once()
			->with(
				'https://memml.com/api/public/v1/river-city-neighbors/committees.json',
				Mockery::type( 'array' )
			)
			->andReturn( $this->http_response( 200, $committees, '"committees-v1"' ) );
		Functions\expect( 'set_transient' )->once();

		$result = ( new Memml_Feed_Client() )->get_committees( 'river-city-neighbors' );

		self::assertSame( $committees, $result['data'] );
	}

	/**
	 * A failed check records when it happened.
	 *
	 * @return void
	 */
	public function test_failure_records_when_it_happened() {
		Functions\expect( 'get_transient' )->once()->andReturn( $this->cache_record( time() - 1 ) );
		Functions\expect( 'wp_remote_get' )->once()->andReturn( new WP_Error( 'http_request_failed', 'Timed out.' ) );
		$stored = null;

		Functions\when( 'set_transient' )->alias(
			static function ( $key, $record ) use ( &$stored ) {
				unset( $key );
				$stored = $record;
			}
		);

		( new Memml_Feed_Client() )->get_events( 'river-city-neighbors' );

		self::assertLessThan( 5, abs( time() - $stored['error']['failed_at'] ) );
	}

	/**
	 * Refreshing a feed bypasses a fresh copy, and unknown feeds are refused.
	 *
	 * @return void
	 */
	public function test_refresh_feed_checks_memml_right_away() {
		Functions\expect( 'get_transient' )->once()->andReturn( $this->cache_record( time() + 600 ) );
		Functions\expect( 'wp_remote_get' )
			->once()
			->with( 'https://memml.com/api/public/v1/river-city-neighbors/volunteer-opportunities.json', Mockery::type( 'array' ) )
			->andReturn( $this->http_response( 304 ) );
		Functions\expect( 'set_transient' )->once();

		$client = new Memml_Feed_Client();
		$result = $client->refresh_feed( 'river-city-neighbors', 'volunteers' );

		self::assertFalse( $result['is_stale'] );
		self::assertInstanceOf( WP_Error::class, $client->refresh_feed( 'river-city-neighbors', 'sponsors' ) );
	}

	/**
	 * Feed status reads the saved copy without contacting Memml.
	 *
	 * @return void
	 */
	public function test_feed_status_reports_fresh_copy_details() {
		$now = 1800000000;

		Functions\expect( 'get_transient' )
			->once()
			->with( 'memml_feed_' . md5( 'https://memml.com/api/public/v1/river-city-neighbors/events.json' ) )
			->andReturn(
				array(
					'data'       => $this->events,
					'fetched_at' => $now - 120,
					'expires_at' => $now + 480,
				)
			);
		Functions\expect( 'wp_remote_get' )->never();

		$status = ( new Memml_Feed_Client() )->get_feed_status( 'river-city-neighbors', 'events', $now );

		self::assertSame( 'fresh', $status['state'] );
		self::assertSame( 'River City Neighbors', $status['organization'] );
		self::assertSame( 'America/New_York', $status['timezone'] );
		self::assertSame( count( $this->events['events'] ), $status['item_count'] );
		self::assertSame( $now - 120, $status['fetched_at'] );
		self::assertNull( $status['error'] );
	}

	/**
	 * Each saved-copy situation maps to one plain status.
	 *
	 * @return void
	 */
	public function test_feed_status_states() {
		$now   = 1800000000;
		$error = array(
			'code'        => 'memml_network_error',
			'message'     => 'Memml could not be reached. Please try again.',
			'allow_stale' => true,
			'failed_at'   => $now - 30,
		);
		$data  = array(
			'data'       => $this->events,
			'fetched_at' => $now - 3600,
			'expires_at' => $now - 3000,
		);
		$cases = array(
			'not_loaded' => false,
			'cached'     => $data,
			'stale'      => $data + array( 'error' => $error ),
			'error'      => array(
				'error'      => $error,
				'expires_at' => 0,
			),
		);

		foreach ( $cases as $expected => $record ) {
			Functions\expect( 'get_transient' )->once()->andReturn( $record );

			$status = ( new Memml_Feed_Client() )->get_feed_status( 'river-city-neighbors', 'events', $now );

			self::assertSame( $expected, $status['state'], $expected );
		}

		self::assertSame( 'Memml could not be reached. Please try again.', $status['error'] );
		self::assertSame( $now - 30, $status['failed_at'] );
		self::assertNull( $status['item_count'] );
	}

	/**
	 * A missing organization stays unavailable even with an older copy saved.
	 *
	 * @return void
	 */
	public function test_feed_status_not_found_with_saved_copy_is_an_error() {
		$now = 1800000000;

		Functions\expect( 'get_transient' )->once()->andReturn(
			array(
				'data'       => $this->events,
				'fetched_at' => $now - 3600,
				'expires_at' => $now - 3000,
				'error'      => array(
					'code'        => 'memml_organization_not_found',
					'message'     => 'No Memml organization was found for that key.',
					'allow_stale' => false,
				),
			)
		);

		$status = ( new Memml_Feed_Client() )->get_feed_status( 'river-city-neighbors', 'events', $now );

		self::assertSame( 'error', $status['state'] );
		self::assertSame( 0, $status['failed_at'] );
	}

	/**
	 * Volunteer and committee counts use their own item lists.
	 *
	 * @return void
	 */
	public function test_feed_status_counts_each_feed_items() {
		$now = 1800000000;

		Functions\expect( 'get_transient' )->twice()->andReturn(
			array(
				'data'       => array(
					'organization'           => array( 'name' => 'River City Neighbors' ),
					'volunteerOpportunities' => array( array(), array(), array() ),
					'committees'             => array( array() ),
				),
				'fetched_at' => $now,
				'expires_at' => $now + 600,
			)
		);

		$client = new Memml_Feed_Client();

		self::assertSame( 3, $client->get_feed_status( 'river-city-neighbors', 'volunteers', $now )['item_count'] );
		$committees = $client->get_feed_status( 'river-city-neighbors', 'committees', $now );

		self::assertSame( 1, $committees['item_count'] );
		self::assertSame( '', $committees['timezone'] );
	}

	/**
	 * Creates a cache record.
	 *
	 * @param int $expires_at Fresh-cache expiration timestamp.
	 * @return array
	 */
	private function cache_record( $expires_at ) {
		return array(
			'data'       => $this->events,
			'etag'       => '"events-v1"',
			'fetched_at' => time() - 700,
			'expires_at' => $expires_at,
		);
	}

	/**
	 * Creates a WordPress-shaped HTTP response.
	 *
	 * @param int    $status HTTP response status.
	 * @param array  $body   JSON body data.
	 * @param string $etag   ETag header.
	 * @return array
	 */
	private function http_response( $status, $body = array(), $etag = '' ) {
		return array(
			'response' => array( 'code' => $status ),
			'headers'  => array( 'etag' => $etag ),
			'body'     => $body ? json_encode( $body ) : '', // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- WordPress is intentionally not loaded in isolated tests.
		);
	}
}
