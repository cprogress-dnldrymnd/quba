<?php

/**
 * Template Name: Qualifications Archive
 * * Loaded securely via Quba_Controllers hook from plugin root.
 */
get_header();
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
                        </div>
                    </div>

                    <div class="qualification-filter-holder position-relative">
                        <div class="spinner-holder">
                            <div class="spinner d-inline-block"> <svg xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 512 512">
                                    <path
                                        d="M304 48a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zm0 416a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zM48 304a48 48 0 1 0 0-96 48 48 0 1 0 0 96zm464-48a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zM142.9 437A48 48 0 1 0 75 369.1 48 48 0 1 0 142.9 437zm0-294.2A48 48 0 1 0 75 75a48 48 0 1 0 67.9 67.9zM369.1 437A48 48 0 1 0 437 369.1 48 48 0 1 0 369.1 437z">
                                    </path>
                                </svg> </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-12 search-field search-qual search-units keywords">
                                <input type="text" name="Title" placeholder="Keywords e.g., warehousing" class="trigger-type">
                            </div>

                            <div class="col-lg-6 search-field search-qual qualification-code">
                                <input type="text" name="qualificationNumber" placeholder="Qualification Code e.g., 600/5640/X" class="trigger-type">
                            </div>

                            <div class="col-lg-6 search-field search-units unit-code d-none">
                                <input type="text" name="qcaCode" placeholder="Unit Code e.g. Y/505/4889" class="trigger-type">
                            </div>
                            <div class="col-lg-6 search-field search-units open-awards-unit-id d-none">
                                <input type="text" name="unitID" placeholder="Open Awards Unit ID e.g. CBF498" class="trigger-type">
                            </div>

                            <div class="col-lg-6 search-field search-qual search-units level">
                                <?php
                                // 1. Fetch and filter the raw levels
                                $raw_levels = array_filter(get_unique_meta_values('_level'));
                                $level_val = isset($_GET['Level']) ? sanitize_text_field($_GET['Level']) : '';

                                // 2. Prepare an associative array mapping the raw value to the formatted label
                                $sorted_levels = [];
                                foreach ($raw_levels as $level) {
                                    $level_label = $level;
                                    if (strpos($level, 'E') === 0) {
                                        $level_label = 'Entry Level ' . substr($level, 1);
                                    } elseif (strpos($level, 'L') === 0) {
                                        $level_label = 'Level ' . substr($level, 1);
                                    }
                                    $sorted_levels[$level] = $level_label;
                                }

                                // 3. Sort the array alphabetically by the label (using natural sort so Level 2 is before Level 10)
                                asort($sorted_levels, SORT_NATURAL | SORT_FLAG_CASE);
                                ?>
                                <select class="trigger-ajax-change" name="Level" id="level">
                                    <option value="">Level</option>
                                    <?php foreach ($sorted_levels as $val => $label) { ?>
                                        <option value="<?= esc_attr($val) ?>" <?= selected($val, $level_val, false) ?>><?= esc_html($label) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-lg-6 search-field search-qual search-units sector">
                                <?php
                                $sectors = Quba_API::get_qca_sectors();
                                $qcaSector_val = isset($_GET['qcaSector']) ? sanitize_text_field($_GET['qcaSector']) : false;
                                ?>
                                <select class="trigger-ajax-change" name="qcaSector" id="qcaSector">
                                    <option value="">Sector</option>
                                    <?php if (!($sectors instanceof Exception) && !empty($sectors)): foreach ($sectors as $sector) { ?>
                                            <option value="<?= esc_attr($sector->Code) ?>" <?= selected($sector->Code, $qcaSector_val, false) ?>><?= esc_html($sector->Classification) ?></option>
                                    <?php }
                                    endif; ?>
                                </select>
                            </div>

                            <div class="col-lg-6 search-field search-units unit-type d-none">
                                <?php $unit_type_val = isset($_GET['unitType']) ? sanitize_text_field($_GET['unitType']) : ''; ?>
                                <select class="trigger-ajax-change" name="unitType" id="unitType">
                                    <option value="">Unit Type</option>
                                    <option value="Quality Endorsed" <?= selected($unit_type_val, 'Quality Endorsed', false) ?>>Quality Endorsed Unit</option>
                                    <option value="Qualification" <?= selected($unit_type_val, 'Qualification', false) ?>>Regulated Qualification Unit</option>
                                    <option value="Access" <?= selected($unit_type_val, 'Access', false) ?>>Access to HE Unit</option>
                                </select>
                            </div>
                            <div class="col-lg-6 search-field search-qual type">
                                <?php $qual_type_val = isset($_GET['qualificationType']) ? sanitize_text_field($_GET['qualificationType']) : ''; ?>
                                <select class="trigger-ajax-change" name="qualificationType" id="type">
                                    <option value="">Qualification Type</option>
                                    <option value="Access to HE" <?= selected($qual_type_val, 'Access to HE', false) ?>>Access to HE</option>
                                    <option value="End-Point Assessment" <?= selected($qual_type_val, 'End-Point Assessment', false) ?>>Apprenticeship Assessment</option>
                                    <option value="Essential Digital Skills" <?= selected($qual_type_val, 'Essential Digital Skills', false) ?>>Essential Digital Skills</option>
                                    <option value="Experienced Worker Assessment" <?= selected($qual_type_val, 'Experienced Worker Assessment', false) ?>>Experienced Worker Assessment</option>
                                    <option value="Functional Skills" <?= selected($qual_type_val, 'Functional Skills', false) ?>>Functional Skills</option>
                                    <option value="Micro-credentials" <?= selected($qual_type_val, 'Micro-credentials', false) ?>>Micro-credentials</option>
                                    <option value="Occupational Qualification" <?= selected($qual_type_val, 'Occupational Qualification', false) ?>>Occupational Qualifications</option>
                                    <option value="Other Life Skills Qualification" <?= selected($qual_type_val, 'Other Life Skills Qualification', false) ?>>Other Life Skills</option>
                                    <option value="Other Vocational Qualification" <?= selected($qual_type_val, 'Other Vocational Qualification', false) ?>>Other Vocational</option>
                                    <option value="Technical Occupational Qualification" <?= selected($qual_type_val, 'Technical Occupational Qualification', false) ?>>Technical Occupational Qualifications</option>
                                    <option value="Vocationally-Related Qualification" <?= selected($qual_type_val, 'Vocationally-Related Qualification', false) ?>>Vocationally-Related Qualifications</option>
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
                    <div class="results-holder"></div>
                </div>
            </div>
        </section>

        <?= do_shortcode('[template template_id=2969]') ?>
    </div>
</div>
<?php get_footer(); ?>