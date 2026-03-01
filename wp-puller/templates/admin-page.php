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

$theme_status   = $data['theme_status'];
$plugin_status  = $data['plugin_status'];
$theme_info     = $data['theme_info'];
$plugin_info    = $data['plugin_info'];
$webhook_info   = $data['webhook_info'];
$theme_backups  = $data['theme_backups'];
$plugin_backups = $data['plugin_backups'];
$logs           = $data['logs'];
$backup_class   = $data['backup_class'];
$masked_pat     = WP_Puller_Admin::get_masked_pat();
$pat_status     = WP_Puller_Admin::get_pat_status();
$active_tab     = get_user_meta( get_current_user_id(), 'wp_puller_active_tab', true );
if ( ! in_array( $active_tab, array( 'theme', 'plugin' ), true ) ) {
    $active_tab = 'theme';
}
?>
<div class="wrap wp-puller-wrap">
    <h1 class="wp-puller-title">
        <span class="dashicons dashicons-update"></span>
        <?php esc_html_e( 'WP Puller', 'wp-puller' ); ?>
        <span class="wp-puller-version">v<?php echo esc_html( WP_PULLER_VERSION ); ?></span>
    </h1>

    <div class="wp-puller-notice" id="wp-puller-notice" style="display: none;"></div>

    <!-- Tab Navigation -->
    <div class="wp-puller-tabs">
        <button type="button" class="wp-puller-tab<?php echo 'theme' === $active_tab ? ' wp-puller-tab-active' : ''; ?>" data-tab="theme">
            <span class="dashicons dashicons-admin-appearance"></span>
            <?php esc_html_e( 'Theme', 'wp-puller' ); ?>
        </button>
        <button type="button" class="wp-puller-tab<?php echo 'plugin' === $active_tab ? ' wp-puller-tab-active' : ''; ?>" data-tab="plugin">
            <span class="dashicons dashicons-admin-plugins"></span>
            <?php esc_html_e( 'Plugin', 'wp-puller' ); ?>
        </button>
    </div>

    <!-- ============ THEME TAB ============ -->
    <div class="wp-puller-tab-content<?php echo 'theme' === $active_tab ? ' wp-puller-tab-content-active' : ''; ?>" id="wp-puller-tab-theme" data-asset-type="theme">
        <div class="wp-puller-grid">
            <!-- Theme Status Card -->
            <div class="wp-puller-card wp-puller-card-status">
                <div class="wp-puller-card-header">
                    <h2><?php esc_html_e( 'Theme Status', 'wp-puller' ); ?></h2>
                    <?php if ( $theme_status['is_configured'] ) : ?>
                        <span class="wp-puller-badge wp-puller-badge-success wp-puller-status-badge"><?php esc_html_e( 'Connected', 'wp-puller' ); ?></span>
                    <?php else : ?>
                        <span class="wp-puller-badge wp-puller-badge-warning wp-puller-status-badge"><?php esc_html_e( 'Not Configured', 'wp-puller' ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="wp-puller-card-body">
                    <div class="wp-puller-status-grid">
                        <div class="wp-puller-status-item">
                            <span class="wp-puller-status-label"><?php esc_html_e( 'Active Theme', 'wp-puller' ); ?></span>
                            <span class="wp-puller-status-value wp-puller-asset-name"><?php echo esc_html( $theme_info['name'] ); ?></span>
                        </div>
                        <div class="wp-puller-status-item">
                            <span class="wp-puller-status-label"><?php esc_html_e( 'Theme Version', 'wp-puller' ); ?></span>
                            <span class="wp-puller-status-value wp-puller-asset-version"><?php echo esc_html( $theme_info['version'] ?: '-' ); ?></span>
                        </div>
                        <div class="wp-puller-status-item">
                            <span class="wp-puller-status-label"><?php esc_html_e( 'Current Commit', 'wp-puller' ); ?></span>
                            <span class="wp-puller-status-value wp-puller-mono wp-puller-current-commit">
                                <?php echo $theme_status['short_commit'] ? esc_html( $theme_status['short_commit'] ) : '-'; ?>
                            </span>
                        </div>
                        <div class="wp-puller-status-item">
                            <span class="wp-puller-status-label"><?php esc_html_e( 'Last Check', 'wp-puller' ); ?></span>
                            <span class="wp-puller-status-value wp-puller-last-check">
                                <?php
                                if ( $theme_status['last_check'] ) {
                                    echo esc_html( human_time_diff( $theme_status['last_check'], time() ) . ' ' . __( 'ago', 'wp-puller' ) );
                                } else {
                                    echo '-';
                                }
                                ?>
                            </span>
                        </div>
                    </div>

                    <?php
                    $theme_deployed = get_option( 'wp_puller_deployed_branch', '' );
                    if ( ! empty( $theme_deployed ) && $theme_deployed !== $theme_status['branch'] ) :
                    ?>
                        <div class="wp-puller-deployed-notice">
                            <span class="dashicons dashicons-info"></span>
                            <?php
                            printf(
                                /* translators: %1$s: deployed branch, %2$s: configured branch */
                                esc_html__( 'Currently deployed from branch "%1$s" (configured: "%2$s")', 'wp-puller' ),
                                esc_html( $theme_deployed ),
                                esc_html( $theme_status['branch'] )
                            );
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="wp-puller-actions">
                        <button type="button" class="button wp-puller-check-updates" <?php disabled( ! $theme_status['is_configured'] ); ?>>
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e( 'Check for Updates', 'wp-puller' ); ?>
                        </button>
                        <button type="button" class="button button-primary wp-puller-update-now" <?php disabled( ! $theme_status['is_configured'] ); ?>>
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e( 'Update Now', 'wp-puller' ); ?>
                        </button>
                    </div>

                    <div class="wp-puller-update-result" style="display: none;"></div>
                </div>
            </div>

            <!-- Theme Settings Card -->
            <div class="wp-puller-card wp-puller-card-settings">
                <div class="wp-puller-card-header">
                    <h2><?php esc_html_e( 'Theme Repository', 'wp-puller' ); ?></h2>
                </div>
                <div class="wp-puller-card-body">
                    <form class="wp-puller-settings-form" data-asset-type="theme">
                        <div class="wp-puller-field">
                            <label><?php esc_html_e( 'Repository URL', 'wp-puller' ); ?></label>
                            <div class="wp-puller-input-group">
                                <input type="url"
                                       name="repo_url"
                                       value="<?php echo esc_attr( $theme_status['repo_url'] ); ?>"
                                       placeholder="https://github.com/username/theme-repo"
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
                                   value="<?php echo esc_attr( $theme_status['branch'] ); ?>"
                                   placeholder="main"
                                   class="regular-text">
                            <p class="description"><?php esc_html_e( 'Enter the branch name to track for updates (e.g. main, develop).', 'wp-puller' ); ?></p>
                        </div>

                        <div class="wp-puller-field">
                            <label><?php esc_html_e( 'Theme Path', 'wp-puller' ); ?></label>
                            <input type="text"
                                   name="path"
                                   value="<?php echo esc_attr( $theme_status['theme_path'] ); ?>"
                                   placeholder="<?php esc_attr_e( 'Leave empty if at repo root', 'wp-puller' ); ?>"
                                   class="regular-text">
                            <p class="description"><?php esc_html_e( 'Subdirectory containing the theme (e.g., "my-theme"). Leave empty if at repo root.', 'wp-puller' ); ?></p>
                        </div>

                        <div class="wp-puller-field">
                            <label><?php esc_html_e( 'Personal Access Token', 'wp-puller' ); ?></label>
                            <input type="password"
                                   name="pat"
                                   value="<?php echo esc_attr( $masked_pat ); ?>"
                                   placeholder="<?php esc_attr_e( 'ghp_xxxxx or github_pat_xxxxx', 'wp-puller' ); ?>"
                                   class="regular-text"
                                   autocomplete="off">
                            <p class="description">
                                <?php esc_html_e( 'Required for private repositories. Shared across tabs.', 'wp-puller' ); ?>
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
                                       name="auto_update"
                                       value="1"
                                       <?php checked( $theme_status['auto_update'] ); ?>>
                                <?php esc_html_e( 'Auto-update on webhook', 'wp-puller' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Automatically update when GitHub sends a push notification.', 'wp-puller' ); ?></p>
                        </div>

                        <div class="wp-puller-field">
                            <label><?php esc_html_e( 'Backups to Keep', 'wp-puller' ); ?></label>
                            <select name="backup_count">
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

            <!-- Theme Branch Testing Card -->
            <?php if ( $theme_status['is_configured'] ) : ?>
            <div class="wp-puller-card wp-puller-card-branches">
                <div class="wp-puller-card-header">
                    <h2><?php esc_html_e( 'Branch Testing', 'wp-puller' ); ?></h2>
                    <button type="button" class="button button-small wp-puller-refresh-branches">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Refresh', 'wp-puller' ); ?>
                    </button>
                </div>
                <div class="wp-puller-card-body">
                    <p class="description" style="margin: 0 0 12px;">
                        <?php esc_html_e( 'Deploy any branch to test. A backup is created automatically before switching.', 'wp-puller' ); ?>
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
            <?php endif; ?>

            <!-- Theme Backups Card -->
            <div class="wp-puller-card wp-puller-card-backups">
                <div class="wp-puller-card-header">
                    <h2><?php esc_html_e( 'Theme Backups', 'wp-puller' ); ?></h2>
                    <span class="wp-puller-badge"><?php echo count( $theme_backups ); ?></span>
                </div>
                <div class="wp-puller-card-body">
                    <?php if ( empty( $theme_backups ) ) : ?>
                        <p class="wp-puller-empty"><?php esc_html_e( 'No backups yet. A backup is created automatically before each update.', 'wp-puller' ); ?></p>
                    <?php else : ?>
                        <ul class="wp-puller-backup-list">
                            <?php foreach ( $theme_backups as $backup ) :
                                $backup_version = $backup_class->get_backup_version( $backup['path'], 'theme' );
                            ?>
                                <li class="wp-puller-backup-item" data-name="<?php echo esc_attr( $backup['name'] ); ?>">
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
        </div>
    </div>

    <!-- ============ PLUGIN TAB ============ -->
    <div class="wp-puller-tab-content<?php echo 'plugin' === $active_tab ? ' wp-puller-tab-content-active' : ''; ?>" id="wp-puller-tab-plugin" data-asset-type="plugin">
        <div class="wp-puller-grid">
            <!-- Plugin Status Card -->
            <div class="wp-puller-card wp-puller-card-status">
                <div class="wp-puller-card-header">
                    <h2><?php esc_html_e( 'Plugin Status', 'wp-puller' ); ?></h2>
                    <?php if ( $plugin_status['is_configured'] ) : ?>
                        <span class="wp-puller-badge wp-puller-badge-success wp-puller-status-badge"><?php esc_html_e( 'Connected', 'wp-puller' ); ?></span>
                    <?php else : ?>
                        <span class="wp-puller-badge wp-puller-badge-warning wp-puller-status-badge"><?php esc_html_e( 'Not Configured', 'wp-puller' ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="wp-puller-card-body">
                    <div class="wp-puller-status-grid">
                        <div class="wp-puller-status-item">
                            <span class="wp-puller-status-label"><?php esc_html_e( 'Target Plugin', 'wp-puller' ); ?></span>
                            <span class="wp-puller-status-value wp-puller-asset-name"><?php echo esc_html( $plugin_info['name'] ?: $plugin_info['slug'] ?: '-' ); ?></span>
                        </div>
                        <div class="wp-puller-status-item">
                            <span class="wp-puller-status-label"><?php esc_html_e( 'Plugin Version', 'wp-puller' ); ?></span>
                            <span class="wp-puller-status-value wp-puller-asset-version"><?php echo esc_html( $plugin_info['version'] ?: '-' ); ?></span>
                        </div>
                        <div class="wp-puller-status-item">
                            <span class="wp-puller-status-label"><?php esc_html_e( 'Current Commit', 'wp-puller' ); ?></span>
                            <span class="wp-puller-status-value wp-puller-mono wp-puller-current-commit">
                                <?php echo $plugin_status['short_commit'] ? esc_html( $plugin_status['short_commit'] ) : '-'; ?>
                            </span>
                        </div>
                        <div class="wp-puller-status-item">
                            <span class="wp-puller-status-label"><?php esc_html_e( 'Last Check', 'wp-puller' ); ?></span>
                            <span class="wp-puller-status-value wp-puller-last-check">
                                <?php
                                if ( $plugin_status['last_check'] ) {
                                    echo esc_html( human_time_diff( $plugin_status['last_check'], time() ) . ' ' . __( 'ago', 'wp-puller' ) );
                                } else {
                                    echo '-';
                                }
                                ?>
                            </span>
                        </div>
                    </div>

                    <?php
                    $plugin_deployed = get_option( 'wp_puller_plugin_deployed_branch', '' );
                    if ( ! empty( $plugin_deployed ) && $plugin_deployed !== $plugin_status['branch'] ) :
                    ?>
                        <div class="wp-puller-deployed-notice">
                            <span class="dashicons dashicons-info"></span>
                            <?php
                            printf(
                                /* translators: %1$s: deployed branch, %2$s: configured branch */
                                esc_html__( 'Currently deployed from branch "%1$s" (configured: "%2$s")', 'wp-puller' ),
                                esc_html( $plugin_deployed ),
                                esc_html( $plugin_status['branch'] )
                            );
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="wp-puller-actions">
                        <button type="button" class="button wp-puller-check-updates" <?php disabled( ! $plugin_status['is_configured'] ); ?>>
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e( 'Check for Updates', 'wp-puller' ); ?>
                        </button>
                        <button type="button" class="button button-primary wp-puller-update-now" <?php disabled( ! $plugin_status['is_configured'] ); ?>>
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e( 'Update Now', 'wp-puller' ); ?>
                        </button>
                    </div>

                    <div class="wp-puller-update-result" style="display: none;"></div>
                </div>
            </div>

            <!-- Plugin Settings Card -->
            <div class="wp-puller-card wp-puller-card-settings">
                <div class="wp-puller-card-header">
                    <h2><?php esc_html_e( 'Plugin Repository', 'wp-puller' ); ?></h2>
                </div>
                <div class="wp-puller-card-body">
                    <form class="wp-puller-settings-form" data-asset-type="plugin">
                        <div class="wp-puller-field">
                            <label><?php esc_html_e( 'Repository URL', 'wp-puller' ); ?></label>
                            <div class="wp-puller-input-group">
                                <input type="url"
                                       name="repo_url"
                                       value="<?php echo esc_attr( $plugin_status['repo_url'] ); ?>"
                                       placeholder="https://github.com/username/plugin-repo"
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
                                   value="<?php echo esc_attr( $plugin_status['branch'] ); ?>"
                                   placeholder="main"
                                   class="regular-text">
                            <p class="description"><?php esc_html_e( 'Enter the branch name to track for updates (e.g. main, develop).', 'wp-puller' ); ?></p>
                        </div>

                        <div class="wp-puller-field">
                            <label><?php esc_html_e( 'Plugin Slug', 'wp-puller' ); ?></label>
                            <input type="text"
                                   name="plugin_slug"
                                   value="<?php echo esc_attr( get_option( 'wp_puller_plugin_slug', '' ) ); ?>"
                                   placeholder="<?php esc_attr_e( 'my-plugin', 'wp-puller' ); ?>"
                                   class="regular-text">
                            <p class="description"><?php esc_html_e( 'The plugin directory name in wp-content/plugins/. This is where files will be deployed.', 'wp-puller' ); ?></p>
                        </div>

                        <div class="wp-puller-field">
                            <label><?php esc_html_e( 'Plugin Path', 'wp-puller' ); ?></label>
                            <input type="text"
                                   name="path"
                                   value="<?php echo esc_attr( $plugin_status['plugin_path'] ); ?>"
                                   placeholder="<?php esc_attr_e( 'Leave empty if at repo root', 'wp-puller' ); ?>"
                                   class="regular-text">
                            <p class="description"><?php esc_html_e( 'Subdirectory containing the plugin (e.g., "src"). Leave empty if at repo root.', 'wp-puller' ); ?></p>
                        </div>

                        <div class="wp-puller-field">
                            <label><?php esc_html_e( 'Personal Access Token', 'wp-puller' ); ?></label>
                            <input type="password"
                                   name="pat"
                                   value="<?php echo esc_attr( $masked_pat ); ?>"
                                   placeholder="<?php esc_attr_e( 'ghp_xxxxx or github_pat_xxxxx', 'wp-puller' ); ?>"
                                   class="regular-text"
                                   autocomplete="off">
                            <p class="description">
                                <?php esc_html_e( 'Required for private repositories. Shared across tabs.', 'wp-puller' ); ?>
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
                                       name="auto_update"
                                       value="1"
                                       <?php checked( $plugin_status['auto_update'] ); ?>>
                                <?php esc_html_e( 'Auto-update on webhook', 'wp-puller' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Automatically update when GitHub sends a push notification.', 'wp-puller' ); ?></p>
                        </div>

                        <div class="wp-puller-field">
                            <label><?php esc_html_e( 'Backups to Keep', 'wp-puller' ); ?></label>
                            <select name="backup_count">
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

            <!-- Plugin Branch Testing Card -->
            <?php if ( $plugin_status['is_configured'] ) : ?>
            <div class="wp-puller-card wp-puller-card-branches">
                <div class="wp-puller-card-header">
                    <h2><?php esc_html_e( 'Branch Testing', 'wp-puller' ); ?></h2>
                    <button type="button" class="button button-small wp-puller-refresh-branches">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Refresh', 'wp-puller' ); ?>
                    </button>
                </div>
                <div class="wp-puller-card-body">
                    <p class="description" style="margin: 0 0 12px;">
                        <?php esc_html_e( 'Deploy any branch to test. A backup is created automatically before switching.', 'wp-puller' ); ?>
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
            <?php endif; ?>

            <!-- Plugin Backups Card -->
            <div class="wp-puller-card wp-puller-card-backups">
                <div class="wp-puller-card-header">
                    <h2><?php esc_html_e( 'Plugin Backups', 'wp-puller' ); ?></h2>
                    <span class="wp-puller-badge"><?php echo count( $plugin_backups ); ?></span>
                </div>
                <div class="wp-puller-card-body">
                    <?php if ( empty( $plugin_backups ) ) : ?>
                        <p class="wp-puller-empty"><?php esc_html_e( 'No backups yet. A backup is created automatically before each update.', 'wp-puller' ); ?></p>
                    <?php else : ?>
                        <ul class="wp-puller-backup-list">
                            <?php foreach ( $plugin_backups as $backup ) :
                                $backup_version = $backup_class->get_backup_version( $backup['path'], 'plugin' );
                            ?>
                                <li class="wp-puller-backup-item" data-name="<?php echo esc_attr( $backup['name'] ); ?>">
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
        </div>
    </div>

    <!-- ============ SHARED SECTIONS ============ -->
    <div class="wp-puller-grid wp-puller-shared-section">
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
