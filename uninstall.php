<?php
/**
 * Removes plugin data when Memml Calendar is deleted.
 *
 * @package Memml
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$memml_options = get_option( 'memml_settings' );

if ( is_array( $memml_options ) && ! empty( $memml_options['organization_key'] ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-feed-client.php';

	$memml_base_url = ! empty( $memml_options['base_url'] )
		? $memml_options['base_url']
		: Memml_Feed_Client::DEFAULT_BASE_URL;

	$memml_client = new Memml_Feed_Client( $memml_base_url );
	$memml_client->flush_cache( $memml_options['organization_key'] );
}

delete_option( 'memml_settings' );

// Sweeps feed transients left behind by earlier organization keys or base URLs.
global $wpdb;

$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup of plugin transients that cannot be enumerated through the options API.
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_memml_feed_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_memml_feed_' ) . '%'
	)
);
