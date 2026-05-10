<?php
/**
 * Plugin Name:       Link Manager
 * GitHub Plugin URI: https://github.com/zu/wp-link-manager
 * Primary Branch:    main
 * Plugin URI:        https://github.com/zu/wp-link-manager
 * Description:       Custom Post Type "Link" mit Kategorien, Bewertungen, Kommentaren, Vorschlägen und automatischen Screenshots.
 * Version:           1.0.4
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Christian Zumbrunnen
 * License:           GPL v2 or later
 * Text Domain:       link-manager
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// Plugin-Konstanten
define( 'LM_VERSION',     '1.0.4' );
define( 'LM_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'LM_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'LM_PLUGIN_FILE', __FILE__ );

/**
 * Autoloader für alle Klassen im includes/-Verzeichnis.
 */
spl_autoload_register( function ( string $class ): void {
    $prefix = 'LinkManager\\';
    if ( ! str_starts_with( $class, $prefix ) ) {
        return;
    }
    $relative = str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class, strlen( $prefix ) ) );
    $file     = LM_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . 'class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/**
 * Gibt die einzige Plugin-Instanz zurück (Singleton).
 */
function lm_plugin(): LinkManager\Plugin {
    return LinkManager\Plugin::instance();
}

// Plugin starten
add_action( 'plugins_loaded', 'lm_plugin' );

// Aktivierungs- / Deaktivierungs-Hooks
register_activation_hook(   __FILE__, [ 'LinkManager\Installer', 'activate'   ] );
register_deactivation_hook( __FILE__, [ 'LinkManager\Installer', 'deactivate' ] );
