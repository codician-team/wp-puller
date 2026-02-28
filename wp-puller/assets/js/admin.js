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
            this.initAssetTypeToggle();
        },

        bindEvents: function() {
            $('#wp-puller-settings-form').on('submit', this.saveSettings.bind(this));
            $('#wp-puller-test-connection').on('click', this.testConnection.bind(this));
            $('#wp-puller-fetch-branches').on('click', this.fetchBranches.bind(this));
            $('#wp-puller-check-updates').on('click', this.checkUpdates.bind(this));
            $('#wp-puller-update-now').on('click', this.updateTheme.bind(this));
            $('#wp-puller-regenerate-secret').on('click', this.regenerateSecret.bind(this));
            $('#wp-puller-clear-logs').on('click', this.clearLogs.bind(this));

            $(document).on('click', '.wp-puller-restore-backup', this.restoreBackup.bind(this));
            $(document).on('click', '.wp-puller-delete-backup', this.deleteBackup.bind(this));
            $(document).on('click', '.wp-puller-copy-btn', this.copyToClipboard.bind(this));

            // Branch testing
            $('#wp-puller-refresh-branches').on('click', this.refreshBranchList.bind(this));
            $(document).on('click', '.wp-puller-deploy-branch', this.deployBranch.bind(this));
            $(document).on('click', '.wp-puller-compare-branch', this.compareBranch.bind(this));
            $('#wp-puller-close-compare').on('click', this.closeCompare.bind(this));

            // Asset type toggle
            $('input[name="asset_type"]').on('change', this.toggleAssetType.bind(this));
        },

        initAssetTypeToggle: function() {
            var assetType = $('input[name="asset_type"]:checked').val() || 'theme';
            this.applyAssetTypeUI(assetType);
        },

        toggleAssetType: function() {
            var assetType = $('input[name="asset_type"]:checked').val();
            this.applyAssetTypeUI(assetType);
        },

        applyAssetTypeUI: function(assetType) {
            if (assetType === 'plugin') {
                $('#wp-puller-plugin-slug-field').show();
                $('.wp-puller-label-theme').hide();
                $('.wp-puller-label-plugin').show();
            } else {
                $('#wp-puller-plugin-slug-field').hide();
                $('.wp-puller-label-plugin').hide();
                $('.wp-puller-label-theme').show();
            }
        },

        saveSettings: function(e) {
            e.preventDefault();

            var $form = $(e.currentTarget);
            var $btn = $form.find('[type="submit"]');

            this.setLoading($btn, true);

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_save_settings',
                    nonce: wpPuller.nonce,
                    repo_url: $('#wp-puller-repo-url').val(),
                    branch: $('#wp-puller-branch').val(),
                    theme_path: $('#wp-puller-theme-path').val(),
                    pat: $('#wp-puller-pat').val(),
                    auto_update: $('#wp-puller-auto-update').is(':checked') ? 'true' : 'false',
                    backup_count: $('#wp-puller-backup-count').val(),
                    asset_type: $('input[name="asset_type"]:checked').val() || 'theme',
                    plugin_slug: $('#wp-puller-plugin-slug').val()
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.showNotice(response.data.message, 'success');
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
            var repoUrl = $('#wp-puller-repo-url').val();

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

                        // Auto-fetch branches after successful connection test
                        WPPuller.fetchBranches(null, response.data.repo ? response.data.repo.default_branch : null);
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

        fetchBranches: function(e, defaultBranch) {
            var $btn = $('#wp-puller-fetch-branches');
            var $select = $('#wp-puller-branch');
            var repoUrl = $('#wp-puller-repo-url').val();

            if (!repoUrl) {
                this.showNotice('Please enter a repository URL first.', 'error');
                return;
            }

            // If called from a click event, use the button from the event
            if (e && e.currentTarget) {
                $btn = $(e.currentTarget);
            }

            this.setLoading($btn, true);

            var currentBranch = $select.val() || wpPuller.currentBranch || 'main';

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_fetch_branches',
                    nonce: wpPuller.nonce,
                    repo_url: repoUrl
                },
                success: function(response) {
                    if (response.success) {
                        var branches = response.data.branches;
                        var repoDefault = defaultBranch || response.data.default_branch || 'main';

                        $select.empty();

                        if (branches.length === 0) {
                            $select.append('<option value="">' + WPPuller.escapeHtml(wpPuller.strings.noBranches) + '</option>');
                            return;
                        }

                        // Pin default branch first, keep recency order for the rest
                        branches.sort(function(a, b) {
                            if (a === repoDefault) return -1;
                            if (b === repoDefault) return 1;
                            return 0;
                        });

                        for (var i = 0; i < branches.length; i++) {
                            var label = branches[i];
                            if (branches[i] === repoDefault) {
                                label += ' (default)';
                            }
                            var $option = $('<option></option>')
                                .val(branches[i])
                                .text(label);

                            if (branches[i] === currentBranch) {
                                $option.prop('selected', true);
                            }

                            $select.append($option);
                        }

                        // If the current saved branch wasn't in the list, select the default
                        if ($select.val() === null && branches.length > 0) {
                            $select.val(repoDefault);
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

        checkUpdates: function(e) {
            var $btn = $(e.currentTarget);
            var $result = $('#wp-puller-update-result');

            this.setLoading($btn, true);
            $result.hide();

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_check_updates',
                    nonce: wpPuller.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var html = '';

                        if (data.is_new_setup) {
                            html = '<p><strong>Ready to install.</strong> Click "Update Now" to pull from GitHub.</p>';
                            $result.removeClass('has-update no-update').addClass('has-update');
                        } else if (data.update_available) {
                            html = '<p><strong>Update available!</strong></p>';
                            html += '<p>Current: <code>' + data.current_commit.substring(0, 7) + '</code></p>';
                            html += '<p>Latest: <code>' + data.latest_commit.short_sha + '</code>';
                            if (data.latest_commit.message) {
                                html += ' - ' + WPPuller.escapeHtml(data.latest_commit.message.substring(0, 60));
                            }
                            html += '</p>';
                            $result.removeClass('has-update no-update').addClass('has-update');
                        } else {
                            html = '<p><strong>Up to date.</strong></p>';
                            html += '<p>Current commit: <code>' + data.latest_commit.short_sha + '</code></p>';
                            $result.removeClass('has-update no-update').addClass('no-update');
                        }

                        $result.html(html).show();
                        $('#last-check').text('just now');
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

        updateTheme: function(e) {
            var $btn = $(e.currentTarget);

            this.setLoading($btn, true);

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_update_theme',
                    nonce: wpPuller.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.showNotice(response.data.message, 'success');

                        if (response.data.status) {
                            $('#current-commit').text(response.data.status.short_commit || '-');
                        }

                        $('#wp-puller-update-result').hide();

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

        // --- Branch Testing ---

        refreshBranchList: function(e) {
            var $btn = $(e && e.currentTarget ? e.currentTarget : '#wp-puller-refresh-branches');
            var $container = $('#wp-puller-branch-list');

            this.setLoading($btn, true);
            $container.html('<p class="wp-puller-empty">Loading branches...</p>');

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_get_branches_with_info',
                    nonce: wpPuller.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.renderBranchList(response.data);
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

        renderBranchList: function(data) {
            var $container = $('#wp-puller-branch-list');
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
                    branch: branch
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.showNotice(response.data.message, 'success');
                        wpPuller.deployedBranch = branch;

                        if (response.data.status) {
                            $('#current-commit').text(response.data.status.short_commit || '-');
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
            var baseBranch = wpPuller.deployedBranch || wpPuller.currentBranch || 'main';

            if (headBranch === baseBranch) {
                this.showNotice('Cannot compare a branch with itself.', 'info');
                return;
            }

            this.setLoading($btn, true);

            var $panel = $('#wp-puller-compare-panel');
            var $content = $('#wp-puller-compare-content');
            var $title = $('#wp-puller-compare-title');

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
                    head: headBranch
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.renderComparison(response.data, baseBranch, headBranch);
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

        renderComparison: function(data, baseBranch, headBranch) {
            var $content = $('#wp-puller-compare-content');
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

        closeCompare: function() {
            $('#wp-puller-compare-panel').hide();
        },

        // --- Existing functionality ---

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

                            if ($('#wp-puller-backup-list li').length === 0) {
                                $('#wp-puller-backup-list').replaceWith(
                                    '<p class="wp-puller-empty">No backups yet. A backup is created automatically before each update.</p>'
                                );
                            }
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
