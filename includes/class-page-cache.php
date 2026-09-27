<?php
/**
 * Full-page cache compatibility.
 *
 * @package Memml
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps full-page caches from serving outdated Memml content.
 *
 * A page that renders a Memml feed asks the page cache to keep it no longer
 * than that feed stays fresh, and pages are purged when the feed cache is
 * cleared or the plugin settings change. LiteSpeed Cache is supported
 * directly; other caches can use the memml_page_cache_limit and
 * memml_page_cache_purge actions.
 */
final class Memml_Page_Cache {

	/**
	 * Cache tag applied to pages that render Memml content.
	 *
	 * @var string
	 */
	public const TAG = 'memml';

	/**
	 * Shortest page lifetime requested, in seconds.
	 *
	 * Also used while Memml is unavailable or stale data is shown, so a page
	 * recovers soon after the feed does.
	 *
	 * @var int
	 */
	public const MIN_TTL = 60;

	/**
	 * Lowest lifetime requested during this request.
	 *
	 * @var int|null
	 */
	private static $ttl = null;

	/**
	 * Registers purge hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_option_' . Memml_Settings::OPTION_NAME, array( __CLASS__, 'purge' ) );
		add_action( 'update_option_' . Memml_Settings::OPTION_NAME, array( __CLASS__, 'purge' ) );
	}

	/**
	 * Limits the current page's cache lifetime to a feed result's freshness.
	 *
	 * @param string         $feed   Feed identifier.
	 * @param array|WP_Error $result Feed client result.
	 * @return void
	 */
	public static function limit_for_feed( $feed, $result ) {
		/**
		 * Filters how long a page cache may keep a page that shows a Memml feed.
		 *
		 * @param int            $ttl    Lifetime in seconds. Return 0 to leave the page cache alone.
		 * @param string         $feed   Feed identifier: events, volunteers, or committees.
		 * @param array|WP_Error $result Feed client result.
		 */
		$ttl = (int) apply_filters( 'memml_page_cache_ttl', self::get_feed_ttl( $result ), $feed, $result );

		if ( $ttl <= 0 || ( null !== self::$ttl && $ttl >= self::$ttl ) ) {
			return;
		}

		self::$ttl = $ttl;

		// LiteSpeed Cache applies the last lifetime set, so never exceed the site's own public lifetime.
		$configured = apply_filters( 'litespeed_conf', 'cache-ttl_pub' );
		$litespeed  = is_numeric( $configured ) && (int) $configured > 0 ? min( $ttl, (int) $configured ) : $ttl;

		do_action( 'litespeed_tag_add', self::TAG );
		do_action( 'litespeed_control_set_ttl', $litespeed );

		/**
		 * Fires when a page showing Memml content should expire from page caches sooner.
		 *
		 * Fires again with a lower value if a later feed on the same page expires sooner.
		 *
		 * @param int $ttl Maximum page lifetime in seconds.
		 */
		do_action( 'memml_page_cache_limit', $ttl );
	}

	/**
	 * Purges cached pages that show Memml content.
	 *
	 * @return void
	 */
	public static function purge() {
		do_action( 'litespeed_purge', self::TAG );

		/**
		 * Fires when cached pages showing Memml content should be discarded.
		 */
		do_action( 'memml_page_cache_purge' );
	}

	/**
	 * Clears the per-request lifetime. Intended for tests.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$ttl = null;
	}

	/**
	 * Gets the lifetime a feed result supports.
	 *
	 * @param array|WP_Error $result Feed client result.
	 * @return int
	 */
	private static function get_feed_ttl( $result ) {
		if ( ! is_array( $result ) || ! empty( $result['is_stale'] ) || empty( $result['expires_at'] ) ) {
			return self::MIN_TTL;
		}

		return max( self::MIN_TTL, (int) $result['expires_at'] - time() );
	}
}
