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

$options = array(
    'wp_puller_repo_url',
    'wp_puller_branch',
    'wp_puller_theme_path',
    'wp_puller_pat',
    'wp_puller_webhook_secret',
    'wp_puller_last_check',
    'wp_puller_latest_commit',
    'wp_puller_auto_update',
    'wp_puller_update_log',
    'wp_puller_backup_count',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

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
