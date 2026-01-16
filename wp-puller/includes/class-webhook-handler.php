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
     * Logger instance.
     *
     * @var WP_Puller_Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param WP_Puller_Theme_Updater $updater Theme updater instance.
     * @param WP_Puller_Logger        $logger  Logger instance.
     */
    public function __construct( $updater, $logger ) {
        $this->updater = $updater;
        $this->logger  = $logger;
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
     * @param array $payload Push event payload.
     * @return WP_REST_Response
     */
    private function handle_push_event( $payload ) {
        $ref        = isset( $payload['ref'] ) ? $payload['ref'] : '';
        $pushed_branch = str_replace( 'refs/heads/', '', $ref );
        $configured_branch = get_option( 'wp_puller_branch', 'main' );

        if ( $pushed_branch !== $configured_branch ) {
            $this->logger->log(
                sprintf(
                    /* translators: %1$s: pushed branch, %2$s: configured branch */
                    __( 'Push to branch %1$s ignored (configured: %2$s)', 'wp-puller' ),
                    $pushed_branch,
                    $configured_branch
                ),
                WP_Puller_Logger::STATUS_INFO,
                WP_Puller_Logger::SOURCE_WEBHOOK
            );

            return new WP_REST_Response(
                array(
                    'success' => true,
                    'message' => 'Push to non-tracked branch ignored.',
                ),
                200
            );
        }

        $auto_update = get_option( 'wp_puller_auto_update', true );

        if ( ! $auto_update ) {
            $this->logger->log(
                __( 'Push received but auto-update is disabled', 'wp-puller' ),
                WP_Puller_Logger::STATUS_INFO,
                WP_Puller_Logger::SOURCE_WEBHOOK
            );

            return new WP_REST_Response(
                array(
                    'success' => true,
                    'message' => 'Auto-update is disabled. Push notification logged.',
                ),
                200
            );
        }

        $commit_sha = isset( $payload['after'] ) ? $payload['after'] : '';
        $commit_msg = '';

        if ( isset( $payload['head_commit']['message'] ) ) {
            $commit_msg = $payload['head_commit']['message'];
        }

        $this->logger->log(
            sprintf(
                /* translators: %1$s: short commit SHA, %2$s: commit message excerpt */
                __( 'Processing push: %1$s - %2$s', 'wp-puller' ),
                substr( $commit_sha, 0, 7 ),
                substr( $commit_msg, 0, 50 )
            ),
            WP_Puller_Logger::STATUS_INFO,
            WP_Puller_Logger::SOURCE_WEBHOOK
        );

        $result = $this->updater->update( WP_Puller_Logger::SOURCE_WEBHOOK );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => $result->get_error_message(),
                ),
                500
            );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'Theme updated successfully.',
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
