<?php
/**
 * Plugin Name: WP Puller
 * Plugin URI: https://github.com/developer/wp-puller
 * Description: Automatically update your WordPress theme from GitHub. Supports public and private repositories with webhook-based real-time updates.
 * Version: 1.0.7
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Developer
 * Author URI: https://github.com/developer
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wp-puller
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WP_PULLER_VERSION', '1.0.7' );
define( 'WP_PULLER_PLUGIN_FILE', __FILE__ );
define( 'WP_PULLER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_PULLER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_PULLER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WP_PULLER_PLUGIN_DIR . 'includes/class-wp-puller.php';

/**
 * Returns the main instance of WP_Puller.
 *
 * @since 1.0.0
 * @return WP_Puller
 */
function wp_puller() {
    return WP_Puller::instance();
}

/**
 * Activation hook.
 *
 * @since 1.0.0
 */
function wp_puller_activate() {
    if ( ! get_option( 'wp_puller_webhook_secret' ) ) {
        update_option( 'wp_puller_webhook_secret', wp_generate_password( 32, false ) );
    }

    if ( false === get_option( 'wp_puller_branch' ) ) {
        update_option( 'wp_puller_branch', 'main' );
    }

    if ( false === get_option( 'wp_puller_auto_update' ) ) {
        update_option( 'wp_puller_auto_update', true );
    }

    if ( false === get_option( 'wp_puller_backup_count' ) ) {
        update_option( 'wp_puller_backup_count', 3 );
    }

    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wp_puller_activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 */
function wp_puller_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wp_puller_deactivate' );

wp_puller();
