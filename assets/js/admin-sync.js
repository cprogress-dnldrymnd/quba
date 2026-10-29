jQuery(document).ready(function ($) {
    var $progressBar = $('#quba-sync-progress-bar');
    var $statusText = $('#quba-sync-status');
    var $startButton = $('#quba-start-sync');
    var totalItems = 0;
    var processedItems = 0;
    var mediaFrame = null;
    var activePicker = null;

    $startButton.on('click', function (e) {
        e.preventDefault();

        // Retrieve the selected synchronization scope and specific ID(s) from the UI
        var syncType = $('input[name="quba_sync_type"]:checked').val();
        var specificId = $('#quba_sync_specific_id').val();

        $startButton.prop('disabled', true).text('Initializing...');
        $progressBar.css('width', '0%').text('0%');
        $statusText.text('Fetching target data from QUBA API. This may take a minute...');

        /**
         * Step 1: Initialize the Queue
         */
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'quba_init_sync',
                nonce: qubaAdminAjax.nonce,
                sync_type: syncType,
                specific_id: specificId // Now passes a comma-separated string
            },
            success: function (response) {
                if (response.success) {
                    totalItems = response.data.total;
                    processedItems = 0;

                    // Print Debug Information to the UI
                    if (response.data.debug) {
                        var debugHtml = "=== API ENDPOINT ===\n" + response.data.debug.method + "\n\n";
                        debugHtml += "=== PARAMETERS SENT ===\n" + JSON.stringify(response.data.debug.parameters, null, 2) + "\n\n";
                        debugHtml += "=== RAW RESPONSE ===\n" +  response.data.debug.response_status;
                        
                        $('#quba-debug-output').text(debugHtml);
                        $('#quba-debug-panel').slideDown();
                    } else {
                        $('#quba-debug-panel').hide();
                    }

                    if (totalItems === 0) {
                        $statusText.html('<span style="color:#b32d2e;"><strong>Queue built, but 0 items were found. Ensure the API is responsive and the ID(s) exist.</strong></span>');
                        $startButton.prop('disabled', false).text('Run Sync Again');
                        return;
                    }

                    $statusText.text('Queue built. ' + totalItems + ' items found. Starting batch processing...');
                    processBatch();
                } else {
                    $statusText.html('<span style="color:red;">Initialization failed: ' + response.data + '</span>');
                    $startButton.prop('disabled', false).text('Start Manual Sync');
                }
            },
            error: function () {
                $statusText.html('<span style="color:red;">Server error during initialization.</span>');
                $startButton.prop('disabled', false).text('Start Manual Sync');
            }
        });
    });

    /**
     * Step 2: Process the Queue in Batches
     */
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
                    $progressBar.css('width', percentage + '%').text(percentage + '%');
                    $statusText.text('Processing... ' + processedItems + ' of ' + totalItems + ' completed.');

                    if (remaining > 0) {
                        processBatch(); // Execute the next batch chunk
                    } else {
                        $statusText.html('<span style="color:green;"><strong>Sync Complete! All items processed.</strong></span>');
                        $startButton.prop('disabled', false).text('Run Sync Again');
                    }
                } else {
                    $statusText.html('<span style="color:red;">Batch failed: ' + response.data + '</span>');
                    $startButton.prop('disabled', false).text('Resume Sync');
                }
            },
            error: function () {
                $statusText.html('<span style="color:red;">Server error during batch processing.</span>');
                $startButton.prop('disabled', false).text('Resume Sync');
            }
        });
    }

    /**
     * Collect sector card media attachment IDs from the admin table.
     */
    function collectSectorMedia() {
        var sectorMedia = {};
        var $rows = $('.quba-sector-media-row');

        if (!$rows.length) {
            return null;
        }

        $rows.each(function () {
            var code = String($(this).data('code'));
            sectorMedia[code] = {
                image_id: parseInt($(this).find('.quba-sector-image-id').val(), 10) || 0,
                icon_id: parseInt($(this).find('.quba-sector-icon-id').val(), 10) || 0
            };
        });

        return sectorMedia;
    }

    /**
     * Save notification / display settings and sector media mappings.
     */
    function saveQubaSettings($btn) {
        var $status = $('.quba-settings-status-shared');
        var $buttons = $('.quba-save-settings-btn');
        var payload = {
            action: 'quba_save_settings',
            nonce: qubaAdminAjax.nonce,
            notification_email: $('#quba_notification_email').val(),
            hide_debug_info: $('#quba_hide_debug_info').is(':checked') ? '1' : '0'
        };
        var sectorMedia = collectSectorMedia();

        if (sectorMedia !== null) {
            payload.sector_media = JSON.stringify(sectorMedia);
        }

        $buttons.prop('disabled', true);
        $btn.text('Saving...');

        $.post(ajaxurl, payload, function (res) {
            $buttons.prop('disabled', false).text('Save Settings');
            if (res.success) {
                $status.fadeIn().delay(2000).fadeOut();
            }
        }).fail(function () {
            $buttons.prop('disabled', false).text('Save Settings');
        });
    }

    $(document).on('click', '.quba-save-settings-btn', function (e) {
        e.preventDefault();
        saveQubaSettings($(this));
    });

    $(document).on('click', '.quba-sector-select', function (e) {
        e.preventDefault();

        if (typeof wp === 'undefined' || !wp.media) {
            return;
        }

        activePicker = $(this).closest('.quba-sector-picker');
        var field = activePicker.data('field');
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
            var previewStyle = activePicker.data('field') === 'icon'
                ? 'max-width:48px; max-height:48px; height:auto; display:block; border:1px solid #c3c4c7; border-radius:50%;'
                : 'max-width:120px; max-height:80px; height:auto; display:block; border:1px solid #c3c4c7;';

            if (activePicker.data('field') === 'icon') {
                activePicker.find('.quba-sector-icon-id').val(attachment.id);
                if (attachment.sizes && attachment.sizes.thumbnail) {
                    previewUrl = attachment.sizes.thumbnail.url;
                }
            } else {
                activePicker.find('.quba-sector-image-id').val(attachment.id);
            }

            activePicker.find('.quba-sector-preview').html(
                '<img src="' + previewUrl + '" alt="" style="' + previewStyle + '">'
            );
            activePicker.find('.quba-sector-clear').show();
        });

        mediaFrame.open();
    });

    $(document).on('click', '.quba-sector-clear', function (e) {
        e.preventDefault();
        var $picker = $(this).closest('.quba-sector-picker');
        var emptyLabel = $picker.data('field') === 'icon' ? 'No icon selected' : 'No image selected';

        if ($picker.data('field') === 'icon') {
            $picker.find('.quba-sector-icon-id').val('');
        } else {
            $picker.find('.quba-sector-image-id').val('');
        }

        $picker.find('.quba-sector-preview').html('<em style="color:#646970;">' + emptyLabel + '</em>');
        $(this).hide();
    });
});
