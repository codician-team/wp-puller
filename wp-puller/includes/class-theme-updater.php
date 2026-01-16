<?php
/**
 * Theme Updater class for WP Puller.
 *
 * @package WP_Puller
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP_Puller_Theme_Updater Class.
 */
class WP_Puller_Theme_Updater {

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
     * Update the theme from GitHub.
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

        $backup_path = $this->backup->create_backup();

        if ( is_wp_error( $backup_path ) ) {
            $this->logger->log_update_error( $backup_path->get_error_message(), $source );
            return $backup_path;
        }

        $this->logger->log_backup_created( $backup_path );

        $zip_file = $this->github_api->download_archive( $parsed['owner'], $parsed['repo'], $branch );

        if ( is_wp_error( $zip_file ) ) {
            $this->logger->log_update_error( $zip_file->get_error_message(), $source );
            return $zip_file;
        }

        $result = $this->install_theme( $zip_file, $parsed['repo'], $branch );

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
        ) );

        do_action( 'wp_puller_theme_updated', $latest_commit, $source );

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
     * Install theme from ZIP file.
     *
     * @param string $zip_file ZIP file path.
     * @param string $repo     Repository name.
     * @param string $branch   Branch name.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    private function install_theme( $zip_file, $repo, $branch ) {
        global $wp_filesystem;

        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $theme     = wp_get_theme();
        $theme_dir = $theme->get_stylesheet_directory();

        $temp_dir = get_temp_dir() . 'wp-puller-' . uniqid();

        $result = unzip_file( $zip_file, $temp_dir );

        if ( is_wp_error( $result ) ) {
            $wp_filesystem->delete( $temp_dir, true );
            return new WP_Error(
                'unzip_failed',
                __( 'Failed to extract theme archive.', 'wp-puller' )
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
                    __( 'Invalid theme archive structure.', 'wp-puller' )
                );
            }
        }

        // Handle theme in subdirectory
        $theme_path = get_option( 'wp_puller_theme_path', '' );
        if ( ! empty( $theme_path ) ) {
            $extracted_dir = $extracted_dir . '/' . $theme_path;

            if ( ! is_dir( $extracted_dir ) ) {
                $wp_filesystem->delete( $temp_dir, true );
                return new WP_Error(
                    'path_not_found',
                    sprintf(
                        /* translators: %s: theme path */
                        __( 'Theme path "%s" not found in repository.', 'wp-puller' ),
                        $theme_path
                    )
                );
            }
        }

        $style_css = $extracted_dir . '/style.css';

        if ( ! file_exists( $style_css ) ) {
            $wp_filesystem->delete( $temp_dir, true );

            // Provide helpful error message
            $hint = '';
            if ( empty( $theme_path ) ) {
                // Check if there's a subdirectory with style.css
                $subdirs = glob( $extracted_dir . '/*', GLOB_ONLYDIR );
                foreach ( $subdirs as $subdir ) {
                    if ( file_exists( $subdir . '/style.css' ) ) {
                        $hint = sprintf(
                            /* translators: %s: directory name */
                            __( ' Found theme in "%s" - set this as Theme Path in settings.', 'wp-puller' ),
                            basename( $subdir )
                        );
                        break;
                    }
                }
            }

            return new WP_Error(
                'not_a_theme',
                __( 'The repository does not contain a valid WordPress theme (missing style.css).', 'wp-puller' ) . $hint
            );
        }

        $theme_data = get_file_data( $style_css, array( 'Name' => 'Theme Name' ) );

        if ( empty( $theme_data['Name'] ) ) {
            $wp_filesystem->delete( $temp_dir, true );
            return new WP_Error(
                'invalid_theme',
                __( 'The style.css file does not contain a valid Theme Name header.', 'wp-puller' )
            );
        }

        $this->clear_theme_directory( $theme_dir );

        $copy_result = copy_dir( $extracted_dir, $theme_dir );

        $wp_filesystem->delete( $temp_dir, true );

        if ( is_wp_error( $copy_result ) ) {
            return new WP_Error(
                'copy_failed',
                __( 'Failed to copy theme files.', 'wp-puller' )
            );
        }

        $this->clear_theme_cache();

        return true;
    }

    /**
     * Clear theme directory contents.
     *
     * @param string $dir Directory path.
     */
    private function clear_theme_directory( $dir ) {
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
     * Clear theme-related caches.
     */
    private function clear_theme_cache() {
        wp_clean_themes_cache();

        delete_transient( 'dirsize_cache' );

        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }

        do_action( 'wp_puller_cache_cleared' );
    }

    /**
     * Get the current theme info.
     *
     * @return array
     */
    public function get_current_theme_info() {
        $theme = wp_get_theme();

        return array(
            'name'       => $theme->get( 'Name' ),
            'version'    => $theme->get( 'Version' ),
            'author'     => $theme->get( 'Author' ),
            'stylesheet' => $theme->get_stylesheet(),
            'directory'  => $theme->get_stylesheet_directory(),
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
        $theme_path     = get_option( 'wp_puller_theme_path', '' );
        $current_commit = get_option( 'wp_puller_latest_commit', '' );
        $last_check     = get_option( 'wp_puller_last_check', 0 );
        $auto_update    = get_option( 'wp_puller_auto_update', true );

        $parsed = $this->github_api->parse_repo_url( $repo_url );

        return array(
            'is_configured'  => ! empty( $repo_url ) && false !== $parsed,
            'repo_url'       => $repo_url,
            'branch'         => $branch,
            'theme_path'     => $theme_path,
            'current_commit' => $current_commit,
            'short_commit'   => ! empty( $current_commit ) ? substr( $current_commit, 0, 7 ) : '',
            'last_check'     => $last_check,
            'auto_update'    => $auto_update,
            'repo_owner'     => $parsed ? $parsed['owner'] : '',
            'repo_name'      => $parsed ? $parsed['repo'] : '',
        );
    }
}
