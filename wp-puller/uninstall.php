<?php
/**
 * Uninstall WP Puller
 *
 * Removes all plugin data when uninstalled.
 *
 * @package WP_Puller
 * @since 1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// v2.0.0 options.
$options = array(
    'wp_puller_assets',
    'wp_puller_tokens',
    'wp_puller_webhook_secret',
    'wp_puller_update_log',
    'wp_puller_db_version',
);

// Legacy options (in case migration never ran).
$legacy_options = array(
    'wp_puller_repo_url',
    'wp_puller_branch',
    'wp_puller_theme_path',
    'wp_puller_pat',
    'wp_puller_last_check',
    'wp_puller_latest_commit',
    'wp_puller_auto_update',
    'wp_puller_backup_count',
    'wp_puller_asset_type',
    'wp_puller_plugin_slug',
    'wp_puller_plugin_repo_url',
    'wp_puller_plugin_branch',
    'wp_puller_plugin_path',
    'wp_puller_plugin_auto_update',
    'wp_puller_plugin_latest_commit',
    'wp_puller_plugin_last_check',
    'wp_puller_plugin_deployed_branch',
    'wp_puller_plugin_deployed_commit',
    'wp_puller_deployed_branch',
    'wp_puller_deployed_commit',
);

foreach ( array_merge( $options, $legacy_options ) as $option ) {
    delete_option( $option );
}

// Clean up transients.
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_wp_puller_cache_%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_timeout_wp_puller_cache_%'
    )
);

// Clean up user meta.
delete_metadata( 'user', 0, 'wp_puller_active_tab', '', true );
