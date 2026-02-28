<?php
/**
 * Plugin Updater class for WP Puller.
 *
 * Handles deploying WordPress plugins from GitHub repositories.
 *
 * @package WP_Puller
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP_Puller_Plugin_Updater Class.
 */
class WP_Puller_Plugin_Updater {

    /**
     * GitHub API instance.
     *
     * @var WP_Puller_GitHub_API
     */
    private $github_api;

    /**
     * Backup instance.
     *
     * @var WP_Puller_Backup
     */
    private $backup;

    /**
     * Logger instance.
     *
     * @var WP_Puller_Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param WP_Puller_GitHub_API $github_api GitHub API instance.
     * @param WP_Puller_Backup     $backup     Backup instance.
     * @param WP_Puller_Logger     $logger     Logger instance.
     */
    public function __construct( $github_api, $backup, $logger ) {
        $this->github_api = $github_api;
        $this->backup     = $backup;
        $this->logger     = $logger;
    }

    /**
     * Update the plugin from GitHub.
     *
     * @param string $source Update source (webhook, manual).
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function update( $source = 'manual' ) {
        $repo_url = get_option( 'wp_puller_repo_url', '' );
        $branch   = get_option( 'wp_puller_branch', 'main' );

        if ( empty( $repo_url ) ) {
            $error = new WP_Error(
                'no_repo',
                __( 'No GitHub repository configured.', 'wp-puller' )
            );
            $this->logger->log_update_error( $error->get_error_message(), $source );
            return $error;
        }

        $parsed = $this->github_api->parse_repo_url( $repo_url );

        if ( ! $parsed ) {
            $error = new WP_Error(
                'invalid_repo',
                __( 'Invalid GitHub repository URL.', 'wp-puller' )
            );
            $this->logger->log_update_error( $error->get_error_message(), $source );
            return $error;
        }

        $latest_commit = $this->github_api->get_latest_commit( $parsed['owner'], $parsed['repo'], $branch );

        if ( is_wp_error( $latest_commit ) ) {
            $this->logger->log_update_error( $latest_commit->get_error_message(), $source );
            return $latest_commit;
        }

        $plugin_slug = get_option( 'wp_puller_plugin_slug', '' );

        if ( empty( $plugin_slug ) ) {
            $error = new WP_Error(
                'no_plugin_slug',
                __( 'No plugin slug configured. Set the target plugin directory name in settings.', 'wp-puller' )
            );
            $this->logger->log_update_error( $error->get_error_message(), $source );
            return $error;
        }

        $backup_path = $this->backup->create_plugin_backup( $plugin_slug );

        if ( is_wp_error( $backup_path ) ) {
            $this->logger->log_update_error( $backup_path->get_error_message(), $source );
            return $backup_path;
        }

        if ( $backup_path ) {
            $this->logger->log_backup_created( $backup_path );
        }

        $zip_file = $this->github_api->download_archive( $parsed['owner'], $parsed['repo'], $branch );

        if ( is_wp_error( $zip_file ) ) {
            $this->logger->log_update_error( $zip_file->get_error_message(), $source );
            return $zip_file;
        }

        $result = $this->install_plugin( $zip_file, $parsed['repo'], $branch, $plugin_slug );

        @unlink( $zip_file );

        if ( is_wp_error( $result ) ) {
            $this->logger->log_update_error( $result->get_error_message(), $source );
            return $result;
        }

        update_option( 'wp_puller_latest_commit', $latest_commit['sha'] );
        update_option( 'wp_puller_last_check', time() );

        $this->logger->log_update_success( $latest_commit['short_sha'], $source, array(
            'commit_sha'     => $latest_commit['sha'],
            'commit_message' => substr( $latest_commit['message'], 0, 100 ),
            'plugin_slug'    => $plugin_slug,
        ) );

        do_action( 'wp_puller_plugin_updated', $latest_commit, $source, $plugin_slug );

        return true;
    }

    /**
     * Deploy a specific branch for testing.
     *
     * @param string $branch Branch name to deploy.
     * @param string $source Update source identifier.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function deploy_branch( $branch, $source = 'branch-test' ) {
        $repo_url = get_option( 'wp_puller_repo_url', '' );

        if ( empty( $repo_url ) ) {
            $error = new WP_Error(
                'no_repo',
                __( 'No GitHub repository configured.', 'wp-puller' )
            );
            $this->logger->log_update_error( $error->get_error_message(), $source );
            return $error;
        }

        $parsed = $this->github_api->parse_repo_url( $repo_url );

        if ( ! $parsed ) {
            $error = new WP_Error(
                'invalid_repo',
                __( 'Invalid GitHub repository URL.', 'wp-puller' )
            );
            $this->logger->log_update_error( $error->get_error_message(), $source );
            return $error;
        }

        $plugin_slug = get_option( 'wp_puller_plugin_slug', '' );

        if ( empty( $plugin_slug ) ) {
            $error = new WP_Error(
                'no_plugin_slug',
                __( 'No plugin slug configured.', 'wp-puller' )
            );
            $this->logger->log_update_error( $error->get_error_message(), $source );
            return $error;
        }

        $latest_commit = $this->github_api->get_latest_commit( $parsed['owner'], $parsed['repo'], $branch );

        if ( is_wp_error( $latest_commit ) ) {
            $this->logger->log_update_error( $latest_commit->get_error_message(), $source );
            return $latest_commit;
        }

        $backup_path = $this->backup->create_plugin_backup( $plugin_slug );

        if ( is_wp_error( $backup_path ) ) {
            $this->logger->log_update_error( $backup_path->get_error_message(), $source );
            return $backup_path;
        }

        if ( $backup_path ) {
            $this->logger->log_backup_created( $backup_path );
        }

        $zip_file = $this->github_api->download_archive( $parsed['owner'], $parsed['repo'], $branch );

        if ( is_wp_error( $zip_file ) ) {
            $this->logger->log_update_error( $zip_file->get_error_message(), $source );
            return $zip_file;
        }

        $result = $this->install_plugin( $zip_file, $parsed['repo'], $branch, $plugin_slug );

        @unlink( $zip_file );

        if ( is_wp_error( $result ) ) {
            $this->logger->log_update_error( $result->get_error_message(), $source );
            return $result;
        }

        update_option( 'wp_puller_latest_commit', $latest_commit['sha'] );
        update_option( 'wp_puller_last_check', time() );
        update_option( 'wp_puller_deployed_branch', $branch );
        update_option( 'wp_puller_deployed_commit', $latest_commit['sha'] );

        $this->logger->log_update_success( $latest_commit['short_sha'], $source, array(
            'commit_sha'     => $latest_commit['sha'],
            'commit_message' => substr( $latest_commit['message'], 0, 100 ),
            'branch'         => $branch,
            'plugin_slug'    => $plugin_slug,
        ) );

        do_action( 'wp_puller_plugin_updated', $latest_commit, $source, $plugin_slug );

        return true;
    }

    /**
     * Check if an update is available.
     *
     * @return array|WP_Error Array with update info, or WP_Error on failure.
     */
    public function check_for_updates() {
        $repo_url = get_option( 'wp_puller_repo_url', '' );
        $branch   = get_option( 'wp_puller_branch', 'main' );

        if ( empty( $repo_url ) ) {
            return new WP_Error(
                'no_repo',
                __( 'No GitHub repository configured.', 'wp-puller' )
            );
        }

        $parsed = $this->github_api->parse_repo_url( $repo_url );

        if ( ! $parsed ) {
            return new WP_Error(
                'invalid_repo',
                __( 'Invalid GitHub repository URL.', 'wp-puller' )
            );
        }

        $this->github_api->clear_cache();

        $latest_commit = $this->github_api->get_latest_commit( $parsed['owner'], $parsed['repo'], $branch );

        if ( is_wp_error( $latest_commit ) ) {
            return $latest_commit;
        }

        $current_commit = get_option( 'wp_puller_latest_commit', '' );

        update_option( 'wp_puller_last_check', time() );

        return array(
            'update_available' => ! empty( $current_commit ) && $current_commit !== $latest_commit['sha'],
            'current_commit'   => $current_commit,
            'latest_commit'    => $latest_commit,
            'is_new_setup'     => empty( $current_commit ),
        );
    }

    /**
     * Install plugin from ZIP file.
     *
     * @param string $zip_file    ZIP file path.
     * @param string $repo        Repository name.
     * @param string $branch      Branch name.
     * @param string $plugin_slug Plugin directory name.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    private function install_plugin( $zip_file, $repo, $branch, $plugin_slug ) {
        global $wp_filesystem;

        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;

        $temp_dir = get_temp_dir() . 'wp-puller-plugin-' . uniqid();

        $result = unzip_file( $zip_file, $temp_dir );

        if ( is_wp_error( $result ) ) {
            $wp_filesystem->delete( $temp_dir, true );
            return new WP_Error(
                'unzip_failed',
                __( 'Failed to extract plugin archive.', 'wp-puller' )
            );
        }

        $extracted_dir = $temp_dir . '/' . $repo . '-' . $branch;

        if ( ! is_dir( $extracted_dir ) ) {
            $dirs = glob( $temp_dir . '/*', GLOB_ONLYDIR );

            if ( ! empty( $dirs ) ) {
                $extracted_dir = $dirs[0];
            } else {
                $wp_filesystem->delete( $temp_dir, true );
                return new WP_Error(
                    'invalid_archive',
                    __( 'Invalid plugin archive structure.', 'wp-puller' )
                );
            }
        }

        // Handle plugin in subdirectory
        $theme_path = get_option( 'wp_puller_theme_path', '' );
        if ( ! empty( $theme_path ) ) {
            $extracted_dir = $extracted_dir . '/' . $theme_path;

            if ( ! is_dir( $extracted_dir ) ) {
                $wp_filesystem->delete( $temp_dir, true );
                return new WP_Error(
                    'path_not_found',
                    sprintf(
                        /* translators: %s: plugin path */
                        __( 'Plugin path "%s" not found in repository.', 'wp-puller' ),
                        $theme_path
                    )
                );
            }
        }

        // Validate it contains a PHP file with Plugin Name header
        if ( ! $this->validate_plugin( $extracted_dir ) ) {
            $wp_filesystem->delete( $temp_dir, true );
            return new WP_Error(
                'not_a_plugin',
                __( 'The repository does not contain a valid WordPress plugin (no PHP file with Plugin Name header found).', 'wp-puller' )
            );
        }

        // Clear existing plugin directory
        if ( is_dir( $plugin_dir ) ) {
            $this->clear_directory( $plugin_dir );
        } else {
            $wp_filesystem->mkdir( $plugin_dir, 0755 );
        }

        $copy_result = copy_dir( $extracted_dir, $plugin_dir );

        $wp_filesystem->delete( $temp_dir, true );

        if ( is_wp_error( $copy_result ) ) {
            return new WP_Error(
                'copy_failed',
                __( 'Failed to copy plugin files.', 'wp-puller' )
            );
        }

        $this->clear_plugin_cache();

        return true;
    }

    /**
     * Validate that a directory contains a valid WordPress plugin.
     *
     * @param string $dir Directory path.
     * @return bool
     */
    private function validate_plugin( $dir ) {
        $php_files = glob( $dir . '/*.php' );

        if ( empty( $php_files ) ) {
            return false;
        }

        foreach ( $php_files as $php_file ) {
            $plugin_data = get_file_data( $php_file, array( 'Name' => 'Plugin Name' ) );
            if ( ! empty( $plugin_data['Name'] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear plugin directory contents.
     *
     * @param string $dir Directory path.
     */
    private function clear_directory( $dir ) {
        global $wp_filesystem;

        if ( ! is_dir( $dir ) ) {
            return;
        }

        $files = array_diff( scandir( $dir ), array( '.', '..' ) );

        foreach ( $files as $file ) {
            $path = $dir . '/' . $file;

            if ( is_dir( $path ) ) {
                $wp_filesystem->delete( $path, true );
            } else {
                $wp_filesystem->delete( $path );
            }
        }
    }

    /**
     * Clear plugin-related caches.
     */
    private function clear_plugin_cache() {
        wp_clean_plugins_cache();

        delete_transient( 'dirsize_cache' );

        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }

        do_action( 'wp_puller_cache_cleared' );
    }

    /**
     * Get info about the target plugin.
     *
     * @return array
     */
    public function get_current_plugin_info() {
        $plugin_slug = get_option( 'wp_puller_plugin_slug', '' );

        if ( empty( $plugin_slug ) ) {
            return array(
                'name'      => '',
                'version'   => '',
                'author'    => '',
                'slug'      => '',
                'active'    => false,
                'directory' => '',
            );
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;

        if ( ! is_dir( $plugin_dir ) ) {
            return array(
                'name'      => $plugin_slug,
                'version'   => '',
                'author'    => '',
                'slug'      => $plugin_slug,
                'active'    => false,
                'directory' => $plugin_dir,
            );
        }

        // Find the main plugin file
        $php_files = glob( $plugin_dir . '/*.php' );
        $plugin_name = '';
        $plugin_version = '';
        $plugin_author = '';
        $main_file = '';

        if ( ! empty( $php_files ) ) {
            foreach ( $php_files as $php_file ) {
                $plugin_data = get_file_data( $php_file, array(
                    'Name'    => 'Plugin Name',
                    'Version' => 'Version',
                    'Author'  => 'Author',
                ) );
                if ( ! empty( $plugin_data['Name'] ) ) {
                    $plugin_name    = $plugin_data['Name'];
                    $plugin_version = $plugin_data['Version'];
                    $plugin_author  = $plugin_data['Author'];
                    $main_file      = basename( $php_file );
                    break;
                }
            }
        }

        $plugin_file = $plugin_slug . '/' . $main_file;
        $is_active = is_plugin_active( $plugin_file );

        return array(
            'name'      => $plugin_name,
            'version'   => $plugin_version,
            'author'    => $plugin_author,
            'slug'      => $plugin_slug,
            'active'    => $is_active,
            'directory' => $plugin_dir,
        );
    }

    /**
     * Get update status.
     *
     * @return array
     */
    public function get_status() {
        $repo_url       = get_option( 'wp_puller_repo_url', '' );
        $branch         = get_option( 'wp_puller_branch', 'main' );
        $current_commit = get_option( 'wp_puller_latest_commit', '' );
        $last_check     = get_option( 'wp_puller_last_check', 0 );
        $auto_update    = get_option( 'wp_puller_auto_update', true );
        $plugin_slug    = get_option( 'wp_puller_plugin_slug', '' );
        $deployed_branch = get_option( 'wp_puller_deployed_branch', '' );

        $parsed = $this->github_api->parse_repo_url( $repo_url );

        return array(
            'is_configured'   => ! empty( $repo_url ) && false !== $parsed && ! empty( $plugin_slug ),
            'repo_url'        => $repo_url,
            'branch'          => $branch,
            'theme_path'      => get_option( 'wp_puller_theme_path', '' ),
            'plugin_slug'     => $plugin_slug,
            'current_commit'  => $current_commit,
            'short_commit'    => ! empty( $current_commit ) ? substr( $current_commit, 0, 7 ) : '',
            'last_check'      => $last_check,
            'auto_update'     => $auto_update,
            'repo_owner'      => $parsed ? $parsed['owner'] : '',
            'repo_name'       => $parsed ? $parsed['repo'] : '',
            'deployed_branch' => $deployed_branch,
            'asset_type'      => 'plugin',
        );
    }
}
