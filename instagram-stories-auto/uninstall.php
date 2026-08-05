<?php
/**
 * Disinstallazione: rimuove opzioni, meta e file generati.
 *
 * @package InstagramStoriesAuto
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Rimuove le opzioni del plugin.
delete_option( 'isa_settings' );
delete_transient( 'isa_test_notice' );

// Rimuove le meta associate ai post.
$meta_keys = array(
	'_isa_enabled',
	'_isa_republish_on_update',
	'_isa_pending_intent',
	'_isa_last_status',
	'_isa_last_message',
	'_isa_last_time',
	'_isa_last_media_id',
	'_isa_last_hash',
);
foreach ( $meta_keys as $key ) {
	delete_post_meta_by_key( $key );
}

// Rimuove i file generati.
$upload = wp_upload_dir();
if ( empty( $upload['error'] ) ) {
	$dir = trailingslashit( $upload['basedir'] ) . 'isa-stories';
	if ( is_dir( $dir ) ) {
		$files = glob( trailingslashit( $dir ) . '*' );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
			}
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}
