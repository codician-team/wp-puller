<?php
/**
 * Admin page template.
 *
 * @package WP_Puller
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$status       = $data['status'];
$theme_info   = $data['theme_info'];
$webhook_info = $data['webhook_info'];
$backups      = $data['backups'];
$logs         = $data['logs'];
$backup_class = $data['backup_class'];
$masked_pat   = WP_Puller_Admin::get_masked_pat();
$pat_status   = WP_Puller_Admin::get_pat_status();
?>
<div class="wrap wp-puller-wrap">
    <h1 class="wp-puller-title">
        <span class="dashicons dashicons-update"></span>
        <?php esc_html_e( 'WP Puller', 'wp-puller' ); ?>
        <span class="wp-puller-version">v<?php echo esc_html( WP_PULLER_VERSION ); ?></span>
    </h1>

    <div class="wp-puller-notice" id="wp-puller-notice" style="display: none;"></div>

    <div class="wp-puller-grid">
        <!-- Status Card -->
        <div class="wp-puller-card wp-puller-card-status">
            <div class="wp-puller-card-header">
                <h2><?php esc_html_e( 'Status', 'wp-puller' ); ?></h2>
                <?php if ( $status['is_configured'] ) : ?>
                    <span class="wp-puller-badge wp-puller-badge-success"><?php esc_html_e( 'Connected', 'wp-puller' ); ?></span>
                <?php else : ?>
                    <span class="wp-puller-badge wp-puller-badge-warning"><?php esc_html_e( 'Not Configured', 'wp-puller' ); ?></span>
                <?php endif; ?>
            </div>
            <div class="wp-puller-card-body">
                <div class="wp-puller-status-grid">
                    <div class="wp-puller-status-item">
                        <span class="wp-puller-status-label"><?php esc_html_e( 'Active Theme', 'wp-puller' ); ?></span>
                        <span class="wp-puller-status-value"><?php echo esc_html( $theme_info['name'] ); ?></span>
                    </div>
                    <div class="wp-puller-status-item">
                        <span class="wp-puller-status-label"><?php esc_html_e( 'Theme Version', 'wp-puller' ); ?></span>
                        <span class="wp-puller-status-value"><?php echo esc_html( $theme_info['version'] ?: '-' ); ?></span>
                    </div>
                    <div class="wp-puller-status-item">
                        <span class="wp-puller-status-label"><?php esc_html_e( 'Current Commit', 'wp-puller' ); ?></span>
                        <span class="wp-puller-status-value wp-puller-mono" id="current-commit">
                            <?php echo $status['short_commit'] ? esc_html( $status['short_commit'] ) : '-'; ?>
                        </span>
                    </div>
                    <div class="wp-puller-status-item">
                        <span class="wp-puller-status-label"><?php esc_html_e( 'Last Check', 'wp-puller' ); ?></span>
                        <span class="wp-puller-status-value" id="last-check">
                            <?php
                            if ( $status['last_check'] ) {
                                echo esc_html( human_time_diff( $status['last_check'], time() ) . ' ' . __( 'ago', 'wp-puller' ) );
                            } else {
                                echo '-';
                            }
                            ?>
                        </span>
                    </div>
                </div>

                <div class="wp-puller-actions">
                    <button type="button" class="button" id="wp-puller-check-updates" <?php disabled( ! $status['is_configured'] ); ?>>
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e( 'Check for Updates', 'wp-puller' ); ?>
                    </button>
                    <button type="button" class="button button-primary" id="wp-puller-update-now" <?php disabled( ! $status['is_configured'] ); ?>>
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e( 'Update Now', 'wp-puller' ); ?>
                    </button>
                </div>

                <div class="wp-puller-update-result" id="wp-puller-update-result" style="display: none;"></div>
            </div>
        </div>

        <!-- Settings Card -->
        <div class="wp-puller-card wp-puller-card-settings">
            <div class="wp-puller-card-header">
                <h2><?php esc_html_e( 'GitHub Repository', 'wp-puller' ); ?></h2>
            </div>
            <div class="wp-puller-card-body">
                <form id="wp-puller-settings-form">
                    <div class="wp-puller-field">
                        <label for="wp-puller-repo-url"><?php esc_html_e( 'Repository URL', 'wp-puller' ); ?></label>
                        <div class="wp-puller-input-group">
                            <input type="url"
                                   id="wp-puller-repo-url"
                                   name="repo_url"
                                   value="<?php echo esc_attr( $status['repo_url'] ); ?>"
                                   placeholder="https://github.com/username/theme-repo"
                                   class="regular-text">
                            <button type="button" class="button" id="wp-puller-test-connection">
                                <?php esc_html_e( 'Test', 'wp-puller' ); ?>
                            </button>
                        </div>
                        <p class="description"><?php esc_html_e( 'Enter the full GitHub repository URL containing your theme.', 'wp-puller' ); ?></p>
                    </div>

                    <div class="wp-puller-field">
                        <label for="wp-puller-branch"><?php esc_html_e( 'Branch', 'wp-puller' ); ?></label>
                        <input type="text"
                               id="wp-puller-branch"
                               name="branch"
                               value="<?php echo esc_attr( $status['branch'] ); ?>"
                               placeholder="main"
                               class="regular-text">
                        <p class="description"><?php esc_html_e( 'Branch to track for updates (e.g., main, master, production).', 'wp-puller' ); ?></p>
                    </div>

                    <div class="wp-puller-field">
                        <label for="wp-puller-theme-path"><?php esc_html_e( 'Theme Path', 'wp-puller' ); ?></label>
                        <input type="text"
                               id="wp-puller-theme-path"
                               name="theme_path"
                               value="<?php echo esc_attr( $status['theme_path'] ); ?>"
                               placeholder="<?php esc_attr_e( 'Leave empty if theme is at repo root', 'wp-puller' ); ?>"
                               class="regular-text">
                        <p class="description"><?php esc_html_e( 'Subdirectory containing the theme (e.g., "my-theme" or "themes/starter"). Leave empty if theme files are at repository root.', 'wp-puller' ); ?></p>
                    </div>

                    <div class="wp-puller-field">
                        <label for="wp-puller-pat"><?php esc_html_e( 'Personal Access Token', 'wp-puller' ); ?></label>
                        <input type="password"
                               id="wp-puller-pat"
                               name="pat"
                               value="<?php echo esc_attr( $masked_pat ); ?>"
                               placeholder="<?php esc_attr_e( 'ghp_xxxxx or github_pat_xxxxx', 'wp-puller' ); ?>"
                               class="regular-text"
                               autocomplete="off">
                        <p class="description">
                            <?php esc_html_e( 'Required for private repositories.', 'wp-puller' ); ?>
                            <a href="https://github.com/settings/tokens" target="_blank" rel="noopener">
                                <?php esc_html_e( 'Create a token', 'wp-puller' ); ?>
                            </a>
                        </p>
                        <?php if ( $pat_status['stored'] ) : ?>
                            <p class="description" style="margin-top: 4px;">
                                <strong><?php esc_html_e( 'Token Status:', 'wp-puller' ); ?></strong>
                                <?php if ( $pat_status['decrypts'] ) : ?>
                                    <span style="color: #00a32a;"><?php echo esc_html( $pat_status['message'] ); ?></span>
                                <?php else : ?>
                                    <span style="color: #d63638;"><?php echo esc_html( $pat_status['message'] ); ?></span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="wp-puller-field wp-puller-field-inline">
                        <label>
                            <input type="checkbox"
                                   id="wp-puller-auto-update"
                                   name="auto_update"
                                   value="1"
                                   <?php checked( $status['auto_update'] ); ?>>
                            <?php esc_html_e( 'Auto-update on webhook', 'wp-puller' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Automatically update when GitHub sends a push notification.', 'wp-puller' ); ?></p>
                    </div>

                    <div class="wp-puller-field">
                        <label for="wp-puller-backup-count"><?php esc_html_e( 'Backups to Keep', 'wp-puller' ); ?></label>
                        <select id="wp-puller-backup-count" name="backup_count">
                            <?php for ( $i = 1; $i <= 10; $i++ ) : ?>
                                <option value="<?php echo esc_attr( $i ); ?>" <?php selected( get_option( 'wp_puller_backup_count', 3 ), $i ); ?>>
                                    <?php echo esc_html( $i ); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="wp-puller-field-actions">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e( 'Save Settings', 'wp-puller' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Webhook Card -->
        <div class="wp-puller-card wp-puller-card-webhook">
            <div class="wp-puller-card-header">
                <h2><?php esc_html_e( 'Webhook Setup', 'wp-puller' ); ?></h2>
            </div>
            <div class="wp-puller-card-body">
                <p class="wp-puller-webhook-intro">
                    <?php esc_html_e( 'Configure a GitHub webhook to receive instant updates when you push to your repository.', 'wp-puller' ); ?>
                </p>

                <div class="wp-puller-webhook-field">
                    <label><?php esc_html_e( 'Payload URL', 'wp-puller' ); ?></label>
                    <div class="wp-puller-copy-field">
                        <input type="text" readonly value="<?php echo esc_attr( $webhook_info['url'] ); ?>" id="webhook-url">
                        <button type="button" class="button wp-puller-copy-btn" data-copy="webhook-url">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                    </div>
                </div>

                <div class="wp-puller-webhook-field">
                    <label><?php esc_html_e( 'Secret', 'wp-puller' ); ?></label>
                    <div class="wp-puller-copy-field">
                        <input type="text" readonly value="<?php echo esc_attr( $webhook_info['secret'] ); ?>" id="webhook-secret">
                        <button type="button" class="button wp-puller-copy-btn" data-copy="webhook-secret">
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

        <!-- Backups Card -->
        <div class="wp-puller-card wp-puller-card-backups">
            <div class="wp-puller-card-header">
                <h2><?php esc_html_e( 'Backups', 'wp-puller' ); ?></h2>
                <span class="wp-puller-badge"><?php echo count( $backups ); ?></span>
            </div>
            <div class="wp-puller-card-body">
                <?php if ( empty( $backups ) ) : ?>
                    <p class="wp-puller-empty"><?php esc_html_e( 'No backups yet. A backup is created automatically before each update.', 'wp-puller' ); ?></p>
                <?php else : ?>
                    <ul class="wp-puller-backup-list" id="wp-puller-backup-list">
                        <?php foreach ( $backups as $backup ) : ?>
                            <li class="wp-puller-backup-item" data-name="<?php echo esc_attr( $backup['name'] ); ?>">
                                <div class="wp-puller-backup-info">
                                    <span class="wp-puller-backup-name"><?php echo esc_html( $backup['name'] ); ?></span>
                                    <span class="wp-puller-backup-meta">
                                        <?php echo esc_html( $backup['datetime'] ); ?> &middot;
                                        <?php echo esc_html( WP_Puller_Backup::format_size( $backup['size'] ) ); ?>
                                    </span>
                                </div>
                                <div class="wp-puller-backup-actions">
                                    <button type="button" class="button button-small wp-puller-restore-backup" data-name="<?php echo esc_attr( $backup['name'] ); ?>">
                                        <?php esc_html_e( 'Restore', 'wp-puller' ); ?>
                                    </button>
                                    <button type="button" class="button button-small wp-puller-delete-backup" data-name="<?php echo esc_attr( $backup['name'] ); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity Log Card -->
        <div class="wp-puller-card wp-puller-card-logs">
            <div class="wp-puller-card-header">
                <h2><?php esc_html_e( 'Activity Log', 'wp-puller' ); ?></h2>
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
                        <?php foreach ( $logs as $log ) : ?>
                            <li class="wp-puller-log-item wp-puller-log-<?php echo esc_attr( $log['status'] ); ?>">
                                <span class="wp-puller-log-indicator"></span>
                                <div class="wp-puller-log-content">
                                    <span class="wp-puller-log-message"><?php echo esc_html( $log['message'] ); ?></span>
                                    <span class="wp-puller-log-meta">
                                        <?php echo esc_html( human_time_diff( $log['timestamp'], time() ) ); ?> <?php esc_html_e( 'ago', 'wp-puller' ); ?>
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

    <div class="wp-puller-footer">
        <p>
            <?php
            printf(
                /* translators: %s: GitHub link */
                esc_html__( 'WP Puller is open source. %s', 'wp-puller' ),
                '<a href="https://github.com/developer/wp-puller" target="_blank" rel="noopener">' . esc_html__( 'Star on GitHub', 'wp-puller' ) . '</a>'
            );
            ?>
        </p>
    </div>
</div>
