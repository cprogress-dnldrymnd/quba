jQuery(document).ready(function ($) {
    if (typeof qubaAjaxObj === 'undefined') {
        return;
    }

    var typingTimer;
    var doneTypingInterval = 500;
    var currentPage = 1; // State tracker for pagination

    initAccordion();

    if ($('#qualification-filter').length > 0) {
        setActiveTabFromParams();
        loadSavedFilters();
        bindSearchTriggers();
        performSearch(false); // Initial load is always a fresh search
    }

    function initAccordion() {
        $('#glh').val('');
        $('#tqt').val('');

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
        // First check query string for legacy support, then check the actual URL path
        const urlParams = new URLSearchParams(window.location.search);
        let post_type = urlParams.get('post_type');

        if (!post_type) {
            if (window.location.pathname.indexOf('/units') !== -1) {
                post_type = 'units';
            } else {
                post_type = 'qualifications'; // Default fallback
            }
        }

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

    function hasSearchParams() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.has('Title') ||
            urlParams.has('qualificationNumber') ||
            urlParams.has('Level') ||
            urlParams.has('qcaSector') ||
            urlParams.has('qualificationType') ||
            urlParams.has('qcaCode') ||
            urlParams.has('unitID') ||
            urlParams.has('unitType');
    }

    function loadSavedFilters() {
        const urlParams = new URLSearchParams(window.location.search);

        $('.trigger-type').each(function () {
            const name = $(this).attr('name');
            const value = urlParams.get(name);
            if (value) {
                $(this).val(value);
            }
        });

        $('.trigger-ajax-change').each(function () {
            const name = $(this).attr('name');
            const value = urlParams.get(name);
            if (value) {
                $(this).val(value);
            }
        });
    }

    function saveFilters() {
        const filters = {};
        const post_type = $('#qualification-filter').attr('search_type') || 'qualifications';

        $('.search-field:not(.d-none) .trigger-type').each(function () {
            const name = $(this).attr('name');
            const value = $(this).val();
            if (value) filters[name] = value;
        });

        $('.search-field:not(.d-none) .trigger-ajax-change').each(function () {
            const name = $(this).attr('name');
            const value = $(this).val();
            if (value) filters[name] = value;
        });

        const urlParams = new URLSearchParams();
        for (const key in filters) {
            urlParams.set(key, filters[key]);
        }

        // Cleanly rewrite the URL path instead of adding post_type to the query
        const basePath = '/' + post_type + '/';
        const queryString = urlParams.toString() ? '?' + urlParams.toString() : '';
        const newUrl = basePath + queryString;
        
        window.history.replaceState({}, '', newUrl);
    }

    function bindSearchTriggers() {
        $('.trigger-type').on('keyup', function () {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function () {
                saveFilters();
                currentPage = 1; // Reset pagination on new filter
                performSearch(false);
            }, doneTypingInterval);
        });

        $('.trigger-ajax-change').on('change', function (e) {
            saveFilters();
            currentPage = 1; // Reset pagination on new filter
            performSearch(false);
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

            setTimeout(function () {
                $('.qualification-filter-holder').removeClass('searching');
            }, 500);

            // Strip post_type from URL query and push the new clean path to browser history
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.delete('post_type'); 
            const basePath = '/' + postType + '/';
            const queryString = urlParams.toString() ? '?' + urlParams.toString() : '';
            window.history.pushState({}, '', basePath + queryString);

            currentPage = 1; // Reset pagination on tab change
            performSearch(false);
        });

        // Delegate Load More Button Event
        $(document).on('click', '.quba-load-more', function (e) {
            e.preventDefault();
            var $btn = $(this);
            currentPage = parseInt($btn.attr('data-page'));
            $btn.text('Loading...').prop('disabled', true);
            performSearch(true); // Fire paginated lookup sequence
        });
    }

    function performSearch(isLoadMore = false) {
        var activePostType = $('#qualification-filter').attr('search_type') || 'qualifications';
        var actionName = activePostType === 'units' ? 'archive_ajax_units' : 'archive_ajax_qualifications';

        var searchData = {
            action: actionName,
            nonce: qubaAjaxObj.nonce,
            paged: currentPage,
            is_load_more: isLoadMore ? 'true' : 'false',
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

        if (!isLoadMore) {
            $spinner.show();
            $resultsHolder.fadeTo(200, 0.4);
        }

        $.ajax({
            url: qubaAjaxObj.ajaxUrl,
            type: 'POST',
            data: searchData,
            success: function (response) {
                if (isLoadMore) {
                    $('#quba-load-more-container').remove(); // Strip out the old button
                    $('#quba-grid-container').append(response); // Append newly fetched cards securely to the grid
                } else {
                    $resultsHolder.html(response).fadeTo(200, 1);
                }
            },
            error: function (xhr, status, error) {
                console.error('Quba Search Error:', error);
                if (!isLoadMore) {
                    $resultsHolder.html('<div class="error-message"><p>Unable to retrieve results. Please try again.</p></div>').fadeTo(200, 1);
                } else {
                    alert('Unable to load more items. Please try again.');
                    $('.quba-load-more').text('Load More').prop('disabled', false);
                }
            },
            complete: function () {
                if (!isLoadMore) {
                    $spinner.hide();
                }
            }
        });
    }
});