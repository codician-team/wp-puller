<?php
/**
 * Logger class for WP Puller.
 *
 * @package WP_Puller
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP_Puller_Logger Class.
 */
class WP_Puller_Logger {

    /**
     * Option name for storing logs.
     *
     * @var string
     */
    const OPTION_NAME = 'wp_puller_update_log';

    /**
     * Maximum number of log entries to keep.
     *
     * @var int
     */
    const MAX_ENTRIES = 20;

    /**
     * Log status constants.
     */
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR   = 'error';
    const STATUS_INFO    = 'info';

    /**
     * Log source constants.
     */
    const SOURCE_WEBHOOK = 'webhook';
    const SOURCE_MANUAL  = 'manual';
    const SOURCE_SYSTEM  = 'system';

    /**
     * Add a log entry.
     *
     * @param string $message Log message.
     * @param string $status  Log status (success, error, info).
     * @param string $source  Log source (webhook, manual, system).
     * @param array  $meta    Additional metadata.
     * @return bool
     */
    public function log( $message, $status = self::STATUS_INFO, $source = self::SOURCE_SYSTEM, $meta = array() ) {
        $logs = $this->get_logs();

        $entry = array(
            'id'        => uniqid( 'log_', true ),
            'timestamp' => current_time( 'timestamp' ),
            'datetime'  => current_time( 'mysql' ),
            'message'   => sanitize_text_field( $message ),
            'status'    => in_array( $status, array( self::STATUS_SUCCESS, self::STATUS_ERROR, self::STATUS_INFO ), true )
                ? $status
                : self::STATUS_INFO,
            'source'    => in_array( $source, array( self::SOURCE_WEBHOOK, self::SOURCE_MANUAL, self::SOURCE_SYSTEM ), true )
                ? $source
                : self::SOURCE_SYSTEM,
            'meta'      => $this->sanitize_meta( $meta ),
        );

        array_unshift( $logs, $entry );

        if ( count( $logs ) > self::MAX_ENTRIES ) {
            $logs = array_slice( $logs, 0, self::MAX_ENTRIES );
        }

        return update_option( self::OPTION_NAME, $logs, false );
    }

    /**
     * Log a successful update.
     *
     * @param string $version New version/commit.
     * @param string $source  Update source.
     * @param array  $meta    Additional metadata.
     * @return bool
     */
    public function log_update_success( $version, $source = self::SOURCE_MANUAL, $meta = array() ) {
        $meta['version'] = $version;

        $label = ! empty( $meta['asset_label'] ) ? $meta['asset_label'] : ( ! empty( $meta['asset_slug'] ) ? $meta['asset_slug'] : '' );

        if ( ! empty( $label ) ) {
            $message = sprintf(
                /* translators: %1$s: asset name, %2$s: version/commit identifier */
                __( '%1$s updated successfully to %2$s', 'wp-puller' ),
                $label,
                $version
            );
        } else {
            $message = sprintf(
                /* translators: %s: version/commit identifier */
                __( 'Updated successfully to %s', 'wp-puller' ),
                $version
            );
        }

        return $this->log( $message, self::STATUS_SUCCESS, $source, $meta );
    }

    /**
     * Log a failed update.
     *
     * @param string $error  Error message.
     * @param string $source Update source.
     * @param array  $meta   Additional metadata.
     * @return bool
     */
    public function log_update_error( $error, $source = self::SOURCE_MANUAL, $meta = array() ) {
        $meta['error'] = $error;

        $label = ! empty( $meta['asset_label'] ) ? $meta['asset_label'] : ( ! empty( $meta['asset_slug'] ) ? $meta['asset_slug'] : '' );

        if ( ! empty( $label ) ) {
            $message = sprintf(
                /* translators: %1$s: asset name, %2$s: error message */
                __( '%1$s update failed: %2$s', 'wp-puller' ),
                $label,
                $error
            );
        } else {
            $message = sprintf(
                /* translators: %s: error message */
                __( 'Update failed: %s', 'wp-puller' ),
                $error
            );
        }

        return $this->log( $message, self::STATUS_ERROR, $source, $meta );
    }

    /**
     * Log a backup event.
     *
     * @param string $backup_path Path to backup.
     * @return bool
     */
    public function log_backup_created( $backup_path, $meta = array() ) {
        $meta['backup_path'] = $backup_path;
        $label = ! empty( $meta['asset_label'] ) ? $meta['asset_label'] : ( ! empty( $meta['asset_slug'] ) ? $meta['asset_slug'] : '' );

        if ( ! empty( $label ) ) {
            $message = sprintf(
                /* translators: %s: asset name */
                __( 'Backup created for %s', 'wp-puller' ),
                $label
            );
        } else {
            $message = __( 'Backup created', 'wp-puller' );
        }

        return $this->log( $message, self::STATUS_INFO, self::SOURCE_SYSTEM, $meta );
    }

    /**
     * Log a restore event.
     *
     * @param string $backup_name Name of restored backup.
     * @return bool
     */
    public function log_restore_success( $backup_name, $meta = array() ) {
        $meta['backup_name'] = $backup_name;
        $label = ! empty( $meta['asset_label'] ) ? $meta['asset_label'] : ( ! empty( $meta['asset_slug'] ) ? $meta['asset_slug'] : '' );

        if ( ! empty( $label ) ) {
            $message = sprintf(
                /* translators: %1$s: asset name, %2$s: backup name */
                __( '%1$s restored from backup: %2$s', 'wp-puller' ),
                $label,
                $backup_name
            );
        } else {
            $message = sprintf(
                /* translators: %s: backup name */
                __( 'Restored from backup: %s', 'wp-puller' ),
                $backup_name
            );
        }

        return $this->log( $message, self::STATUS_SUCCESS, self::SOURCE_MANUAL, $meta );
    }

    /**
     * Get all log entries.
     *
     * @return array
     */
    public function get_logs() {
        $logs = get_option( self::OPTION_NAME, array() );
        return is_array( $logs ) ? $logs : array();
    }

    /**
     * Get recent log entries.
     *
     * @param int $count Number of entries to retrieve.
     * @return array
     */
    public function get_recent_logs( $count = 10 ) {
        $logs = $this->get_logs();
        return array_slice( $logs, 0, absint( $count ) );
    }

    /**
     * Clear all logs.
     *
     * @return bool
     */
    public function clear_logs() {
        return delete_option( self::OPTION_NAME );
    }

    /**
     * Sanitize metadata array.
     *
     * @param array $meta Metadata to sanitize.
     * @return array
     */
    private function sanitize_meta( $meta ) {
        if ( ! is_array( $meta ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $meta as $key => $value ) {
            $key = sanitize_key( $key );

            if ( is_array( $value ) ) {
                $sanitized[ $key ] = $this->sanitize_meta( $value );
            } elseif ( is_string( $value ) ) {
                $sanitized[ $key ] = sanitize_text_field( $value );
            } elseif ( is_numeric( $value ) ) {
                $sanitized[ $key ] = $value;
            } elseif ( is_bool( $value ) ) {
                $sanitized[ $key ] = $value;
            }
        }

        return $sanitized;
    }
}
