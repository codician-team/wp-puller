<?php
/**
 * GitHub API class for WP Puller.
 *
 * @package WP_Puller
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP_Puller_GitHub_API Class.
 */
class WP_Puller_GitHub_API {

    /**
     * GitHub API base URL.
     *
     * @var string
     */
    const API_BASE = 'https://api.github.com';

    /**
     * GitHub raw content base URL.
     *
     * @var string
     */
    const RAW_BASE = 'https://raw.githubusercontent.com';

    /**
     * Cache transient prefix.
     *
     * @var string
     */
    const CACHE_PREFIX = 'wp_puller_cache_';

    /**
     * Cache duration in seconds (5 minutes).
     *
     * @var int
     */
    const CACHE_DURATION = 300;

    /**
     * Parse a GitHub repository URL.
     *
     * Supports formats:
     * - https://github.com/owner/repo
     * - https://github.com/owner/repo.git
     * - git@github.com:owner/repo.git
     * - owner/repo
     *
     * @param string $url Repository URL or owner/repo.
     * @return array|false Array with 'owner' and 'repo' keys, or false on failure.
     */
    public function parse_repo_url( $url ) {
        $url = trim( $url );

        if ( empty( $url ) ) {
            return false;
        }

        // Match owner/repo format
        if ( preg_match( '/^([a-zA-Z0-9_-]+)\/([a-zA-Z0-9_.-]+)$/', $url, $matches ) ) {
            return array(
                'owner' => $matches[1],
                'repo'  => $this->strip_git_suffix( $matches[2] ),
            );
        }

        // Match github.com URLs
        if ( preg_match( '/github\.com[\/:]([a-zA-Z0-9_-]+)\/([a-zA-Z0-9_.-]+)/', $url, $matches ) ) {
            return array(
                'owner' => $matches[1],
                'repo'  => $this->strip_git_suffix( $matches[2] ),
            );
        }

        return false;
    }

    /**
     * Remove .git suffix from repo name if present.
     *
     * @param string $repo Repository name.
     * @return string
     */
    private function strip_git_suffix( $repo ) {
        if ( substr( $repo, -4 ) === '.git' ) {
            return substr( $repo, 0, -4 );
        }
        return $repo;
    }

    /**
     * Get repository information.
     *
     * @param string $owner Repository owner.
     * @param string $repo  Repository name.
     * @return array|WP_Error
     */
    public function get_repo_info( $owner, $repo ) {
        $cache_key = self::CACHE_PREFIX . 'repo_' . md5( $owner . $repo );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $response = $this->api_request( "/repos/{$owner}/{$repo}" );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        set_transient( $cache_key, $response, self::CACHE_DURATION );

        return $response;
    }

    /**
     * Get the latest commit on a branch.
     *
     * @param string $owner  Repository owner.
     * @param string $repo   Repository name.
     * @param string $branch Branch name.
     * @return array|WP_Error
     */
    public function get_latest_commit( $owner, $repo, $branch = 'main' ) {
        $cache_key = self::CACHE_PREFIX . 'commit_' . md5( $owner . $repo . $branch );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $response = $this->api_request( "/repos/{$owner}/{$repo}/commits/{$branch}" );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $commit_data = array(
            'sha'       => $response['sha'],
            'message'   => isset( $response['commit']['message'] ) ? $response['commit']['message'] : '',
            'author'    => isset( $response['commit']['author']['name'] ) ? $response['commit']['author']['name'] : '',
            'date'      => isset( $response['commit']['author']['date'] ) ? $response['commit']['author']['date'] : '',
            'short_sha' => substr( $response['sha'], 0, 7 ),
        );

        set_transient( $cache_key, $commit_data, self::CACHE_DURATION );

        return $commit_data;
    }

    /**
     * Get list of branches.
     *
     * @param string $owner Repository owner.
     * @param string $repo  Repository name.
     * @return array|WP_Error
     */
    public function get_branches( $owner, $repo ) {
        $cache_key = self::CACHE_PREFIX . 'branches_' . md5( $owner . $repo );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $response = $this->api_request( "/repos/{$owner}/{$repo}/branches" );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $branches = array();
        foreach ( $response as $branch ) {
            $branches[] = $branch['name'];
        }

        set_transient( $cache_key, $branches, self::CACHE_DURATION );

        return $branches;
    }

    /**
     * Download repository archive as ZIP.
     *
     * @param string $owner  Repository owner.
     * @param string $repo   Repository name.
     * @param string $branch Branch name.
     * @return string|WP_Error Path to downloaded ZIP file, or error.
     */
    public function download_archive( $owner, $repo, $branch = 'main' ) {
        // Use GitHub API endpoint for downloading archives (works better with auth)
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/zipball/%s',
            rawurlencode( $owner ),
            rawurlencode( $repo ),
            rawurlencode( $branch )
        );

        $args = array(
            'timeout'   => 120,
            'sslverify' => true,
            'headers'   => array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'WP-Puller/' . WP_PULLER_VERSION,
            ),
        );

        $auth_header = $this->get_auth_header();
        if ( ! empty( $auth_header ) ) {
            $args['headers']['Authorization'] = $auth_header;
        }

        $tmp_file = wp_tempnam( 'wp-puller-' );
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            @unlink( $tmp_file );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( 200 !== $status_code ) {
            @unlink( $tmp_file );

            if ( 404 === $status_code ) {
                return new WP_Error(
                    'repo_not_found',
                    __( 'Repository or branch not found. Check URL and branch name.', 'wp-puller' )
                );
            }

            if ( 401 === $status_code || 403 === $status_code ) {
                return new WP_Error(
                    'auth_failed',
                    __( 'Authentication failed. Check your Personal Access Token.', 'wp-puller' )
                );
            }

            return new WP_Error(
                'download_failed',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __( 'Failed to download repository. HTTP status: %d', 'wp-puller' ),
                    $status_code
                )
            );
        }

        $body = wp_remote_retrieve_body( $response );

        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! $wp_filesystem->put_contents( $tmp_file, $body ) ) {
            @unlink( $tmp_file );
            return new WP_Error(
                'write_failed',
                __( 'Failed to save downloaded file.', 'wp-puller' )
            );
        }

        return $tmp_file;
    }

    /**
     * Test connection to repository.
     *
     * @param string $repo_url Repository URL.
     * @return array|WP_Error Repository info on success, error on failure.
     */
    public function test_connection( $repo_url ) {
        $parsed = $this->parse_repo_url( $repo_url );

        if ( ! $parsed ) {
            return new WP_Error(
                'invalid_url',
                __( 'Invalid GitHub repository URL.', 'wp-puller' )
            );
        }

        return $this->get_repo_info( $parsed['owner'], $parsed['repo'] );
    }

    /**
     * Make an API request to GitHub.
     *
     * @param string $endpoint API endpoint.
     * @param array  $args     Request arguments.
     * @return array|WP_Error
     */
    private function api_request( $endpoint, $args = array() ) {
        $url = self::API_BASE . $endpoint;

        $default_args = array(
            'timeout'   => 30,
            'sslverify' => true,
            'headers'   => array(
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'WP-Puller/' . WP_PULLER_VERSION,
                'X-GitHub-Api-Version' => '2022-11-28',
            ),
        );

        $auth_header = $this->get_auth_header();
        if ( ! empty( $auth_header ) ) {
            $default_args['headers']['Authorization'] = $auth_header;
        }

        $args     = wp_parse_args( $args, $default_args );
        $response = wp_safe_remote_get( $url, $args );

        // Debug logging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WP Puller API Request: ' . $url );
            error_log( 'WP Puller Auth Header: ' . ( ! empty( $auth_header ) ? substr( $auth_header, 0, 20 ) . '...' : 'none' ) );
            error_log( 'WP Puller Response Code: ' . wp_remote_retrieve_response_code( $response ) );
        }

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        if ( 200 !== $status_code && 201 !== $status_code ) {
            $message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error', 'wp-puller' );
            $doc_url = isset( $data['documentation_url'] ) ? $data['documentation_url'] : '';

            if ( 401 === $status_code ) {
                return new WP_Error(
                    'auth_error',
                    __( 'GitHub authentication failed (401). Your token may be invalid or expired.', 'wp-puller' )
                );
            }

            if ( 403 === $status_code ) {
                if ( strpos( $message, 'rate limit' ) !== false ) {
                    return new WP_Error(
                        'rate_limited',
                        __( 'GitHub API rate limit exceeded. Try again later or add a Personal Access Token.', 'wp-puller' )
                    );
                }
                return new WP_Error(
                    'forbidden',
                    sprintf(
                        /* translators: %s: GitHub error message */
                        __( 'Access forbidden (403): %s', 'wp-puller' ),
                        $message
                    )
                );
            }

            if ( 404 === $status_code ) {
                $pat = $this->get_pat();
                $has_pat = ! empty( $pat );
                $auth_type = '';

                if ( $has_pat ) {
                    if ( strpos( $pat, 'github_pat_' ) === 0 ) {
                        $auth_type = 'fine-grained';
                    } elseif ( strpos( $pat, 'ghp_' ) === 0 ) {
                        $auth_type = 'classic';
                    } else {
                        $auth_type = 'unknown-format';
                    }
                }

                if ( ! $has_pat ) {
                    return new WP_Error(
                        'not_found',
                        __( 'Repository not found (404). For private repos, add a Personal Access Token.', 'wp-puller' )
                    );
                }

                return new WP_Error(
                    'not_found',
                    sprintf(
                        /* translators: %1$s: auth type, %2$s: endpoint */
                        __( 'Repository not found (404). Auth: %1$s. Endpoint: %2$s. Ensure token has Contents + Metadata read access for this specific repo.', 'wp-puller' ),
                        $auth_type,
                        $endpoint
                    )
                );
            }

            return new WP_Error( 'api_error', sprintf( 'GitHub API error (%d): %s', $status_code, $message ) );
        }

        return $data;
    }

    /**
     * Get the stored Personal Access Token.
     *
     * @return string
     */
    private function get_pat() {
        $encrypted = get_option( 'wp_puller_pat', '' );

        if ( empty( $encrypted ) ) {
            return '';
        }

        return WP_Puller::decrypt( $encrypted );
    }

    /**
     * Get the authorization header value for the PAT.
     *
     * Using Bearer auth for all token types - GitHub accepts it for both
     * fine-grained (github_pat_) and classic (ghp_) tokens.
     *
     * @return string
     */
    private function get_auth_header() {
        $pat = $this->get_pat();

        if ( empty( $pat ) ) {
            return '';
        }

        // Use Bearer for all token types - GitHub API accepts it universally
        return 'Bearer ' . $pat;
    }

    /**
     * Clear all caches.
     */
    public function clear_cache() {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . self::CACHE_PREFIX . '%'
            )
        );
    }
}
