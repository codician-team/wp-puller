<?php
/**
 * Backup class for WP Puller.
 *
 * @package WP_Puller
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP_Puller_Backup Class.
 */
class WP_Puller_Backup {

    /**
     * Backup directory name.
     *
     * @var string
     */
    const BACKUP_DIR = 'wp-puller-backups';

    /**
     * Get the backup directory path.
     *
     * @return string
     */
    public function get_backup_dir() {
        return WP_CONTENT_DIR . '/' . self::BACKUP_DIR;
    }

    /**
     * Ensure backup directory exists and is protected.
     *
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function ensure_backup_dir() {
        $backup_dir = $this->get_backup_dir();

        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! $wp_filesystem->is_dir( $backup_dir ) ) {
            if ( ! $wp_filesystem->mkdir( $backup_dir, 0755 ) ) {
                return new WP_Error(
                    'mkdir_failed',
                    __( 'Failed to create backup directory.', 'wp-puller' )
                );
            }
        }

        $htaccess = $backup_dir . '/.htaccess';
        if ( ! $wp_filesystem->exists( $htaccess ) ) {
            $wp_filesystem->put_contents( $htaccess, "Deny from all\n" );
        }

        $index = $backup_dir . '/index.php';
        if ( ! $wp_filesystem->exists( $index ) ) {
            $wp_filesystem->put_contents( $index, "<?php\n// Silence is golden.\n" );
        }

        return true;
    }

    /**
     * Create a backup of a WordPress theme by slug.
     *
     * @param string $theme_slug Theme directory name.
     * @param int    $max_backups Optional. Maximum backups to keep. 0 = use global setting.
     * @return string|false|WP_Error Backup path on success, false if theme doesn't exist, WP_Error on failure.
     */
    public function create_theme_backup( $theme_slug, $max_backups = 0 ) {
        $theme_dir = WP_CONTENT_DIR . '/themes/' . $theme_slug;

        if ( ! is_dir( $theme_dir ) ) {
            return false;
        }

        $result = $this->ensure_backup_dir();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $timestamp   = gmdate( 'Y-m-d_H-i-s' );
        $backup_name = $theme_slug . '_' . $timestamp;
        $backup_path = $this->get_backup_dir() . '/' . $backup_name;

        if ( ! $this->recursive_copy( $theme_dir, $backup_path ) ) {
            return new WP_Error(
                'backup_failed',
                __( 'Failed to create theme backup.', 'wp-puller' )
            );
        }

        $this->cleanup_old_backups( $theme_slug, $max_backups );

        return $backup_path;
    }

    /**
     * Create a backup of the current active theme (legacy wrapper).
     *
     * @return string|WP_Error Backup directory path on success, WP_Error on failure.
     */
    public function create_backup() {
        $theme      = wp_get_theme();
        $theme_slug = $theme->get_stylesheet();

        return $this->create_theme_backup( $theme_slug );
    }

    /**
     * Create a backup of a WordPress plugin.
     *
     * @param string $plugin_slug Plugin directory name.
     * @param int    $max_backups Optional. Maximum backups to keep. 0 = use global setting.
     * @return string|false|WP_Error Backup path on success, false if plugin doesn't exist, WP_Error on failure.
     */
    public function create_plugin_backup( $plugin_slug, $max_backups = 0 ) {
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;

        if ( ! is_dir( $plugin_dir ) ) {
            return false;
        }

        $result = $this->ensure_backup_dir();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $timestamp   = gmdate( 'Y-m-d_H-i-s' );
        $backup_name = 'plugin_' . $plugin_slug . '_' . $timestamp;
        $backup_path = $this->get_backup_dir() . '/' . $backup_name;

        if ( ! $this->recursive_copy( $plugin_dir, $backup_path ) ) {
            return new WP_Error(
                'backup_failed',
                __( 'Failed to create plugin backup.', 'wp-puller' )
            );
        }

        $this->cleanup_old_backups( 'plugin_' . $plugin_slug, $max_backups );

        return $backup_path;
    }

    /**
     * Restore a plugin from a backup.
     *
     * @param string $backup_name Backup directory name.
     * @param string $plugin_slug Plugin directory name.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function restore_plugin_backup( $backup_name, $plugin_slug ) {
        $backup_path = $this->get_backup_dir() . '/' . sanitize_file_name( $backup_name );

        if ( ! is_dir( $backup_path ) ) {
            return new WP_Error(
                'backup_not_found',
                __( 'Backup not found.', 'wp-puller' )
            );
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;

        if ( is_dir( $plugin_dir ) ) {
            if ( ! $this->recursive_delete( $plugin_dir ) ) {
                return new WP_Error(
                    'delete_failed',
                    __( 'Failed to remove current plugin files.', 'wp-puller' )
                );
            }
        }

        if ( ! $this->recursive_copy( $backup_path, $plugin_dir ) ) {
            return new WP_Error(
                'restore_failed',
                __( 'Failed to restore plugin from backup.', 'wp-puller' )
            );
        }

        return true;
    }

    /**
     * Restore a theme from a backup.
     *
     * @param string $backup_name Backup directory name.
     * @param string $theme_slug  Optional. Theme slug to restore to. Defaults to active theme.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function restore_backup( $backup_name, $theme_slug = '' ) {
        $backup_path = $this->get_backup_dir() . '/' . sanitize_file_name( $backup_name );

        if ( ! is_dir( $backup_path ) ) {
            return new WP_Error(
                'backup_not_found',
                __( 'Backup not found.', 'wp-puller' )
            );
        }

        if ( ! empty( $theme_slug ) ) {
            $theme_dir = WP_CONTENT_DIR . '/themes/' . $theme_slug;
        } else {
            $theme     = wp_get_theme();
            $theme_dir = $theme->get_stylesheet_directory();
        }

        if ( is_dir( $theme_dir ) ) {
            if ( ! $this->recursive_delete( $theme_dir ) ) {
                return new WP_Error(
                    'delete_failed',
                    __( 'Failed to remove current theme files.', 'wp-puller' )
                );
            }
        }

        if ( ! $this->recursive_copy( $backup_path, $theme_dir ) ) {
            return new WP_Error(
                'restore_failed',
                __( 'Failed to restore theme from backup.', 'wp-puller' )
            );
        }

        return true;
    }

    /**
     * Get list of available backups for a theme.
     *
     * @param string $theme_slug Optional. Theme slug to filter by.
     * @return array
     */
    public function get_backups( $theme_slug = '' ) {
        $backup_dir = $this->get_backup_dir();

        if ( ! is_dir( $backup_dir ) ) {
            return array();
        }

        $backups = array();
        $dirs    = glob( $backup_dir . '/*', GLOB_ONLYDIR );

        if ( ! $dirs ) {
            return array();
        }

        foreach ( $dirs as $dir ) {
            $name = basename( $dir );

            if ( ! empty( $theme_slug ) && strpos( $name, $theme_slug . '_' ) !== 0 ) {
                continue;
            }

            $timestamp = filemtime( $dir );

            $backups[] = array(
                'name'      => $name,
                'path'      => $dir,
                'timestamp' => $timestamp,
                'datetime'  => gmdate( 'Y-m-d H:i:s', $timestamp ),
                'size'      => $this->get_directory_size( $dir ),
            );
        }

        usort( $backups, function( $a, $b ) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $backups;
    }

    /**
     * Delete a backup.
     *
     * @param string $backup_name Backup directory name.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function delete_backup( $backup_name ) {
        $backup_path = $this->get_backup_dir() . '/' . sanitize_file_name( $backup_name );

        if ( ! is_dir( $backup_path ) ) {
            return new WP_Error(
                'backup_not_found',
                __( 'Backup not found.', 'wp-puller' )
            );
        }

        if ( ! $this->recursive_delete( $backup_path ) ) {
            return new WP_Error(
                'delete_failed',
                __( 'Failed to delete backup.', 'wp-puller' )
            );
        }

        return true;
    }

    /**
     * Cleanup old backups, keeping only the most recent ones.
     *
     * @param string $theme_slug  Theme or plugin slug prefix.
     * @param int    $max_backups Optional. Max to keep. 0 = use global setting.
     */
    private function cleanup_old_backups( $theme_slug, $max_backups = 0 ) {
        if ( $max_backups <= 0 ) {
            $max_backups = absint( get_option( 'wp_puller_backup_count', 3 ) );
        }
        $backups = $this->get_backups( $theme_slug );

        if ( count( $backups ) <= $max_backups ) {
            return;
        }

        $to_delete = array_slice( $backups, $max_backups );

        foreach ( $to_delete as $backup ) {
            $this->recursive_delete( $backup['path'] );
        }
    }

    /**
     * Recursively copy a directory.
     *
     * @param string $source      Source directory.
     * @param string $destination Destination directory.
     * @return bool
     */
    private function recursive_copy( $source, $destination ) {
        global $wp_filesystem;

        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! is_dir( $source ) ) {
            return false;
        }

        if ( ! $wp_filesystem->is_dir( $destination ) ) {
            $wp_filesystem->mkdir( $destination, 0755 );
        }

        $dir = opendir( $source );

        if ( ! $dir ) {
            return false;
        }

        while ( false !== ( $file = readdir( $dir ) ) ) {
            if ( '.' === $file || '..' === $file ) {
                continue;
            }

            $src_path  = $source . '/' . $file;
            $dest_path = $destination . '/' . $file;

            if ( is_dir( $src_path ) ) {
                if ( ! $this->recursive_copy( $src_path, $dest_path ) ) {
                    closedir( $dir );
                    return false;
                }
            } else {
                if ( ! $wp_filesystem->copy( $src_path, $dest_path ) ) {
                    closedir( $dir );
                    return false;
                }
            }
        }

        closedir( $dir );

        return true;
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $path Directory path.
     * @return bool
     */
    private function recursive_delete( $path ) {
        global $wp_filesystem;

        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! is_dir( $path ) ) {
            return $wp_filesystem->delete( $path );
        }

        $dir = opendir( $path );

        if ( ! $dir ) {
            return false;
        }

        while ( false !== ( $file = readdir( $dir ) ) ) {
            if ( '.' === $file || '..' === $file ) {
                continue;
            }

            $full_path = $path . '/' . $file;

            if ( is_dir( $full_path ) ) {
                $this->recursive_delete( $full_path );
            } else {
                $wp_filesystem->delete( $full_path );
            }
        }

        closedir( $dir );

        return $wp_filesystem->rmdir( $path );
    }

    /**
     * Get total size of a directory.
     *
     * @param string $path Directory path.
     * @return int Size in bytes.
     */
    private function get_directory_size( $path ) {
        $size = 0;

        if ( ! is_dir( $path ) ) {
            return $size;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Detect the version of a theme or plugin from a backup directory.
     *
     * @param string $backup_path Full path to the backup directory.
     * @param string $type        'theme' or 'plugin'.
     * @return string Version string or empty string if not found.
     */
    public function get_backup_version( $backup_path, $type = 'theme' ) {
        if ( ! is_dir( $backup_path ) ) {
            return '';
        }

        if ( 'theme' === $type ) {
            $style = $backup_path . '/style.css';
            if ( file_exists( $style ) ) {
                return $this->extract_header_version( $style, 'Version' );
            }
            return '';
        }

        // Plugin: scan top-level PHP files for the "Plugin Name:" header.
        $files = glob( $backup_path . '/*.php' );
        if ( ! $files ) {
            return '';
        }

        foreach ( $files as $file ) {
            $version = $this->extract_header_version( $file, 'Version' );
            if ( '' !== $version ) {
                // Verify it's actually a plugin file by also checking for Plugin Name.
                $content = file_get_contents( $file, false, null, 0, 8192 );
                if ( false !== stripos( $content, 'Plugin Name' ) ) {
                    return $version;
                }
            }
        }

        return '';
    }

    /**
     * Extract a specific header value from a file.
     *
     * @param string $file   File path.
     * @param string $header Header name (e.g. 'Version').
     * @return string Header value or empty string.
     */
    private function extract_header_version( $file, $header ) {
        $content = file_get_contents( $file, false, null, 0, 8192 );
        if ( false === $content ) {
            return '';
        }

        $pattern = '/^[\s*]*' . preg_quote( $header, '/' ) . '\s*:\s*(.+)$/mi';
        if ( preg_match( $pattern, $content, $matches ) ) {
            return trim( $matches[1] );
        }

        return '';
    }

    /**
     * Format bytes to human readable string.
     *
     * @param int $bytes    Size in bytes.
     * @param int $decimals Number of decimal places.
     * @return string
     */
    public static function format_size( $bytes, $decimals = 2 ) {
        if ( $bytes < 1024 ) {
            return $bytes . ' B';
        }

        $units = array( 'KB', 'MB', 'GB' );
        $bytes = (float) $bytes;

        for ( $i = 0; $bytes >= 1024 && $i < count( $units ) - 1; $i++ ) {
            $bytes /= 1024;
        }

        return round( $bytes, $decimals ) . ' ' . $units[ $i ];
    }
}
