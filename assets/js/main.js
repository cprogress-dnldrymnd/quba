jQuery(document).ready(function ($) {
    if (typeof qubaAjaxObj === 'undefined') return;

    var typingTimer;
    var doneTypingInterval = 500;

    initAccordion();

    if ($('#qualification-filter').length > 0) {
        setActiveTabFromParams();
        loadSavedFilters();
        bindSearchTriggers();

        // Initial Load is now always local and fast
        performSearch();
    }

    function initAccordion() {
        $('#glh, #tqt').val(''); // Safely clear without console errors
        $('.qualification-header').on('click', function (e) {
            if ($(e.target).is('a')) return;

            var $box = $(this).closest('.qualification-box');
            var $expandBtn = $(this).find('.expand-btn');
            var $details = $box.find('.qualification-details');
            var isExpanded = $box.hasClass('expanded');

            $('.qualification-box').removeClass('expanded');
            $('.qualification-box .expand-btn').text('+');
            $('.qualification-box .qualification-details').slideUp(250);

            if (!isExpanded) {
                $box.addClass('expanded');
                $expandBtn.text('−');
                $details.slideDown(250);
            }
        });
    }

    function setActiveTabFromParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const post_type = urlParams.get('post_type') || 'qualifications';

        const activeButton = $(`.search-change-trigger[post_type="${post_type}"]`);
        if (activeButton.length) {
            $('.filter-button').removeClass('filter-active');
            activeButton.parent().addClass('filter-active');

            const search_type = activeButton.attr('search_type');
            $('.search-field').addClass('d-none');
            $(search_type).removeClass('d-none');
            $('#qualification-filter').attr('search_type', post_type);
        }
    }

    function loadSavedFilters() {
        const urlParams = new URLSearchParams(window.location.search);
        $('.trigger-type, .trigger-ajax-change').each(function () {
            const name = $(this).attr('name');
            const value = urlParams.get(name);
            if (value) $(this).val(value);
        });
    }

    function saveFilters() {
        const filters = { post_type: $('#qualification-filter').attr('search_type') };

        $('.search-field:not(.d-none) .trigger-type, .search-field:not(.d-none) .trigger-ajax-change').each(function () {
            const name = $(this).attr('name');
            const value = $(this).val();
            if (value) filters[name] = value;
        });

        const urlParams = new URLSearchParams(filters);
        window.history.replaceState({}, '', window.location.pathname + '?' + urlParams.toString());
    }

    function bindSearchTriggers() {
        $('.trigger-type').on('keyup', function () {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function () {
                saveFilters();
                performSearch();
            }, doneTypingInterval);
        });

        $('.trigger-ajax-change').on('change', function () {
            saveFilters();
            performSearch();
        });

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

            setTimeout(() => $('.qualification-filter-holder').removeClass('searching'), 500);

            saveFilters();
            performSearch();
        });
    }

    function performSearch() {
        var activePostType = $('#qualification-filter').attr('search_type') || 'qualifications';
        var actionName = activePostType === 'units' ? 'archive_ajax_units' : 'archive_ajax_qualifications';

        var searchData = {
            action: actionName,
            nonce: qubaAjaxObj.nonce,
            qualificationTitle: $('input[name="Title"]').val(),
            unitTitle: $('input[name="Title"]').val(),
            qualificationNumber: $('input[name="qualificationNumber"]').val(),
            qcaCode: $('input[name="qcaCode"]').val(),
            unitID: $('input[name="unitID"]').val() || $('input[name="open_awards_unit_id"]').val(),
            qualificationLevel: $('#level').val(),
            unitLevel: $('#level').val(),
            qcaSector: $('#qcaSector').val(),
            qualificationType: $('#type').val(),
            unitType: $('#unitType').val()
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