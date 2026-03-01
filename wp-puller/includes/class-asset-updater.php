<?php
/**
 * Unified Asset Updater class for WP Puller.
 *
 * Handles deploying both WordPress themes and plugins from GitHub repositories.
 * Replaces the separate WP_Puller_Theme_Updater and WP_Puller_Plugin_Updater classes.
 *
 * @package WP_Puller
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP_Puller_Asset_Updater Class.
 */
class WP_Puller_Asset_Updater {

    /**
     * Asset configuration array.
     *
     * @var array
     */
    private $config;

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
     * @param array                $config     Asset configuration from wp_puller_assets.
     * @param WP_Puller_GitHub_API $github_api GitHub API instance (with per-asset PAT).
     * @param WP_Puller_Backup     $backup     Backup instance.
     * @param WP_Puller_Logger     $logger     Logger instance.
     */
    public function __construct( $config, $github_api, $backup, $logger ) {
        $this->config     = $config;
        $this->github_api = $github_api;
        $this->backup     = $backup;
        $this->logger     = $logger;
    }

    /**
     * Update the asset from GitHub.
     *
     * @param string $source Update source (webhook, manual).
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function update( $source = 'manual' ) {
        $repo_url = $this->config['repo_url'];
        $branch   = $this->config['branch'];
        $slug     = $this->config['slug'];
        $type     = $this->config['type'];

        if ( empty( $repo_url ) ) {
            $error = new WP_Error(
                'no_repo',
                __( 'No GitHub repository configured.', 'wp-puller' )
            );
            $this->logger->log_update_error( $error->get_error_message(), $source );
            return $error;
        }

        if ( empty( $slug ) ) {
            $error = new WP_Error(
                'no_slug',
                sprintf(
                    /* translators: %s: asset type */
                    __( 'No %s slug configured.', 'wp-puller' ),
                    $type
                )
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

        $backup_path = $this->create_backup();

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

        $result = $this->install_archive( $zip_file, $parsed['repo'], $branch );

        @unlink( $zip_file );

        if ( is_wp_error( $result ) ) {
            $this->logger->log_update_error( $result->get_error_message(), $source );
            return $result;
        }

        $this->save_state( array(
            'latest_commit' => $latest_commit['sha'],
            'last_check'    => time(),
        ) );

        $this->logger->log_update_success( $latest_commit['short_sha'], $source, array(
            'commit_sha'     => $latest_commit['sha'],
            'commit_message' => substr( $latest_commit['message'], 0, 100 ),
            'asset_type'     => $type,
            'asset_slug'     => $slug,
        ) );

        $action = ( 'theme' === $type ) ? 'wp_puller_theme_updated' : 'wp_puller_plugin_updated';
        do_action( $action, $latest_commit, $source, $slug );

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
        $repo_url = $this->config['repo_url'];
        $slug     = $this->config['slug'];
        $type     = $this->config['type'];

        if ( empty( $repo_url ) ) {
            $error = new WP_Error(
                'no_repo',
                __( 'No GitHub repository configured.', 'wp-puller' )
            );
            $this->logger->log_update_error( $error->get_error_message(), $source );
            return $error;
        }

        if ( empty( $slug ) ) {
            $error = new WP_Error(
                'no_slug',
                __( 'No slug configured.', 'wp-puller' )
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

        $backup_path = $this->create_backup();

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

        $result = $this->install_archive( $zip_file, $parsed['repo'], $branch );

        @unlink( $zip_file );

        if ( is_wp_error( $result ) ) {
            $this->logger->log_update_error( $result->get_error_message(), $source );
            return $result;
        }

        $this->save_state( array(
            'latest_commit'    => $latest_commit['sha'],
            'last_check'       => time(),
            'deployed_branch'  => $branch,
            'deployed_commit'  => $latest_commit['sha'],
        ) );

        $this->logger->log_update_success( $latest_commit['short_sha'], $source, array(
            'commit_sha'     => $latest_commit['sha'],
            'commit_message' => substr( $latest_commit['message'], 0, 100 ),
            'branch'         => $branch,
            'asset_type'     => $type,
            'asset_slug'     => $slug,
        ) );

        $action = ( 'theme' === $type ) ? 'wp_puller_theme_updated' : 'wp_puller_plugin_updated';
        do_action( $action, $latest_commit, $source, $slug );

        return true;
    }

    /**
     * Check if an update is available.
     *
     * @return array|WP_Error Array with update info, or WP_Error on failure.
     */
    public function check_for_updates() {
        $repo_url = $this->config['repo_url'];
        $branch   = $this->config['branch'];
        $slug     = $this->config['slug'];

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

        $current_commit = $this->config['latest_commit'];

        $this->save_state( array( 'last_check' => time() ) );

        $latest_version = $this->get_remote_version( $parsed, $branch );

        $current_info    = $this->get_current_info();
        $current_version = $current_info['version'];

        return array(
            'update_available' => ! empty( $current_commit ) && $current_commit !== $latest_commit['sha'],
            'current_commit'   => $current_commit,
            'latest_commit'    => $latest_commit,
            'is_new_setup'     => empty( $current_commit ),
            'current_version'  => $current_version ?: '',
            'latest_version'   => $latest_version,
        );
    }

    /**
     * Get info about the currently installed asset.
     *
     * @return array
     */
    public function get_current_info() {
        $slug = $this->config['slug'];
        $type = $this->config['type'];

        if ( empty( $slug ) ) {
            return array(
                'name'      => '',
                'version'   => '',
                'author'    => '',
                'slug'      => '',
                'active'    => false,
                'directory' => '',
            );
        }

        if ( 'theme' === $type ) {
            return $this->get_theme_info( $slug );
        }

        return $this->get_plugin_info( $slug );
    }

    /**
     * Get update status.
     *
     * @return array
     */
    public function get_status() {
        $repo_url = $this->config['repo_url'];
        $slug     = $this->config['slug'];
        $type     = $this->config['type'];

        $parsed = $this->github_api->parse_repo_url( $repo_url );

        return array(
            'id'              => $this->config['id'],
            'type'            => $type,
            'is_configured'   => ! empty( $repo_url ) && false !== $parsed && ! empty( $slug ),
            'repo_url'        => $repo_url,
            'branch'          => $this->config['branch'],
            'path'            => $this->config['path'],
            'slug'            => $slug,
            'current_commit'  => $this->config['latest_commit'],
            'short_commit'    => ! empty( $this->config['latest_commit'] ) ? substr( $this->config['latest_commit'], 0, 7 ) : '',
            'last_check'      => $this->config['last_check'],
            'auto_update'     => $this->config['auto_update'],
            'backup_count'    => $this->config['backup_count'],
            'repo_owner'      => $parsed ? $parsed['owner'] : '',
            'repo_name'       => $parsed ? $parsed['repo'] : '',
            'deployed_branch' => $this->config['deployed_branch'],
            'asset_type'      => $type,
        );
    }

    /**
     * Install asset from ZIP file.
     *
     * @param string $zip_file ZIP file path.
     * @param string $repo     Repository name.
     * @param string $branch   Branch name.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    private function install_archive( $zip_file, $repo, $branch ) {
        global $wp_filesystem;

        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $target_dir = $this->get_target_directory();
        $type       = $this->config['type'];

        $temp_dir = get_temp_dir() . 'wp-puller-' . $type . '-' . uniqid();

        $result = unzip_file( $zip_file, $temp_dir );

        if ( is_wp_error( $result ) ) {
            $wp_filesystem->delete( $temp_dir, true );
            return new WP_Error(
                'unzip_failed',
                sprintf(
                    /* translators: %s: asset type */
                    __( 'Failed to extract %s archive.', 'wp-puller' ),
                    $type
                )
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
                    sprintf(
                        /* translators: %s: asset type */
                        __( 'Invalid %s archive structure.', 'wp-puller' ),
                        $type
                    )
                );
            }
        }

        // Handle asset in subdirectory.
        $path = $this->config['path'];
        if ( ! empty( $path ) ) {
            $extracted_dir = $extracted_dir . '/' . $path;

            if ( ! is_dir( $extracted_dir ) ) {
                $wp_filesystem->delete( $temp_dir, true );
                return new WP_Error(
                    'path_not_found',
                    sprintf(
                        /* translators: %s: path */
                        __( 'Path "%s" not found in repository.', 'wp-puller' ),
                        $path
                    )
                );
            }
        }

        // Validate the archive contents.
        $validation = $this->validate_archive( $extracted_dir );
        if ( is_wp_error( $validation ) ) {
            $wp_filesystem->delete( $temp_dir, true );
            return $validation;
        }

        // Clear or create target directory.
        if ( is_dir( $target_dir ) ) {
            $this->clear_directory( $target_dir );
        } else {
            $wp_filesystem->mkdir( $target_dir, 0755 );
        }

        $copy_result = copy_dir( $extracted_dir, $target_dir );

        $wp_filesystem->delete( $temp_dir, true );

        if ( is_wp_error( $copy_result ) ) {
            return new WP_Error(
                'copy_failed',
                sprintf(
                    /* translators: %s: asset type */
                    __( 'Failed to copy %s files.', 'wp-puller' ),
                    $type
                )
            );
        }

        $this->clear_asset_cache();

        return true;
    }

    /**
     * Get the target directory for this asset.
     *
     * @return string
     */
    private function get_target_directory() {
        $slug = $this->config['slug'];

        if ( 'theme' === $this->config['type'] ) {
            return WP_CONTENT_DIR . '/themes/' . $slug;
        }

        return WP_PLUGIN_DIR . '/' . $slug;
    }

    /**
     * Validate the extracted archive.
     *
     * @param string $dir Extracted directory path.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    private function validate_archive( $dir ) {
        if ( 'theme' === $this->config['type'] ) {
            return $this->validate_theme( $dir );
        }

        return $this->validate_plugin( $dir );
    }

    /**
     * Validate a theme directory.
     *
     * @param string $dir Directory path.
     * @return bool|WP_Error
     */
    private function validate_theme( $dir ) {
        $style_css = $dir . '/style.css';

        if ( ! file_exists( $style_css ) ) {
            $hint = '';
            if ( empty( $this->config['path'] ) ) {
                $subdirs = glob( $dir . '/*', GLOB_ONLYDIR );
                foreach ( $subdirs as $subdir ) {
                    if ( file_exists( $subdir . '/style.css' ) ) {
                        $hint = sprintf(
                            /* translators: %s: directory name */
                            __( ' Found theme in "%s" - set this as Path in settings.', 'wp-puller' ),
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
            return new WP_Error(
                'invalid_theme',
                __( 'The style.css file does not contain a valid Theme Name header.', 'wp-puller' )
            );
        }

        return true;
    }

    /**
     * Validate a plugin directory.
     *
     * @param string $dir Directory path.
     * @return bool|WP_Error
     */
    private function validate_plugin( $dir ) {
        $php_files = glob( $dir . '/*.php' );

        if ( empty( $php_files ) ) {
            return new WP_Error(
                'not_a_plugin',
                __( 'The repository does not contain a valid WordPress plugin (no PHP files found).', 'wp-puller' )
            );
        }

        foreach ( $php_files as $php_file ) {
            $plugin_data = get_file_data( $php_file, array( 'Name' => 'Plugin Name' ) );
            if ( ! empty( $plugin_data['Name'] ) ) {
                return true;
            }
        }

        return new WP_Error(
            'not_a_plugin',
            __( 'The repository does not contain a valid WordPress plugin (no PHP file with Plugin Name header found).', 'wp-puller' )
        );
    }

    /**
     * Create a backup of the current asset.
     *
     * @return string|false|WP_Error Backup path on success, false if not installed, WP_Error on failure.
     */
    private function create_backup() {
        $slug = $this->config['slug'];
        $type = $this->config['type'];

        if ( 'theme' === $type ) {
            return $this->backup->create_theme_backup( $slug );
        }

        return $this->backup->create_plugin_backup( $slug );
    }

    /**
     * Get the version from the remote repository.
     *
     * @param array  $parsed Parsed repo info (owner, repo).
     * @param string $branch Branch name.
     * @return string Version string or empty.
     */
    private function get_remote_version( $parsed, $branch ) {
        $slug = $this->config['slug'];
        $path = $this->config['path'];
        $type = $this->config['type'];

        $file_prefix = ! empty( $path ) ? $path . '/' : '';

        if ( 'theme' === $type ) {
            $file_path     = $file_prefix . 'style.css';
            $file_content  = $this->github_api->get_raw_file( $parsed['owner'], $parsed['repo'], $branch, $file_path );

            if ( ! is_wp_error( $file_content ) && preg_match( '/^\s*Version:\s*(.+)$/mi', $file_content, $matches ) ) {
                return trim( $matches[1] );
            }
        } else {
            $file_path    = $file_prefix . $slug . '.php';
            $file_content = $this->github_api->get_raw_file( $parsed['owner'], $parsed['repo'], $branch, $file_path );

            if ( ! is_wp_error( $file_content ) && preg_match( '/^\s*\*?\s*Version:\s*(.+)$/mi', $file_content, $matches ) ) {
                return trim( $matches[1] );
            }
        }

        return '';
    }

    /**
     * Get theme info by slug.
     *
     * @param string $slug Theme stylesheet/directory name.
     * @return array
     */
    private function get_theme_info( $slug ) {
        $theme = wp_get_theme( $slug );

        if ( ! $theme->exists() ) {
            return array(
                'name'      => $slug,
                'version'   => '',
                'author'    => '',
                'slug'      => $slug,
                'active'    => false,
                'directory' => WP_CONTENT_DIR . '/themes/' . $slug,
            );
        }

        return array(
            'name'      => $theme->get( 'Name' ),
            'version'   => $theme->get( 'Version' ),
            'author'    => $theme->get( 'Author' ),
            'slug'      => $slug,
            'active'    => ( wp_get_theme()->get_stylesheet() === $slug ),
            'directory' => $theme->get_stylesheet_directory(),
        );
    }

    /**
     * Get plugin info by slug.
     *
     * @param string $slug Plugin directory name.
     * @return array
     */
    private function get_plugin_info( $slug ) {
        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;

        if ( ! is_dir( $plugin_dir ) ) {
            return array(
                'name'      => $slug,
                'version'   => '',
                'author'    => '',
                'slug'      => $slug,
                'active'    => false,
                'directory' => $plugin_dir,
            );
        }

        $php_files = glob( $plugin_dir . '/*.php' );
        $plugin_name    = '';
        $plugin_version = '';
        $plugin_author  = '';
        $main_file      = '';

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

        $plugin_file = $slug . '/' . $main_file;
        $is_active   = ! empty( $main_file ) && is_plugin_active( $plugin_file );

        return array(
            'name'      => $plugin_name ?: $slug,
            'version'   => $plugin_version,
            'author'    => $plugin_author,
            'slug'      => $slug,
            'active'    => $is_active,
            'directory' => $plugin_dir,
        );
    }

    /**
     * Save state back to the wp_puller_assets option.
     *
     * @param array $data Key-value pairs to update.
     */
    private function save_state( $data ) {
        $assets = get_option( 'wp_puller_assets', array() );
        $id     = $this->config['id'];

        if ( ! isset( $assets[ $id ] ) ) {
            return;
        }

        foreach ( $data as $key => $value ) {
            $assets[ $id ][ $key ] = $value;
            $this->config[ $key ]  = $value;
        }

        update_option( 'wp_puller_assets', $assets );
    }

    /**
     * Clear directory contents.
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
     * Clear asset-related caches.
     */
    private function clear_asset_cache() {
        if ( 'theme' === $this->config['type'] ) {
            wp_clean_themes_cache();
        } else {
            wp_clean_plugins_cache();
        }

        delete_transient( 'dirsize_cache' );

        if ( function_exists( 'opcache_reset' ) ) {
            @opcache_reset();
        }

        do_action( 'wp_puller_cache_cleared' );
    }
}
