jQuery(document).ready(function () {
    load_more_button_listener();
});

function load_more_button_listener($) {
    jQuery(document).on("click", '#load-more-qualifications', function (event) {
        event.preventDefault();
        var offset = jQuery('.post-item').length;
        quba_ajax_qualifications(offset, 'append');
    });
}


function quba_ajax_qualifications($offset, $source = 'quba', $event_type = 'html') {

    var $loadmore = jQuery('#load-more-qualifications');

    var $archive_section = jQuery('.archive-section');

    var $result_holder = jQuery('#results .results-holder');

    var $qualificationLevel = jQuery("select[name='Level']").val();

    var $qcaSector = jQuery("select[name='qcaSector']").val();

    var $qualificationNumber = jQuery("input[name='qualificationNumber']").val();

    var $qualificationType = jQuery("select[name='qualificationType']").val();

    var $qualificationTitle = jQuery("input[name='Title']").val();

    var $qualificationRegulator = jQuery("select[name='regulator']").val();

    var $qualificationRiskRating = jQuery("select[name='risk']").val();

    var $qualificationQualAccreditationNumber = jQuery("input[name='qualAccreditationNumber']").val();

    var $qualificationMinage = jQuery("select[name='minage']").val();

    var $qualificationEndDate = jQuery("input[name='endDate']").val();

    $loading = jQuery('<div class="loading-results"> <div class="spinner d-inline-block"> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--> <path d="M304 48a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zm0 416a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zM48 304a48 48 0 1 0 0-96 48 48 0 1 0 0 96zm464-48a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zM142.9 437A48 48 0 1 0 75 369.1 48 48 0 1 0 142.9 437zm0-294.2A48 48 0 1 0 75 75a48 48 0 1 0 67.9 67.9zM369.1 437A48 48 0 1 0 437 369.1 48 48 0 1 0 369.1 437z" /> </svg> </div></div>');
    $archive_section.addClass('loading-post');

    if ($event_type == 'html') {
        jQuery('#results  .results-holder').html($loading);
        $loadmore.addClass('d-none');
    } else {
        $loadmore.addClass('loading');
        $loadmore.find('span').text('Loading');
    }
    jQuery.ajax({

        type: "POST",

        url: qubaAjaxObj.ajaxUrl,

        data: {
            action: 'archive_quba_ajax_qualifications',
            source: $source,
            qcaSector: $qcaSector,
            qualificationLevel: $qualificationLevel,
            qualificationNumber: $qualificationNumber,
            qualificationTitle: $qualificationTitle,
            qualificationType: $qualificationType,
            qualificationRegulator: $qualificationRegulator,
            qualificationRiskRating: $qualificationRiskRating,
            qualificationQualAccreditationNumber: $qualificationQualAccreditationNumber,
            qualificationMinage: $qualificationMinage,
            qualificationEndDate: $qualificationEndDate,
        },

        success: function (response) {

            if ($event_type == 'append') {
                $result_holder_row = $result_holder.find('.row-results');
                jQuery(response).appendTo($result_holder_row);
            } else {
                $result_holder.html(response);
            }
            $loadmore.removeClass('d-none loading');

            $loadmore.find('span').text('Load more');

            $archive_section.removeClass('loading-post');
        }
    });

}

function quba_ajax_units($offset, $source = 'quba', $event_type = 'html') {
    console.log("calling API ............................", $source)
    var $loadmore = jQuery('#load-more-qualifications');

    var $archive_section = jQuery('.archive-section');

    var $result_holder = jQuery('#results .results-holder');

    var $unitLevel = jQuery("select[name='Level']").val();

    var $qcaSector = jQuery("select[name='qcaSector']").val();

    var $qcaCode = jQuery("input[name='qcaCode']").val();

    var $unitID = jQuery("input[name='unitID']").val();

    var $unitTitle = jQuery("input[name='Title']").val();
    // Get current Unit Type
    var unitType = jQuery('#unitType').val();
    $loading = jQuery('<div class="loading-results"> <div class="spinner d-inline-block"> <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.--> <path d="M304 48a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zm0 416a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zM48 304a48 48 0 1 0 0-96 48 48 0 1 0 0 96zm464-48a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zM142.9 437A48 48 0 1 0 75 369.1 48 48 0 1 0 142.9 437zm0-294.2A48 48 0 1 0 75 75a48 48 0 1 0 67.9 67.9zM369.1 437A48 48 0 1 0 437 369.1 48 48 0 1 0 369.1 437z" /> </svg> </div></div>');
    $archive_section.addClass('loading-post');

    if ($event_type == 'html') {
        jQuery('#results  .results-holder').html($loading);
        $loadmore.addClass('d-none');
    } else {
        $loadmore.addClass('loading');
        $loadmore.find('span').text('Loading');
    }

    jQuery.ajax({

        type: "POST",

        url: qubaAjaxObj.ajaxUrl,

        data: {
            action: 'archive_quba_ajax_units',
            unitType: unitType,
            source: $source,
            qcaCode: $qcaCode,
            qcaSector: $qcaSector,
            unitLevel: $unitLevel,
            unitTitle: $unitTitle,
            unitID: $unitID,
        },

        success: function (response) {
            console.log("eventtype", $event_type);
            if ($event_type == 'append') {
                $result_holder_row = $result_holder.find('.row-results');
                jQuery(response).appendTo($result_holder_row);
            } else {
                $result_holder.html(response);
            }
            $loadmore.removeClass('d-none loading');

            $loadmore.find('span').text('Load more');

            $archive_section.removeClass('loading-post');
        }
    });

}

// Unit details
document.addEventListener('DOMContentLoaded', function () {
    const qualificationHeaders = document.querySelectorAll('.qualification-header');
    document.getElementById("glh").value = ""
    document.getElementById("tqt").value = ""
    qualificationHeaders.forEach(header => {
        header.addEventListener('click', function () {
            // Get parent box and its elements
            const box = this.parentElement;
            const expandBtn = this.querySelector('.expand-btn');
            const details = box.querySelector('.qualification-details');

            // Toggle the expanded state
            const isExpanded = box.classList.contains('expanded');

            // First close all boxes
            document.querySelectorAll('.qualification-box').forEach(item => {
                item.classList.remove('expanded');
                const itemBtn = item.querySelector('.expand-btn');
                if (itemBtn) itemBtn.textContent = '+';
                const itemDetails = item.querySelector('.qualification-details');
                if (itemDetails) itemDetails.style.display = 'none';
            });

            // Then open this one if it wasn't already open
            if (!isExpanded) {
                box.classList.add('expanded');
                expandBtn.textContent = '−';
                details.style.display = 'block';
            }
        });
    });
});