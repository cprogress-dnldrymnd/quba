<?php get_header()

 ?>

<?php
$chev = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-down" viewBox="0 0 16 16"> <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/> </svg>';
?>
<div id="primary" class="row-fluid">
    <div id="content" role="main" class="span8 offset2">
        <?php get_template_part('template-parts/page', 'breadcrumbs'); ?>
        <?= do_shortcode('[template template_id=3722]') ?>

       <section class="qualification-filter" id="qualification-filter" search_type="qualifications">
            <div class="container">
                <div class="qualification-filter-wrapper ">
                    <div class="qualification-filter-buttons">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="filter-button filter-active">
                                    <button post_type="qualifications" search_type=".search-qual"
                                        class="search-change-trigger w-100 text-center d-flex justify-content-between align-items-center">
                                        Search Qualifications <?= $chev ?>
                                    </button>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="filter-button filter-units">
                                    <button post_type="units" search_type=".search-units"
                                        class="search-change-trigger w-100 text-center d-flex justify-content-between align-items-center">
                                        Search Units <?= $chev ?>
                                    </button>
                                </div>
                            </div>
                            <div class="col-lg-4 d-none">
                                <div class="filter-button filter-access-to-he">
                                    <button search_type=".search-access-to-he"
                                        class="search-change-trigger w-100 text-center d-flex justify-content-between align-items-center">
                                        Search Access To HE <?= $chev ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="qualification-filter-holder position-relative">
                        <div class="spinner-holder">
                            <div class="spinner d-inline-block"> <svg xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 512 512"><!--!Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
                                    <path
                                        d="M304 48a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zm0 416a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zM48 304a48 48 0 1 0 0-96 48 48 0 1 0 0 96zm464-48a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zM142.9 437A48 48 0 1 0 75 369.1 48 48 0 1 0 142.9 437zm0-294.2A48 48 0 1 0 75 75a48 48 0 1 0 67.9 67.9zM369.1 437A48 48 0 1 0 437 369.1 48 48 0 1 0 369.1 437z">
                                    </path>
                                </svg> </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-12 search-field search-qual search-units search-access-to-he keywords">
                                <input type="text" name="Title" placeholder="Keywords e.g., warehousing"
                                    class="trigger-type">
                            </div>
                            <div class="col-lg-6 search-field search-qual qualification-code">
                                <input type="text" name="qualificationNumber" placeholder="Qualification Code e.g., 600/5640/X"
                                    class="trigger-type">
                            </div>
							
                            <div class="col-lg-6 search-field search-units unit-code d-none">
                                <input type="text" name="qcaCode" placeholder="Unit Code e.g. Y/505/4889"
                                    class="trigger-type">
                            </div>
                            <div class="col-lg-6 search-field search-access-to-he open-awards-code d-none">
                                <input type="text" name="open_awards_unit_id"
                                    placeholder="Open Awards Unit Code e.g. UA33ART12" class="trigger-type">
                            </div>
                            <div
                                class="col-lg-6 search-field search-units search-access-to-he open-awards-unit-id d-none">
                                <input type="text" name="unitID"
                                    placeholder="Open Awards Unit ID e.g. CBF498" class="trigger-type">
                            </div>
                            <div class="col-lg-6 search-field search-qual search-units search-access-to-he level">
                                <?php
                                $levels = array_filter(get_unique_meta_values('_level'));
                                $level_val = isset($_GET['Level']) ? $_GET['Level'] : '';
                                ?>
                                <select class="trigger-ajax-change" name="Level" id="level">
                                    <option value="">Level</option>
									
                                    <?php foreach ($levels as $level) { 

										$level_label = $level;
										if (strpos($level, 'E') === 0) {
											$level_label = 'Entry Level ' . substr($level, 1);
										} elseif (strpos($level, 'L') === 0) {
											$level_label = 'Level ' . substr($level, 1);
										}
									
									?>
                                        <option value="<?= $level ?>" <?= $level == $level_val ? 'selected' : '' ?>><?= $level_label ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-lg-6 search-field search-qual search-units search-access-to-he sector">
                                <?php
                                $sectors = QUBA_GetQCASectors();
								if(isset($_GET['qcaSector'])) {
									$qcaSector_val = $_GET['qcaSector'];
								} else {
									$qcaSector_val = false;
								}
                                ?>
                                <select class="trigger-ajax-change" name="qcaSector" id="qcaSector">
                                    <option value="">Sector</option>
                                    <?php foreach ($sectors as $sector) { ?>
                                        <option value="<?= $sector->Code ?>" <?= $sector->Code == $qcaSector_val ? 'selected' : '' ?>><?= $sector->Classification ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            
                            <div class="col-lg-6 search-field search-units unit-type">
                                <?php
                                $unit_type_val = isset($_GET['unitType']) ? $_GET['unitType'] : '';
                                ?>
                                <select class="trigger-ajax-change" name="unitType" id="unitType">
                                    <option value="">Unit Type</option>
                                    <option value="QEunits" <?= $unit_type_val == 'QEunits' ? 'selected' : '' ?>>Quality Endorsed Unit</option>
                                    <option value="QUAL" <?= $unit_type_val == 'QUAL' ? 'selected' : '' ?>>Regulated Qualification Unit</option>
                                    <option value="ACC" <?= $unit_type_val == 'ACC' ? 'selected' : '' ?>>Access to HE Unit</option>
                                </select>
                            </div>

						
							<div class="col-lg-6 search-field search-qual type">
							<?php
							$qual_type_val = isset($_GET['qualificationType']) ? $_GET['qualificationType'] : '';
							?>
							<select class="trigger-ajax-change" name="qualificationType" id="type">
								<option value="">Qualification Type</option>
								<option value="Access to HE" <?= $qual_type_val == 'Access to HE' ? 'selected' : '' ?>>Access to HE</option>
								<option value="End-Point Assessment" <?= $qual_type_val == 'End-Point Assessment' ? 'selected' : '' ?>>Apprenticeship Assessment</option>
								<option value="Essential Digital Skills" <?= $qual_type_val == 'Essential Digital Skills' ? 'selected' : '' ?>>Essential Digital Skills</option>
								<option value="Experienced Worker Assessment" <?= $qual_type_val == 'Experienced Worker Assessment' ? 'selected' : '' ?>>Experienced Worker Assessment</option>
								<option value="Functional Skills" <?= $qual_type_val == 'Functional Skills' ? 'selected' : '' ?>>Functional Skills</option>
								<option value="Micro-credentials" <?= $qual_type_val == 'Micro-credentials' ? 'selected' : '' ?>>Micro-credentials</option>
								<option value="Occupational Qualification" <?= $qual_type_val == 'Occupational Qualification' ? 'selected' : '' ?>>Occupational Qualifications</option>
								<option value="Other Life Skills Qualification" <?= $qual_type_val == 'Other Life Skills Qualification' ? 'selected' : '' ?>>Other Life Skills</option>
								<option value="Other Vocational Qualification" <?= $qual_type_val == 'Other Vocational Qualification' ? 'selected' : '' ?>>Other Vocational</option>
								<option value="Technical Occupational Qualification" <?= $qual_type_val == 'Technical Occupational Qualification' ? 'selected' : '' ?>>Technical Occupational Qualifications</option>
								<option value="Vocationally-Related Qualification" <?= $qual_type_val == 'Vocationally-Related Qualification' ? 'selected' : '' ?>>Vocationally-Related Qualifications</option>
							</select>
						</div>

                            <div class="col-lg-6 search-field min-age d-none">
                                <?php
                                $minages = get_unique_meta_values('_minage');
                                $minage_val = isset($_GET['minage']) ? $_GET['minage'] : '';
                                ?>
                                <select class="trigger-ajax-change" name="minage" id="minage">
                                    <option value="">Minimum age e.g.16</option>
                                    <?php foreach ($minages as $minage) { ?>
                                        <?php if ($minage) { ?>
                                            <option value="<?= $minage ?>" <?= $minage == $minage_val ? 'selected' : '' ?>><?= $minage ?></option>
                                        <?php } ?>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="archive-section archive-section-qualifications position-relative">
            <div class="container">
                <div id="results">
                    <div class="results-holder">

                    </div>
                </div>

                <div class="vc_btn3-container custom-button text-center mt-5 load-more d-none">
                    <button
                        class="vc_general vc_btn3 vc_btn3-size-lg vc_btn3-shape-rounded vc_btn3-style-modern vc_btn3-color-violet"
                        title="" id="load-more-qualifications">
                        <span>Load More</span>
                        <svg xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 512 512"><!--!Font Awesome Free 6.5.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
                            <path fill="currentColor"
                                d="M304 48a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zm0 416a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zM48 304a48 48 0 1 0 0-96 48 48 0 1 0 0 96zm464-48a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zM142.9 437A48 48 0 1 0 75 369.1 48 48 0 1 0 142.9 437zm0-294.2A48 48 0 1 0 75 75a48 48 0 1 0 67.9 67.9zM369.1 437A48 48 0 1 0 437 369.1 48 48 0 1 0 369.1 437z">
                            </path>
                        </svg>
                    </button>
                </div>
            </div>
        </section>

        <?= do_shortcode('[template template_id=2969]') ?>
    </div><!-- #content .site-content -->
</div><!-- #primary .content-area -->
<?php get_footer(); // This fxn gets the footer.php file and renders it 
?>
<style>
.search-results-count-wrapper {
  margin: 20px 0;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 8px;
  border-left: 4px solid #007cba;
}

.search-results-count {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
}

.count-display {
  font-size: 16px;
  font-weight: 500;
}

.results-number {
  color: #007cba;
  font-weight: bold;
  font-size: 18px;
}

.results-text {
  color: #666;
  margin-left: 5px;
}

.search-term {
  color: #333;
  font-style: italic;
  margin-left: 5px;
}

.active-filters {
  color: #666;
  margin-top: 5px;
}

.search-results-info .alert {
  margin-bottom: 0;
  padding: 12px 20px;
}

@media (max-width: 768px) {
  .search-results-count {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .count-display {
    margin-bottom: 10px;
  }
}
	
</style>
<script>
    jQuery(document).ready(function() {
        // Set the proper active tab based on URL parameters
        setActiveTabFromParams();

        // Initialize the search with saved parameters
        loadSavedFilters();
        
        // Important: Execute the search with the loaded filters
        // This ensures results are filtered when returning to the page
        if (hasSearchParams()) {
            search_function();
        } else {
            // Initial load without parameters
            ajax_qualifications(0, 'post');
        }
        
        // Setup event handlers
        search_change();
        var typingTimer;
        var search_functionInterval = 500;

        jQuery('.trigger-type').on('keyup', function() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function() {
                search_function();
                saveFilters();
            }, search_functionInterval);
        });

        jQuery('.trigger-ajax-change').change(function(e) {
            search_function();
            saveFilters();
        });
    });

    function setActiveTabFromParams() {
        // Check if we have a post_type parameter to determine which tab should be active
        const urlParams = new URLSearchParams(window.location.search);
        const post_type = urlParams.get('post_type') || 'qualifications'; // Default to qualifications

        // Select the correct tab button
        const activeButton = jQuery(`.search-change-trigger[post_type="${post_type}"]`);
        if (activeButton.length) {
            jQuery('.filter-button').removeClass('filter-active');
            activeButton.parent().addClass('filter-active');
            
            // Update filter visibility
            const search_type = activeButton.attr('search_type');
            jQuery('.search-field').addClass('d-none');
            jQuery(search_type).removeClass('d-none');
            
            // Update the search type attribute
            jQuery('#qualification-filter').attr('search_type', post_type);
        }
    }

    function hasSearchParams() {
        const urlParams = new URLSearchParams(window.location.search);
        // Check if there are any search parameters that would affect results
        return urlParams.has('Title') || 
               urlParams.has('qualificationNumber') || 
               urlParams.has('Level') || 
               urlParams.has('qcaSector') ||
               urlParams.has('qualificationType') ||
               urlParams.has('qcaCode') ||
               urlParams.has('unitID');
    }

    function loadSavedFilters() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // Populate text inputs from URL parameters
        jQuery('.trigger-type').each(function() {
            const name = jQuery(this).attr('name');
            const value = urlParams.get(name);
            if (value) {
                jQuery(this).val(value);
            }
        });
        
        // Also initialize selects from URL (though they might already be set via PHP)
        jQuery('.trigger-ajax-change').each(function() {
            const name = jQuery(this).attr('name');
            const value = urlParams.get(name);
            if (value) {
                jQuery(this).val(value);
            }
        });
        
        // Note: We no longer automatically call search_function_tab() here
        // Instead, we'll execute the search explicitly in the document.ready function
    }

    function saveFilters() {
        // Get current filters
        const filters = {};
        const post_type = jQuery('#qualification-filter').attr('search_type');
        filters['post_type'] = post_type;

        // Get values from visible text inputs
        jQuery('.search-field:not(.d-none) .trigger-type').each(function() {
            const name = jQuery(this).attr('name');
            const value = jQuery(this).val();
            if (value) {
                filters[name] = value;
            }
        });
        
        // Get values from visible selects
        jQuery('.search-field:not(.d-none) .trigger-ajax-change').each(function() {
            const name = jQuery(this).attr('name');
            const value = jQuery(this).val();
            if (value) {
                filters[name] = value;
            }
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

    function search_change() {
        jQuery('.search-change-trigger').click(function(e) {
            jQuery('.qualification-filter-holder').addClass('searching');
            jQuery('.filter-button').removeClass('filter-active');
            jQuery(this).parent().addClass('filter-active');
            $search_type = jQuery(this).attr('search_type');
            $post_type = jQuery(this).attr('post_type');
            jQuery('.search-field').addClass('d-none');
            jQuery($search_type).removeClass('d-none');
            jQuery('#qualification-filter').attr('search_type', $post_type);
            
            // Update post_type in URL
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('post_type', $post_type);
            const newUrl = window.location.pathname + '?' + urlParams.toString();
            window.history.replaceState({}, '', newUrl);
			
            // Call your API here
            search_function_tab();
			
            setTimeout(function() {
                jQuery('.qualification-filter-holder').removeClass('searching');
            }, 500);
            e.preventDefault();
        });
    }
    
    function search_function_tab() {
        $post_type = jQuery('#qualification-filter').attr('search_type');
   
        if ($post_type == 'qualifications') {
            console.log("qualifications tab");
            // If there are search parameters, do a filtered search, otherwise do initial load
            if (hasSearchParams()) {
                ajax_qualifications(0);
            } else {
                ajax_qualifications(0, 'post');
            }
        } else if ($post_type == 'units') {
            console.log("units tab");
            // If there are search parameters, do a filtered search, otherwise do initial load  
            if (hasSearchParams()) {
                ajax_units(0);
            } else {
                ajax_units(0, 'post');
            }
        }
    }
	
    function search_function() {
        $post_type = jQuery('#qualification-filter').attr('search_type');
   
        if ($post_type == 'qualifications') {
            console.log("qualifications");
            ajax_qualifications(0);
        } else if ($post_type == 'units') {
            console.log("units");
            ajax_units(0);
        }
    }
</script>