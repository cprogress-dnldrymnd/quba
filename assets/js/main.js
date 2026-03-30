jQuery(document).ready(function ($) {
    /**
     * Ensure the localized WP object is present before executing.
     * This prevents JS errors on pages where the script is enqueued but the object isn't needed.
     */
    if (typeof qubaAjaxObj === 'undefined') {
        return;
    }

    var typingTimer;
    var doneTypingInterval = 500;

    // Initialize UI and Event Listeners
    initSearchUI();
    bindSearchTriggers();

    /**
     * Binds input mechanisms to the search function.
     */
    function bindSearchTriggers() {
        // Debounce text inputs to prevent overwhelming the server
        $('.trigger-type').on('keyup', function () {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(performSearch, doneTypingInterval);
        });

        // Trigger search immediately on dropdown selections
        $('.trigger-ajax-change').on('change', function (e) {
            performSearch();
        });
    }

    /**
     * Handles the tab switching logic between Qualifications and Units.
     */
    function initSearchUI() {
        $('.search-change-trigger').on('click', function (e) {
            e.preventDefault();

            var $this = $(this);
            var searchType = $this.attr('search_type'); // e.g., '.search-qual'
            var postType = $this.attr('post_type');     // e.g., 'qualifications' or 'units'

            // Update state attributes and UI classes
            $('#qualification-filter').attr('search_type', postType);
            $('.qualification-filter-holder').addClass('searching');

            $('.filter-button').removeClass('filter-active');
            $this.parent().addClass('filter-active');

            // Toggle visibility of contextual form fields
            $('.search-field').addClass('d-none');
            $(searchType).removeClass('d-none');

            // Remove searching animation class after transition
            setTimeout(function () {
                $('.qualification-filter-holder').removeClass('searching');
            }, 500);

            // Automatically trigger a fresh search when switching contexts
            performSearch();
        });
    }

    /**
     * Executes the AJAX request to the WordPress backend.
     * Dynamically determines the payload and action based on the active tab.
     */
    function performSearch() {
        // Determine current search context (Qualifications vs. Units)
        var activePostType = $('#qualification-filter').attr('search_type') || 'qualifications';
        var actionName = activePostType === 'units' ? 'archive_ajax_units' : 'archive_ajax_qualifications';

        // Construct payload from visible/active DOM elements
        var searchData = {
            action: actionName,
            source: 'quba',
            nonce: qubaAjaxObj.nonce, // Security token

            // Text Inputs (Shared or distinct mapped to their API param expectations)
            qualificationTitle: $('input[name="Title"]').val(),
            unitTitle: $('input[name="Title"]').val(),
            qualificationNumber: $('input[name="qualificationNumber"]').val(),
            qcaCode: $('input[name="qcaCode"]').val(),
            unitID: $('input[name="unitID"]').val() || $('input[name="open_awards_unit_id"]').val(),

            // Select Dropdowns
            qualificationLevel: $('#level').val(),
            unitLevel: $('#level').val(),
            qcaSector: $('#qcaSector').val(),
            qualificationType: $('#type').val(),
            unitType: $('#unitType').val()
        };

        // UI Loading State
        var $resultsHolder = $('.results-holder');
        var $spinner = $('.spinner-holder');

        $spinner.show();
        $resultsHolder.fadeTo(200, 0.4);

        // Execute Request
        $.ajax({
            url: qubaAjaxObj.ajaxUrl,
            type: 'POST',
            data: searchData,
            success: function (response) {
                $resultsHolder.html(response).fadeTo(200, 1);
            },
            error: function (xhr, status, error) {
                console.error('Quba Search Error:', error);
                $resultsHolder.html('<div class="error-message"><p>Unable to retrieve results. Please try again.</p></div>').fadeTo(200, 1);
            },
            complete: function () {
                $spinner.hide();
            }
        });
    }
});