jQuery(document).ready(function ($) {
    var $progressBar = $('#quba-sync-progress-bar');
    var $statusText = $('#quba-sync-status');
    var $startButton = $('#quba-start-sync');
    var totalItems = 0;
    var processedItems = 0;

    $startButton.on('click', function (e) {
        e.preventDefault();
        
        $startButton.prop('disabled', true).text('Initializing...');
        $progressBar.css('width', '0%').text('0%');
        $statusText.text('Fetching master list from QUBA API. This may take a minute...');

        // Step 1: Initialize the Queue
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'quba_init_sync',
                nonce: qubaAdminAjax.nonce
            },
            success: function (response) {
                if (response.success) {
                    totalItems = response.data.total;
                    processedItems = 0;
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

    // Step 2: Process the Queue in Batches
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
                        processBatch(); // Continue to next batch
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
});