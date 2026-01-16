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

        bindEvents: function() {
            $('#wp-puller-settings-form').on('submit', this.saveSettings.bind(this));
            $('#wp-puller-test-connection').on('click', this.testConnection.bind(this));
            $('#wp-puller-check-updates').on('click', this.checkUpdates.bind(this));
            $('#wp-puller-update-now').on('click', this.updateTheme.bind(this));
            $('#wp-puller-regenerate-secret').on('click', this.regenerateSecret.bind(this));
            $('#wp-puller-clear-logs').on('click', this.clearLogs.bind(this));

            $(document).on('click', '.wp-puller-restore-backup', this.restoreBackup.bind(this));
            $(document).on('click', '.wp-puller-delete-backup', this.deleteBackup.bind(this));
            $(document).on('click', '.wp-puller-copy-btn', this.copyToClipboard.bind(this));
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
                    backup_count: $('#wp-puller-backup-count').val()
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

                            if (response.data.repo.default_branch && !$('#wp-puller-branch').val()) {
                                $('#wp-puller-branch').val(response.data.repo.default_branch);
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
                            html = '<p><strong>Ready to install.</strong> Click "Update Now" to pull the theme from GitHub.</p>';
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
                            html = '<p><strong>Theme is up to date.</strong></p>';
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
                        WPPuller.showNotice(wpPuller.strings.updated, 'success');

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
                // Fallback for older browsers
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
