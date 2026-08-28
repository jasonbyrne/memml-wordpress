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

		$result = ( new Memml_Feed_Client() )->get_events( 'river-city-neighbors' );

		self::assertTrue( $result['is_stale'] );
		self::assertSame( 'memml_invalid_feed', $result['warning']->get_error_code() );
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
