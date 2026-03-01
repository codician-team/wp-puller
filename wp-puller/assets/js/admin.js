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

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Escape a string for safe HTML insertion.
     *
     * @param {string} str
     * @return {string}
     */
    function escapeHtml(str) {
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

    /**
     * AJAX helper. Returns a jQuery AJAX promise.
     * Always includes the nonce and handles common error display.
     *
     * @param {string} action  The wp_ajax action name.
     * @param {Object} [data]  Additional POST data.
     * @return {Object} jQuery jqXHR promise.
     */
    function doAjax(action, data) {
        var payload = $.extend({}, data || {}, {
            action: action,
            nonce: wpPuller.nonce
        });

        return $.ajax({
            url: wpPuller.ajaxUrl,
            type: 'POST',
            data: payload
        }).fail(function() {
            showNotice(wpPuller.strings.error, 'error');
        });
    }

    /**
     * Show a notice message at the top of the admin page.
     *
     * @param {string} message
     * @param {string} [type='success'] - 'success', 'error', 'warning'
     */
    function showNotice(message, type) {
        var $notice = $('#wp-puller-notice');
        type = type || 'success';

        $notice
            .removeClass('notice-success notice-error notice-warning')
            .addClass('notice-' + type)
            .html(escapeHtml(message))
            .stop(true, true)
            .fadeIn(200);

        // Auto-hide after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(400);
        }, 5000);

        // Scroll to notice if it is off-screen
        var noticeTop = $notice.offset().top;
        var scrollTop = $(window).scrollTop();
        if (noticeTop < scrollTop || noticeTop > scrollTop + $(window).height()) {
            $('html, body').animate({
                scrollTop: Math.max(0, noticeTop - 50)
            }, 300);
        }
    }

    /**
     * Show the confirmation modal.
     *
     * @param {string}   title     Modal title text.
     * @param {string}   message   Modal body message.
     * @param {Function} onConfirm Callback executed when the user clicks Confirm.
     */
    function showModal(title, message, onConfirm) {
        var $modal = $('#wp-puller-modal');

        $modal.find('.wp-puller-modal-title').text(title);
        $modal.find('.wp-puller-modal-message').text(message);
        $modal.fadeIn(150);

        // Unbind previous handlers to avoid stacking
        $modal.find('.wp-puller-modal-confirm').off('click.wpPullerModal');
        $modal.find('.wp-puller-modal-cancel').off('click.wpPullerModal');
        $modal.find('.wp-puller-modal-close').off('click.wpPullerModal');
        $modal.find('.wp-puller-modal-overlay').off('click.wpPullerModal');

        // Confirm
        $modal.find('.wp-puller-modal-confirm').on('click.wpPullerModal', function() {
            closeModal();
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        });

        // Cancel / close / overlay
        $modal.find('.wp-puller-modal-cancel').on('click.wpPullerModal', function() {
            closeModal();
        });
        $modal.find('.wp-puller-modal-close').on('click.wpPullerModal', function() {
            closeModal();
        });
        $modal.find('.wp-puller-modal-overlay').on('click.wpPullerModal', function() {
            closeModal();
        });
    }

    /**
     * Close the confirmation modal.
     */
    function closeModal() {
        var $modal = $('#wp-puller-modal');
        $modal.fadeOut(100);
        $modal.find('.wp-puller-modal-confirm').off('click.wpPullerModal');
        $modal.find('.wp-puller-modal-cancel').off('click.wpPullerModal');
        $modal.find('.wp-puller-modal-close').off('click.wpPullerModal');
        $modal.find('.wp-puller-modal-overlay').off('click.wpPullerModal');
    }

    /**
     * Set or unset the loading / spinner state on a button.
     *
     * @param {jQuery}  $btn
     * @param {boolean} loading
     */
    function setLoading($btn, loading) {
        if (loading) {
            $btn.addClass('wp-puller-btn-loading').prop('disabled', true);
        } else {
            $btn.removeClass('wp-puller-btn-loading').prop('disabled', false);
        }
    }

    // =========================================================================
    // Currently active panel tracker
    // =========================================================================

    var activePanel = null; // { assetId: string, panel: string } or null

    // =========================================================================
    // Panel Management
    // =========================================================================

    /**
     * Open a panel for a given asset.
     * Only one panel may be open at a time.
     *
     * @param {string} panelName  'settings', 'branches', or 'backups'
     * @param {string} assetId
     */
    function openPanel(panelName, assetId) {
        var $panel = $('#wp-puller-panel-' + panelName);
        if (!$panel.length) {
            return;
        }

        var asset = wpPuller.assets[assetId];
        if (!asset) {
            return;
        }

        // Highlight active card
        $('.wp-puller-asset-card').removeClass('wp-puller-card-active');
        $('.wp-puller-asset-card[data-asset-id="' + assetId + '"]').addClass('wp-puller-card-active');

        // Store asset id on the panel element
        $panel.attr('data-asset-id', assetId);

        // Update the panel asset label
        $panel.find('.wp-puller-panel-asset-label').text(asset.info && asset.info.name ? asset.info.name : (asset.label || asset.slug || assetId));

        // Populate panel-specific content
        switch (panelName) {
            case 'settings':
                populateSettingsPanel(assetId, asset, $panel);
                break;
            case 'branches':
                populateBranchesPanel(assetId, asset, $panel);
                break;
            case 'backups':
                populateBackupsPanel(assetId, asset, $panel);
                break;
        }

        // Slide down
        $panel.stop(true, true).slideDown(250);

        activePanel = {
            assetId: assetId,
            panel: panelName
        };
    }

    /**
     * Close the currently open panel.
     *
     * @param {Function} [callback] Called after the panel finishes closing.
     */
    function closePanel(callback) {
        if (!activePanel) {
            if (typeof callback === 'function') {
                callback();
            }
            return;
        }

        var $panel = $('#wp-puller-panel-' + activePanel.panel);
        $('.wp-puller-asset-card').removeClass('wp-puller-card-active');
        activePanel = null;

        $panel.stop(true, true).slideUp(200, function() {
            $panel.attr('data-asset-id', '');
            if (typeof callback === 'function') {
                callback();
            }
        });
    }

    // =========================================================================
    // Settings Panel Population
    // =========================================================================

    /**
     * Fill the settings form fields from the asset's configuration data.
     *
     * @param {string} assetId
     * @param {Object} asset   wpPuller.assets[assetId]
     * @param {jQuery} $panel
     */
    function populateSettingsPanel(assetId, asset, $panel) {
        var $form = $panel.find('.wp-puller-settings-form');
        var status = asset.status || {};

        // Basic fields
        $form.find('[name="repo_url"]').val(status.repo_url || '');
        $form.find('[name="branch"]').val(asset.branch || '');
        $form.find('[name="slug"]').val(asset.slug || '');
        $form.find('[name="path"]').val(status.path || '');
        $form.find('[name="type"]').val(asset.type || 'plugin');

        // Auto-update checkbox
        var autoUpdate = status.auto_update !== undefined ? status.auto_update : true;
        $form.find('[name="auto_update"]').prop('checked', !!autoUpdate);

        // Backup count select
        $form.find('[name="backup_count"]').val(status.backup_count || 3);

        // Token handling
        var $tokenChoice = $form.find('.wp-puller-token-choice');
        var $tokenSelect = $form.find('.wp-puller-token-select');
        var $newTokenFields = $form.find('.wp-puller-new-token-fields');

        if ($tokenChoice.length && wpPuller.tokens && wpPuller.tokens.length > 0) {
            // Tokens exist — use the radio toggle
            if (asset.token_id) {
                // Pre-select existing radio
                $form.find('input[name="token_mode"][value="existing"]').prop('checked', true);
                $tokenSelect.show();
                $newTokenFields.hide();
                // Select the matching token in the dropdown
                $form.find('select[name="token_id"]').val(asset.token_id);
            } else {
                // Default to new
                $form.find('input[name="token_mode"][value="new"]').prop('checked', true);
                $tokenSelect.hide();
                $newTokenFields.show();
            }
        } else {
            // No token choice UI — just clear new token fields
            $form.find('[name="pat"]').val('');
            $form.find('[name="token_label"]').val('');
        }
    }

    // =========================================================================
    // Branches Panel Population
    // =========================================================================

    /**
     * Set up the branches panel. Clears previous data and shows a loading message.
     */
    function populateBranchesPanel(assetId, asset, $panel) {
        var $branchList = $panel.find('.wp-puller-branch-list');
        $branchList.html('<p class="wp-puller-empty">Loading branches...</p>');

        // Hide any previous comparison
        $panel.find('.wp-puller-compare-panel').hide();

        // Auto-fetch branches
        fetchBranches(assetId, $panel);
    }

    // =========================================================================
    // Backups Panel Population
    // =========================================================================

    /**
     * Show the correct backup list for the selected asset and hide others.
     */
    function populateBackupsPanel(assetId, asset, $panel) {
        // Hide all backup list wraps, then show the one for this asset
        $panel.find('.wp-puller-backup-list-wrap').hide();
        $panel.find('.wp-puller-backup-list-wrap[data-asset-id="' + assetId + '"]').show();

        // Update the count badge
        var $wrap = $panel.find('.wp-puller-backup-list-wrap[data-asset-id="' + assetId + '"]');
        var count = $wrap.find('.wp-puller-backup-item').length;
        $panel.find('.wp-puller-backup-count-badge').text(count);
    }

    // =========================================================================
    // Card UI Updates
    // =========================================================================

    /**
     * Update a card's display elements after a save, update, or deploy.
     *
     * @param {string} assetId
     * @param {Object} responseData  response.data from AJAX
     */
    function updateCardUI(assetId, responseData) {
        var $card = $('.wp-puller-asset-card[data-asset-id="' + assetId + '"]');
        if (!$card.length) {
            return;
        }

        var status = responseData.status || {};
        var info = responseData.info || {};

        // Version
        if (info.version) {
            $card.find('.wp-puller-asset-version').text('v' + info.version);
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
    }

    /**
     * Render the result of a check-updates call into a card's .wp-puller-update-result area.
     *
     * @param {jQuery} $card
     * @param {Object} data
     */
    function renderCheckResult($card, data) {
        var $result = $card.find('.wp-puller-update-result');
        var html = '';

        if (data.is_new_setup) {
            html = '<p><strong>Ready to install.</strong> Click "Update Now" to pull from GitHub.</p>';
            if (data.latest_version) {
                html += '<p>Version in repository: <strong>' + escapeHtml(data.latest_version) + '</strong></p>';
            }
            $result.removeClass('has-update no-update').addClass('has-update');
        } else if (data.update_available || data.has_update) {
            html = '<p><strong>Update available!</strong></p>';
            if (data.message) {
                html += '<p>' + escapeHtml(data.message) + '</p>';
            }
            if (data.current_version || data.latest_version) {
                html += '<p>';
                if (data.current_version) {
                    html += 'Installed: <strong>' + escapeHtml(data.current_version) + '</strong>';
                }
                if (data.current_version && data.latest_version) {
                    html += ' &rarr; ';
                }
                if (data.latest_version) {
                    html += 'New: <strong>' + escapeHtml(data.latest_version) + '</strong>';
                }
                html += '</p>';
            }
            if (data.current_commit) {
                html += '<p>Current: <code>' + escapeHtml(String(data.current_commit).substring(0, 7)) + '</code>';
            }
            if (data.latest_commit) {
                var latestSha = typeof data.latest_commit === 'object' ? data.latest_commit.short_sha : String(data.latest_commit).substring(0, 7);
                html += ' &rarr; Latest: <code>' + escapeHtml(latestSha) + '</code>';
                if (typeof data.latest_commit === 'object' && data.latest_commit.message) {
                    html += ' - ' + escapeHtml(data.latest_commit.message.substring(0, 60));
                }
            }
            html += '</p>';
            $result.removeClass('has-update no-update').addClass('has-update');
        } else {
            html = '<p><strong>Up to date.</strong></p>';
            if (data.message) {
                html += '<p>' + escapeHtml(data.message) + '</p>';
            }
            if (data.current_version) {
                html += '<p>Version: <strong>' + escapeHtml(data.current_version) + '</strong>';
                if (data.latest_commit) {
                    var sha = typeof data.latest_commit === 'object' ? data.latest_commit.short_sha : String(data.latest_commit).substring(0, 7);
                    html += ' (commit: <code>' + escapeHtml(sha) + '</code>)';
                }
                html += '</p>';
            }
            $result.removeClass('has-update no-update').addClass('no-update');
        }

        $result.html(html).show();
    }

    // =========================================================================
    // Branch Helpers
    // =========================================================================

    /**
     * Fetch branches for a given asset and render them into the panel.
     *
     * @param {string}   assetId
     * @param {jQuery}   $panel
     * @param {Function} [callback]
     */
    function fetchBranches(assetId, $panel, callback) {
        var $container = $panel.find('.wp-puller-branch-list');
        $container.html('<p class="wp-puller-empty">Loading branches...</p>');

        doAjax('wp_puller_get_branches_with_info', {
            asset_id: assetId
        }).done(function(response) {
            if (response.success) {
                renderBranchList($container, response.data, assetId);
            } else {
                $container.html('<p class="wp-puller-empty">' + escapeHtml(response.data.message) + '</p>');
            }
        }).fail(function() {
            $container.html('<p class="wp-puller-empty">Failed to load branches.</p>');
        }).always(function() {
            if (typeof callback === 'function') {
                callback();
            }
        });
    }

    /**
     * Render the branch table into a container.
     *
     * @param {jQuery} $container
     * @param {Object} data      { branches, configured, deployed_branch }
     * @param {string} assetId
     */
    function renderBranchList($container, data, assetId) {
        var branches = data.branches;
        var configured = data.configured;
        var deployed = data.deployed_branch;

        if (!branches || branches.length === 0) {
            $container.html('<p class="wp-puller-empty">No branches found.</p>');
            return;
        }

        var html = '<table class="widefat wp-puller-branch-table">';
        html += '<thead><tr>';
        html += '<th>Branch</th>';
        html += '<th>Last Commit</th>';
        html += '<th>Author</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead>';
        html += '<tbody>';

        for (var i = 0; i < branches.length; i++) {
            var b = branches[i];
            var isConfigured = (b.name === configured);
            var isDeployed = (b.name === deployed);

            html += '<tr';
            if (isDeployed) {
                html += ' class="wp-puller-branch-deployed"';
            }
            html += '>';

            // Branch name column
            html += '<td>';
            html += escapeHtml(b.name);
            if (isConfigured) {
                html += ' <span class="wp-puller-badge">configured</span>';
            }
            if (isDeployed) {
                html += ' <span class="wp-puller-badge">deployed</span>';
            }
            html += '</td>';

            // Last commit column
            html += '<td>';
            html += '<code>' + escapeHtml(b.short_sha || '') + '</code> ';
            html += escapeHtml((b.message || '').split('\n')[0].substring(0, 60));
            html += '</td>';

            // Author column
            html += '<td>' + escapeHtml(b.author || '') + '</td>';

            // Actions column
            html += '<td>';
            html += '<button class="button button-small wp-puller-deploy-branch" data-branch="' + escapeHtml(b.name) + '">Deploy</button> ';
            html += '<button class="button button-small wp-puller-compare-branch" data-branch="' + escapeHtml(b.name) + '" data-base="' + escapeHtml(configured || 'main') + '">Compare</button>';
            html += '</td>';

            html += '</tr>';
        }

        html += '</tbody></table>';
        $container.html(html);
    }

    /**
     * Render comparison data into the compare content area.
     *
     * @param {jQuery} $content
     * @param {Object} data
     */
    function renderComparison($content, data) {
        var html = '';

        // Summary
        html += '<div class="wp-puller-compare-summary">';
        html += '<span class="wp-puller-compare-stat">';
        html += '<strong>' + (data.total_commits || 0) + '</strong> commit' + ((data.total_commits || 0) !== 1 ? 's' : '');
        html += '</span>';
        if (data.files) {
            html += '<span class="wp-puller-compare-stat">';
            html += '<strong>' + data.files.length + '</strong> file' + (data.files.length !== 1 ? 's' : '') + ' changed';
            html += '</span>';
        }
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
                html += '<code>' + escapeHtml(c.short_sha) + '</code> ';
                html += escapeHtml((c.message || '').split('\n')[0].substring(0, 80));
                html += ' <span class="wp-puller-compare-author">- ' + escapeHtml(c.author || '') + '</span>';
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
                var statusClass = 'wp-puller-file-' + (f.status || 'modified');
                var statusIcon = f.status === 'added' ? '+' : (f.status === 'removed' ? '-' : 'M');
                html += '<li class="' + statusClass + '">';
                html += '<span class="wp-puller-file-status">' + statusIcon + '</span> ';
                html += escapeHtml(f.filename);
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

        // Empty state
        if ((!data.total_commits || data.total_commits === 0) && (!data.files || data.files.length === 0)) {
            html = '<p class="wp-puller-empty">' + wpPuller.strings.noChanges + '</p>';
        }

        $content.html(html);
    }

    /**
     * Flash the copy button icon to indicate success.
     *
     * @param {jQuery} $btn
     */
    function flashCopyIcon($btn) {
        $btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
        setTimeout(function() {
            $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
        }, 1500);
    }

    /**
     * Fallback copy method using document.execCommand.
     *
     * @param {jQuery} $input
     * @param {jQuery} $btn
     */
    function fallbackCopy($input, $btn) {
        $input.select();
        try {
            document.execCommand('copy');
            flashCopyIcon($btn);
        } catch (err) {
            window.prompt('Copy to clipboard:', $input.val());
        }
    }

    // =========================================================================
    // Event Binding (document.ready)
    // =========================================================================

    $(document).ready(function() {

        // -----------------------------------------------------------------
        // Panel open / close
        // -----------------------------------------------------------------

        $(document).on('click', '.wp-puller-open-panel', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var panelName = $btn.data('panel');
            var assetId = $btn.data('asset-id');

            if (!panelName || !assetId) {
                return;
            }

            // If same panel for same asset is already open, toggle it closed
            if (activePanel &&
                activePanel.assetId === assetId &&
                activePanel.panel === panelName) {
                closePanel();
                return;
            }

            // Close any open panel first, then open the new one
            closePanel(function() {
                openPanel(panelName, assetId);
            });
        });

        $(document).on('click', '.wp-puller-close-panel', function(e) {
            e.preventDefault();
            closePanel();
        });

        // -----------------------------------------------------------------
        // Token mode toggle (radio buttons)
        // -----------------------------------------------------------------

        $(document).on('change', 'input[name="token_mode"]', function() {
            var mode = $(this).val();
            var $form = $(this).closest('form');
            var $tokenSelect = $form.find('.wp-puller-token-select');
            var $newTokenFields = $form.find('.wp-puller-new-token-fields');

            if (mode === 'existing') {
                $tokenSelect.show();
                $newTokenFields.hide();
            } else {
                $tokenSelect.hide();
                $newTokenFields.show();
            }
        });

        // -----------------------------------------------------------------
        // Settings form submit
        // -----------------------------------------------------------------

        $(document).on('submit', '.wp-puller-settings-form', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $btn = $form.find('[type="submit"]');
            var $panel = $form.closest('.wp-puller-panel');
            var assetId = $panel.attr('data-asset-id');

            if (!assetId) {
                showNotice('No asset selected.', 'error');
                return;
            }

            setLoading($btn, true);

            var tokenMode = $form.find('input[name="token_mode"]:checked').val() || '';
            var postData = {
                asset_id: assetId,
                repo_url: $form.find('[name="repo_url"]').val(),
                branch: $form.find('[name="branch"]').val(),
                slug: $form.find('[name="slug"]').val(),
                path: $form.find('[name="path"]').val(),
                type: $form.find('[name="type"]').val(),
                auto_update: $form.find('[name="auto_update"]').is(':checked') ? 'true' : 'false',
                backup_count: $form.find('[name="backup_count"]').val()
            };

            // Token handling based on mode
            if (tokenMode === 'existing') {
                postData.reuse_token_id = $form.find('select[name="token_id"]').val();
            } else {
                postData.pat = $form.find('[name="pat"]').val();
                postData.token_label = $form.find('[name="token_label"]').val();
            }

            doAjax('wp_puller_save_settings', postData).done(function(response) {
                if (response.success) {
                    showNotice(response.data.message || wpPuller.strings.saved, 'success');

                    // Update local asset data
                    if (response.data.status) {
                        wpPuller.assets[assetId].status = response.data.status;
                    }
                    if (response.data.info) {
                        wpPuller.assets[assetId].info = response.data.info;
                    }

                    updateCardUI(assetId, response.data);

                    // Reload page after 1 second to refresh server-rendered state
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotice(response.data.message || wpPuller.strings.error, 'error');
                }
            }).always(function() {
                setLoading($btn, false);
            });
        });

        // -----------------------------------------------------------------
        // Add New Asset
        // -----------------------------------------------------------------

        $(document).on('click', '#wp-puller-add-new', function(e) {
            e.preventDefault();
            var $btn = $(this);
            setLoading($btn, true);

            doAjax('wp_puller_add_asset', {
                type: 'plugin'
            }).done(function(response) {
                if (response.success) {
                    showNotice(response.data.message || 'Asset added.', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 800);
                } else {
                    showNotice(response.data.message || wpPuller.strings.error, 'error');
                    setLoading($btn, false);
                }
            }).fail(function() {
                setLoading($btn, false);
            });
        });

        // -----------------------------------------------------------------
        // Remove Asset
        // -----------------------------------------------------------------

        $(document).on('click', '.wp-puller-remove-item', function(e) {
            e.preventDefault();
            var $btn = $(this);

            // Determine asset ID from the closest panel
            var $panel = $btn.closest('.wp-puller-panel');
            var assetId = $panel.attr('data-asset-id');

            if (!assetId) {
                // Try from closest card
                var $card = $btn.closest('.wp-puller-asset-card');
                assetId = $card.data('asset-id');
            }

            if (!assetId) {
                showNotice('Unable to determine asset.', 'error');
                return;
            }

            showModal('Remove Item', wpPuller.strings.confirmRemove, function() {
                setLoading($btn, true);

                doAjax('wp_puller_remove_asset', {
                    asset_id: assetId
                }).done(function(response) {
                    if (response.success) {
                        showNotice(response.data.message || 'Asset removed.', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 800);
                    } else {
                        showNotice(response.data.message || wpPuller.strings.error, 'error');
                    }
                }).always(function() {
                    setLoading($btn, false);
                });
            });
        });

        // -----------------------------------------------------------------
        // Check for Updates (single asset)
        // -----------------------------------------------------------------

        $(document).on('click', '.wp-puller-check-updates', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var assetId = $btn.data('asset-id');
            var $card = $btn.closest('.wp-puller-asset-card');
            var $result = $card.find('.wp-puller-update-result');

            setLoading($btn, true);
            $result.hide().empty();

            doAjax('wp_puller_check_updates', {
                asset_id: assetId
            }).done(function(response) {
                if (response.success) {
                    renderCheckResult($card, response.data);
                    $card.find('.wp-puller-last-check').text('just now');
                } else {
                    showNotice(response.data.message || wpPuller.strings.error, 'error');
                }
            }).always(function() {
                setLoading($btn, false);
            });
        });

        // -----------------------------------------------------------------
        // Update Now (single asset)
        // -----------------------------------------------------------------

        $(document).on('click', '.wp-puller-update-now', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var assetId = $btn.data('asset-id');
            var $card = $btn.closest('.wp-puller-asset-card');

            setLoading($btn, true);

            doAjax('wp_puller_update_asset', {
                asset_id: assetId
            }).done(function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');

                    // Update local data
                    if (response.data.status) {
                        wpPuller.assets[assetId].status = response.data.status;
                    }
                    if (response.data.info) {
                        wpPuller.assets[assetId].info = response.data.info;
                    }

                    updateCardUI(assetId, response.data);
                    $card.find('.wp-puller-update-result').hide();
                } else {
                    showNotice(response.data.message || wpPuller.strings.error, 'error');
                }
            }).always(function() {
                setLoading($btn, false);
            });
        });

        // -----------------------------------------------------------------
        // Check All
        // -----------------------------------------------------------------

        $(document).on('click', '#wp-puller-check-all', function(e) {
            e.preventDefault();
            var $btn = $(this);

            setLoading($btn, true);

            doAjax('wp_puller_check_all').done(function(response) {
                if (response.success && response.data.results) {
                    var results = response.data.results;
                    // Iterate results and update each card
                    $.each(results, function(assetId, data) {
                        var $card = $('.wp-puller-asset-card[data-asset-id="' + assetId + '"]');
                        if ($card.length) {
                            if (data.error) {
                                $card.find('.wp-puller-update-result')
                                    .html('<p class="wp-puller-error">' + escapeHtml(data.error) + '</p>')
                                    .show();
                            } else {
                                renderCheckResult($card, data);
                            }
                            $card.find('.wp-puller-last-check').text('just now');
                        }
                    });
                } else {
                    showNotice(wpPuller.strings.error, 'error');
                }
            }).always(function() {
                setLoading($btn, false);
            });
        });

        // -----------------------------------------------------------------
        // Update All
        // -----------------------------------------------------------------

        $(document).on('click', '#wp-puller-update-all', function(e) {
            e.preventDefault();
            var $btn = $(this);

            showModal('Update All', wpPuller.strings.confirmUpdateAll, function() {
                setLoading($btn, true);

                doAjax('wp_puller_update_all').done(function(response) {
                    if (response.success) {
                        showNotice('All items updated.', 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        showNotice(wpPuller.strings.error, 'error');
                    }
                }).always(function() {
                    setLoading($btn, false);
                });
            });
        });

        // -----------------------------------------------------------------
        // Branch Testing: Refresh Branches
        // -----------------------------------------------------------------

        $(document).on('click', '.wp-puller-refresh-branches', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $panel = $btn.closest('.wp-puller-panel');
            var assetId = $panel.attr('data-asset-id');

            if (!assetId) {
                return;
            }

            setLoading($btn, true);
            fetchBranches(assetId, $panel, function() {
                setLoading($btn, false);
            });
        });

        // -----------------------------------------------------------------
        // Branch Testing: Deploy Branch (delegated for dynamic rows)
        // -----------------------------------------------------------------

        $(document).on('click', '.wp-puller-deploy-branch', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var branch = $btn.data('branch');
            var $panel = $btn.closest('.wp-puller-panel');
            var assetId = $panel.attr('data-asset-id');

            if (!assetId || !branch) {
                showNotice('Unable to determine asset or branch.', 'error');
                return;
            }

            showModal('Deploy Branch', wpPuller.strings.confirmBranchDeploy, function() {
                setLoading($btn, true);

                doAjax('wp_puller_deploy_branch', {
                    asset_id: assetId,
                    branch: branch
                }).done(function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');

                        // Update local data
                        if (response.data.status) {
                            wpPuller.assets[assetId].status = response.data.status;
                        }
                        if (response.data.info) {
                            wpPuller.assets[assetId].info = response.data.info;
                        }
                        wpPuller.assets[assetId].deployedBranch = branch;

                        updateCardUI(assetId, response.data);

                        // Reload to refresh full UI state
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotice(response.data.message || wpPuller.strings.error, 'error');
                    }
                }).always(function() {
                    setLoading($btn, false);
                });
            });
        });

        // -----------------------------------------------------------------
        // Branch Testing: Compare Branch (delegated for dynamic rows)
        // -----------------------------------------------------------------

        $(document).on('click', '.wp-puller-compare-branch', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var headBranch = $btn.data('branch');
            var baseBranch = $btn.data('base');
            var $panel = $btn.closest('.wp-puller-panel');
            var assetId = $panel.attr('data-asset-id');

            if (!assetId) {
                showNotice('Unable to determine asset.', 'error');
                return;
            }

            // Fallback for base branch
            if (!baseBranch) {
                var asset = wpPuller.assets[assetId] || {};
                baseBranch = asset.deployedBranch || asset.branch || 'main';
            }

            if (headBranch === baseBranch) {
                showNotice('Cannot compare a branch with itself.', 'warning');
                return;
            }

            setLoading($btn, true);

            var $comparePanel = $panel.find('.wp-puller-compare-panel');
            var $content = $panel.find('.wp-puller-compare-content');
            var $title = $panel.find('.wp-puller-compare-title');

            $title.text(baseBranch + ' ... ' + headBranch);
            $content.html('<p>Loading comparison...</p>');
            $comparePanel.show();

            doAjax('wp_puller_compare_branches', {
                asset_id: assetId,
                base: baseBranch,
                head: headBranch
            }).done(function(response) {
                if (response.success) {
                    renderComparison($content, response.data);
                } else {
                    $content.html('<p class="wp-puller-empty">' + escapeHtml(response.data.message) + '</p>');
                }
            }).fail(function() {
                $content.html('<p class="wp-puller-empty">Failed to load comparison.</p>');
            }).always(function() {
                setLoading($btn, false);
            });
        });

        // -----------------------------------------------------------------
        // Close Compare Panel
        // -----------------------------------------------------------------

        $(document).on('click', '.wp-puller-close-compare', function(e) {
            e.preventDefault();
            $(this).closest('.wp-puller-compare-panel').hide();
        });

        // -----------------------------------------------------------------
        // Backups: Restore
        // -----------------------------------------------------------------

        $(document).on('click', '.wp-puller-restore-backup', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var backupName = $btn.data('name');
            var assetId = $btn.data('asset-id');

            if (!assetId) {
                assetId = $btn.closest('.wp-puller-panel').attr('data-asset-id');
            }

            showModal('Restore Backup', wpPuller.strings.confirmRestore, function() {
                setLoading($btn, true);

                doAjax('wp_puller_restore_backup', {
                    asset_id: assetId,
                    backup_name: backupName
                }).done(function(response) {
                    if (response.success) {
                        showNotice(response.data.message || wpPuller.strings.restored, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotice(response.data.message || wpPuller.strings.error, 'error');
                    }
                }).always(function() {
                    setLoading($btn, false);
                });
            });
        });

        // -----------------------------------------------------------------
        // Backups: Delete
        // -----------------------------------------------------------------

        $(document).on('click', '.wp-puller-delete-backup', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var backupName = $btn.data('name');

            showModal('Delete Backup', wpPuller.strings.confirmDelete, function() {
                setLoading($btn, true);

                doAjax('wp_puller_delete_backup', {
                    asset_id: $btn.data('asset-id'),
                    backup_name: backupName
                }).done(function(response) {
                    if (response.success) {
                        $btn.closest('.wp-puller-backup-item').fadeOut(300, function() {
                            $(this).remove();
                        });
                        showNotice(response.data.message || wpPuller.strings.deleted, 'success');
                    } else {
                        showNotice(response.data.message || wpPuller.strings.error, 'error');
                    }
                }).always(function() {
                    setLoading($btn, false);
                });
            });
        });

        // -----------------------------------------------------------------
        // Test Connection
        // -----------------------------------------------------------------

        $(document).on('click', '.wp-puller-test-connection', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $form = $btn.closest('.wp-puller-settings-form');
            var repoUrl = $form.find('[name="repo_url"]').val();

            if (!repoUrl) {
                showNotice('Please enter a repository URL.', 'error');
                return;
            }

            setLoading($btn, true);

            var tokenMode = $form.find('input[name="token_mode"]:checked').val() || '';
            var testData = {
                repo_url: repoUrl
            };

            if (tokenMode === 'existing') {
                testData.token_id = $form.find('select[name="token_id"]').val();
            } else {
                testData.pat = $form.find('[name="pat"]').val();
            }

            doAjax('wp_puller_test_connection', testData).done(function(response) {
                if (response.success) {
                    var msg = wpPuller.strings.connected;
                    if (response.data.repo) {
                        msg += ' Repository: ' + response.data.repo.full_name;
                        if (response.data.repo.private) {
                            msg += ' (Private)';
                        }
                    }
                    showNotice(msg, 'success');
                } else {
                    showNotice(response.data.message || wpPuller.strings.error, 'error');
                }
            }).always(function() {
                setLoading($btn, false);
            });
        });

        // -----------------------------------------------------------------
        // Regenerate Webhook Secret
        // -----------------------------------------------------------------

        $(document).on('click', '#wp-puller-regenerate-secret', function(e) {
            e.preventDefault();
            var $btn = $(this);

            setLoading($btn, true);

            doAjax('wp_puller_regenerate_secret').done(function(response) {
                if (response.success) {
                    $('#webhook-secret').val(response.data.secret);
                    showNotice(response.data.message || wpPuller.strings.regenerated, 'success');
                } else {
                    showNotice(response.data.message || wpPuller.strings.error, 'error');
                }
            }).always(function() {
                setLoading($btn, false);
            });
        });

        // -----------------------------------------------------------------
        // Clear Logs
        // -----------------------------------------------------------------

        $(document).on('click', '#wp-puller-clear-logs', function(e) {
            e.preventDefault();
            var $btn = $(this);

            setLoading($btn, true);

            doAjax('wp_puller_clear_logs').done(function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    showNotice(response.data.message || wpPuller.strings.error, 'error');
                }
            }).always(function() {
                setLoading($btn, false);
            });
        });

        // -----------------------------------------------------------------
        // Copy to Clipboard
        // -----------------------------------------------------------------

        $(document).on('click', '.wp-puller-copy-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var inputId = $btn.data('copy');
            var $input = $('#' + inputId);
            var textToCopy = $input.val() || $input.text();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    flashCopyIcon($btn);
                }).catch(function() {
                    fallbackCopy($input, $btn);
                });
            } else {
                fallbackCopy($input, $btn);
            }
        });

    }); // end document.ready

})(jQuery);
