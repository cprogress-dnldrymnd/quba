jQuery(document).ready(function ($) {
    var $progressBar = $('#quba-sync-progress-bar');
    var $progressLabel = $('#quba-sync-progress-label');
    var $statusText = $('#quba-sync-status');
    var $statusPill = $('#quba-sync-status-pill');
    var $startButton = $('#quba-start-sync');
    var $logViewer = $('#quba-log-viewer');
    var totalItems = 0;
    var processedItems = 0;
    var mediaFrame = null;
    var activePicker = null;

    if ($logViewer.length) {
        $logViewer.scrollTop($logViewer[0].scrollHeight);
    }

    function setProgress(percentage) {
        var value = Math.max(0, Math.min(100, percentage || 0));
        $progressBar.css('width', value + '%').text(value + '%');
        $progressLabel.text(value + '%');
    }

    function setStatus(message, state) {
        $statusText.html(message);
        $statusPill.removeClass('is-running is-success');
        if (state) {
            $statusPill.addClass(state);
        }
    }

    $startButton.on('click', function (e) {
        e.preventDefault();

        var syncType = $('input[name="quba_sync_type"]:checked').val();
        var specificId = $('#quba_sync_specific_id').val();
        var enableFallback = $('#quba_specific_id_fallback').is(':checked') ? '1' : '0';

        $startButton.prop('disabled', true).text('Initializing...');
        setProgress(0);
        setStatus('Fetching target data from QUBA API. This may take a minute...', 'is-running');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'quba_init_sync',
                nonce: qubaAdminAjax.nonce,
                sync_type: syncType,
                specific_id: specificId,
                enable_specific_id_fallback: enableFallback
            },
            success: function (response) {
                if (response.success) {
                    totalItems = response.data.total;
                    processedItems = 0;

                    if (response.data.debug) {
                        var debugHtml = "=== API ENDPOINT ===\n" + response.data.debug.method + "\n\n";
                        debugHtml += "=== PARAMETERS SENT ===\n" + JSON.stringify(response.data.debug.parameters, null, 2) + "\n\n";
                        debugHtml += "=== RAW RESPONSE ===\n" + response.data.debug.response_status;

                        $('#quba-debug-output').text(debugHtml);
                        $('#quba-debug-panel').slideDown();
                    } else {
                        $('#quba-debug-panel').hide();
                    }

                    if (totalItems === 0) {
                        setStatus('<strong>Queue built, but 0 items were found.</strong> Ensure the API is responsive and the ID(s) exist.', '');
                        $startButton.prop('disabled', false).text('Run sync again');
                        return;
                    }

                    setStatus('Queue built. ' + totalItems + ' items found. Starting batch processing...', 'is-running');
                    processBatch();
                } else {
                    setStatus('Initialization failed: ' + response.data, '');
                    $startButton.prop('disabled', false).text('Start manual sync');
                }
            },
            error: function () {
                setStatus('Server error during initialization.', '');
                $startButton.prop('disabled', false).text('Start manual sync');
            }
        });
    });

    function processBatch() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'quba_process_batch',
                nonce: qubaAdminAjax.nonce
            },
            success: function (response) {
                if (response.success) {
                    var remaining = response.data.remaining;
                    processedItems = totalItems - remaining;

                    var percentage = Math.round((processedItems / totalItems) * 100);
                    setProgress(percentage);
                    setStatus('Processing... ' + processedItems + ' of ' + totalItems + ' completed.', 'is-running');

                    if (remaining > 0) {
                        processBatch();
                    } else {
                        setStatus('<strong>Sync complete!</strong> All items processed.', 'is-success');
                        $startButton.prop('disabled', false).text('Run sync again');
                    }
                } else {
                    setStatus('Batch failed: ' + response.data, '');
                    $startButton.prop('disabled', false).text('Resume sync');
                }
            },
            error: function () {
                setStatus('Server error during batch processing.', '');
                $startButton.prop('disabled', false).text('Resume sync');
            }
        });
    }

    function collectSectorMedia() {
        var sectorMedia = {};
        var $rows = $('.quba-sector-media-row');

        if (!$rows.length) {
            return null;
        }

        $rows.each(function () {
            var code = String($(this).attr('data-code') || '').trim();
            if (!code) {
                return;
            }
            sectorMedia[code] = {
                image_id: parseInt($(this).find('.quba-sector-image-id').val(), 10) || 0,
                icon_id: parseInt($(this).find('.quba-sector-icon-id').val(), 10) || 0,
                label: String($(this).attr('data-label') || '').trim()
            };
        });

        return sectorMedia;
    }

    function saveQubaSettings($btn) {
        var $status = $('.quba-settings-status-shared');
        var $buttons = $('.quba-save-settings-btn');
        var originalLabels = $buttons.map(function () {
            return $(this).text();
        }).get();
        var payload = {
            action: 'quba_save_settings',
            nonce: qubaAdminAjax.nonce,
            notification_email: $('#quba_notification_email').val(),
            hide_debug_info: $('#quba_hide_debug_info').is(':checked') ? '1' : '0'
        };
        var sectorMedia = collectSectorMedia();

        if (sectorMedia !== null) {
            payload.sector_media = sectorMedia;
            payload.sector_media_json = JSON.stringify(sectorMedia);
        }

        $buttons.prop('disabled', true);
        $btn.text('Saving...');

        $.post(ajaxurl, payload, function (res) {
            $buttons.each(function (index) {
                $(this).prop('disabled', false).text(originalLabels[index] || 'Save settings');
            });
            if (res.success) {
                $status.fadeIn().delay(2000).fadeOut();
            } else {
                window.alert('Unable to save settings. Please try again.');
            }
        }).fail(function () {
            $buttons.each(function (index) {
                $(this).prop('disabled', false).text(originalLabels[index] || 'Save settings');
            });
            window.alert('Unable to save settings. Please try again.');
        });
    }

    $(document).on('click', '.quba-save-settings-btn', function (e) {
        e.preventDefault();
        saveQubaSettings($(this));
    });

    $('#quba-clear-logs').on('click', function (e) {
        e.preventDefault();
        if (!window.confirm('Are you sure you want to delete the sync log history?')) {
            return;
        }

        $.post(ajaxurl, {
            action: 'quba_clear_logs',
            nonce: qubaAdminAjax.nonce
        }, function () {
            $logViewer.val('Log cleared.');
        });
    });

    $(document).on('click', '.quba-sector-select', function (e) {
        e.preventDefault();

        if (typeof wp === 'undefined' || !wp.media) {
            return;
        }

        activePicker = $(this).closest('.quba-sector-picker');
        var field = activePicker.attr('data-field') || activePicker.data('field');
        var title = field === 'icon' ? 'Select Sector Badge Icon' : 'Select Sector Card Image';
        var buttonText = field === 'icon' ? 'Use this icon' : 'Use this image';

        if (mediaFrame) {
            mediaFrame.off('select');
        }

        mediaFrame = wp.media({
            title: title,
            button: { text: buttonText },
            library: { type: 'image' },
            multiple: false
        });

        mediaFrame.on('select', function () {
            if (!activePicker || !activePicker.length) {
                return;
            }

            var attachment = mediaFrame.state().get('selection').first().toJSON();
            var previewUrl = (attachment.sizes && attachment.sizes.medium)
                ? attachment.sizes.medium.url
                : attachment.url;

            if (field === 'icon') {
                activePicker.find('.quba-sector-icon-id').val(attachment.id);
                if (attachment.sizes && attachment.sizes.thumbnail) {
                    previewUrl = attachment.sizes.thumbnail.url;
                }
            } else {
                activePicker.find('.quba-sector-image-id').val(attachment.id);
            }

            activePicker.find('.quba-sector-preview').html('<img src="' + previewUrl + '" alt="">');
            activePicker.find('.quba-sector-clear').show();
        });

        mediaFrame.open();
    });

    $(document).on('click', '.quba-sector-clear', function (e) {
        e.preventDefault();
        var $picker = $(this).closest('.quba-sector-picker');
        var field = $picker.attr('data-field') || $picker.data('field');
        var emptyLabel = field === 'icon' ? 'No icon selected' : 'No image selected';

        if (field === 'icon') {
            $picker.find('.quba-sector-icon-id').val('');
        } else {
            $picker.find('.quba-sector-image-id').val('');
        }

        $picker.find('.quba-sector-preview').html('<span class="quba-sector-preview-empty">' + emptyLabel + '</span>');
        $(this).hide();
    });
});
