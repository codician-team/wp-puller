<?php
/**
 * Webhook Handler class for WP Puller.
 *
 * @package WP_Puller
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP_Puller_Webhook_Handler Class.
 */
class WP_Puller_Webhook_Handler {

    /**
     * REST API namespace.
     *
     * @var string
     */
    const REST_NAMESPACE = 'wp-puller/v1';

    /**
     * REST API route.
     *
     * @var string
     */
    const REST_ROUTE = '/webhook';

    /**
     * Theme updater instance.
     *
     * @var WP_Puller_Theme_Updater
     */
    private $updater;

    /**
     * Plugin updater instance.
     *
     * @var WP_Puller_Plugin_Updater
     */
    private $plugin_updater;

    /**
     * Logger instance.
     *
     * @var WP_Puller_Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param WP_Puller_Theme_Updater  $updater        Theme updater instance.
     * @param WP_Puller_Plugin_Updater $plugin_updater Plugin updater instance.
     * @param WP_Puller_Logger         $logger         Logger instance.
     */
    public function __construct( $updater, $plugin_updater, $logger ) {
        $this->updater        = $updater;
        $this->plugin_updater = $plugin_updater;
        $this->logger         = $logger;
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            self::REST_ROUTE,
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_webhook' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Get the webhook URL.
     *
     * @return string
     */
    public static function get_webhook_url() {
        return rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
    }

    /**
     * Handle incoming webhook request.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_webhook( $request ) {
        $signature = $request->get_header( 'X-Hub-Signature-256' );
        $event     = $request->get_header( 'X-GitHub-Event' );
        $delivery  = $request->get_header( 'X-GitHub-Delivery' );

        $this->logger->log(
            sprintf(
                /* translators: %1$s: event type, %2$s: delivery ID */
                __( 'Webhook received: %1$s (delivery: %2$s)', 'wp-puller' ),
                $event ?: 'unknown',
                $delivery ?: 'unknown'
            ),
            WP_Puller_Logger::STATUS_INFO,
            WP_Puller_Logger::SOURCE_WEBHOOK
        );

        if ( 'ping' === $event ) {
            return new WP_REST_Response(
                array(
                    'success' => true,
                    'message' => 'Pong! Webhook is configured correctly.',
                ),
                200
            );
        }

        if ( empty( $signature ) ) {
            $this->logger->log(
                __( 'Webhook rejected: missing signature', 'wp-puller' ),
                WP_Puller_Logger::STATUS_ERROR,
                WP_Puller_Logger::SOURCE_WEBHOOK
            );

            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Missing signature header.',
                ),
                401
            );
        }

        $body = $request->get_body();

        if ( ! $this->verify_signature( $body, $signature ) ) {
            $this->logger->log(
                __( 'Webhook rejected: invalid signature', 'wp-puller' ),
                WP_Puller_Logger::STATUS_ERROR,
                WP_Puller_Logger::SOURCE_WEBHOOK
            );

            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Invalid signature.',
                ),
                401
            );
        }

        if ( 'push' !== $event ) {
            return new WP_REST_Response(
                array(
                    'success' => true,
                    'message' => 'Event type not handled.',
                ),
                200
            );
        }

        $payload = json_decode( $body, true );

        if ( ! $payload ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Invalid JSON payload.',
                ),
                400
            );
        }

        return $this->handle_push_event( $payload );
    }

    /**
     * Handle push event.
     *
     * Checks the pushed repo/branch against both theme and plugin configurations,
     * updating whichever matches.
     *
     * @param array $payload Push event payload.
     * @return WP_REST_Response
     */
    private function handle_push_event( $payload ) {
        $ref           = isset( $payload['ref'] ) ? $payload['ref'] : '';
        $pushed_branch = str_replace( 'refs/heads/', '', $ref );
        $repo_url      = isset( $payload['repository']['html_url'] ) ? $payload['repository']['html_url'] : '';

        $commit_sha = isset( $payload['after'] ) ? $payload['after'] : '';
        $commit_msg = '';
        if ( isset( $payload['head_commit']['message'] ) ) {
            $commit_msg = $payload['head_commit']['message'];
        }

        $github_api = new WP_Puller_GitHub_API();
        $pushed_parsed = $github_api->parse_repo_url( $repo_url );

        $updated = array();

        // Check theme configuration
        $theme_repo_url = get_option( 'wp_puller_repo_url', '' );
        $theme_branch   = get_option( 'wp_puller_branch', 'main' );
        $theme_auto     = get_option( 'wp_puller_auto_update', true );
        $theme_parsed   = $github_api->parse_repo_url( $theme_repo_url );

        if ( $pushed_parsed && $theme_parsed
            && strtolower( $pushed_parsed['owner'] ) === strtolower( $theme_parsed['owner'] )
            && strtolower( $pushed_parsed['repo'] ) === strtolower( $theme_parsed['repo'] )
            && $pushed_branch === $theme_branch
        ) {
            if ( $theme_auto ) {
                $this->logger->log(
                    sprintf(
                        /* translators: %1$s: short commit SHA, %2$s: commit message excerpt */
                        __( 'Processing theme push: %1$s - %2$s', 'wp-puller' ),
                        substr( $commit_sha, 0, 7 ),
                        substr( $commit_msg, 0, 50 )
                    ),
                    WP_Puller_Logger::STATUS_INFO,
                    WP_Puller_Logger::SOURCE_WEBHOOK
                );

                $result = $this->updater->update( WP_Puller_Logger::SOURCE_WEBHOOK );
                if ( is_wp_error( $result ) ) {
                    $updated[] = 'theme: ' . $result->get_error_message();
                } else {
                    $updated[] = 'theme';
                }
            } else {
                $this->logger->log(
                    __( 'Theme push received but auto-update is disabled', 'wp-puller' ),
                    WP_Puller_Logger::STATUS_INFO,
                    WP_Puller_Logger::SOURCE_WEBHOOK
                );
            }
        }

        // Check plugin configuration
        $plugin_repo_url = get_option( 'wp_puller_plugin_repo_url', '' );
        $plugin_branch   = get_option( 'wp_puller_plugin_branch', 'main' );
        $plugin_auto     = get_option( 'wp_puller_plugin_auto_update', true );
        $plugin_parsed   = $github_api->parse_repo_url( $plugin_repo_url );

        if ( $pushed_parsed && $plugin_parsed
            && strtolower( $pushed_parsed['owner'] ) === strtolower( $plugin_parsed['owner'] )
            && strtolower( $pushed_parsed['repo'] ) === strtolower( $plugin_parsed['repo'] )
            && $pushed_branch === $plugin_branch
        ) {
            if ( $plugin_auto ) {
                $this->logger->log(
                    sprintf(
                        /* translators: %1$s: short commit SHA, %2$s: commit message excerpt */
                        __( 'Processing plugin push: %1$s - %2$s', 'wp-puller' ),
                        substr( $commit_sha, 0, 7 ),
                        substr( $commit_msg, 0, 50 )
                    ),
                    WP_Puller_Logger::STATUS_INFO,
                    WP_Puller_Logger::SOURCE_WEBHOOK
                );

                $result = $this->plugin_updater->update( WP_Puller_Logger::SOURCE_WEBHOOK );
                if ( is_wp_error( $result ) ) {
                    $updated[] = 'plugin: ' . $result->get_error_message();
                } else {
                    $updated[] = 'plugin';
                }
            } else {
                $this->logger->log(
                    __( 'Plugin push received but auto-update is disabled', 'wp-puller' ),
                    WP_Puller_Logger::STATUS_INFO,
                    WP_Puller_Logger::SOURCE_WEBHOOK
                );
            }
        }

        if ( empty( $updated ) ) {
            $this->logger->log(
                sprintf(
                    /* translators: %s: branch name */
                    __( 'Push to %s did not match any configured asset', 'wp-puller' ),
                    $pushed_branch
                ),
                WP_Puller_Logger::STATUS_INFO,
                WP_Puller_Logger::SOURCE_WEBHOOK
            );

            return new WP_REST_Response(
                array(
                    'success' => true,
                    'message' => 'Push did not match any configured asset.',
                ),
                200
            );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'Updated: ' . implode( ', ', $updated ),
            ),
            200
        );
    }

    /**
     * Verify GitHub webhook signature.
     *
     * @param string $payload   Request body.
     * @param string $signature Signature header value.
     * @return bool
     */
    private function verify_signature( $payload, $signature ) {
        $secret = get_option( 'wp_puller_webhook_secret', '' );

        if ( empty( $secret ) ) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac( 'sha256', $payload, $secret );

        return hash_equals( $expected, $signature );
    }

    /**
     * Generate a new webhook secret.
     *
     * @return string
     */
    public static function generate_secret() {
        return wp_generate_password( 32, false );
    }

    /**
     * Get webhook configuration instructions.
     *
     * @return array
     */
    public static function get_setup_instructions() {
        $webhook_url    = self::get_webhook_url();
        $webhook_secret = get_option( 'wp_puller_webhook_secret', '' );

        return array(
            'url'          => $webhook_url,
            'secret'       => $webhook_secret,
            'content_type' => 'application/json',
            'events'       => array( 'push' ),
            'steps'        => array(
                __( 'Go to your GitHub repository Settings > Webhooks', 'wp-puller' ),
                __( 'Click "Add webhook"', 'wp-puller' ),
                sprintf(
                    /* translators: %s: webhook URL */
                    __( 'Set Payload URL to: %s', 'wp-puller' ),
                    $webhook_url
                ),
                __( 'Set Content type to: application/json', 'wp-puller' ),
                sprintf(
                    /* translators: %s: webhook secret */
                    __( 'Set Secret to: %s', 'wp-puller' ),
                    $webhook_secret
                ),
                __( 'Select "Just the push event"', 'wp-puller' ),
                __( 'Check "Active" and click "Add webhook"', 'wp-puller' ),
            ),
        );
    }
}
