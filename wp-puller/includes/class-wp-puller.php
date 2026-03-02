<?php
/**
 * Main WP Puller class.
 *
 * @package WP_Puller
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main WP_Puller Class.
 *
 * @class WP_Puller
 */
final class WP_Puller {

    /**
     * WP_Puller version.
     *
     * @var string
     */
    public $version = '2.3.0';

    /**
     * The single instance of the class.
     *
     * @var WP_Puller
     */
    protected static $instance = null;

    /**
     * Backup instance.
     *
     * @var WP_Puller_Backup
     */
    public $backup = null;

    /**
     * Logger instance.
     *
     * @var WP_Puller_Logger
     */
    public $logger = null;

    /**
     * Webhook handler instance.
     *
     * @var WP_Puller_Webhook_Handler
     */
    public $webhook = null;

    /**
     * Admin instance.
     *
     * @var WP_Puller_Admin
     */
    public $admin = null;

    /**
     * Main WP_Puller Instance.
     *
     * @since 1.0.0
     * @return WP_Puller Main instance.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * WP_Puller Constructor.
     */
    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files.
     */
    private function includes() {
        require_once WP_PULLER_PLUGIN_DIR . 'includes/class-logger.php';
        require_once WP_PULLER_PLUGIN_DIR . 'includes/class-github-api.php';
        require_once WP_PULLER_PLUGIN_DIR . 'includes/class-backup.php';
        require_once WP_PULLER_PLUGIN_DIR . 'includes/class-asset-updater.php';
        require_once WP_PULLER_PLUGIN_DIR . 'includes/class-webhook-handler.php';

        if ( is_admin() ) {
            require_once WP_PULLER_PLUGIN_DIR . 'includes/class-admin.php';
        }
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'init' ), 0 );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Init WP_Puller when WordPress initializes.
     */
    public function init() {
        $this->load_textdomain();
        $this->maybe_migrate();
        $this->init_classes();

        do_action( 'wp_puller_init' );
    }

    /**
     * Load plugin text domain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-puller',
            false,
            dirname( WP_PULLER_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Initialize plugin classes.
     */
    private function init_classes() {
        $this->logger  = new WP_Puller_Logger();
        $this->backup  = new WP_Puller_Backup();
        $this->webhook = new WP_Puller_Webhook_Handler( $this->backup, $this->logger );

        if ( is_admin() ) {
            $this->admin = new WP_Puller_Admin( $this->backup, $this->logger );
        }
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes() {
        if ( $this->webhook ) {
            $this->webhook->register_routes();
        }
    }

    /**
     * Create a GitHub API instance for a specific asset.
     *
     * @param array $asset_config Asset configuration with token_id.
     * @return WP_Puller_GitHub_API
     */
    public static function create_github_api( $asset_config ) {
        $pat = '';

        if ( ! empty( $asset_config['token_id'] ) ) {
            $tokens = get_option( 'wp_puller_tokens', array() );
            if ( isset( $tokens[ $asset_config['token_id'] ] ) ) {
                $pat = self::decrypt( $tokens[ $asset_config['token_id'] ]['encrypted'] );
            }
        }

        return new WP_Puller_GitHub_API( $pat );
    }

    /**
     * Create an asset updater for a given asset ID.
     *
     * @param string $asset_id Asset ID.
     * @return WP_Puller_Asset_Updater|WP_Error
     */
    public static function create_updater( $asset_id ) {
        $assets = get_option( 'wp_puller_assets', array() );

        if ( ! isset( $assets[ $asset_id ] ) ) {
            return new WP_Error( 'asset_not_found', __( 'Asset not found.', 'wp-puller' ) );
        }

        $config     = $assets[ $asset_id ];
        $github_api = self::create_github_api( $config );
        $instance   = self::instance();

        return new WP_Puller_Asset_Updater( $config, $github_api, $instance->backup, $instance->logger );
    }

    /**
     * Get all configured assets.
     *
     * @return array
     */
    public static function get_assets() {
        return get_option( 'wp_puller_assets', array() );
    }

    /**
     * Get all stored tokens.
     *
     * @return array
     */
    public static function get_tokens() {
        return get_option( 'wp_puller_tokens', array() );
    }

    /**
     * Run database migration if needed.
     */
    private function maybe_migrate() {
        $db_version = get_option( 'wp_puller_db_version', '' );

        if ( version_compare( $db_version, '2.0.0', '>=' ) ) {
            return;
        }

        // Already migrated if wp_puller_assets exists.
        $existing_assets = get_option( 'wp_puller_assets' );
        if ( false !== $existing_assets ) {
            update_option( 'wp_puller_db_version', '2.0.0' );
            return;
        }

        $assets = array();
        $tokens = array();

        // Migrate PAT if exists.
        $encrypted_pat = get_option( 'wp_puller_pat', '' );
        $token_id      = '';

        if ( ! empty( $encrypted_pat ) ) {
            $token_id = 'tok_' . wp_generate_password( 12, false );
            $pat      = self::decrypt( $encrypted_pat );
            $label    = 'Migrated token';

            if ( ! empty( $pat ) ) {
                if ( strpos( $pat, 'github_pat_' ) === 0 ) {
                    $label = sprintf( 'fine-grained, %d chars', strlen( $pat ) );
                } elseif ( strpos( $pat, 'ghp_' ) === 0 ) {
                    $label = sprintf( 'classic, %d chars', strlen( $pat ) );
                }
            }

            $tokens[ $token_id ] = array(
                'id'        => $token_id,
                'label'     => $label,
                'encrypted' => $encrypted_pat,
            );
        }

        // Migrate theme settings.
        $theme_repo = get_option( 'wp_puller_repo_url', '' );
        if ( ! empty( $theme_repo ) ) {
            $theme        = wp_get_theme();
            $theme_slug   = $theme->get_stylesheet();
            $theme_id     = 'asset_' . wp_generate_password( 12, false );

            $assets[ $theme_id ] = array(
                'id'               => $theme_id,
                'type'             => 'theme',
                'label'            => $theme->get( 'Name' ) ?: $theme_slug,
                'repo_url'         => $theme_repo,
                'branch'           => get_option( 'wp_puller_branch', 'main' ),
                'path'             => get_option( 'wp_puller_theme_path', '' ),
                'slug'             => $theme_slug,
                'auto_update'      => (bool) get_option( 'wp_puller_auto_update', true ),
                'backup_count'     => absint( get_option( 'wp_puller_backup_count', 3 ) ),
                'token_id'         => $token_id,
                'latest_commit'    => get_option( 'wp_puller_latest_commit', '' ),
                'last_check'       => (int) get_option( 'wp_puller_last_check', 0 ),
                'deployed_branch'  => get_option( 'wp_puller_deployed_branch', '' ),
                'deployed_commit'  => get_option( 'wp_puller_deployed_commit', '' ),
            );
        }

        // Migrate plugin settings.
        $plugin_repo = get_option( 'wp_puller_plugin_repo_url', '' );
        if ( ! empty( $plugin_repo ) ) {
            $plugin_slug = get_option( 'wp_puller_plugin_slug', '' );
            $plugin_id   = 'asset_' . wp_generate_password( 12, false );

            $assets[ $plugin_id ] = array(
                'id'               => $plugin_id,
                'type'             => 'plugin',
                'label'            => $plugin_slug ?: 'Migrated Plugin',
                'repo_url'         => $plugin_repo,
                'branch'           => get_option( 'wp_puller_plugin_branch', 'main' ),
                'path'             => get_option( 'wp_puller_plugin_path', '' ),
                'slug'             => $plugin_slug,
                'auto_update'      => (bool) get_option( 'wp_puller_plugin_auto_update', true ),
                'backup_count'     => absint( get_option( 'wp_puller_backup_count', 3 ) ),
                'token_id'         => $token_id,
                'latest_commit'    => get_option( 'wp_puller_plugin_latest_commit', '' ),
                'last_check'       => (int) get_option( 'wp_puller_plugin_last_check', 0 ),
                'deployed_branch'  => get_option( 'wp_puller_plugin_deployed_branch', '' ),
                'deployed_commit'  => get_option( 'wp_puller_plugin_deployed_commit', '' ),
            );
        }

        // Save new format.
        update_option( 'wp_puller_assets', $assets );
        update_option( 'wp_puller_tokens', $tokens );
        update_option( 'wp_puller_db_version', '2.0.0' );

        // Delete legacy options.
        $legacy_options = array(
            'wp_puller_repo_url',
            'wp_puller_branch',
            'wp_puller_theme_path',
            'wp_puller_pat',
            'wp_puller_auto_update',
            'wp_puller_latest_commit',
            'wp_puller_last_check',
            'wp_puller_deployed_branch',
            'wp_puller_deployed_commit',
            'wp_puller_backup_count',
            'wp_puller_asset_type',
            'wp_puller_plugin_repo_url',
            'wp_puller_plugin_branch',
            'wp_puller_plugin_path',
            'wp_puller_plugin_slug',
            'wp_puller_plugin_auto_update',
            'wp_puller_plugin_latest_commit',
            'wp_puller_plugin_last_check',
            'wp_puller_plugin_deployed_branch',
            'wp_puller_plugin_deployed_commit',
        );

        foreach ( $legacy_options as $option ) {
            delete_option( $option );
        }
    }

    /**
     * Get the plugin URL.
     *
     * @return string
     */
    public function plugin_url() {
        return WP_PULLER_PLUGIN_URL;
    }

    /**
     * Get the plugin path.
     *
     * @return string
     */
    public function plugin_path() {
        return WP_PULLER_PLUGIN_DIR;
    }

    /**
     * Encrypt a value using WordPress salts.
     *
     * @param string $value Value to encrypt.
     * @return string
     */
    public static function encrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $key    = self::get_encryption_key();
        $iv     = openssl_random_pseudo_bytes( 16 );
        $cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $cipher ) {
            return '';
        }

        return base64_encode( $iv . $cipher );
    }

    /**
     * Decrypt a value using WordPress salts.
     *
     * @param string $value Value to decrypt.
     * @return string
     */
    public static function decrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }

        $key  = self::get_encryption_key();
        $data = base64_decode( $value );

        if ( false === $data || strlen( $data ) < 17 ) {
            return '';
        }

        $iv     = substr( $data, 0, 16 );
        $cipher = substr( $data, 16 );

        $decrypted = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        return false === $decrypted ? '' : $decrypted;
    }

    /**
     * Get encryption key from WordPress salts.
     *
     * @return string
     */
    private static function get_encryption_key() {
        $salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wp-puller-default-key';
        return hash( 'sha256', $salt, true );
    }
}
