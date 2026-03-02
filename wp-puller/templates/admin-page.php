<?php
/**
 * Admin page template.
 *
 * @package WP_Puller
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$assets       = $data['assets'];
$tokens       = $data['tokens'];
$webhook_info = $data['webhook_info'];
$logs         = $data['logs'];
$backup_class = $data['backup_class'];
?>
<div class="wrap wp-puller-wrap">
    <div class="wp-puller-header">
        <h1 class="wp-puller-title">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e( 'WP Puller', 'wp-puller' ); ?>
            <span class="wp-puller-version">v<?php echo esc_html( WP_PULLER_VERSION ); ?></span>
        </h1>
        <div class="wp-puller-header-actions">
            <button type="button" class="button button-primary" id="wp-puller-add-new">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Add New', 'wp-puller' ); ?>
            </button>
            <button type="button" class="button" id="wp-puller-check-all">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e( 'Check All for Updates', 'wp-puller' ); ?>
            </button>
            <button type="button" class="button" id="wp-puller-update-all">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e( 'Update All', 'wp-puller' ); ?>
            </button>
            <button type="button" class="button" id="wp-puller-toggle-webhook">
                <span class="dashicons dashicons-admin-links"></span>
                <?php esc_html_e( 'Webhook', 'wp-puller' ); ?>
            </button>
        </div>
    </div>

    <div class="wp-puller-notice" id="wp-puller-notice" style="display: none;"></div>

    <!-- ============ ASSET CARD GRID ============ -->
    <?php if ( empty( $assets ) ) : ?>
        <div class="wp-puller-empty-state">
            <span class="dashicons dashicons-admin-plugins"></span>
            <p><?php esc_html_e( 'No items configured yet. Click Add New to get started.', 'wp-puller' ); ?></p>
        </div>
    <?php else : ?>
        <div class="wp-puller-card-grid">
            <?php foreach ( $assets as $asset_id => $asset ) :
                $config  = $asset['config'];
                $info    = $asset['info'];
                $status  = $asset['status'];
                $backups = $asset['backups'];

                $display_name = ! empty( $info['name'] ) ? $info['name'] : $config['slug'];
                $display_version = ! empty( $info['version'] ) ? $info['version'] : '-';
                $is_configured = ! empty( $status['is_configured'] );
                $is_theme = 'theme' === $config['type'];
                $type_icon = $is_theme ? 'dashicons-admin-appearance' : 'dashicons-admin-plugins';
                $type_label = $is_theme ? __( 'Theme', 'wp-puller' ) : __( 'Plugin', 'wp-puller' );
                $deployed_branch = ! empty( $config['deployed_branch'] ) ? $config['deployed_branch'] : '';
            ?>
                <div class="wp-puller-card wp-puller-asset-card" data-asset-id="<?php echo esc_attr( $asset_id ); ?>">
                    <div class="wp-puller-card-header">
                        <span class="wp-puller-type-badge wp-puller-type-badge-<?php echo esc_attr( $config['type'] ); ?>">
                            <span class="dashicons <?php echo esc_attr( $type_icon ); ?>"></span>
                            <?php echo esc_html( $type_label ); ?>
                        </span>
                        <?php if ( $is_configured ) : ?>
                            <span class="wp-puller-badge wp-puller-badge-success wp-puller-status-badge"><?php esc_html_e( 'Connected', 'wp-puller' ); ?></span>
                        <?php else : ?>
                            <span class="wp-puller-badge wp-puller-badge-warning wp-puller-status-badge"><?php esc_html_e( 'Not Configured', 'wp-puller' ); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="wp-puller-card-body">
                        <h3 class="wp-puller-asset-name"><?php echo esc_html( $display_name ); ?></h3>
                        <span class="wp-puller-asset-version">
                            <?php
                            /* translators: %s: version string */
                            printf( esc_html__( 'v%s', 'wp-puller' ), esc_html( $display_version ) );
                            ?>
                        </span>
                        <?php if ( ! empty( $config['repo_url'] ) ) : ?>
                            <a href="<?php echo esc_url( $config['repo_url'] ); ?>" class="wp-puller-repo-link" target="_blank" rel="noopener">
                                <span class="dashicons dashicons-github"></span>
                                <?php echo esc_html( preg_replace( '#^https?://(www\.)?github\.com/#', '', $config['repo_url'] ) ); ?>
                            </a>
                        <?php endif; ?>

                        <div class="wp-puller-asset-meta">
                            <div class="wp-puller-meta-item">
                                <span class="wp-puller-meta-label"><?php esc_html_e( 'Commit', 'wp-puller' ); ?></span>
                                <span class="wp-puller-meta-value wp-puller-mono wp-puller-current-commit">
                                    <?php echo ! empty( $status['short_commit'] ) ? esc_html( $status['short_commit'] ) : '-'; ?>
                                </span>
                            </div>
                            <div class="wp-puller-meta-item">
                                <span class="wp-puller-meta-label"><?php esc_html_e( 'Last Check', 'wp-puller' ); ?></span>
                                <span class="wp-puller-meta-value wp-puller-last-check">
                                    <?php
                                    if ( ! empty( $status['last_check'] ) && $status['last_check'] > 0 ) {
                                        /* translators: %s: human-readable time difference */
                                        printf( esc_html__( '%s ago', 'wp-puller' ), esc_html( human_time_diff( $status['last_check'], time() ) ) );
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <?php if ( ! empty( $deployed_branch ) && $deployed_branch !== $config['branch'] ) : ?>
                            <div class="wp-puller-deployed-notice">
                                <span class="dashicons dashicons-info"></span>
                                <?php
                                printf(
                                    /* translators: %1$s: deployed branch, %2$s: configured branch */
                                    esc_html__( 'Deployed from "%1$s" (configured: "%2$s")', 'wp-puller' ),
                                    esc_html( $deployed_branch ),
                                    esc_html( $config['branch'] )
                                );
                                ?>
                            </div>
                        <?php endif; ?>

                        <div class="wp-puller-card-actions">
                            <button type="button" class="button wp-puller-check-updates" data-asset-id="<?php echo esc_attr( $asset_id ); ?>" <?php disabled( ! $is_configured ); ?>>
                                <span class="dashicons dashicons-search"></span>
                                <?php esc_html_e( 'Check for Updates', 'wp-puller' ); ?>
                            </button>
                            <button type="button" class="button button-primary wp-puller-update-now" data-asset-id="<?php echo esc_attr( $asset_id ); ?>" <?php disabled( ! $is_configured ); ?>>
                                <span class="dashicons dashicons-download"></span>
                                <?php esc_html_e( 'Update Now', 'wp-puller' ); ?>
                            </button>
                        </div>

                        <div class="wp-puller-update-result" style="display: none;"></div>
                    </div>

                    <div class="wp-puller-card-footer">
                        <button type="button" class="wp-puller-icon-btn wp-puller-open-panel" data-panel="settings" data-asset-id="<?php echo esc_attr( $asset_id ); ?>" title="<?php esc_attr_e( 'Settings', 'wp-puller' ); ?>">
                            <span class="dashicons dashicons-admin-generic"></span>
                        </button>
                        <button type="button" class="wp-puller-icon-btn wp-puller-open-panel" data-panel="branches" data-asset-id="<?php echo esc_attr( $asset_id ); ?>" title="<?php esc_attr_e( 'Branches', 'wp-puller' ); ?>">
                            <span class="dashicons dashicons-randomize"></span>
                        </button>
                        <button type="button" class="wp-puller-icon-btn wp-puller-open-panel" data-panel="backups" data-asset-id="<?php echo esc_attr( $asset_id ); ?>" title="<?php esc_attr_e( 'Backups', 'wp-puller' ); ?>">
                            <span class="dashicons dashicons-backup"></span>
                            <?php if ( ! empty( $backups ) ) : ?>
                                <span class="wp-puller-icon-badge"><?php echo count( $backups ); ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ============ PANELS ============ -->

    <!-- Settings Panel -->
    <div class="wp-puller-panel wp-puller-panel-settings" id="wp-puller-panel-settings" data-asset-id="" style="display: none;">
        <div class="wp-puller-card wp-puller-card-full">
            <div class="wp-puller-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e( 'Settings', 'wp-puller' ); ?>
                    <span class="wp-puller-panel-asset-label"></span>
                </h2>
                <button type="button" class="button button-small wp-puller-close-panel" title="<?php esc_attr_e( 'Close', 'wp-puller' ); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="wp-puller-card-body">
                <form class="wp-puller-settings-form">
                    <div class="wp-puller-field">
                        <label><?php esc_html_e( 'Repository URL', 'wp-puller' ); ?></label>
                        <div class="wp-puller-input-group">
                            <input type="url"
                                   name="repo_url"
                                   value=""
                                   placeholder="https://github.com/username/repo"
                                   class="regular-text">
                            <button type="button" class="button wp-puller-test-connection">
                                <?php esc_html_e( 'Test', 'wp-puller' ); ?>
                            </button>
                        </div>
                        <p class="description"><?php esc_html_e( 'Enter the full GitHub repository URL.', 'wp-puller' ); ?></p>
                    </div>

                    <div class="wp-puller-field">
                        <label><?php esc_html_e( 'Branch', 'wp-puller' ); ?></label>
                        <input type="text"
                               name="branch"
                               value=""
                               placeholder="main"
                               class="regular-text">
                        <p class="description"><?php esc_html_e( 'Branch name to track for updates (e.g. main, develop).', 'wp-puller' ); ?></p>
                    </div>

                    <div class="wp-puller-field">
                        <label><?php esc_html_e( 'Slug', 'wp-puller' ); ?></label>
                        <input type="text"
                               name="slug"
                               value=""
                               placeholder="<?php esc_attr_e( 'my-theme-or-plugin', 'wp-puller' ); ?>"
                               class="regular-text">
                        <p class="description"><?php esc_html_e( 'The directory name in wp-content/themes/ or wp-content/plugins/. This is where files will be deployed.', 'wp-puller' ); ?></p>
                    </div>

                    <div class="wp-puller-field">
                        <label><?php esc_html_e( 'Path', 'wp-puller' ); ?></label>
                        <input type="text"
                               name="path"
                               value=""
                               placeholder="<?php esc_attr_e( 'Leave empty if at repo root', 'wp-puller' ); ?>"
                               class="regular-text">
                        <p class="description"><?php esc_html_e( 'Subdirectory containing the theme or plugin within the repo. Leave empty if at repo root.', 'wp-puller' ); ?></p>
                    </div>

                    <div class="wp-puller-field">
                        <label><?php esc_html_e( 'Type', 'wp-puller' ); ?></label>
                        <select name="type">
                            <option value="theme"><?php esc_html_e( 'Theme', 'wp-puller' ); ?></option>
                            <option value="plugin"><?php esc_html_e( 'Plugin', 'wp-puller' ); ?></option>
                        </select>
                    </div>

                    <div class="wp-puller-field wp-puller-pat-section">
                        <label><?php esc_html_e( 'Personal Access Token', 'wp-puller' ); ?></label>
                        <?php if ( ! empty( $tokens ) ) : ?>
                            <div class="wp-puller-token-choice">
                                <label class="wp-puller-token-option">
                                    <input type="radio" name="token_mode" value="existing" checked>
                                    <?php esc_html_e( 'Use existing token', 'wp-puller' ); ?>
                                </label>
                                <select name="token_id" class="wp-puller-token-select">
                                    <option value=""><?php esc_html_e( '-- Select a token --', 'wp-puller' ); ?></option>
                                    <?php foreach ( $tokens as $token_id => $token ) : ?>
                                        <option value="<?php echo esc_attr( $token_id ); ?>">
                                            <?php echo esc_html( $token['label'] ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <label class="wp-puller-token-option">
                                    <input type="radio" name="token_mode" value="new">
                                    <?php esc_html_e( 'Add new token', 'wp-puller' ); ?>
                                </label>
                                <div class="wp-puller-new-token-fields" style="display: none;">
                                    <input type="text"
                                           name="token_label"
                                           placeholder="<?php esc_attr_e( 'Token label (e.g. My GitHub PAT)', 'wp-puller' ); ?>"
                                           class="regular-text">
                                    <input type="password"
                                           name="pat"
                                           placeholder="<?php esc_attr_e( 'ghp_xxxxx or github_pat_xxxxx', 'wp-puller' ); ?>"
                                           class="regular-text"
                                           autocomplete="off">
                                </div>
                            </div>
                        <?php else : ?>
                            <input type="text"
                                   name="token_label"
                                   placeholder="<?php esc_attr_e( 'Token label (e.g. My GitHub PAT)', 'wp-puller' ); ?>"
                                   class="regular-text">
                            <input type="password"
                                   name="pat"
                                   placeholder="<?php esc_attr_e( 'ghp_xxxxx or github_pat_xxxxx', 'wp-puller' ); ?>"
                                   class="regular-text"
                                   autocomplete="off">
                        <?php endif; ?>
                        <p class="description">
                            <?php esc_html_e( 'Required for private repositories.', 'wp-puller' ); ?>
                            <a href="https://github.com/settings/tokens" target="_blank" rel="noopener">
                                <?php esc_html_e( 'Create a token', 'wp-puller' ); ?>
                            </a>
                        </p>
                    </div>

                    <div class="wp-puller-field wp-puller-field-inline">
                        <label>
                            <input type="checkbox"
                                   name="auto_update"
                                   value="1">
                            <?php esc_html_e( 'Auto-update on webhook', 'wp-puller' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Automatically update when GitHub sends a push notification.', 'wp-puller' ); ?></p>
                    </div>

                    <div class="wp-puller-field">
                        <label><?php esc_html_e( 'Backups to Keep', 'wp-puller' ); ?></label>
                        <select name="backup_count">
                            <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                                <option value="<?php echo esc_attr( $i ); ?>" <?php selected( 3, $i ); ?>>
                                    <?php echo esc_html( $i ); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="wp-puller-field-actions">
                        <button type="submit" class="button button-primary wp-puller-save-settings">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e( 'Save Settings', 'wp-puller' ); ?>
                        </button>
                        <button type="button" class="button wp-puller-remove-item wp-puller-danger-btn">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e( 'Remove Item', 'wp-puller' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Branches Panel -->
    <div class="wp-puller-panel wp-puller-panel-branches" id="wp-puller-panel-branches" data-asset-id="" style="display: none;">
        <div class="wp-puller-card wp-puller-card-full">
            <div class="wp-puller-card-header">
                <h2>
                    <span class="dashicons dashicons-randomize"></span>
                    <?php esc_html_e( 'Branches', 'wp-puller' ); ?>
                    <span class="wp-puller-panel-asset-label"></span>
                </h2>
                <div class="wp-puller-card-header-actions">
                    <button type="button" class="button button-small wp-puller-refresh-branches">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Refresh', 'wp-puller' ); ?>
                    </button>
                    <button type="button" class="button button-small wp-puller-close-panel" title="<?php esc_attr_e( 'Close', 'wp-puller' ); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            </div>
            <div class="wp-puller-card-body">
                <p class="description" style="margin: 0 0 12px;">
                    <?php esc_html_e( 'Recent branches sorted by latest activity. Deploy any branch or set one as your updates branch.', 'wp-puller' ); ?>
                </p>
                <div class="wp-puller-branch-list">
                    <p class="wp-puller-empty"><?php esc_html_e( 'Click "Refresh" to load branches.', 'wp-puller' ); ?></p>
                </div>

                <div class="wp-puller-compare-panel" style="display: none;">
                    <div class="wp-puller-compare-header">
                        <h3 class="wp-puller-compare-title"></h3>
                        <button type="button" class="button button-small wp-puller-close-compare">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <div class="wp-puller-compare-content"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Backups Panel -->
    <div class="wp-puller-panel wp-puller-panel-backups" id="wp-puller-panel-backups" data-asset-id="" style="display: none;">
        <div class="wp-puller-card wp-puller-card-full">
            <div class="wp-puller-card-header">
                <h2>
                    <span class="dashicons dashicons-backup"></span>
                    <?php esc_html_e( 'Backups', 'wp-puller' ); ?>
                    <span class="wp-puller-panel-asset-label"></span>
                    <span class="wp-puller-badge wp-puller-backup-count-badge">0</span>
                </h2>
                <button type="button" class="button button-small wp-puller-close-panel" title="<?php esc_attr_e( 'Close', 'wp-puller' ); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="wp-puller-card-body">
                <div class="wp-puller-backup-list-container">
                    <?php
                    // Render backup lists per asset for JS to toggle visibility
                    foreach ( $assets as $asset_id => $asset ) :
                        $backups = $asset['backups'];
                        $config  = $asset['config'];
                    ?>
                        <div class="wp-puller-backup-list-wrap" data-asset-id="<?php echo esc_attr( $asset_id ); ?>" style="display: none;">
                            <?php if ( empty( $backups ) ) : ?>
                                <p class="wp-puller-empty"><?php esc_html_e( 'No backups yet. A backup is created automatically before each update.', 'wp-puller' ); ?></p>
                            <?php else : ?>
                                <ul class="wp-puller-backup-list">
                                    <?php foreach ( $backups as $backup ) :
                                        $backup_version = $backup_class->get_backup_version( $backup['path'], $config['type'] );
                                    ?>
                                        <li class="wp-puller-backup-item" data-name="<?php echo esc_attr( $backup['name'] ); ?>" data-asset-id="<?php echo esc_attr( $asset_id ); ?>">
                                            <div class="wp-puller-backup-info">
                                                <span class="wp-puller-backup-name"><?php echo esc_html( $backup['name'] ); ?></span>
                                                <span class="wp-puller-backup-meta">
                                                    <?php if ( $backup_version ) : ?>
                                                        v<?php echo esc_html( $backup_version ); ?> &middot;
                                                    <?php endif; ?>
                                                    <?php echo esc_html( $backup['datetime'] ); ?> &middot;
                                                    <?php echo esc_html( WP_Puller_Backup::format_size( $backup['size'] ) ); ?>
                                                </span>
                                            </div>
                                            <div class="wp-puller-backup-actions">
                                                <button type="button" class="button button-small wp-puller-restore-backup" data-name="<?php echo esc_attr( $backup['name'] ); ?>" data-asset-id="<?php echo esc_attr( $asset_id ); ?>">
                                                    <?php esc_html_e( 'Restore', 'wp-puller' ); ?>
                                                </button>
                                                <button type="button" class="button button-small wp-puller-delete-backup" data-name="<?php echo esc_attr( $backup['name'] ); ?>" data-asset-id="<?php echo esc_attr( $asset_id ); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ WEBHOOK PANEL (global) ============ -->
    <div class="wp-puller-panel wp-puller-panel-webhook" id="wp-puller-panel-webhook" style="display: none;">
        <div class="wp-puller-card wp-puller-card-full">
            <div class="wp-puller-card-header">
                <h2>
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e( 'Webhook Setup', 'wp-puller' ); ?>
                </h2>
                <button type="button" class="button button-small" id="wp-puller-close-webhook" title="<?php esc_attr_e( 'Close', 'wp-puller' ); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="wp-puller-card-body">
                <p class="description" style="margin: 0 0 12px;">
                    <?php esc_html_e( 'Configure a GitHub webhook to receive instant updates when you push. This applies to all configured repositories.', 'wp-puller' ); ?>
                </p>

                <div class="wp-puller-webhook-field">
                    <label><?php esc_html_e( 'Payload URL', 'wp-puller' ); ?></label>
                    <div class="wp-puller-copy-field">
                        <input type="text" readonly value="<?php echo esc_attr( $webhook_info['url'] ); ?>" id="webhook-url">
                        <button type="button" class="button wp-puller-copy-btn" data-copy="webhook-url" title="<?php esc_attr_e( 'Copy to clipboard', 'wp-puller' ); ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                    </div>
                </div>

                <div class="wp-puller-webhook-field">
                    <label><?php esc_html_e( 'Secret', 'wp-puller' ); ?></label>
                    <div class="wp-puller-copy-field">
                        <input type="text" readonly value="<?php echo esc_attr( $webhook_info['secret'] ); ?>" id="webhook-secret">
                        <button type="button" class="button wp-puller-copy-btn" data-copy="webhook-secret" title="<?php esc_attr_e( 'Copy to clipboard', 'wp-puller' ); ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                        <button type="button" class="button" id="wp-puller-regenerate-secret" title="<?php esc_attr_e( 'Regenerate Secret', 'wp-puller' ); ?>">
                            <span class="dashicons dashicons-update"></span>
                        </button>
                    </div>
                </div>

                <div class="wp-puller-webhook-field">
                    <label><?php esc_html_e( 'Content Type', 'wp-puller' ); ?></label>
                    <code>application/json</code>
                </div>

                <details class="wp-puller-instructions">
                    <summary><?php esc_html_e( 'Setup Instructions', 'wp-puller' ); ?></summary>
                    <ol>
                        <?php foreach ( $webhook_info['steps'] as $step ) : ?>
                            <li><?php echo esc_html( $step ); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </details>
            </div>
        </div>
    </div>

    <!-- ============ SHARED SECTIONS ============ -->
    <div class="wp-puller-shared-section">
        <!-- Activity Log Card -->
        <div class="wp-puller-card wp-puller-card-full wp-puller-card-logs">
            <div class="wp-puller-card-header">
                <h2>
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Activity Log', 'wp-puller' ); ?>
                </h2>
                <?php if ( ! empty( $logs ) ) : ?>
                    <button type="button" class="button button-small" id="wp-puller-clear-logs">
                        <?php esc_html_e( 'Clear', 'wp-puller' ); ?>
                    </button>
                <?php endif; ?>
            </div>
            <div class="wp-puller-card-body">
                <?php if ( empty( $logs ) ) : ?>
                    <p class="wp-puller-empty"><?php esc_html_e( 'No activity recorded yet.', 'wp-puller' ); ?></p>
                <?php else : ?>
                    <ul class="wp-puller-log-list" id="wp-puller-log-list">
                        <?php foreach ( $logs as $log ) :
                            $meta       = isset( $log['meta'] ) ? $log['meta'] : array();
                            $asset_name = '';
                            if ( ! empty( $meta['asset_label'] ) ) {
                                $asset_name = $meta['asset_label'];
                            } elseif ( ! empty( $meta['asset_slug'] ) ) {
                                $asset_name = $meta['asset_slug'];
                            }
                            $version = ! empty( $meta['version'] ) ? $meta['version'] : '';
                        ?>
                            <li class="wp-puller-log-item wp-puller-log-<?php echo esc_attr( $log['status'] ); ?>">
                                <span class="wp-puller-log-indicator"></span>
                                <div class="wp-puller-log-content">
                                    <span class="wp-puller-log-message"><?php echo esc_html( $log['message'] ); ?></span>
                                    <span class="wp-puller-log-meta">
                                        <?php if ( ! empty( $asset_name ) ) : ?>
                                            <strong><?php echo esc_html( $asset_name ); ?></strong>
                                            <?php if ( ! empty( $version ) ) : ?>
                                                <span class="wp-puller-log-version">v<?php echo esc_html( $version ); ?></span>
                                            <?php endif; ?>
                                            &middot;
                                        <?php endif; ?>
                                        <?php
                                        /* translators: %s: human-readable time difference */
                                        printf( esc_html__( '%s ago', 'wp-puller' ), esc_html( human_time_diff( $log['timestamp'], time() ) ) );
                                        ?>
                                        &middot; <?php echo esc_html( ucfirst( $log['source'] ) ); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============ CONFIRMATION MODAL ============ -->
    <div class="wp-puller-modal" id="wp-puller-modal" style="display: none;">
        <div class="wp-puller-modal-overlay"></div>
        <div class="wp-puller-modal-dialog">
            <div class="wp-puller-modal-header">
                <h3 class="wp-puller-modal-title"></h3>
                <button type="button" class="wp-puller-modal-close" title="<?php esc_attr_e( 'Close', 'wp-puller' ); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="wp-puller-modal-body">
                <p class="wp-puller-modal-message"></p>
            </div>
            <div class="wp-puller-modal-footer">
                <button type="button" class="button wp-puller-modal-cancel">
                    <?php esc_html_e( 'Cancel', 'wp-puller' ); ?>
                </button>
                <button type="button" class="button button-primary wp-puller-modal-confirm">
                    <?php esc_html_e( 'Confirm', 'wp-puller' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ============ FOOTER ============ -->
    <div class="wp-puller-footer">
        <p>
            <a href="https://github.com/techtherapy/wp-puller" target="_blank" rel="noopener">techtherapy/wp-puller</a>
            <?php esc_html_e( 'is a fork of', 'wp-puller' ); ?>
            <a href="https://github.com/codician-team/wp-puller" target="_blank" rel="noopener">codician-team/wp-puller</a>
        </p>
    </div>
</div>
