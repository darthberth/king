<?php
/**
 * Plugin Name:       Instagram Stories Auto Publisher
 * Plugin URI:        https://github.com/darthberth/king
 * Description:       Pubblica automaticamente una Storia su Instagram quando pubblichi (o aggiorni) un post WordPress, usando l'immagine in evidenza e sovrimprimendo titolo, call-to-action e link all'articolo.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            darthberth
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       instagram-stories-auto
 * Domain Path:       /languages
 *
 * @package InstagramStoriesAuto
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Accesso diretto non consentito.
}

define( 'ISA_VERSION', '1.0.0' );
define( 'ISA_PLUGIN_FILE', __FILE__ );
define( 'ISA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ISA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Versione delle Graph API di Meta usata dal client.
if ( ! defined( 'ISA_GRAPH_API_VERSION' ) ) {
	define( 'ISA_GRAPH_API_VERSION', 'v21.0' );
}

require_once ISA_PLUGIN_DIR . 'includes/class-isa-instagram-api.php';
require_once ISA_PLUGIN_DIR . 'includes/class-isa-image-generator.php';
require_once ISA_PLUGIN_DIR . 'includes/class-isa-publisher.php';
require_once ISA_PLUGIN_DIR . 'includes/class-isa-settings.php';
require_once ISA_PLUGIN_DIR . 'includes/class-isa-meta-box.php';
require_once ISA_PLUGIN_DIR . 'includes/class-isa-plugin.php';

/**
 * Avvia il plugin.
 */
function isa_bootstrap() {
	$plugin = new ISA_Plugin();
	$plugin->init();
}
add_action( 'plugins_loaded', 'isa_bootstrap' );

/**
 * Attivazione: prepara la cartella di lavoro e i valori di default.
 */
function isa_activate() {
	// Assicura che esista la cartella per le immagini generate.
	$upload = wp_upload_dir();
	$dir    = trailingslashit( $upload['basedir'] ) . 'isa-stories';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	// Blocca il listing della cartella.
	$index = trailingslashit( $dir ) . 'index.html';
	if ( ! file_exists( $index ) ) {
		@file_put_contents( $index, '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	// Valori di default delle impostazioni, senza sovrascrivere quelli esistenti.
	$defaults = ISA_Settings::get_defaults();
	$current  = get_option( ISA_Settings::OPTION_KEY, array() );
	if ( ! is_array( $current ) ) {
		$current = array();
	}
	update_option( ISA_Settings::OPTION_KEY, array_merge( $defaults, $current ) );
}
register_activation_hook( __FILE__, 'isa_activate' );

/**
 * Disattivazione: rimuove eventuali eventi cron ancora pianificati.
 */
function isa_deactivate() {
	$timestamp = wp_next_scheduled( ISA_Plugin::CRON_HOOK );
	while ( $timestamp ) {
		wp_unschedule_event( $timestamp, ISA_Plugin::CRON_HOOK );
		$timestamp = wp_next_scheduled( ISA_Plugin::CRON_HOOK );
	}
}
register_deactivation_hook( __FILE__, 'isa_deactivate' );
