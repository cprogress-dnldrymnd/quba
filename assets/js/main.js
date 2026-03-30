jQuery(document).ready(function ($) {
    /**
     * Ensure the localized WP object is present before executing.
     */
    if (typeof qubaAjaxObj === 'undefined') {
        return;
    }

    var typingTimer;
    var doneTypingInterval = 500;

    // Initialize the accordion logic for single unit pages
    initAccordion();

    // Only initialize the search logic if we are on the Archive page
    if ($('#qualification-filter').length > 0) {
        
        // 1. Set the proper active tab based on URL parameters
        setActiveTabFromParams();

        // 2. Initialize the search inputs with saved URL parameters
        loadSavedFilters();
        
        // 3. Setup event handlers for inputs and tabs
        bindSearchTriggers();

        // 4. Initial Load: Execute search with loaded filters or local fallback
        if (hasSearchParams()) {
            performSearch('quba'); // Filtered API Search
        } else {
            performSearch('post'); // Unfiltered Local Fallback
        }
    }

    /**
     * Initializes the Accordion for Related Qualifications on the Single Units template.
     * Safely prevents console errors if elements do not exist on the current page.
     */
    function initAccordion() {
        // jQuery silently ignores these if the elements don't exist (No console errors)
        $('#glh').val('');
        $('#tqt').val('');

        // Attach click event to qualification headers
        $('.qualification-header').on('click', function(e) {
            // Prevent default link behavior if clicking directly on the header area
            if ($(e.target).is('a')) return; 

            var $box = $(this).closest('.qualification-box');
            var $expandBtn = $(this).find('.expand-btn');
            var $details = $box.find('.qualification-details');
            
            var isExpanded = $box.hasClass('expanded');
            
            // First close all boxes
            $('.qualification-box').removeClass('expanded');
            $('.qualification-box .expand-btn').text('+');
            $('.qualification-box .qualification-details').slideUp(250); // Smooth collapse
            
            // Then open this one if it wasn't already open
            if (!isExpanded) {
                $box.addClass('expanded');
                $expandBtn.text('−');
                $details.slideDown(250); // Smooth expand
            }
        });
    }

    /**
     * Parses the URL to determine which tab (Qualifications/Units) should be active.
     */
    function setActiveTabFromParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const post_type = urlParams.get('post_type') || 'qualifications'; // Default to qualifications

        const activeButton = $(`.search-change-trigger[post_type="${post_type}"]`);
        
        if (activeButton.length) {
            $('.filter-button').removeClass('filter-active');
            activeButton.parent().addClass('filter-active');
            
            // Update filter visibility
            const search_type = activeButton.attr('search_type');
            $('.search-field').addClass('d-none');
            $(search_type).removeClass('d-none');
            
            // Update the search type attribute state
            $('#qualification-filter').attr('search_type', post_type);
        }
    }

    /**
     * Checks if the URL contains any active filtering parameters.
     */
    function hasSearchParams() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.has('Title') || 
               urlParams.has('qualificationNumber') || 
               urlParams.has('Level') || 
               urlParams.has('qcaSector') ||
               urlParams.has('qualificationType') ||
               urlParams.has('qcaCode') ||
               urlParams.has('unitID');
    }

    /**
     * Populates form fields on page load based on existing URL parameters.
     */
    function loadSavedFilters() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Populate text inputs
        $('.trigger-type').each(function() {
            const name = $(this).attr('name');
            const value = urlParams.get(name);
            if (value) {
                $(this).val(value);
            }
        });
        
        // Populate dropdown selects
        $('.trigger-ajax-change').each(function() {
            const name = $(this).attr('name');
            const value = urlParams.get(name);
            if (value) {
                $(this).val(value);
            }
        });
    }

    /**
     * Gathers active filters and pushes them to the browser URL silently.
     */
    function saveFilters() {
        const filters = {};
        const post_type = $('#qualification-filter').attr('search_type');
        filters['post_type'] = post_type;

        // Get values from visible text inputs
        $('.search-field:not(.d-none) .trigger-type').each(function() {
            const name = $(this).attr('name');
            const value = $(this).val();
            if (value) filters[name] = value;
        });
        
        // Get values from visible selects
        $('.search-field:not(.d-none) .trigger-ajax-change').each(function() {
            const name = $(this).attr('name');
            const value = $(this).val();
            if (value) filters[name] = value;
        });

        // Build and update URL with query parameters
        const urlParams = new URLSearchParams();
        for (const key in filters) {
            urlParams.set(key, filters[key]);
        }
        
        // Update URL without reloading page
        const newUrl = window.location.pathname + '?' + urlParams.toString();
        window.history.replaceState({}, '', newUrl);
    }

    /**
     * Binds input mechanisms to the search and URL update functions.
     */
    function bindSearchTriggers() {
        // Debounce text inputs
        $('.trigger-type').on('keyup', function () {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function() {
                saveFilters();
                performSearch('quba');
            }, doneTypingInterval);
        });

        // Dropdown selections
        $('.trigger-ajax-change').on('change', function (e) {
            saveFilters();
            performSearch('quba');
        });

        // Tab Switching
        $('.search-change-trigger').on('click', function (e) {
            e.preventDefault();

            var $this = $(this);
            var searchType = $this.attr('search_type');
            var postType = $this.attr('post_type');

            $('#qualification-filter').attr('search_type', postType);
            $('.qualification-filter-holder').addClass('searching');
            
            $('.filter-button').removeClass('filter-active');
            $this.parent().addClass('filter-active');

            $('.search-field').addClass('d-none');
            $(searchType).removeClass('d-none');

            setTimeout(function () {
                $('.qualification-filter-holder').removeClass('searching');
            }, 500);

            saveFilters();
            
            // Trigger contextual search mode
            if (hasSearchParams()) {
                performSearch('quba');
            } else {
                performSearch('post');
            }
        });
    }

    /**
     * Executes the AJAX request to the WordPress backend.
     * @param {string} sourceType - Dictates whether to fetch from API ('quba') or local WP Fallback ('post').
     */
    function performSearch(sourceType = 'quba') {
        var activePostType = $('#qualification-filter').attr('search_type') || 'qualifications';
        var actionName = activePostType === 'units' ? 'archive_ajax_units' : 'archive_ajax_qualifications';

        var searchData = {
            action: actionName,
            source: sourceType, 
            nonce: qubaAjaxObj.nonce,

            // Texts
            qualificationTitle:  $('input[name="Title"]').val(),
            unitTitle:           $('input[name="Title"]').val(), 
            qualificationNumber: $('input[name="qualificationNumber"]').val(),
            qcaCode:             $('input[name="qcaCode"]').val(),
            unitID:              $('input[name="unitID"]').val() || $('input[name="open_awards_unit_id"]').val(),

            // Selects
            qualificationLevel:  $('#level').val(),
            unitLevel:           $('#level').val(),
            qcaSector:           $('#qcaSector').val(),
            qualificationType:   $('#type').val(),
            unitType:            $('#unitType').val()
        };

        var $resultsHolder = $('.results-holder');
        var $spinner = $('.spinner-holder');
        
        $spinner.show();
        $resultsHolder.fadeTo(200, 0.4);

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