<?php

/**
 * Template Name: Unit Single
 * Description: Fully localized single unit layout.
 */
get_header();

$post_id = get_the_ID();
$unit_id = get_post_meta($post_id, '_id', true);
$unitPdf = get_post_meta($post_id, '_unit_listing_document_url', true);

function key_info_unit($key, $label, $type = 'string')
{
    $keyinfo = get_post_meta(get_the_ID(), $key, true);
    if (!$keyinfo) return '';
    if ($type == 'date') $keyinfo = date("d F Y", strtotime($keyinfo));
    return "<div class='key-info-item'><strong>$label:</strong> " . esc_html($keyinfo) . "</div>";
}
?>

<div id="primary" class="row-fluid">
    <div id="content" role="main" class="span8 offset2">
        <section class="hero-style-1" style="background-image: url(...)">
            <div class="container">
                <div class="title-box">
                    <h1><?php the_title() ?></h1>
                </div>
                <div class="key-information-box">
                    <h3>Key Information</h3>
                    <div class="key-information-holder">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="key-info-items">
                                    <?= key_info_unit('_nationalcode', 'Unit Code') ?>
                                    <?= key_info_unit('_id_alpha', 'Open Awards Unit ID') ?>
                                    <?php
                                    $level = get_post_meta($post_id, '_level', true);
                                    if ($level) {
                                        $level_display = (strpos($level, 'E') === 0) ? 'Entry Level ' . substr($level, 1) : 'Level ' . substr($level, 1);
                                        echo "<div class='key-info-item'><strong>Level:</strong> $level_display</div>";
                                    }
                                    ?>
                                    <?= key_info_unit('_qcasector', 'Sector') ?>
                                    <?= key_info_unit('_credits', 'Credit Value') ?>
                                    <?= key_info_unit('_classification2', 'Risk Rating') ?>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="key-info-items">
                                    <?= key_info_unit('_classification3', 'Unit Type') ?>
                                    <?= key_info_unit('_recognitiondate', 'Start Date', 'date') ?>
                                    <?= key_info_unit('_reviewdate', 'Review Date', 'date') ?>
                                    <?= key_info_unit('_expirydate', 'End Date', 'date') ?>
                                    <?= key_info_unit('_glh', 'Guided Learning Hours') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="info-boxes">
            <div class="container">
                <div class="row g-4">
                    <?php if ($unitPdf): ?>
                        <div class="col-12">
                            <div class="info-box">
                                <div class="inner justify-content-start mx-0 mw-100">
                                    <h2 class="h3-style-1">Unit Content (PDF)</h2>
                                    <div class="button-box-v2 button-primary">
                                        <a href="<?= esc_url($unitPdf) ?>" target="_blank" rel="noopener noreferrer">Download PDF</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="related-qualifications archive-section pt-0">
            <div class="container">
                <h2 class="h2-style-1">Explore our Units</h2>
                <?= do_shortcode('[related_units]') ?>
            </div>
        </section>
    </div>
</div>
<?php get_footer(); ?>