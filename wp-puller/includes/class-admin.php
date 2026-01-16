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
     * GitHub API instance.
     *
     * @var WP_Puller_GitHub_API
     */
    private $github_api;

    /**
     * Theme updater instance.
     *
     * @var WP_Puller_Theme_Updater
     */
    private $updater;

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
     * @param WP_Puller_GitHub_API    $github_api GitHub API instance.
     * @param WP_Puller_Theme_Updater $updater    Theme updater instance.
     * @param WP_Puller_Backup        $backup     Backup instance.
     * @param WP_Puller_Logger        $logger     Logger instance.
     */
    public function __construct( $github_api, $updater, $backup, $logger ) {
        $this->github_api = $github_api;
        $this->updater    = $updater;
        $this->backup     = $backup;
        $this->logger     = $logger;

        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        add_action( 'wp_ajax_wp_puller_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_wp_puller_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_wp_puller_check_updates', array( $this, 'ajax_check_updates' ) );
        add_action( 'wp_ajax_wp_puller_update_theme', array( $this, 'ajax_update_theme' ) );
        add_action( 'wp_ajax_wp_puller_restore_backup', array( $this, 'ajax_restore_backup' ) );
        add_action( 'wp_ajax_wp_puller_delete_backup', array( $this, 'ajax_delete_backup' ) );
        add_action( 'wp_ajax_wp_puller_regenerate_secret', array( $this, 'ajax_regenerate_secret' ) );
        add_action( 'wp_ajax_wp_puller_clear_logs', array( $this, 'ajax_clear_logs' ) );
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'WP Puller', 'wp-puller' ),
            __( 'WP Puller', 'wp-puller' ),
            'manage_options',
            'wp-puller',
            array( $this, 'render_admin_page' ),
            'dashicons-update',
            80
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
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

        wp_localize_script( 'wp-puller-admin', 'wpPuller', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wp_puller_nonce' ),
            'strings'  => array(
                'saving'           => __( 'Saving...', 'wp-puller' ),
                'saved'            => __( 'Settings saved!', 'wp-puller' ),
                'testing'          => __( 'Testing connection...', 'wp-puller' ),
                'connected'        => __( 'Connected successfully!', 'wp-puller' ),
                'checking'         => __( 'Checking for updates...', 'wp-puller' ),
                'updating'         => __( 'Updating theme...', 'wp-puller' ),
                'updated'          => __( 'Theme updated successfully!', 'wp-puller' ),
                'restoring'        => __( 'Restoring backup...', 'wp-puller' ),
                'restored'         => __( 'Backup restored successfully!', 'wp-puller' ),
                'deleting'         => __( 'Deleting backup...', 'wp-puller' ),
                'deleted'          => __( 'Backup deleted!', 'wp-puller' ),
                'regenerating'     => __( 'Regenerating secret...', 'wp-puller' ),
                'regenerated'      => __( 'Secret regenerated!', 'wp-puller' ),
                'error'            => __( 'An error occurred.', 'wp-puller' ),
                'confirmRestore'   => __( 'Are you sure you want to restore this backup? Your current theme will be replaced.', 'wp-puller' ),
                'confirmDelete'    => __( 'Are you sure you want to delete this backup?', 'wp-puller' ),
                'confirmRegenerate'=> __( 'Are you sure? You will need to update the secret in GitHub.', 'wp-puller' ),
            ),
        ) );
    }

    /**
     * Render admin page.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-puller' ) );
        }

        $data = array(
            'status'       => $this->updater->get_status(),
            'theme_info'   => $this->updater->get_current_theme_info(),
            'webhook_info' => WP_Puller_Webhook_Handler::get_setup_instructions(),
            'backups'      => $this->backup->get_backups( wp_get_theme()->get_stylesheet() ),
            'logs'         => $this->logger->get_recent_logs( 10 ),
            'backup_class' => $this->backup,
        );

        include WP_PULLER_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * AJAX: Save settings.
     */
    public function ajax_save_settings() {
        $this->verify_ajax_request();

        $repo_url    = isset( $_POST['repo_url'] ) ? esc_url_raw( wp_unslash( $_POST['repo_url'] ) ) : '';
        $branch      = isset( $_POST['branch'] ) ? sanitize_text_field( wp_unslash( $_POST['branch'] ) ) : 'main';
        $theme_path  = isset( $_POST['theme_path'] ) ? sanitize_text_field( wp_unslash( $_POST['theme_path'] ) ) : '';
        $pat         = isset( $_POST['pat'] ) ? sanitize_text_field( wp_unslash( $_POST['pat'] ) ) : '';
        $auto_update = isset( $_POST['auto_update'] ) && 'true' === $_POST['auto_update'];
        $backup_count = isset( $_POST['backup_count'] ) ? absint( $_POST['backup_count'] ) : 3;

        // Clean up theme path - remove leading/trailing slashes
        $theme_path = trim( $theme_path, '/' );

        update_option( 'wp_puller_repo_url', $repo_url );
        update_option( 'wp_puller_branch', $branch );
        update_option( 'wp_puller_theme_path', $theme_path );
        update_option( 'wp_puller_auto_update', $auto_update );
        update_option( 'wp_puller_backup_count', max( 1, min( 10, $backup_count ) ) );

        if ( ! empty( $pat ) && '*****' !== substr( $pat, 0, 5 ) ) {
            update_option( 'wp_puller_pat', WP_Puller::encrypt( $pat ) );
        }

        $this->github_api->clear_cache();

        $this->logger->log(
            __( 'Settings updated', 'wp-puller' ),
            WP_Puller_Logger::STATUS_INFO,
            WP_Puller_Logger::SOURCE_MANUAL
        );

        wp_send_json_success( array(
            'message' => __( 'Settings saved successfully.', 'wp-puller' ),
        ) );
    }

    /**
     * AJAX: Test connection.
     */
    public function ajax_test_connection() {
        $this->verify_ajax_request();

        $repo_url = isset( $_POST['repo_url'] ) ? esc_url_raw( wp_unslash( $_POST['repo_url'] ) ) : '';

        if ( empty( $repo_url ) ) {
            wp_send_json_error( array(
                'message' => __( 'Please enter a repository URL.', 'wp-puller' ),
            ) );
        }

        $result = $this->github_api->test_connection( $repo_url );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }

        $repo_info = array(
            'name'        => isset( $result['name'] ) ? $result['name'] : '',
            'full_name'   => isset( $result['full_name'] ) ? $result['full_name'] : '',
            'description' => isset( $result['description'] ) ? $result['description'] : '',
            'private'     => isset( $result['private'] ) ? $result['private'] : false,
            'default_branch' => isset( $result['default_branch'] ) ? $result['default_branch'] : 'main',
        );

        wp_send_json_success( array(
            'message' => __( 'Connection successful!', 'wp-puller' ),
            'repo'    => $repo_info,
        ) );
    }

    /**
     * AJAX: Check for updates.
     */
    public function ajax_check_updates() {
        $this->verify_ajax_request();

        $result = $this->updater->check_for_updates();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Update theme.
     */
    public function ajax_update_theme() {
        $this->verify_ajax_request();

        $result = $this->updater->update( WP_Puller_Logger::SOURCE_MANUAL );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Theme updated successfully!', 'wp-puller' ),
            'status'  => $this->updater->get_status(),
        ) );
    }

    /**
     * AJAX: Restore backup.
     */
    public function ajax_restore_backup() {
        $this->verify_ajax_request();

        $backup_name = isset( $_POST['backup_name'] ) ? sanitize_file_name( wp_unslash( $_POST['backup_name'] ) ) : '';

        if ( empty( $backup_name ) ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid backup name.', 'wp-puller' ),
            ) );
        }

        $result = $this->backup->restore_backup( $backup_name );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }

        $this->logger->log_restore_success( $backup_name );

        wp_send_json_success( array(
            'message' => __( 'Backup restored successfully!', 'wp-puller' ),
        ) );
    }

    /**
     * AJAX: Delete backup.
     */
    public function ajax_delete_backup() {
        $this->verify_ajax_request();

        $backup_name = isset( $_POST['backup_name'] ) ? sanitize_file_name( wp_unslash( $_POST['backup_name'] ) ) : '';

        if ( empty( $backup_name ) ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid backup name.', 'wp-puller' ),
            ) );
        }

        $result = $this->backup->delete_backup( $backup_name );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Backup deleted successfully!', 'wp-puller' ),
        ) );
    }

    /**
     * AJAX: Regenerate webhook secret.
     */
    public function ajax_regenerate_secret() {
        $this->verify_ajax_request();

        $new_secret = WP_Puller_Webhook_Handler::generate_secret();
        update_option( 'wp_puller_webhook_secret', $new_secret );

        $this->logger->log(
            __( 'Webhook secret regenerated', 'wp-puller' ),
            WP_Puller_Logger::STATUS_INFO,
            WP_Puller_Logger::SOURCE_MANUAL
        );

        wp_send_json_success( array(
            'message' => __( 'Secret regenerated. Update it in GitHub.', 'wp-puller' ),
            'secret'  => $new_secret,
        ) );
    }

    /**
     * AJAX: Clear logs.
     */
    public function ajax_clear_logs() {
        $this->verify_ajax_request();

        $this->logger->clear_logs();

        wp_send_json_success( array(
            'message' => __( 'Logs cleared.', 'wp-puller' ),
        ) );
    }

    /**
     * Verify AJAX request.
     */
    private function verify_ajax_request() {
        if ( ! check_ajax_referer( 'wp_puller_nonce', 'nonce', false ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'wp-puller' ),
            ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'wp-puller' ),
            ) );
        }
    }

    /**
     * Get masked PAT for display.
     *
     * @return string
     */
    public static function get_masked_pat() {
        $encrypted = get_option( 'wp_puller_pat', '' );

        if ( empty( $encrypted ) ) {
            return '';
        }

        $decrypted = WP_Puller::decrypt( $encrypted );

        if ( empty( $decrypted ) ) {
            return '';
        }

        return str_repeat( '*', min( strlen( $decrypted ), 20 ) ) . substr( $decrypted, -4 );
    }

    /**
     * Get PAT status for debugging.
     *
     * @return array
     */
    public static function get_pat_status() {
        $encrypted = get_option( 'wp_puller_pat', '' );

        if ( empty( $encrypted ) ) {
            return array(
                'stored'    => false,
                'decrypts'  => false,
                'type'      => 'none',
                'message'   => 'No token saved',
            );
        }

        $decrypted = WP_Puller::decrypt( $encrypted );

        if ( empty( $decrypted ) ) {
            return array(
                'stored'    => true,
                'decrypts'  => false,
                'type'      => 'unknown',
                'message'   => 'Token stored but decryption failed',
            );
        }

        $type = 'classic';
        if ( strpos( $decrypted, 'github_pat_' ) === 0 ) {
            $type = 'fine-grained';
        } elseif ( strpos( $decrypted, 'ghp_' ) === 0 ) {
            $type = 'classic';
        }

        return array(
            'stored'    => true,
            'decrypts'  => true,
            'type'      => $type,
            'length'    => strlen( $decrypted ),
            'prefix'    => substr( $decrypted, 0, 10 ) . '...',
            'message'   => sprintf( 'Token OK (%s, %d chars)', $type, strlen( $decrypted ) ),
        );
    }
}
