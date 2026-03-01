/**
 * WP Puller Admin JavaScript
 *
 * @package WP_Puller
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var WPPuller = {
        init: function() {
            this.bindEvents();
        },

        /**
         * Get the active tab's asset type.
         */
        getActiveAssetType: function() {
            return $('.wp-puller-tab-content-active').data('asset-type') || 'theme';
        },

        /**
         * Get the active tab container.
         */
        getActiveTab: function() {
            return $('.wp-puller-tab-content-active');
        },

        /**
         * Get asset type from a button's closest tab container.
         */
        getAssetTypeFromElement: function($el) {
            return $el.closest('.wp-puller-tab-content').data('asset-type') || 'theme';
        },

        bindEvents: function() {
            // Tab switching
            $(document).on('click', '.wp-puller-tab', this.switchTab.bind(this));

            // Per-tab actions (use delegation from tab containers)
            $(document).on('submit', '.wp-puller-settings-form', this.saveSettings.bind(this));
            $(document).on('click', '.wp-puller-test-connection', this.testConnection.bind(this));
            $(document).on('click', '.wp-puller-check-updates', this.checkUpdates.bind(this));
            $(document).on('click', '.wp-puller-update-now', this.updateAsset.bind(this));
            $(document).on('click', '.wp-puller-refresh-branches', this.refreshBranchList.bind(this));
            $(document).on('click', '.wp-puller-deploy-branch', this.deployBranch.bind(this));
            $(document).on('click', '.wp-puller-compare-branch', this.compareBranch.bind(this));
            $(document).on('click', '.wp-puller-close-compare', this.closeCompare.bind(this));

            // Shared actions
            $(document).on('click', '.wp-puller-restore-backup', this.restoreBackup.bind(this));
            $(document).on('click', '.wp-puller-delete-backup', this.deleteBackup.bind(this));
            $(document).on('click', '.wp-puller-copy-btn', this.copyToClipboard.bind(this));
            $('#wp-puller-regenerate-secret').on('click', this.regenerateSecret.bind(this));
            $('#wp-puller-clear-logs').on('click', this.clearLogs.bind(this));
        },

        // --- Tab Switching ---

        switchTab: function(e) {
            var $btn = $(e.currentTarget);
            var tab = $btn.data('tab');

            // Update tab buttons
            $('.wp-puller-tab').removeClass('wp-puller-tab-active');
            $btn.addClass('wp-puller-tab-active');

            // Update tab content
            $('.wp-puller-tab-content').removeClass('wp-puller-tab-content-active');
            $('#wp-puller-tab-' + tab).addClass('wp-puller-tab-content-active');
        },

        // --- Settings ---

        saveSettings: function(e) {
            e.preventDefault();

            var $form = $(e.currentTarget);
            var $btn = $form.find('[type="submit"]');
            var assetType = $form.data('asset-type');

            this.setLoading($btn, true);

            var data = {
                action: 'wp_puller_save_settings',
                nonce: wpPuller.nonce,
                asset_type: assetType,
                repo_url: $form.find('[name="repo_url"]').val(),
                branch: $form.find('[name="branch"]').val(),
                path: $form.find('[name="path"]').val(),
                pat: $form.find('[name="pat"]').val(),
                auto_update: $form.find('[name="auto_update"]').is(':checked') ? 'true' : 'false',
                backup_count: $form.find('[name="backup_count"]').val()
            };

            if (assetType === 'plugin') {
                data.plugin_slug = $form.find('[name="plugin_slug"]').val();
            }

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        WPPuller.showNotice(response.data.message, 'success');
                        if (response.data.status) {
                            WPPuller.updateStatusUI(assetType, response.data.status, response.data);
                        }
                    } else {
                        WPPuller.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WPPuller.showNotice(wpPuller.strings.error, 'error');
                },
                complete: function() {
                    WPPuller.setLoading($btn, false);
                }
            });
        },

        testConnection: function(e) {
            var $btn = $(e.currentTarget);
            var $form = $btn.closest('.wp-puller-settings-form');
            var repoUrl = $form.find('[name="repo_url"]').val();

            if (!repoUrl) {
                this.showNotice('Please enter a repository URL.', 'error');
                return;
            }

            this.setLoading($btn, true);

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_test_connection',
                    nonce: wpPuller.nonce,
                    repo_url: repoUrl
                },
                success: function(response) {
                    if (response.success) {
                        var msg = wpPuller.strings.connected;
                        if (response.data.repo) {
                            msg += ' Repository: ' + response.data.repo.full_name;
                            if (response.data.repo.private) {
                                msg += ' (Private)';
                            }
                        }
                        WPPuller.showNotice(msg, 'success');
                    } else {
                        WPPuller.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WPPuller.showNotice(wpPuller.strings.error, 'error');
                },
                complete: function() {
                    WPPuller.setLoading($btn, false);
                }
            });
        },

        // --- Updates ---

        checkUpdates: function(e) {
            var $btn = $(e.currentTarget);
            var assetType = this.getAssetTypeFromElement($btn);
            var $tab = $btn.closest('.wp-puller-tab-content');
            var $result = $tab.find('.wp-puller-update-result');

            this.setLoading($btn, true);
            $result.hide();

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_check_updates',
                    nonce: wpPuller.nonce,
                    asset_type: assetType
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var html = '';

                        if (data.is_new_setup) {
                            html = '<p><strong>Ready to install.</strong> Click "Update Now" to pull from GitHub.</p>';
                            if (data.latest_version) {
                                html += '<p>Version in repository: <strong>' + WPPuller.escapeHtml(data.latest_version) + '</strong></p>';
                            }
                            $result.removeClass('has-update no-update').addClass('has-update');
                        } else if (data.update_available) {
                            html = '<p><strong>Update available!</strong></p>';
                            if (data.current_version || data.latest_version) {
                                html += '<p>';
                                if (data.current_version) {
                                    html += 'Installed: <strong>' + WPPuller.escapeHtml(data.current_version) + '</strong>';
                                }
                                if (data.current_version && data.latest_version) {
                                    html += ' &rarr; ';
                                }
                                if (data.latest_version) {
                                    html += 'New: <strong>' + WPPuller.escapeHtml(data.latest_version) + '</strong>';
                                }
                                html += '</p>';
                            }
                            html += '<p>Current commit: <code>' + data.current_commit.substring(0, 7) + '</code>';
                            html += ' &rarr; Latest: <code>' + data.latest_commit.short_sha + '</code>';
                            if (data.latest_commit.message) {
                                html += ' - ' + WPPuller.escapeHtml(data.latest_commit.message.substring(0, 60));
                            }
                            html += '</p>';
                            $result.removeClass('has-update no-update').addClass('has-update');
                        } else {
                            html = '<p><strong>Up to date.</strong></p>';
                            if (data.current_version) {
                                html += '<p>Version: <strong>' + WPPuller.escapeHtml(data.current_version) + '</strong>';
                                html += ' (commit: <code>' + data.latest_commit.short_sha + '</code>)</p>';
                            } else {
                                html += '<p>Current commit: <code>' + data.latest_commit.short_sha + '</code></p>';
                            }
                            $result.removeClass('has-update no-update').addClass('no-update');
                        }

                        $result.html(html).show();
                        $tab.find('.wp-puller-last-check').text('just now');
                    } else {
                        WPPuller.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WPPuller.showNotice(wpPuller.strings.error, 'error');
                },
                complete: function() {
                    WPPuller.setLoading($btn, false);
                }
            });
        },

        updateAsset: function(e) {
            var $btn = $(e.currentTarget);
            var assetType = this.getAssetTypeFromElement($btn);

            this.setLoading($btn, true);

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_update_theme',
                    nonce: wpPuller.nonce,
                    asset_type: assetType
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.showNotice(response.data.message, 'success');
                        WPPuller.updateStatusUI(assetType, response.data.status, response.data);

                        var $tab = $('#wp-puller-tab-' + assetType);
                        $tab.find('.wp-puller-update-result').hide();
                    } else {
                        WPPuller.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WPPuller.showNotice(wpPuller.strings.error, 'error');
                },
                complete: function() {
                    WPPuller.setLoading($btn, false);
                }
            });
        },

        /**
         * Update the status card UI after an update/save/deploy without page reload.
         */
        updateStatusUI: function(assetType, status, responseData) {
            var $tab = $('#wp-puller-tab-' + assetType);

            // Update commit
            if (status && status.short_commit) {
                $tab.find('.wp-puller-current-commit').text(status.short_commit);
            }

            // Update last check
            $tab.find('.wp-puller-last-check').text('just now');

            // Update version display
            if (assetType === 'plugin' && responseData.plugin_info) {
                var info = responseData.plugin_info;
                $tab.find('.wp-puller-asset-name').text(info.name || info.slug || '-');
                $tab.find('.wp-puller-asset-version').text(info.version || '-');
            } else if (assetType === 'theme' && responseData.theme_info) {
                var tInfo = responseData.theme_info;
                $tab.find('.wp-puller-asset-name').text(tInfo.name || '-');
                $tab.find('.wp-puller-asset-version').text(tInfo.version || '-');
            }

            // Update status badge
            if (status && status.is_configured) {
                $tab.find('.wp-puller-status-badge')
                    .removeClass('wp-puller-badge-warning')
                    .addClass('wp-puller-badge-success')
                    .text('Connected');
                $tab.find('.wp-puller-check-updates, .wp-puller-update-now').prop('disabled', false);
            }
        },

        // --- Branch Testing ---

        refreshBranchList: function(e) {
            var $btn = $(e.currentTarget);
            var assetType = this.getAssetTypeFromElement($btn);
            var $tab = $btn.closest('.wp-puller-tab-content');
            var $container = $tab.find('.wp-puller-branch-list');

            this.setLoading($btn, true);
            $container.html('<p class="wp-puller-empty">Loading branches...</p>');

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_get_branches_with_info',
                    nonce: wpPuller.nonce,
                    asset_type: assetType
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.renderBranchList($container, response.data);
                    } else {
                        $container.html('<p class="wp-puller-empty">' + WPPuller.escapeHtml(response.data.message) + '</p>');
                    }
                },
                error: function() {
                    $container.html('<p class="wp-puller-empty">Failed to load branches.</p>');
                },
                complete: function() {
                    WPPuller.setLoading($btn, false);
                }
            });
        },

        renderBranchList: function($container, data) {
            var branches = data.branches;
            var configured = data.configured;
            var deployed = data.deployed_branch;

            if (!branches || branches.length === 0) {
                $container.html('<p class="wp-puller-empty">No branches found.</p>');
                return;
            }

            var html = '<table class="wp-puller-branch-table"><thead><tr>';
            html += '<th>Branch</th>';
            html += '<th>Commit</th>';
            html += '<th>Message</th>';
            html += '<th>Author</th>';
            html += '<th>Actions</th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < branches.length; i++) {
                var b = branches[i];
                var isConfigured = (b.name === configured);
                var isDeployed = (b.name === deployed);

                html += '<tr class="wp-puller-branch-row';
                if (isDeployed) html += ' wp-puller-branch-deployed';
                html += '">';

                html += '<td class="wp-puller-branch-name-cell">';
                html += '<span class="wp-puller-branch-name-text">' + this.escapeHtml(b.name) + '</span>';
                if (isConfigured) {
                    html += ' <span class="wp-puller-badge wp-puller-badge-info">default</span>';
                }
                if (isDeployed) {
                    html += ' <span class="wp-puller-badge wp-puller-badge-success">deployed</span>';
                }
                html += '</td>';

                html += '<td class="wp-puller-mono">' + this.escapeHtml(b.short_sha || '') + '</td>';
                html += '<td class="wp-puller-branch-message">' + this.escapeHtml((b.message || '').substring(0, 50)) + '</td>';
                html += '<td>' + this.escapeHtml(b.author || '') + '</td>';

                html += '<td class="wp-puller-branch-actions-cell">';
                html += '<button type="button" class="button button-small wp-puller-compare-branch" data-branch="' + this.escapeHtml(b.name) + '" title="Compare with deployed">';
                html += '<span class="dashicons dashicons-randomize"></span>';
                html += '</button> ';
                if (!isDeployed) {
                    html += '<button type="button" class="button button-small button-primary wp-puller-deploy-branch" data-branch="' + this.escapeHtml(b.name) + '">';
                    html += 'Deploy';
                    html += '</button>';
                }
                html += '</td>';

                html += '</tr>';
            }

            html += '</tbody></table>';
            $container.html(html);
        },

        deployBranch: function(e) {
            var $btn = $(e.currentTarget);
            var branch = $btn.data('branch');
            var assetType = this.getAssetTypeFromElement($btn);

            if (!confirm(wpPuller.strings.confirmBranchDeploy)) {
                return;
            }

            this.setLoading($btn, true);

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_deploy_branch',
                    nonce: wpPuller.nonce,
                    branch: branch,
                    asset_type: assetType
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.showNotice(response.data.message, 'success');
                        WPPuller.updateStatusUI(assetType, response.data.status, response.data);

                        // Update deployed branch tracking
                        if (wpPuller[assetType]) {
                            wpPuller[assetType].deployedBranch = branch;
                        }

                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        WPPuller.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WPPuller.showNotice(wpPuller.strings.error, 'error');
                },
                complete: function() {
                    WPPuller.setLoading($btn, false);
                }
            });
        },

        // --- Branch Comparison ---

        compareBranch: function(e) {
            var $btn = $(e.currentTarget);
            var headBranch = $btn.data('branch');
            var assetType = this.getAssetTypeFromElement($btn);
            var assetData = wpPuller[assetType] || {};
            var baseBranch = assetData.deployedBranch || assetData.branch || 'main';

            if (headBranch === baseBranch) {
                this.showNotice('Cannot compare a branch with itself.', 'info');
                return;
            }

            this.setLoading($btn, true);

            var $tab = $btn.closest('.wp-puller-tab-content');
            var $panel = $tab.find('.wp-puller-compare-panel');
            var $content = $tab.find('.wp-puller-compare-content');
            var $title = $tab.find('.wp-puller-compare-title');

            $title.text(baseBranch + ' ... ' + headBranch);
            $content.html('<p>Loading comparison...</p>');
            $panel.show();

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_compare_branches',
                    nonce: wpPuller.nonce,
                    base: baseBranch,
                    head: headBranch,
                    asset_type: assetType
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.renderComparison($content, response.data, baseBranch, headBranch);
                    } else {
                        $content.html('<p class="wp-puller-empty">' + WPPuller.escapeHtml(response.data.message) + '</p>');
                    }
                },
                error: function() {
                    $content.html('<p class="wp-puller-empty">Failed to load comparison.</p>');
                },
                complete: function() {
                    WPPuller.setLoading($btn, false);
                }
            });
        },

        renderComparison: function($content, data) {
            var html = '';

            // Summary
            html += '<div class="wp-puller-compare-summary">';
            html += '<span class="wp-puller-compare-stat">';
            html += '<strong>' + data.total_commits + '</strong> commit' + (data.total_commits !== 1 ? 's' : '');
            html += '</span>';
            html += '<span class="wp-puller-compare-stat">';
            html += '<strong>' + data.files.length + '</strong> file' + (data.files.length !== 1 ? 's' : '') + ' changed';
            html += '</span>';
            if (data.ahead_by > 0) {
                html += '<span class="wp-puller-compare-stat wp-puller-compare-ahead">';
                html += data.ahead_by + ' ahead';
                html += '</span>';
            }
            if (data.behind_by > 0) {
                html += '<span class="wp-puller-compare-stat wp-puller-compare-behind">';
                html += data.behind_by + ' behind';
                html += '</span>';
            }
            html += '</div>';

            // Commits
            if (data.commits && data.commits.length > 0) {
                html += '<div class="wp-puller-compare-section">';
                html += '<h4>Commits</h4>';
                html += '<ul class="wp-puller-compare-commits">';
                var maxCommits = Math.min(data.commits.length, 20);
                for (var i = 0; i < maxCommits; i++) {
                    var c = data.commits[i];
                    html += '<li>';
                    html += '<code>' + this.escapeHtml(c.short_sha) + '</code> ';
                    html += this.escapeHtml((c.message || '').split('\n')[0].substring(0, 80));
                    html += ' <span class="wp-puller-compare-author">- ' + this.escapeHtml(c.author || '') + '</span>';
                    html += '</li>';
                }
                if (data.commits.length > 20) {
                    html += '<li class="wp-puller-compare-more">... and ' + (data.commits.length - 20) + ' more commits</li>';
                }
                html += '</ul>';
                html += '</div>';
            }

            // Files changed
            if (data.files && data.files.length > 0) {
                html += '<div class="wp-puller-compare-section">';
                html += '<h4>Files Changed</h4>';
                html += '<ul class="wp-puller-compare-files">';
                var maxFiles = Math.min(data.files.length, 30);
                for (var j = 0; j < maxFiles; j++) {
                    var f = data.files[j];
                    var statusClass = 'wp-puller-file-' + f.status;
                    var statusIcon = f.status === 'added' ? '+' : (f.status === 'removed' ? '-' : 'M');
                    html += '<li class="' + statusClass + '">';
                    html += '<span class="wp-puller-file-status">' + statusIcon + '</span> ';
                    html += this.escapeHtml(f.filename);
                    html += ' <span class="wp-puller-file-changes">';
                    if (f.additions > 0) html += '<span class="wp-puller-additions">+' + f.additions + '</span>';
                    if (f.deletions > 0) html += '<span class="wp-puller-deletions">-' + f.deletions + '</span>';
                    html += '</span>';
                    html += '</li>';
                }
                if (data.files.length > 30) {
                    html += '<li class="wp-puller-compare-more">... and ' + (data.files.length - 30) + ' more files</li>';
                }
                html += '</ul>';
                html += '</div>';
            }

            if (data.total_commits === 0 && data.files.length === 0) {
                html = '<p class="wp-puller-empty">' + wpPuller.strings.noChanges + '</p>';
            }

            $content.html(html);
        },

        closeCompare: function(e) {
            $(e.currentTarget).closest('.wp-puller-compare-panel').hide();
        },

        // --- Shared functionality ---

        restoreBackup: function(e) {
            var $btn = $(e.currentTarget);
            var backupName = $btn.data('name');

            if (!confirm(wpPuller.strings.confirmRestore)) {
                return;
            }

            this.setLoading($btn, true);

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_restore_backup',
                    nonce: wpPuller.nonce,
                    backup_name: backupName
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.showNotice(wpPuller.strings.restored, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        WPPuller.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WPPuller.showNotice(wpPuller.strings.error, 'error');
                },
                complete: function() {
                    WPPuller.setLoading($btn, false);
                }
            });
        },

        deleteBackup: function(e) {
            var $btn = $(e.currentTarget);
            var backupName = $btn.data('name');

            if (!confirm(wpPuller.strings.confirmDelete)) {
                return;
            }

            this.setLoading($btn, true);

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_delete_backup',
                    nonce: wpPuller.nonce,
                    backup_name: backupName
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.wp-puller-backup-item').fadeOut(function() {
                            $(this).remove();
                        });
                        WPPuller.showNotice(wpPuller.strings.deleted, 'success');
                    } else {
                        WPPuller.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WPPuller.showNotice(wpPuller.strings.error, 'error');
                },
                complete: function() {
                    WPPuller.setLoading($btn, false);
                }
            });
        },

        regenerateSecret: function(e) {
            var $btn = $(e.currentTarget);

            if (!confirm(wpPuller.strings.confirmRegenerate)) {
                return;
            }

            this.setLoading($btn, true);

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_regenerate_secret',
                    nonce: wpPuller.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#webhook-secret').val(response.data.secret);
                        WPPuller.showNotice(wpPuller.strings.regenerated, 'success');
                    } else {
                        WPPuller.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WPPuller.showNotice(wpPuller.strings.error, 'error');
                },
                complete: function() {
                    WPPuller.setLoading($btn, false);
                }
            });
        },

        clearLogs: function(e) {
            var $btn = $(e.currentTarget);

            this.setLoading($btn, true);

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_clear_logs',
                    nonce: wpPuller.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#wp-puller-log-list').replaceWith(
                            '<p class="wp-puller-empty">No activity recorded yet.</p>'
                        );
                        $btn.remove();
                    } else {
                        WPPuller.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    WPPuller.showNotice(wpPuller.strings.error, 'error');
                },
                complete: function() {
                    WPPuller.setLoading($btn, false);
                }
            });
        },

        copyToClipboard: function(e) {
            var $btn = $(e.currentTarget);
            var inputId = $btn.data('copy');
            var $input = $('#' + inputId);

            $input.select();

            try {
                document.execCommand('copy');
                $btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');

                setTimeout(function() {
                    $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
                }, 1500);
            } catch (err) {
                window.prompt('Copy to clipboard:', $input.val());
            }
        },

        setLoading: function($btn, loading) {
            if (loading) {
                $btn.addClass('wp-puller-btn-loading').prop('disabled', true);
            } else {
                $btn.removeClass('wp-puller-btn-loading').prop('disabled', false);
            }
        },

        showNotice: function(message, type) {
            var $notice = $('#wp-puller-notice');
            var className = 'notice-' + (type || 'info');

            $notice
                .removeClass('notice-success notice-error notice-info')
                .addClass(className)
                .html(this.escapeHtml(message))
                .fadeIn();

            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);

            $('html, body').animate({
                scrollTop: $('.wp-puller-wrap').offset().top - 50
            }, 300);
        },

        escapeHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        WPPuller.init();
    });

})(jQuery);
