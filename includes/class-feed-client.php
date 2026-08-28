<?php
/**
 * Memml public feed client.
 *
 * @package Memml
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetches and caches Memml public feeds.
 */
final class Memml_Feed_Client {

	/**
	 * Production API origin.
	 *
	 * @var string
	 */
	public const DEFAULT_BASE_URL = 'https://memml.com';

	/**
	 * Default fresh-cache lifetime in seconds.
	 *
	 * @var int
	 */
	public const DEFAULT_CACHE_TTL = 600;

	/**
	 * Default lifetime of the last known-good response in seconds.
	 *
	 * @var int
	 */
	public const DEFAULT_STALE_TTL = 604800;

	/**
	 * Default backoff after a failed refresh, in seconds.
	 *
	 * Keeps a slow or unreachable Memml service from being re-requested on
	 * every page view while an error or stale response is being served.
	 *
	 * @var int
	 */
	public const DEFAULT_FAILURE_TTL = 60;

	/**
	 * Feed filename for events.
	 *
	 * @var string
	 */
	private const EVENTS_FEED = 'events.json';

	/**
	 * Feed filename for volunteer opportunities.
	 *
	 * @var string
	 */
	private const VOLUNTEERS_FEED = 'volunteer-opportunities.json';

	/**
	 * API origin.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Fresh-cache lifetime.
	 *
	 * @var int
	 */
	private $cache_ttl;

	/**
	 * Constructor.
	 *
	 * @param string $base_url  API origin.
	 * @param int    $cache_ttl Fresh-cache lifetime in seconds.
	 */
	public function __construct( $base_url = self::DEFAULT_BASE_URL, $cache_ttl = self::DEFAULT_CACHE_TTL ) {
		$this->base_url  = untrailingslashit( '' !== $base_url ? $base_url : self::DEFAULT_BASE_URL );
		$this->cache_ttl = max( 0, (int) $cache_ttl );
	}

	/**
	 * Gets the public events feed.
	 *
	 * @param string $organization_key Memml organization key.
	 * @param bool   $force_revalidate  Whether to bypass a still-fresh response.
	 * @return array|WP_Error Normalized response or an error.
	 */
	public function get_events( $organization_key, $force_revalidate = false ) {
		return $this->get_feed( $organization_key, self::EVENTS_FEED, $force_revalidate );
	}

	/**
	 * Gets the public volunteer opportunities feed.
	 *
	 * @param string $organization_key Memml organization key.
	 * @param bool   $force_revalidate  Whether to bypass a still-fresh response.
	 * @return array|WP_Error Normalized response or an error.
	 */
	public function get_volunteer_opportunities( $organization_key, $force_revalidate = false ) {
		return $this->get_feed( $organization_key, self::VOLUNTEERS_FEED, $force_revalidate );
	}

	/**
	 * Gets the public feed URLs for an organization.
	 *
	 * @param string $organization_key Memml organization key.
	 * @return array Feed label keyed URLs.
	 */
	public function get_feed_urls( $organization_key ) {
		return array(
			'events'     => $this->build_feed_url( $organization_key, self::EVENTS_FEED ),
			'volunteers' => $this->build_feed_url( $organization_key, self::VOLUNTEERS_FEED ),
		);
	}

	/**
	 * Discards every cached response for an organization.
	 *
	 * @param string $organization_key Memml organization key.
	 * @return void
	 */
	public function flush_cache( $organization_key ) {
		foreach ( $this->get_feed_urls( $organization_key ) as $url ) {
			delete_transient( 'memml_feed_' . md5( $url ) );
		}
	}

	/**
	 * Gets a public feed with ETag revalidation and stale-on-error fallback.
	 *
	 * @param string $organization_key Memml organization key.
	 * @param string $feed_filename    Allowed feed filename.
	 * @param bool   $force_revalidate Whether to bypass a still-fresh response.
	 * @return array|WP_Error Normalized response or an error.
	 */
	private function get_feed( $organization_key, $feed_filename, $force_revalidate ) {
		$organization_key = trim( (string) $organization_key );

		if ( '' === $organization_key ) {
			return new WP_Error(
				'memml_missing_organization_key',
				__( 'A Memml organization key is required.', 'memml' )
			);
		}

		$url       = $this->build_feed_url( $organization_key, $feed_filename );
		$cache_key = 'memml_feed_' . md5( $url );
		$cached    = get_transient( $cache_key );
		$now       = time();

		if ( ! $force_revalidate && is_array( $cached ) ) {
			if ( isset( $cached['expires_at'], $cached['data'] ) && (int) $cached['expires_at'] > $now ) {
				return $this->format_result( $cached, false, true );
			}

			$backoff = $this->get_backoff_result( $cached, $now );

			if ( null !== $backoff ) {
				return $backoff;
			}
		}

		$headers = array(
			'Accept' => 'application/json',
		);

		if ( is_array( $cached ) && ! empty( $cached['etag'] ) ) {
			$headers['If-None-Match'] = (string) $cached['etag'];
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $headers,
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->stale_or_error(
				$cache_key,
				$cached,
				$feed_filename,
				new WP_Error(
					'memml_network_error',
					__( 'Memml could not be reached. Please try again.', 'memml' ),
					array( 'cause' => $response )
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( 304 === $status_code && is_array( $cached ) && isset( $cached['data'] ) ) {
			$cached['fetched_at'] = $now;
			$cached['expires_at'] = $now + $this->get_cache_ttl( $feed_filename );
			unset( $cached['error'], $cached['retry_after'] );
			$this->store_cache( $cache_key, $cached, $feed_filename );

			return $this->format_result( $cached, false, true );
		}

		if ( 404 === $status_code ) {
			$not_found = new WP_Error(
				'memml_organization_not_found',
				__( 'No Memml organization was found for that key.', 'memml' ),
				array( 'status' => 404 )
			);

			$this->remember_failure( $cache_key, $cached, $feed_filename, $not_found, false );

			return $not_found;
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return $this->stale_or_error(
				$cache_key,
				$cached,
				$feed_filename,
				new WP_Error(
					'memml_service_error',
					__( 'Memml returned an unexpected response. Please try again.', 'memml' ),
					array( 'status' => $status_code )
				)
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! $this->is_valid_envelope( $data ) ) {
			return $this->stale_or_error(
				$cache_key,
				$cached,
				$feed_filename,
				new WP_Error(
					'memml_invalid_feed',
					__( 'Memml returned a response that could not be read.', 'memml' )
				)
			);
		}

		$record = array(
			'data'       => $data,
			'etag'       => (string) wp_remote_retrieve_header( $response, 'etag' ),
			'fetched_at' => $now,
			'expires_at' => $now + $this->get_cache_ttl( $feed_filename ),
		);

		$this->store_cache( $cache_key, $record, $feed_filename );

		return $this->format_result( $record, false, false );
	}

	/**
	 * Builds a feed URL.
	 *
	 * @param string $organization_key Memml organization key.
	 * @param string $feed_filename    Feed filename.
	 * @return string
	 */
	private function build_feed_url( $organization_key, $feed_filename ) {
		return sprintf(
			'%1$s/api/public/v1/%2$s/%3$s',
			$this->base_url,
			rawurlencode( $organization_key ),
			$feed_filename
		);
	}

	/**
	 * Checks the minimum public-feed envelope contract.
	 *
	 * @param mixed $data Decoded response.
	 * @return bool
	 */
	private function is_valid_envelope( $data ) {
		return is_array( $data ) &&
			isset( $data['organization'] ) &&
			is_array( $data['organization'] ) &&
			! empty( $data['organization']['name'] ) &&
			! empty( $data['organization']['organizationKey'] ) &&
			! empty( $data['organization']['timezone'] );
	}

	/**
	 * Returns stale data when possible, otherwise the given error.
	 *
	 * Either way the failure is remembered so the next page view does not
	 * repeat the request while Memml is unavailable.
	 *
	 * @param string   $cache_key     Transient key.
	 * @param mixed    $cached        Cached record.
	 * @param string   $feed_filename Feed filename.
	 * @param WP_Error $error         Request error.
	 * @return array|WP_Error
	 */
	private function stale_or_error( $cache_key, $cached, $feed_filename, $error ) {
		$this->remember_failure( $cache_key, $cached, $feed_filename, $error, true );

		if ( is_array( $cached ) && isset( $cached['data'] ) ) {
			return $this->format_result( $cached, true, true, $error );
		}

		return $error;
	}

	/**
	 * Records a failed refresh without discarding the last known-good response.
	 *
	 * @param string   $cache_key      Transient key.
	 * @param mixed    $cached         Cached record.
	 * @param string   $feed_filename  Feed filename.
	 * @param WP_Error $error          Request error.
	 * @param bool     $allow_stale    Whether stale data may be served during backoff.
	 * @return void
	 */
	private function remember_failure( $cache_key, $cached, $feed_filename, $error, $allow_stale ) {
		$record = is_array( $cached ) ? $cached : array();

		$record['error'] = array(
			'code'        => $error->get_error_code(),
			'message'     => $error->get_error_message(),
			'data'        => $error->get_error_data(),
			'allow_stale' => (bool) $allow_stale,
		);

		$record['retry_after'] = time() + $this->get_failure_ttl( $feed_filename );

		if ( ! isset( $record['expires_at'] ) ) {
			$record['expires_at'] = 0;
		}

		$this->store_cache( $cache_key, $record, $feed_filename );
	}

	/**
	 * Replays a recent failure instead of re-requesting an unavailable feed.
	 *
	 * @param array $cached Cached record.
	 * @param int   $now    Current timestamp.
	 * @return array|WP_Error|null Result during backoff, or null to request again.
	 */
	private function get_backoff_result( $cached, $now ) {
		if ( ! isset( $cached['error'], $cached['retry_after'] ) || (int) $cached['retry_after'] <= $now ) {
			return null;
		}

		$error = new WP_Error(
			$cached['error']['code'],
			$cached['error']['message'],
			isset( $cached['error']['data'] ) ? $cached['error']['data'] : ''
		);

		if ( ! empty( $cached['error']['allow_stale'] ) && isset( $cached['data'] ) ) {
			return $this->format_result( $cached, true, true, $error );
		}

		return $error;
	}

	/**
	 * Stores a response long enough to support stale fallback.
	 *
	 * @param string $cache_key    Transient key.
	 * @param array  $record       Cache record.
	 * @param string $feed_filename Feed filename.
	 * @return void
	 */
	private function store_cache( $cache_key, $record, $feed_filename ) {
		$stale_ttl = (int) apply_filters(
			'memml_feed_stale_ttl',
			self::DEFAULT_STALE_TTL,
			$feed_filename
		);

		set_transient( $cache_key, $record, max( $this->get_cache_ttl( $feed_filename ), $stale_ttl ) );
	}

	/**
	 * Gets the filterable fresh-cache lifetime.
	 *
	 * @param string $feed_filename Feed filename.
	 * @return int
	 */
	private function get_cache_ttl( $feed_filename ) {
		return max(
			0,
			(int) apply_filters( 'memml_feed_cache_ttl', $this->cache_ttl, $feed_filename )
		);
	}

	/**
	 * Gets the filterable backoff applied after a failed refresh.
	 *
	 * @param string $feed_filename Feed filename.
	 * @return int
	 */
	private function get_failure_ttl( $feed_filename ) {
		return max(
			0,
			(int) apply_filters( 'memml_feed_failure_ttl', self::DEFAULT_FAILURE_TTL, $feed_filename )
		);
	}

	/**
	 * Normalizes a successful response.
	 *
	 * @param array         $record     Cache record.
	 * @param bool          $is_stale   Whether stale fallback is in use.
	 * @param bool          $from_cache Whether a cached representation was used.
	 * @param WP_Error|null $warning    Recoverable refresh error.
	 * @return array
	 */
	private function format_result( $record, $is_stale, $from_cache, $warning = null ) {
		return array(
			'data'       => $record['data'],
			'etag'       => isset( $record['etag'] ) ? $record['etag'] : '',
			'fetched_at' => isset( $record['fetched_at'] ) ? (int) $record['fetched_at'] : 0,
			'is_stale'   => (bool) $is_stale,
			'from_cache' => (bool) $from_cache,
			'warning'    => $warning,
		);
	}
}
