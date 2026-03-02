<?php
/**
 * Admin class for WP Puller.
 *
 * @package WP_Puller
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP_Puller_Admin Class.
 */
class WP_Puller_Admin {

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
     * @param WP_Puller_Backup $backup Backup instance.
     * @param WP_Puller_Logger $logger Logger instance.
     */
    public function __construct( $backup, $logger ) {
        $this->backup = $backup;
        $this->logger = $logger;

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Asset management.
        add_action( 'wp_ajax_wp_puller_add_asset', array( $this, 'ajax_add_asset' ) );
        add_action( 'wp_ajax_wp_puller_remove_asset', array( $this, 'ajax_remove_asset' ) );
        add_action( 'wp_ajax_wp_puller_save_settings', array( $this, 'ajax_save_settings' ) );

        // Update actions.
        add_action( 'wp_ajax_wp_puller_check_updates', array( $this, 'ajax_check_updates' ) );
        add_action( 'wp_ajax_wp_puller_update_asset', array( $this, 'ajax_update_asset' ) );
        add_action( 'wp_ajax_wp_puller_check_all', array( $this, 'ajax_check_all' ) );
        add_action( 'wp_ajax_wp_puller_update_all', array( $this, 'ajax_update_all' ) );

        // Branch testing.
        add_action( 'wp_ajax_wp_puller_deploy_branch', array( $this, 'ajax_deploy_branch' ) );
        add_action( 'wp_ajax_wp_puller_get_branches_with_info', array( $this, 'ajax_get_branches_with_info' ) );
        add_action( 'wp_ajax_wp_puller_compare_branches', array( $this, 'ajax_compare_branches' ) );

        // Backups.
        add_action( 'wp_ajax_wp_puller_restore_backup', array( $this, 'ajax_restore_backup' ) );
        add_action( 'wp_ajax_wp_puller_delete_backup', array( $this, 'ajax_delete_backup' ) );

        // Branch configuration.
        add_action( 'wp_ajax_wp_puller_set_updates_branch', array( $this, 'ajax_set_updates_branch' ) );

        // Shared.
        add_action( 'wp_ajax_wp_puller_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_wp_puller_regenerate_secret', array( $this, 'ajax_regenerate_secret' ) );
        add_action( 'wp_ajax_wp_puller_clear_logs', array( $this, 'ajax_clear_logs' ) );
    }

    /**
     * Add admin menu page.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'WP Puller', 'wp-puller' ),
            __( 'WP Puller', 'wp-puller' ),
            'manage_options',
            'wp-puller',
            array( $this, 'render_admin_page' ),
            'dashicons-update',
            81
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_scripts( $hook ) {
        if ( 'toplevel_page_wp-puller' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'wp-puller-admin',
            WP_PULLER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WP_PULLER_VERSION
        );

        wp_enqueue_script(
            'wp-puller-admin',
            WP_PULLER_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            WP_PULLER_VERSION,
            true
        );

        $assets = WP_Puller::get_assets();
        $tokens = WP_Puller::get_tokens();

        // Build tokens list for dropdown (masked).
        $token_list = array();
        foreach ( $tokens as $tok_id => $tok ) {
            $asset_names = array();
            foreach ( $assets as $a ) {
                if ( ! empty( $a['token_id'] ) && $a['token_id'] === $tok_id ) {
                    $asset_names[] = $a['label'] ?: $a['slug'];
                }
            }
            $token_list[] = array(
                'id'      => $tok_id,
                'label'   => $tok['label'],
                'used_by' => $asset_names,
            );
        }

        // Build asset data for JS.
        $js_assets = array();
        foreach ( $assets as $id => $config ) {
            $github_api = WP_Puller::create_github_api( $config );
            $updater    = new WP_Puller_Asset_Updater( $config, $github_api, $this->backup, $this->logger );
            $info       = $updater->get_current_info();
            $status     = $updater->get_status();

            $js_assets[ $id ] = array(
                'id'             => $id,
                'type'           => $config['type'],
                'label'          => $config['label'],
                'slug'           => $config['slug'],
                'token_id'       => $config['token_id'],
                'info'           => $info,
                'status'         => $status,
                'branch'         => $config['branch'],
                'deployedBranch' => $config['deployed_branch'],
            );
        }

        wp_localize_script( 'wp-puller-admin', 'wpPuller', array(
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'wp_puller_nonce' ),
            'assets'      => $js_assets,
            'tokens'      => $token_list,
            'webhookInfo' => WP_Puller_Webhook_Handler::get_setup_instructions(),
            'strings'     => array(
                'error'               => __( 'An error occurred. Please try again.', 'wp-puller' ),
                'connected'           => __( 'Connection successful!', 'wp-puller' ),
                'saved'               => __( 'Settings saved.', 'wp-puller' ),
                'confirmRestore'      => __( 'Are you sure you want to restore this backup? Current files will be overwritten.', 'wp-puller' ),
                'confirmDelete'       => __( 'Are you sure you want to delete this backup?', 'wp-puller' ),
                'confirmRemove'       => __( 'Are you sure you want to remove this item? This cannot be undone.', 'wp-puller' ),
                'confirmUpdateAll'    => __( 'Update all items? A backup will be created before each update.', 'wp-puller' ),
                'confirmBranchDeploy' => __( 'Deploy this branch? A backup will be created first.', 'wp-puller' ),
                'restored'            => __( 'Backup restored successfully.', 'wp-puller' ),
                'deleted'             => __( 'Backup deleted.', 'wp-puller' ),
                'regenerated'         => __( 'Webhook secret regenerated. Update your GitHub webhook settings.', 'wp-puller' ),
                'noChanges'           => __( 'No changes between these branches.', 'wp-puller' ),
                'confirmSetBranch'    => __( 'Set this as the updates branch? Future updates and webhooks will track this branch.', 'wp-puller' ),
            ),
        ) );
    }

    /**
     * Render the admin page.
     */
    public function render_admin_page() {
        $assets       = WP_Puller::get_assets();
        $tokens       = WP_Puller::get_tokens();
        $webhook_info = WP_Puller_Webhook_Handler::get_setup_instructions();
        $logs         = $this->logger->get_logs();

        // Build per-asset data for the template.
        $asset_data = array();
        foreach ( $assets as $id => $config ) {
            $github_api = WP_Puller::create_github_api( $config );
            $updater    = new WP_Puller_Asset_Updater( $config, $github_api, $this->backup, $this->logger );
            $info       = $updater->get_current_info();
            $status     = $updater->get_status();

            // Get backups for this asset.
            $backup_prefix = ( 'plugin' === $config['type'] ) ? 'plugin_' . $config['slug'] : $config['slug'];
            $backups       = ! empty( $config['slug'] ) ? $this->backup->get_backups( $backup_prefix ) : array();

            $asset_data[ $id ] = array(
                'config'  => $config,
                'info'    => $info,
                'status'  => $status,
                'backups' => $backups,
            );
        }

        $data = array(
            'assets'       => $asset_data,
            'tokens'       => $tokens,
            'webhook_info' => $webhook_info,
            'logs'         => $logs,
            'backup_class' => $this->backup,
        );

        require WP_PULLER_PLUGIN_DIR . 'templates/admin-page.php';
    }

    // =========================================================================
    // AJAX: Asset Management
    // =========================================================================

    /**
     * Add a new asset.
     */
    public function ajax_add_asset() {
        $this->verify_ajax();

        $type = sanitize_text_field( wp_unslash( $_POST['type'] ?? 'plugin' ) );
        if ( ! in_array( $type, array( 'theme', 'plugin' ), true ) ) {
            $type = 'plugin';
        }

        $asset_id = 'asset_' . wp_generate_password( 12, false );
        $assets   = WP_Puller::get_assets();

        $assets[ $asset_id ] = array(
            'id'               => $asset_id,
            'type'             => $type,
            'label'            => '',
            'repo_url'         => '',
            'branch'           => 'main',
            'path'             => '',
            'slug'             => '',
            'auto_update'      => true,
            'backup_count'     => 3,
            'token_id'         => '',
            'latest_commit'    => '',
            'last_check'       => 0,
            'deployed_branch'  => '',
            'deployed_commit'  => '',
        );

        update_option( 'wp_puller_assets', $assets );

        wp_send_json_success( array(
            'message'  => __( 'Item added. Configure its settings.', 'wp-puller' ),
            'asset_id' => $asset_id,
        ) );
    }

    /**
     * Remove an asset.
     */
    public function ajax_remove_asset() {
        $this->verify_ajax();

        $asset_id = sanitize_text_field( wp_unslash( $_POST['asset_id'] ?? '' ) );
        $assets   = WP_Puller::get_assets();

        if ( ! isset( $assets[ $asset_id ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Asset not found.', 'wp-puller' ) ) );
        }

        $removed_token_id = $assets[ $asset_id ]['token_id'];
        unset( $assets[ $asset_id ] );
        update_option( 'wp_puller_assets', $assets );

        // Clean up orphaned token.
        if ( ! empty( $removed_token_id ) ) {
            $still_used = false;
            foreach ( $assets as $a ) {
                if ( $a['token_id'] === $removed_token_id ) {
                    $still_used = true;
                    break;
                }
            }
            if ( ! $still_used ) {
                $tokens = WP_Puller::get_tokens();
                unset( $tokens[ $removed_token_id ] );
                update_option( 'wp_puller_tokens', $tokens );
            }
        }

        wp_send_json_success( array( 'message' => __( 'Item removed.', 'wp-puller' ) ) );
    }

    /**
     * Save settings for an asset.
     */
    public function ajax_save_settings() {
        $this->verify_ajax();

        $asset_id = sanitize_text_field( wp_unslash( $_POST['asset_id'] ?? '' ) );
        $assets   = WP_Puller::get_assets();

        if ( ! isset( $assets[ $asset_id ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Asset not found.', 'wp-puller' ) ) );
        }

        $config = $assets[ $asset_id ];

        $config['repo_url']     = esc_url_raw( wp_unslash( $_POST['repo_url'] ?? '' ) );
        $config['branch']       = sanitize_text_field( wp_unslash( $_POST['branch'] ?? 'main' ) );
        $config['slug']         = sanitize_file_name( wp_unslash( $_POST['slug'] ?? '' ) );
        $config['path']         = sanitize_text_field( wp_unslash( $_POST['path'] ?? '' ) );
        $config['type']         = in_array( $_POST['type'] ?? '', array( 'theme', 'plugin' ), true )
            ? sanitize_text_field( $_POST['type'] )
            : $config['type'];
        $config['auto_update']  = ( $_POST['auto_update'] ?? '' ) === 'true';
        $config['backup_count'] = max( 1, min( 10, absint( $_POST['backup_count'] ?? 3 ) ) );

        // Handle PAT / token.
        $pat_value      = wp_unslash( $_POST['pat'] ?? '' );
        $reuse_token_id = sanitize_text_field( wp_unslash( $_POST['reuse_token_id'] ?? '' ) );

        if ( ! empty( $reuse_token_id ) && 'new' !== $reuse_token_id ) {
            // Reuse existing token.
            $config['token_id'] = $reuse_token_id;
        } elseif ( ! empty( $pat_value ) && ! $this->is_masked_value( $pat_value ) ) {
            // New token entered.
            $token_id  = 'tok_' . wp_generate_password( 12, false );
            $encrypted = WP_Puller::encrypt( $pat_value );
            $label     = $this->generate_token_label( $pat_value );

            $tokens = WP_Puller::get_tokens();
            $tokens[ $token_id ] = array(
                'id'        => $token_id,
                'label'     => $label,
                'encrypted' => $encrypted,
            );
            update_option( 'wp_puller_tokens', $tokens );

            $config['token_id'] = $token_id;
        }

        // Auto-detect label from installed asset.
        if ( empty( $config['label'] ) && ! empty( $config['slug'] ) ) {
            $github_api = WP_Puller::create_github_api( $config );
            $updater    = new WP_Puller_Asset_Updater( $config, $github_api, $this->backup, $this->logger );
            $info       = $updater->get_current_info();
            if ( ! empty( $info['name'] ) && $info['name'] !== $config['slug'] ) {
                $config['label'] = $info['name'];
            }
        }

        $assets[ $asset_id ] = $config;
        update_option( 'wp_puller_assets', $assets );

        // Return updated status.
        $github_api = WP_Puller::create_github_api( $config );
        $updater    = new WP_Puller_Asset_Updater( $config, $github_api, $this->backup, $this->logger );
        $status     = $updater->get_status();
        $info       = $updater->get_current_info();

        wp_send_json_success( array(
            'message' => __( 'Settings saved.', 'wp-puller' ),
            'status'  => $status,
            'info'    => $info,
        ) );
    }

    // =========================================================================
    // AJAX: Updates
    // =========================================================================

    /**
     * Check for updates on a single asset.
     */
    public function ajax_check_updates() {
        $this->verify_ajax();

        $asset_id = sanitize_text_field( wp_unslash( $_POST['asset_id'] ?? '' ) );
        $updater  = $this->get_updater( $asset_id );

        if ( is_wp_error( $updater ) ) {
            wp_send_json_error( array( 'message' => $updater->get_error_message() ) );
        }

        $result = $updater->check_for_updates();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * Update a single asset.
     */
    public function ajax_update_asset() {
        $this->verify_ajax();

        $asset_id = sanitize_text_field( wp_unslash( $_POST['asset_id'] ?? '' ) );
        $updater  = $this->get_updater( $asset_id );

        if ( is_wp_error( $updater ) ) {
            wp_send_json_error( array( 'message' => $updater->get_error_message() ) );
        }

        $result = $updater->update( 'manual' );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $status = $updater->get_status();
        $info   = $updater->get_current_info();

        wp_send_json_success( array(
            'message' => __( 'Updated successfully.', 'wp-puller' ),
            'status'  => $status,
            'info'    => $info,
        ) );
    }

    /**
     * Check all assets for updates.
     */
    public function ajax_check_all() {
        $this->verify_ajax();

        $assets  = WP_Puller::get_assets();
        $results = array();

        foreach ( $assets as $id => $config ) {
            if ( empty( $config['repo_url'] ) || empty( $config['slug'] ) ) {
                continue;
            }

            $updater = $this->get_updater( $id );
            if ( is_wp_error( $updater ) ) {
                $results[ $id ] = array( 'error' => $updater->get_error_message() );
                continue;
            }

            $check = $updater->check_for_updates();
            if ( is_wp_error( $check ) ) {
                $results[ $id ] = array( 'error' => $check->get_error_message() );
            } else {
                $results[ $id ] = $check;
            }
        }

        wp_send_json_success( array( 'results' => $results ) );
    }

    /**
     * Update all assets.
     */
    public function ajax_update_all() {
        $this->verify_ajax();

        $assets  = WP_Puller::get_assets();
        $results = array();

        foreach ( $assets as $id => $config ) {
            if ( empty( $config['repo_url'] ) || empty( $config['slug'] ) ) {
                continue;
            }

            $updater = $this->get_updater( $id );
            if ( is_wp_error( $updater ) ) {
                $results[ $id ] = array( 'error' => $updater->get_error_message() );
                continue;
            }

            $result = $updater->update( 'manual' );
            if ( is_wp_error( $result ) ) {
                $results[ $id ] = array( 'error' => $result->get_error_message() );
            } else {
                $results[ $id ] = array(
                    'success' => true,
                    'info'    => $updater->get_current_info(),
                    'status'  => $updater->get_status(),
                );
            }
        }

        wp_send_json_success( array( 'results' => $results ) );
    }

    // =========================================================================
    // AJAX: Branch Testing
    // =========================================================================

    /**
     * Deploy a branch for an asset.
     */
    public function ajax_deploy_branch() {
        $this->verify_ajax();

        $asset_id = sanitize_text_field( wp_unslash( $_POST['asset_id'] ?? '' ) );
        $branch   = sanitize_text_field( wp_unslash( $_POST['branch'] ?? '' ) );

        $updater = $this->get_updater( $asset_id );
        if ( is_wp_error( $updater ) ) {
            wp_send_json_error( array( 'message' => $updater->get_error_message() ) );
        }

        $result = $updater->deploy_branch( $branch );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %s: branch name */
                __( 'Branch "%s" deployed successfully.', 'wp-puller' ),
                $branch
            ),
            'status' => $updater->get_status(),
            'info'   => $updater->get_current_info(),
        ) );
    }

    /**
     * Get branches with info for an asset.
     */
    public function ajax_get_branches_with_info() {
        $this->verify_ajax();

        $asset_id = sanitize_text_field( wp_unslash( $_POST['asset_id'] ?? '' ) );
        $assets   = WP_Puller::get_assets();

        if ( ! isset( $assets[ $asset_id ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Asset not found.', 'wp-puller' ) ) );
        }

        $config     = $assets[ $asset_id ];
        $github_api = WP_Puller::create_github_api( $config );
        $parsed     = $github_api->parse_repo_url( $config['repo_url'] );

        if ( ! $parsed ) {
            wp_send_json_error( array( 'message' => __( 'Invalid repository URL.', 'wp-puller' ) ) );
        }

        $branches = $github_api->get_branches_with_info(
            $parsed['owner'],
            $parsed['repo'],
            20,
            $config['branch'],
            $config['deployed_branch']
        );

        if ( is_wp_error( $branches ) ) {
            wp_send_json_error( array( 'message' => $branches->get_error_message() ) );
        }

        wp_send_json_success( array(
            'branches'        => $branches,
            'configured'      => $config['branch'],
            'deployed_branch' => $config['deployed_branch'],
        ) );
    }

    /**
     * Compare branches for an asset.
     */
    public function ajax_compare_branches() {
        $this->verify_ajax();

        $asset_id = sanitize_text_field( wp_unslash( $_POST['asset_id'] ?? '' ) );
        $base     = sanitize_text_field( wp_unslash( $_POST['base'] ?? '' ) );
        $head     = sanitize_text_field( wp_unslash( $_POST['head'] ?? '' ) );

        $assets = WP_Puller::get_assets();

        if ( ! isset( $assets[ $asset_id ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Asset not found.', 'wp-puller' ) ) );
        }

        $config     = $assets[ $asset_id ];
        $github_api = WP_Puller::create_github_api( $config );
        $parsed     = $github_api->parse_repo_url( $config['repo_url'] );

        if ( ! $parsed ) {
            wp_send_json_error( array( 'message' => __( 'Invalid repository URL.', 'wp-puller' ) ) );
        }

        $comparison = $github_api->compare_commits( $parsed['owner'], $parsed['repo'], $base, $head );

        if ( is_wp_error( $comparison ) ) {
            wp_send_json_error( array( 'message' => $comparison->get_error_message() ) );
        }

        wp_send_json_success( $comparison );
    }

    /**
     * Set the updates branch for an asset.
     */
    public function ajax_set_updates_branch() {
        $this->verify_ajax();

        $asset_id = sanitize_text_field( wp_unslash( $_POST['asset_id'] ?? '' ) );
        $branch   = sanitize_text_field( wp_unslash( $_POST['branch'] ?? '' ) );

        $assets = WP_Puller::get_assets();

        if ( ! isset( $assets[ $asset_id ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Asset not found.', 'wp-puller' ) ) );
        }

        if ( empty( $branch ) ) {
            wp_send_json_error( array( 'message' => __( 'No branch specified.', 'wp-puller' ) ) );
        }

        $assets[ $asset_id ]['branch'] = $branch;
        update_option( 'wp_puller_assets', $assets );

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %s: branch name */
                __( 'Updates branch set to "%s".', 'wp-puller' ),
                $branch
            ),
            'branch' => $branch,
        ) );
    }

    // =========================================================================
    // AJAX: Backups
    // =========================================================================

    /**
     * Restore a backup.
     */
    public function ajax_restore_backup() {
        $this->verify_ajax();

        $asset_id    = sanitize_text_field( wp_unslash( $_POST['asset_id'] ?? '' ) );
        $backup_name = sanitize_text_field( wp_unslash( $_POST['backup_name'] ?? '' ) );
        $assets      = WP_Puller::get_assets();

        if ( ! isset( $assets[ $asset_id ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Asset not found.', 'wp-puller' ) ) );
        }

        $config = $assets[ $asset_id ];

        if ( 'plugin' === $config['type'] ) {
            $result = $this->backup->restore_plugin_backup( $backup_name, $config['slug'] );
        } else {
            $result = $this->backup->restore_backup( $backup_name, $config['slug'] );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $this->logger->log_restore_success( $backup_name, array(
            'asset_type'  => $config['type'],
            'asset_slug'  => $config['slug'],
            'asset_label' => ! empty( $config['label'] ) ? $config['label'] : $config['slug'],
        ) );

        wp_send_json_success( array( 'message' => __( 'Backup restored successfully.', 'wp-puller' ) ) );
    }

    /**
     * Delete a backup.
     */
    public function ajax_delete_backup() {
        $this->verify_ajax();

        $backup_name = sanitize_text_field( wp_unslash( $_POST['backup_name'] ?? '' ) );

        $result = $this->backup->delete_backup( $backup_name );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => __( 'Backup deleted.', 'wp-puller' ) ) );
    }

    // =========================================================================
    // AJAX: Shared
    // =========================================================================

    /**
     * Test GitHub connection.
     */
    public function ajax_test_connection() {
        $this->verify_ajax();

        $repo_url = esc_url_raw( wp_unslash( $_POST['repo_url'] ?? '' ) );
        $pat      = wp_unslash( $_POST['pat'] ?? '' );

        $actual_pat = '';
        if ( ! empty( $pat ) && ! $this->is_masked_value( $pat ) ) {
            $actual_pat = $pat;
        } elseif ( ! empty( $_POST['token_id'] ) ) {
            $tokens = WP_Puller::get_tokens();
            $tid    = sanitize_text_field( wp_unslash( $_POST['token_id'] ) );
            if ( isset( $tokens[ $tid ] ) ) {
                $actual_pat = WP_Puller::decrypt( $tokens[ $tid ]['encrypted'] );
            }
        }

        $github_api = new WP_Puller_GitHub_API( $actual_pat );
        $result     = $github_api->test_connection( $repo_url );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Connection successful!', 'wp-puller' ),
            'repo'    => $result,
        ) );
    }

    /**
     * Regenerate webhook secret.
     */
    public function ajax_regenerate_secret() {
        $this->verify_ajax();

        $secret = WP_Puller_Webhook_Handler::generate_secret();
        update_option( 'wp_puller_webhook_secret', $secret );

        wp_send_json_success( array(
            'secret'  => $secret,
            'message' => __( 'Secret regenerated.', 'wp-puller' ),
        ) );
    }

    /**
     * Clear activity logs.
     */
    public function ajax_clear_logs() {
        $this->verify_ajax();

        $this->logger->clear_logs();
        wp_send_json_success();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Get an updater for a given asset ID.
     *
     * @param string $asset_id Asset ID.
     * @return WP_Puller_Asset_Updater|WP_Error
     */
    private function get_updater( $asset_id ) {
        return WP_Puller::create_updater( $asset_id );
    }

    /**
     * Verify AJAX request.
     */
    private function verify_ajax() {
        check_ajax_referer( 'wp_puller_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-puller' ) ) );
        }
    }

    /**
     * Check if a value is a masked PAT.
     *
     * @param string $value Value to check.
     * @return bool
     */
    private function is_masked_value( $value ) {
        return false !== strpos( $value, '****' );
    }

    /**
     * Generate a label for a PAT based on its format.
     *
     * @param string $pat Plain-text PAT.
     * @return string
     */
    private function generate_token_label( $pat ) {
        $len = strlen( $pat );
        if ( strpos( $pat, 'github_pat_' ) === 0 ) {
            return sprintf( 'fine-grained, %d chars', $len );
        }
        if ( strpos( $pat, 'ghp_' ) === 0 ) {
            return sprintf( 'classic, %d chars', $len );
        }
        return sprintf( 'token, %d chars', $len );
    }

    /**
     * Get masked token value for display.
     *
     * @param string $token_id Token ID.
     * @return string
     */
    public static function get_masked_token( $token_id ) {
        $tokens = WP_Puller::get_tokens();

        if ( ! isset( $tokens[ $token_id ] ) ) {
            return '';
        }

        $pat = WP_Puller::decrypt( $tokens[ $token_id ]['encrypted'] );

        if ( empty( $pat ) ) {
            return '';
        }

        $len = strlen( $pat );
        if ( $len <= 8 ) {
            return str_repeat( '*', $len );
        }

        return substr( $pat, 0, 4 ) . str_repeat( '*', $len - 8 ) . substr( $pat, -4 );
    }

    /**
     * Get token status for display.
     *
     * @param string $token_id Token ID.
     * @return array
     */
    public static function get_token_status( $token_id ) {
        $tokens = WP_Puller::get_tokens();

        if ( ! isset( $tokens[ $token_id ] ) ) {
            return array(
                'stored'   => false,
                'decrypts' => false,
                'message'  => '',
            );
        }

        $pat = WP_Puller::decrypt( $tokens[ $token_id ]['encrypted'] );

        return array(
            'stored'   => true,
            'decrypts' => ! empty( $pat ),
            'message'  => ! empty( $pat )
                ? sprintf( 'OK (%s)', $tokens[ $token_id ]['label'] )
                : __( 'Decryption failed', 'wp-puller' ),
        );
    }
}
