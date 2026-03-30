<?php

/**
 * Template Name: Qualifications Archive
 * * Loaded securely via Quba_Controllers hook from plugin root.
 */
get_header();
$chev = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-down" viewBox="0 0 16 16"> <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/> </svg>';
?>
<pre> xx
    <?=  do_shortcode('[display_qualified_units]') ?>
</pre>
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
                                    viewBox="0 0 512 512"><path
                                        d="M304 48a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zm0 416a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zM48 304a48 48 0 1 0 0-96 48 48 0 1 0 0 96zm464-48a48 48 0 1 0 -96 0 48 48 0 1 0 96 0zM142.9 437A48 48 0 1 0 75 369.1 48 48 0 1 0 142.9 437zm0-294.2A48 48 0 1 0 75 75a48 48 0 1 0 67.9 67.9zM369.1 437A48 48 0 1 0 437 369.1 48 48 0 1 0 369.1 437z">
                                    </path>
                                </svg> </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-12 search-field search-qual search-units search-access-to-he keywords">
                                <input type="text" name="Title" placeholder="Keywords e.g., warehousing" class="trigger-type">
                            </div>
                            <div class="col-lg-6 search-field search-qual qualification-code">
                                <input type="text" name="qualificationNumber" placeholder="Qualification Code e.g., 600/5640/X" class="trigger-type">
                            </div>
                            <div class="col-lg-6 search-field search-qual search-units search-access-to-he level">
                                <?php
                                $levels = array_filter(get_unique_meta_values('_level'));
                                $level_val = isset($_GET['Level']) ? sanitize_text_field($_GET['Level']) : '';
                                ?>
                                <select class="trigger-ajax-change" name="Level" id="level">
                                    <option value="">Level</option>
                                    <?php foreach ($levels as $level) {
                                        $level_label = $level;
                                        if (strpos($level, 'E') === 0) $level_label = 'Entry Level ' . substr($level, 1);
                                        elseif (strpos($level, 'L') === 0) $level_label = 'Level ' . substr($level, 1);
                                    ?>
                                        <option value="<?= esc_attr($level) ?>" <?= selected($level, $level_val, false) ?>><?= esc_html($level_label) ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-lg-6 search-field search-qual search-units search-access-to-he sector">
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