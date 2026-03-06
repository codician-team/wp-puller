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

// Resolve backup directory path before options are deleted.
$backup_dir_suffix = get_option( 'wp_puller_backup_dir_suffix', '' );
$backup_dir        = '';
if ( ! empty( $backup_dir_suffix ) ) {
    $backup_dir = WP_CONTENT_DIR . '/wp-puller-backups-' . $backup_dir_suffix;
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
    'wp_puller_encryption_key',
    'wp_puller_backup_dir_suffix',
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

// Remove rate-limit transients (wp_puller_rl_*).
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_wp_puller_rl_%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_timeout_wp_puller_rl_%'
    )
);

// Remove update lock transient if it happens to be set during uninstall.
delete_transient( 'wp_puller_update_lock' );

// Remove backup directory from the filesystem.
if ( ! empty( $backup_dir ) && is_dir( $backup_dir ) ) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $backup_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ( $iterator as $file ) {
        if ( $file->isDir() ) {
            rmdir( $file->getRealPath() );
        } else {
            unlink( $file->getRealPath() );
        }
    }
    rmdir( $backup_dir );
}
