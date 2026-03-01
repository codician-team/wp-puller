/**
 * WP Puller Admin JavaScript
 *
 * Card-based single-page admin UI for managing multiple themes and plugins.
 *
 * @package WP_Puller
 * @since 2.0.0
 */

(function($) {
    'use strict';

    var WPPuller = {

        /**
         * Currently open panel info: { assetId: string, panel: string } or null.
         */
        activePanel: null,

        /**
         * Initialize the admin UI.
         */
        init: function() {
            this.bindEvents();
            this.initTokenToggle();
        },

        // =====================================================================
        // Event Binding
        // =====================================================================

        bindEvents: function() {
            // Panel triggers on cards (uses data-panel attribute)
            $(document).on('click', '.wp-puller-open-panel', this.onOpenPanelClick.bind(this));

            // Close panel buttons
            $(document).on('click', '.wp-puller-close-panel', this.closePanel.bind(this, null));

            // Settings form
            $(document).on('submit', '.wp-puller-settings-form', this.saveSettings.bind(this));
            $(document).on('click', '.wp-puller-test-connection', this.testConnection.bind(this));

            // Token mode toggle (radio buttons)
            $(document).on('change', 'input[name="token_mode"]', this.toggleTokenMode.bind(this));

            // Per-card update actions
            $(document).on('click', '.wp-puller-check-updates', this.checkUpdates.bind(this));
            $(document).on('click', '.wp-puller-update-now', this.updateAsset.bind(this));

            // Global actions
            $(document).on('click', '#wp-puller-add-new', this.addAsset.bind(this));
            $(document).on('click', '#wp-puller-check-all', this.checkAll.bind(this));
            $(document).on('click', '#wp-puller-update-all', this.updateAll.bind(this));

            // Remove asset
            $(document).on('click', '.wp-puller-remove-item', this.removeAsset.bind(this));

            // Branch testing
            $(document).on('click', '.wp-puller-refresh-branches', this.refreshBranchList.bind(this));
            $(document).on('click', '.wp-puller-deploy-branch', this.deployBranch.bind(this));
            $(document).on('click', '.wp-puller-compare-branch', this.compareBranch.bind(this));
            $(document).on('click', '.wp-puller-close-compare', this.closeCompare.bind(this));

            // Backups
            $(document).on('click', '.wp-puller-restore-backup', this.restoreBackup.bind(this));
            $(document).on('click', '.wp-puller-delete-backup', this.deleteBackup.bind(this));

            // Webhook
            $(document).on('click', '.wp-puller-copy-btn', this.copyToClipboard.bind(this));
            $(document).on('click', '#wp-puller-regenerate-secret', this.regenerateSecret.bind(this));

            // Logs
            $(document).on('click', '#wp-puller-clear-logs', this.clearLogs.bind(this));

            // Modal
            $(document).on('click', '.wp-puller-modal-cancel', this.closeModal.bind(this));
            $(document).on('click', '.wp-puller-modal', this.onModalBackdropClick.bind(this));
        },

        // =====================================================================
        // Token Toggle
        // =====================================================================

        /**
         * Initialize token input mode based on available tokens.
         */
        initTokenToggle: function() {
            // If tokens exist, default to reuse mode; otherwise show input
            // This will be applied when the settings panel opens
        },

        /**
         * Toggle between "reuse existing token" and "enter new token" modes.
         */
        toggleTokenMode: function(e) {
            var $radio = $(e.currentTarget);
            var mode = $radio.val();
            var $container = $radio.closest('.wp-puller-token-choice');
            var $select = $container.find('.wp-puller-token-select');
            var $newFields = $container.find('.wp-puller-new-token-fields');

            if (mode === 'existing') {
                $select.show();
                $newFields.hide();
                $container.find('[name="pat"]').val('');
            } else {
                $select.hide();
                $newFields.show();
                $container.find('[name="token_id"]').val('');
            }
        },

        // =====================================================================
        // Panel Switching
        // =====================================================================

        /**
         * Handle panel open/close when a card icon is clicked.
         *
         * @param {Event} e
         */
        onOpenPanelClick: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var panelName = $btn.data('panel');
            var assetId = $btn.data('asset-id') || $btn.closest('.wp-puller-asset-card').data('asset-id');

            // If same panel for same card is already open, close it
            if (this.activePanel &&
                this.activePanel.assetId === assetId &&
                this.activePanel.panel === panelName) {
                this.closePanel();
                return;
            }

            // Close any open panel first
            this.closePanel(function() {
                WPPuller.openPanel(panelName, assetId);
            });
        },

        /**
         * Open a specific panel for a given asset.
         *
         * @param {string} panelName
         * @param {string} assetId
         */
        openPanel: function(panelName, assetId) {
            var $panel = $('#wp-puller-panel-' + panelName);
            if (!$panel.length) {
                return;
            }

            // Highlight the active card
            $('.wp-puller-asset-card').removeClass('wp-puller-card-active');
            $('.wp-puller-asset-card[data-asset-id="' + assetId + '"]').addClass('wp-puller-card-active');

            // Populate the panel with asset data
            this.populatePanel(panelName, assetId, $panel);

            // Show with animation
            $panel.attr('data-asset-id', assetId);
            $panel.stop(true, true).slideDown(250);

            this.activePanel = {
                assetId: assetId,
                panel: panelName
            };
        },

        /**
         * Close the currently open panel.
         *
         * @param {Function} [callback] - Called after the panel is closed.
         */
        closePanel: function(callback) {
            if (!this.activePanel) {
                if (typeof callback === 'function') {
                    callback();
                }
                return;
            }

            var $panel = $('#wp-puller-panel-' + this.activePanel.panel);
            $('.wp-puller-asset-card').removeClass('wp-puller-card-active');
            this.activePanel = null;

            $panel.stop(true, true).slideUp(200, function() {
                $panel.removeAttr('data-asset-id');
                if (typeof callback === 'function') {
                    callback();
                }
            });
        },

        /**
         * Populate panel content with asset-specific data.
         *
         * @param {string} panelName
         * @param {string} assetId
         * @param {jQuery} $panel
         */
        populatePanel: function(panelName, assetId, $panel) {
            var asset = wpPuller.assets[assetId];
            if (!asset) {
                return;
            }

            switch (panelName) {
                case 'settings':
                    this.populateSettingsPanel(assetId, asset, $panel);
                    break;
                case 'branches':
                    this.populateBranchesPanel(assetId, asset, $panel);
                    break;
                case 'backups':
                    this.populateBackupsPanel(assetId, asset, $panel);
                    break;
            }
        },

        /**
         * Fill the settings form with this asset's current configuration.
         */
        populateSettingsPanel: function(assetId, asset, $panel) {
            var status = asset.status || {};
            var $form = $panel.find('.wp-puller-settings-form');

            $form.attr('data-asset-id', assetId);

            $form.find('[name="repo_url"]').val(status.repo_url || '');
            $form.find('[name="branch"]').val(status.branch || '');
            $form.find('[name="slug"]').val(status.slug || asset.slug || '');
            $form.find('[name="path"]').val(status.path || '');
            $form.find('[name="type"]').val(asset.type || '');
            $form.find('[name="auto_update"]').prop('checked', !!status.auto_update);
            $form.find('[name="backup_count"]').val(status.backup_count || 3);

            // Set the hidden asset_id field
            $form.find('[name="asset_id"]').val(assetId);

            // Token handling
            var $patSection = $form.find('.wp-puller-pat-section');
            var $tokenChoice = $patSection.find('.wp-puller-token-choice');
            var $tokenSelect = $patSection.find('.wp-puller-token-select');
            var $newFields = $patSection.find('.wp-puller-new-token-fields');

            // Update token dropdown options dynamically
            if ($tokenSelect.length) {
                $tokenSelect.find('option:not(:first)').remove();
                if (wpPuller.tokens && wpPuller.tokens.length > 0) {
                    for (var i = 0; i < wpPuller.tokens.length; i++) {
                        var token = wpPuller.tokens[i];
                        var selected = (asset.token_id && token.id === asset.token_id) ? ' selected' : '';
                        var usedBy = token.used_by && token.used_by.length > 0 ? ' (used by: ' + token.used_by.join(', ') + ')' : '';
                        $tokenSelect.append(
                            '<option value="' + this.escapeHtml(token.id) + '"' + selected + '>' +
                            this.escapeHtml(token.label) + usedBy +
                            '</option>'
                        );
                    }
                }
            }

            // Set radio and visibility based on current token
            $form.find('[name="pat"]').val('');
            if ($tokenChoice.length && asset.token_id) {
                $tokenChoice.find('input[name="token_mode"][value="existing"]').prop('checked', true);
                $tokenSelect.show();
                $newFields.hide();
            } else if ($tokenChoice.length) {
                $tokenChoice.find('input[name="token_mode"][value="new"]').prop('checked', true);
                $tokenSelect.hide();
                $newFields.show();
            }

            // Show/hide the asset label
            $panel.find('.wp-puller-panel-asset-label').text(asset.label || assetId);
        },

        /**
         * Populate the branches panel and auto-refresh the branch list.
         */
        populateBranchesPanel: function(assetId, asset, $panel) {
            $panel.find('.wp-puller-panel-asset-label').text(asset.label || assetId);
            $panel.attr('data-asset-id', assetId);

            // Clear previous content
            var $branchList = $panel.find('.wp-puller-branch-list');
            $branchList.html('<p class="wp-puller-empty">Loading branches...</p>');

            // Hide any previous comparison
            $panel.find('.wp-puller-compare-panel').hide();

            // Auto-fetch branches
            this.fetchBranches(assetId, $panel);
        },

        /**
         * Populate the backups panel.
         */
        populateBackupsPanel: function(assetId, asset, $panel) {
            $panel.find('.wp-puller-panel-asset-label').text(asset.label || assetId);
            $panel.attr('data-asset-id', assetId);

            // Hide all backup lists, show only the one for this asset.
            $panel.find('.wp-puller-backup-list-wrap').hide();
            var $assetBackups = $panel.find('.wp-puller-backup-list-wrap[data-asset-id="' + assetId + '"]');
            $assetBackups.show();

            // Update backup count badge.
            var count = $assetBackups.find('.wp-puller-backup-item').length;
            $panel.find('.wp-puller-backup-count-badge').text(count);
        },

        // =====================================================================
        // Settings: Save & Test Connection
        // =====================================================================

        saveSettings: function(e) {
            e.preventDefault();

            var $form = $(e.currentTarget);
            var $btn = $form.find('[type="submit"]');
            var assetId = $form.attr('data-asset-id') || $form.find('[name="asset_id"]').val();

            if (!assetId) {
                this.showNotice('No asset selected.', 'error');
                return;
            }

            this.setLoading($btn, true);

            var data = {
                action: 'wp_puller_save_settings',
                nonce: wpPuller.nonce,
                asset_id: assetId,
                repo_url: $form.find('[name="repo_url"]').val(),
                branch: $form.find('[name="branch"]').val(),
                slug: $form.find('[name="slug"]').val(),
                path: $form.find('[name="path"]').val(),
                type: $form.find('[name="type"]').val(),
                pat: $form.find('[name="pat"]').val(),
                reuse_token_id: $form.find('[name="token_id"]').val() || '',
                auto_update: $form.find('[name="auto_update"]').is(':checked') ? 'true' : 'false',
                backup_count: $form.find('[name="backup_count"]').val()
            };

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        WPPuller.showNotice(response.data.message || wpPuller.strings.saved, 'success');

                        // Update local asset data
                        if (response.data.status) {
                            wpPuller.assets[assetId].status = response.data.status;
                        }
                        if (response.data.info) {
                            wpPuller.assets[assetId].info = response.data.info;
                        }

                        WPPuller.updateCardUI(assetId, response.data);
                    } else {
                        WPPuller.showNotice(response.data.message || wpPuller.strings.error, 'error');
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

            var data = {
                action: 'wp_puller_test_connection',
                nonce: wpPuller.nonce,
                repo_url: repoUrl,
                pat: $form.find('[name="pat"]').val(),
                token_id: $form.find('[name="token_id"]').val()
            };

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: data,
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
                        WPPuller.showNotice(response.data.message || wpPuller.strings.error, 'error');
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

        // =====================================================================
        // Per-Card: Check Updates / Update Now
        // =====================================================================

        checkUpdates: function(e) {
            var $btn = $(e.currentTarget);
            var $card = $btn.closest('.wp-puller-asset-card');
            var assetId = $card.data('asset-id');
            var $result = $card.find('.wp-puller-update-result');

            this.setLoading($btn, true);
            $result.hide().empty();

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_check_updates',
                    nonce: wpPuller.nonce,
                    asset_id: assetId
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        WPPuller.renderCheckResult($card, data);
                        $card.find('.wp-puller-last-check').text('just now');
                    } else {
                        WPPuller.showNotice(response.data.message || wpPuller.strings.error, 'error');
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
         * Render the result of a check-updates call into a card's result area.
         *
         * @param {jQuery} $card
         * @param {Object} data
         */
        renderCheckResult: function($card, data) {
            var $result = $card.find('.wp-puller-update-result');
            var html = '';

            if (data.is_new_setup) {
                html = '<p><strong>Ready to install.</strong> Click "Update Now" to pull from GitHub.</p>';
                if (data.latest_version) {
                    html += '<p>Version in repository: <strong>' + this.escapeHtml(data.latest_version) + '</strong></p>';
                }
                $result.removeClass('has-update no-update').addClass('has-update');
            } else if (data.update_available) {
                html = '<p><strong>Update available!</strong></p>';
                if (data.current_version || data.latest_version) {
                    html += '<p>';
                    if (data.current_version) {
                        html += 'Installed: <strong>' + this.escapeHtml(data.current_version) + '</strong>';
                    }
                    if (data.current_version && data.latest_version) {
                        html += ' &rarr; ';
                    }
                    if (data.latest_version) {
                        html += 'New: <strong>' + this.escapeHtml(data.latest_version) + '</strong>';
                    }
                    html += '</p>';
                }
                if (data.current_commit) {
                    html += '<p>Current: <code>' + this.escapeHtml(data.current_commit.substring(0, 7)) + '</code>';
                }
                if (data.latest_commit) {
                    var latestSha = typeof data.latest_commit === 'object' ? data.latest_commit.short_sha : String(data.latest_commit).substring(0, 7);
                    html += ' &rarr; Latest: <code>' + this.escapeHtml(latestSha) + '</code>';
                    if (data.latest_commit.message) {
                        html += ' - ' + this.escapeHtml(data.latest_commit.message.substring(0, 60));
                    }
                }
                html += '</p>';
                $result.removeClass('has-update no-update').addClass('has-update');
            } else {
                html = '<p><strong>Up to date.</strong></p>';
                if (data.current_version) {
                    html += '<p>Version: <strong>' + this.escapeHtml(data.current_version) + '</strong>';
                    if (data.latest_commit) {
                        var sha = typeof data.latest_commit === 'object' ? data.latest_commit.short_sha : String(data.latest_commit).substring(0, 7);
                        html += ' (commit: <code>' + this.escapeHtml(sha) + '</code>)';
                    }
                    html += '</p>';
                }
                $result.removeClass('has-update no-update').addClass('no-update');
            }

            $result.html(html).show();
        },

        updateAsset: function(e) {
            var $btn = $(e.currentTarget);
            var $card = $btn.closest('.wp-puller-asset-card');
            var assetId = $card.data('asset-id');

            this.setLoading($btn, true);

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_update_asset',
                    nonce: wpPuller.nonce,
                    asset_id: assetId
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.showNotice(response.data.message, 'success');

                        // Update local data
                        if (response.data.status) {
                            wpPuller.assets[assetId].status = response.data.status;
                        }
                        if (response.data.info) {
                            wpPuller.assets[assetId].info = response.data.info;
                        }

                        WPPuller.updateCardUI(assetId, response.data);
                        $card.find('.wp-puller-update-result').hide();
                    } else {
                        WPPuller.showNotice(response.data.message || wpPuller.strings.error, 'error');
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

        // =====================================================================
        // Global: Add New / Check All / Update All
        // =====================================================================

        /**
         * Add a new asset. Opens a modal asking for the type (theme or plugin).
         */
        addAsset: function(e) {
            e.preventDefault();

            var html = '<div class="wp-puller-modal-body">';
            html += '<h3>Add New Asset</h3>';
            html += '<p>What type of asset do you want to manage?</p>';
            html += '<div class="wp-puller-add-type-buttons">';
            html += '<button type="button" class="button button-primary wp-puller-add-type-btn" data-type="theme">Theme</button> ';
            html += '<button type="button" class="button button-primary wp-puller-add-type-btn" data-type="plugin">Plugin</button>';
            html += '</div>';
            html += '</div>';

            this.openModal(html);

            // Bind the type selection (one-time)
            $(document).off('click.wpPullerAddType').on('click.wpPullerAddType', '.wp-puller-add-type-btn', function() {
                var type = $(this).data('type');
                var $modalBtn = $(this);
                WPPuller.setLoading($modalBtn, true);

                $.ajax({
                    url: wpPuller.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wp_puller_add_asset',
                        nonce: wpPuller.nonce,
                        type: type
                    },
                    success: function(response) {
                        if (response.success) {
                            WPPuller.showNotice(response.data.message || 'Asset added.', 'success');
                            WPPuller.closeModal();
                            // Reload to show the new card
                            setTimeout(function() {
                                location.reload();
                            }, 800);
                        } else {
                            WPPuller.showNotice(response.data.message || wpPuller.strings.error, 'error');
                            WPPuller.setLoading($modalBtn, false);
                        }
                    },
                    error: function() {
                        WPPuller.showNotice(wpPuller.strings.error, 'error');
                        WPPuller.setLoading($modalBtn, false);
                    }
                });
            });
        },

        /**
         * Check for updates on all assets sequentially.
         */
        checkAll: function(e) {
            var $btn = $(e.currentTarget);
            var assetIds = Object.keys(wpPuller.assets);

            if (assetIds.length === 0) {
                this.showNotice('No assets configured.', 'info');
                return;
            }

            this.setLoading($btn, true);
            this.processAssetsSequentially(assetIds, 0, 'check', function() {
                WPPuller.setLoading($btn, false);
            });
        },

        /**
         * Update all assets sequentially. Requires confirmation.
         */
        updateAll: function(e) {
            var $btn = $(e.currentTarget);

            this.showConfirmModal(wpPuller.strings.confirmUpdateAll, function() {
                var assetIds = Object.keys(wpPuller.assets);

                if (assetIds.length === 0) {
                    WPPuller.showNotice('No assets configured.', 'info');
                    return;
                }

                WPPuller.setLoading($btn, true);
                WPPuller.processAssetsSequentially(assetIds, 0, 'update', function() {
                    WPPuller.setLoading($btn, false);
                });
            });
        },

        /**
         * Process assets one by one for check or update.
         *
         * @param {string[]} assetIds
         * @param {number} index
         * @param {string} mode - 'check' or 'update'
         * @param {Function} doneCallback
         */
        processAssetsSequentially: function(assetIds, index, mode, doneCallback) {
            if (index >= assetIds.length) {
                if (typeof doneCallback === 'function') {
                    doneCallback();
                }
                return;
            }

            var assetId = assetIds[index];
            var $card = $('.wp-puller-asset-card[data-asset-id="' + assetId + '"]');
            var action = mode === 'check' ? 'wp_puller_check_updates' : 'wp_puller_update_asset';

            // Visual indicator: highlight the card being processed
            $card.addClass('wp-puller-card-processing');

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    nonce: wpPuller.nonce,
                    asset_id: assetId
                },
                success: function(response) {
                    if (response.success) {
                        if (mode === 'check') {
                            WPPuller.renderCheckResult($card, response.data);
                            $card.find('.wp-puller-last-check').text('just now');
                        } else {
                            WPPuller.updateCardUI(assetId, response.data);
                            $card.find('.wp-puller-update-result').hide();
                        }
                    }
                },
                complete: function() {
                    $card.removeClass('wp-puller-card-processing');
                    // Process next asset
                    WPPuller.processAssetsSequentially(assetIds, index + 1, mode, doneCallback);
                }
            });
        },

        // =====================================================================
        // Remove Asset
        // =====================================================================

        removeAsset: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var assetId;

            // Try to get asset ID from the closest settings form, panel, or card
            var $form = $btn.closest('.wp-puller-settings-form');
            if ($form.length) {
                assetId = $form.attr('data-asset-id') || $form.find('[name="asset_id"]').val();
            }
            if (!assetId) {
                var $panel = $btn.closest('[data-asset-id]');
                assetId = $panel.data('asset-id');
            }
            if (!assetId) {
                var $card = $btn.closest('.wp-puller-asset-card');
                assetId = $card.data('asset-id');
            }

            if (!assetId) {
                this.showNotice('Unable to determine asset.', 'error');
                return;
            }

            this.showConfirmModal(wpPuller.strings.confirmRemove, function() {
                WPPuller.setLoading($btn, true);

                $.ajax({
                    url: wpPuller.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wp_puller_remove_asset',
                        nonce: wpPuller.nonce,
                        asset_id: assetId
                    },
                    success: function(response) {
                        if (response.success) {
                            WPPuller.showNotice(response.data.message || 'Asset removed.', 'success');

                            // Close panel if it's for this asset
                            if (WPPuller.activePanel && WPPuller.activePanel.assetId === assetId) {
                                WPPuller.closePanel();
                            }

                            // Remove the card from the DOM
                            var $card = $('.wp-puller-asset-card[data-asset-id="' + assetId + '"]');
                            $card.fadeOut(300, function() {
                                $(this).remove();
                            });

                            // Remove from local data
                            delete wpPuller.assets[assetId];
                        } else {
                            WPPuller.showNotice(response.data.message || wpPuller.strings.error, 'error');
                        }
                    },
                    error: function() {
                        WPPuller.showNotice(wpPuller.strings.error, 'error');
                    },
                    complete: function() {
                        WPPuller.setLoading($btn, false);
                    }
                });
            });
        },

        // =====================================================================
        // Branch Testing
        // =====================================================================

        refreshBranchList: function(e) {
            var $btn = $(e.currentTarget);
            var $panel = $btn.closest('[data-asset-id]');
            var assetId = $panel.data('asset-id');

            if (!assetId) {
                return;
            }

            this.setLoading($btn, true);
            this.fetchBranches(assetId, $panel, function() {
                WPPuller.setLoading($btn, false);
            });
        },

        /**
         * Fetch branches for a given asset and render into the panel.
         *
         * @param {string} assetId
         * @param {jQuery} $panel
         * @param {Function} [callback]
         */
        fetchBranches: function(assetId, $panel, callback) {
            var $container = $panel.find('.wp-puller-branch-list');
            $container.html('<p class="wp-puller-empty">Loading branches...</p>');

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_get_branches_with_info',
                    nonce: wpPuller.nonce,
                    asset_id: assetId
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.renderBranchList($container, response.data, assetId);
                    } else {
                        $container.html('<p class="wp-puller-empty">' + WPPuller.escapeHtml(response.data.message) + '</p>');
                    }
                },
                error: function() {
                    $container.html('<p class="wp-puller-empty">Failed to load branches.</p>');
                },
                complete: function() {
                    if (typeof callback === 'function') {
                        callback();
                    }
                }
            });
        },

        /**
         * Render the branch table into a container.
         *
         * @param {jQuery} $container
         * @param {Object} data - { branches, configured, deployed_branch }
         * @param {string} assetId
         */
        renderBranchList: function($container, data, assetId) {
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
                if (isDeployed) {
                    html += ' wp-puller-branch-deployed';
                }
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
                html += '<button type="button" class="button button-small wp-puller-compare-branch" data-branch="' + this.escapeHtml(b.name) + '" data-asset-id="' + this.escapeHtml(assetId) + '" title="Compare with deployed">';
                html += '<span class="dashicons dashicons-randomize"></span>';
                html += '</button> ';
                if (!isDeployed) {
                    html += '<button type="button" class="button button-small button-primary wp-puller-deploy-branch" data-branch="' + this.escapeHtml(b.name) + '" data-asset-id="' + this.escapeHtml(assetId) + '">';
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
            var assetId = $btn.data('asset-id') || $btn.closest('[data-asset-id]').data('asset-id');

            if (!assetId) {
                this.showNotice('Unable to determine asset.', 'error');
                return;
            }

            this.showConfirmModal(wpPuller.strings.confirmBranchDeploy, function() {
                WPPuller.setLoading($btn, true);

                $.ajax({
                    url: wpPuller.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wp_puller_deploy_branch',
                        nonce: wpPuller.nonce,
                        asset_id: assetId,
                        branch: branch
                    },
                    success: function(response) {
                        if (response.success) {
                            WPPuller.showNotice(response.data.message, 'success');

                            // Update local data
                            if (response.data.status) {
                                wpPuller.assets[assetId].status = response.data.status;
                            }
                            if (response.data.info) {
                                wpPuller.assets[assetId].info = response.data.info;
                            }
                            wpPuller.assets[assetId].deployedBranch = branch;

                            WPPuller.updateCardUI(assetId, response.data);

                            // Reload after a short delay to refresh the full UI state
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            WPPuller.showNotice(response.data.message || wpPuller.strings.error, 'error');
                        }
                    },
                    error: function() {
                        WPPuller.showNotice(wpPuller.strings.error, 'error');
                    },
                    complete: function() {
                        WPPuller.setLoading($btn, false);
                    }
                });
            });
        },

        // =====================================================================
        // Branch Comparison
        // =====================================================================

        compareBranch: function(e) {
            var $btn = $(e.currentTarget);
            var headBranch = $btn.data('branch');
            var assetId = $btn.data('asset-id') || $btn.closest('[data-asset-id]').data('asset-id');

            if (!assetId) {
                this.showNotice('Unable to determine asset.', 'error');
                return;
            }

            var asset = wpPuller.assets[assetId] || {};
            var baseBranch = asset.deployedBranch || asset.branch || (asset.status && asset.status.deployed_branch) || 'main';

            if (headBranch === baseBranch) {
                this.showNotice('Cannot compare a branch with itself.', 'info');
                return;
            }

            this.setLoading($btn, true);

            var $panel = $btn.closest('[data-asset-id]');
            var $comparePanel = $panel.find('.wp-puller-compare-panel');
            var $content = $panel.find('.wp-puller-compare-content');
            var $title = $panel.find('.wp-puller-compare-title');

            $title.text(baseBranch + ' ... ' + headBranch);
            $content.html('<p>Loading comparison...</p>');
            $comparePanel.show();

            $.ajax({
                url: wpPuller.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_puller_compare_branches',
                    nonce: wpPuller.nonce,
                    asset_id: assetId,
                    base: baseBranch,
                    head: headBranch
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.renderComparison($content, response.data);
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

        /**
         * Render comparison data into the comparison content area.
         *
         * @param {jQuery} $content
         * @param {Object} data
         */
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
                    if (f.additions > 0) {
                        html += '<span class="wp-puller-additions">+' + f.additions + '</span>';
                    }
                    if (f.deletions > 0) {
                        html += '<span class="wp-puller-deletions">-' + f.deletions + '</span>';
                    }
                    html += '</span>';
                    html += '</li>';
                }
                if (data.files.length > 30) {
                    html += '<li class="wp-puller-compare-more">... and ' + (data.files.length - 30) + ' more files</li>';
                }
                html += '</ul>';
                html += '</div>';
            }

            if (data.total_commits === 0 && (!data.files || data.files.length === 0)) {
                html = '<p class="wp-puller-empty">' + wpPuller.strings.noChanges + '</p>';
            }

            $content.html(html);
        },

        closeCompare: function(e) {
            $(e.currentTarget).closest('.wp-puller-compare-panel').hide();
        },

        // =====================================================================
        // Backups: Restore / Delete
        // =====================================================================

        restoreBackup: function(e) {
            var $btn = $(e.currentTarget);
            var backupName = $btn.data('name');
            var assetId = $btn.closest('[data-asset-id]').data('asset-id') || $btn.data('asset-id');

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
                    asset_id: assetId,
                    backup_name: backupName
                },
                success: function(response) {
                    if (response.success) {
                        WPPuller.showNotice(response.data.message || wpPuller.strings.restored, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        WPPuller.showNotice(response.data.message || wpPuller.strings.error, 'error');
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
                        $btn.closest('.wp-puller-backup-item').fadeOut(300, function() {
                            $(this).remove();
                        });
                        WPPuller.showNotice(response.data.message || wpPuller.strings.deleted, 'success');
                    } else {
                        WPPuller.showNotice(response.data.message || wpPuller.strings.error, 'error');
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

        // =====================================================================
        // Webhook: Copy / Regenerate Secret
        // =====================================================================

        copyToClipboard: function(e) {
            var $btn = $(e.currentTarget);
            var inputId = $btn.data('copy');
            var $input = $('#' + inputId);
            var textToCopy = $input.val() || $input.text();

            // Use the modern clipboard API if available, fall back to execCommand
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    WPPuller.flashCopyIcon($btn);
                }).catch(function() {
                    WPPuller.fallbackCopy($input, $btn);
                });
            } else {
                this.fallbackCopy($input, $btn);
            }
        },

        fallbackCopy: function($input, $btn) {
            $input.select();
            try {
                document.execCommand('copy');
                this.flashCopyIcon($btn);
            } catch (err) {
                window.prompt('Copy to clipboard:', $input.val());
            }
        },

        flashCopyIcon: function($btn) {
            $btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
            setTimeout(function() {
                $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
            }, 1500);
        },

        regenerateSecret: function(e) {
            var $btn = $(e.currentTarget);

            this.showConfirmModal('Regenerate the webhook secret? All existing webhook integrations will need to be updated.', function() {
                WPPuller.setLoading($btn, true);

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
                            WPPuller.showNotice(response.data.message || wpPuller.strings.regenerated, 'success');
                        } else {
                            WPPuller.showNotice(response.data.message || wpPuller.strings.error, 'error');
                        }
                    },
                    error: function() {
                        WPPuller.showNotice(wpPuller.strings.error, 'error');
                    },
                    complete: function() {
                        WPPuller.setLoading($btn, false);
                    }
                });
            });
        },

        // =====================================================================
        // Logs
        // =====================================================================

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
                        WPPuller.showNotice(response.data.message || wpPuller.strings.error, 'error');
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

        // =====================================================================
        // Card UI Updates
        // =====================================================================

        /**
         * Update a card's display elements after a save, update, or deploy.
         *
         * @param {string} assetId
         * @param {Object} responseData - response.data from AJAX
         */
        updateCardUI: function(assetId, responseData) {
            var $card = $('.wp-puller-asset-card[data-asset-id="' + assetId + '"]');
            if (!$card.length) {
                return;
            }

            var status = responseData.status || {};
            var info = responseData.info || {};

            // Version
            if (info.version) {
                $card.find('.wp-puller-asset-version').text(info.version);
            }

            // Name
            if (info.name) {
                $card.find('.wp-puller-asset-name').text(info.name);
            }

            // Commit
            if (status.short_commit) {
                $card.find('.wp-puller-current-commit').text(status.short_commit);
            }

            // Last check
            $card.find('.wp-puller-last-check').text('just now');

            // Status badge
            if (status.is_configured) {
                $card.find('.wp-puller-status-badge')
                    .removeClass('wp-puller-badge-warning wp-puller-badge-error')
                    .addClass('wp-puller-badge-success')
                    .text('Connected');
                $card.find('.wp-puller-check-updates, .wp-puller-update-now').prop('disabled', false);
            }
        },

        // =====================================================================
        // Modal System
        // =====================================================================

        /**
         * Open the generic modal with custom HTML content.
         *
         * @param {string} html - Inner HTML for the modal body.
         */
        openModal: function(html) {
            // Remove any existing modal
            this.closeModal();

            var modalHtml = '<div class="wp-puller-modal">';
            modalHtml += '<div class="wp-puller-modal-dialog">';
            modalHtml += '<div class="wp-puller-modal-content">';
            modalHtml += html;
            modalHtml += '</div>';
            modalHtml += '</div>';
            modalHtml += '</div>';

            $('body').append(modalHtml);
            $('.wp-puller-modal').fadeIn(150);
        },

        /**
         * Open a confirmation modal with a message and confirm/cancel buttons.
         *
         * @param {string} message
         * @param {Function} onConfirm - Called when the user confirms.
         */
        showConfirmModal: function(message, onConfirm) {
            var html = '<div class="wp-puller-modal-body">';
            html += '<p>' + this.escapeHtml(message) + '</p>';
            html += '<div class="wp-puller-modal-actions">';
            html += '<button type="button" class="button button-primary wp-puller-modal-confirm">Confirm</button> ';
            html += '<button type="button" class="button wp-puller-modal-cancel">Cancel</button>';
            html += '</div>';
            html += '</div>';

            this.openModal(html);

            // Bind confirm handler (one-time)
            $(document).off('click.wpPullerConfirm').on('click.wpPullerConfirm', '.wp-puller-modal-confirm', function() {
                WPPuller.closeModal();
                $(document).off('click.wpPullerConfirm');
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            });
        },

        /**
         * Close the modal.
         */
        closeModal: function() {
            var $modal = $('.wp-puller-modal');
            if ($modal.length) {
                $modal.fadeOut(100, function() {
                    $(this).remove();
                });
            }
            // Clean up any namespaced event handlers
            $(document).off('click.wpPullerAddType');
            $(document).off('click.wpPullerConfirm');
        },

        /**
         * Close modal when clicking the backdrop (outside the dialog).
         */
        onModalBackdropClick: function(e) {
            if ($(e.target).hasClass('wp-puller-modal')) {
                this.closeModal();
            }
        },

        // =====================================================================
        // Utility Functions
        // =====================================================================

        /**
         * Set or unset the loading state on a button.
         *
         * @param {jQuery} $btn
         * @param {boolean} loading
         */
        setLoading: function($btn, loading) {
            if (loading) {
                $btn.addClass('wp-puller-btn-loading').prop('disabled', true);
            } else {
                $btn.removeClass('wp-puller-btn-loading').prop('disabled', false);
            }
        },

        /**
         * Show a notice message at the top of the admin page.
         *
         * @param {string} message
         * @param {string} [type='info'] - 'success', 'error', or 'info'
         */
        showNotice: function(message, type) {
            var $notice = $('#wp-puller-notice');
            var className = 'wp-puller-notice-' + (type || 'info');

            $notice
                .removeClass('wp-puller-notice-success wp-puller-notice-error wp-puller-notice-info wp-puller-notice-warning')
                .addClass(className)
                .html(this.escapeHtml(message))
                .stop(true, true)
                .fadeIn(200);

            setTimeout(function() {
                $notice.fadeOut(400);
            }, 5000);

            // Scroll to notice area if not visible
            var noticeTop = $notice.offset().top;
            var scrollTop = $(window).scrollTop();
            if (noticeTop < scrollTop || noticeTop > scrollTop + $(window).height()) {
                $('html, body').animate({
                    scrollTop: Math.max(0, noticeTop - 50)
                }, 300);
            }
        },

        /**
         * Escape a string for safe HTML insertion.
         *
         * @param {string} str
         * @return {string}
         */
        escapeHtml: function(str) {
            if (!str) {
                return '';
            }
            if (typeof str !== 'string') {
                str = String(str);
            }
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        WPPuller.init();
    });

})(jQuery);
