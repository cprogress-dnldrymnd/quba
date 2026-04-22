<?php 
/**
 * Template Name: Qualification Single
 * Description: Renders individual qualification pages decoupled from SOAP limitations mapping to WP post meta fields.
 * Author: Digitally Disruptive - Donald Raymundo
 * Author URI: https://digitallydisruptive.co.uk/
 */
get_header(); 

$post_id = get_the_ID();
$id = get_post_meta($post_id, '_id', true);

// Extract Local Sync PDFs Mapping
$pdf_url = get_post_meta($post_id, '_purpose_statement_url', true);
$baseURL = get_post_meta($post_id, '_qualification_guide_url', true);

function key_info($key, $label, $type = 'string') {
    global $post;
    $keyinfo = get_post_meta($post->ID, '_' . $key, true);
    
    if ($key == 'level' && !empty($keyinfo)) {
        if (strpos($keyinfo, 'E') === 0) $keyinfo = 'Entry Level ' . str_replace('E', '', $keyinfo);
        elseif (strpos($keyinfo, 'L') === 0) $keyinfo = 'Level ' . str_replace('L', '', $keyinfo);
    }
    
    if ($type == 'date' && !empty($keyinfo)) {
        $keyinfo = date("d F Y", strtotime($keyinfo));
    }
    
    return $keyinfo ? "<div class='key-info-item'><strong>$label:</strong> " . esc_html($keyinfo) . "</div>" : '';
}

$additional_documents = get_post_meta($post_id, 'additional_documents', true);
?>


<div id="primary" class="row-fluid">
    <div id="content" role="main" class="span8 offset2">
        <?php 
        if (current_user_can('administrator')) {
            echo '<div class="debug-info">';
            echo '<h3>Debug Information (Admin Only)</h3>';
            echo '<p>Mapped WP Post ID: ' . esc_html($post_id) . '</p>';
            echo '<p>Qualification ID: ' . esc_html($id) . '</p>';
            echo '<p>Purpose Statement Local PDF: ' . ($pdf_url ? 'Available' : 'Not Available') . '</p>';
            echo '<p>Qualification Guide Local PDF: ' . ($baseURL ? 'Available' : 'Not Available') . '</p>';
            echo '</div>';
        }
        ?>

        <section class="hero-style-1" style="background-image: url(https://openawards.theprogressteam.com/wp-content/uploads/2024/12/qual-hero-bg.png)">
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
                                    <?php
                                    echo key_info('type', 'Qualification Type');
                                    
                                    $sector = get_post_meta($post_id, '_classification1', true);
                                    echo "<div class='key-info-item'><strong>Sector:</strong> " . (!empty($sector) ? esc_html($sector) : 'N/A') . "</div>";

                                    $code = get_post_meta($post_id, '_qualificationreferencenumber', true);
                                    echo "<div class='key-info-item'><strong>Qualification Code:</strong> " . (!empty($code) ? esc_html($code) : 'N/A') . "</div>";

                                    $risk = get_post_meta($post_id, '_classification2', true);
                                    echo "<div class='key-info-item'><strong>Risk:</strong> " . (!empty($risk) ? esc_html($risk) : 'N/A') . "</div>";
                                                        
                                    echo key_info('level', 'Level');
                                    ?>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="key-info-items">
                                    <?php
                                    echo key_info('regulationenddate', 'Start Date', 'date');

                                    $review_date = get_post_meta($post_id, '_reviewdate', true);        
                                    if ($review_date) {
                                        $adjusted_date = date('d F Y', strtotime($review_date . ' +1 day'));
                                        echo "<div class='key-info-item'><strong>Review Date:</strong> " . esc_html($adjusted_date) . "</div>";
                                    }

                                    $end_date = get_post_meta($post_id, '_regulationenddate', true);
                                    echo "<div class='key-info-item'><strong>Certification End Date:</strong> " . 
                                        (!empty($end_date) ? date('d F Y', strtotime($end_date)) : 'NA') . "</div>";
                                                    
                                    $min_age = get_post_meta($post_id, '_minage', true);
                                    echo "<div class='key-info-item'><strong>Min Age:</strong> " . 
                                        (!empty($min_age) ? esc_html($min_age) : 'NA') . "</div>";

                                    echo key_info('glh', 'Guided Learning Hours');
                                    echo key_info('tqt', 'Total Qualification Time');
                                    ?>
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
                    <?php if ($pdf_url): ?>
                    <div class="col-lg-6">
                        <div class="info-box">
                            <div class="inner">
                                <h2 class="h2-style-1">Purpose Statement<span>.</span></h2>
                                <ul>
                                    <li>Who is it for?</li>
                                    <li>What does this qualification cover?</li>
                                    <li>What are the Entry Requirements?</li>
                                    <li>What are the Assessment Methods?</li>
                                    <li>What are the Progression Opportunities?</li>
                                    <li>Who supports this qualification?</li>
                                </ul>
                                <div class="button-box-v2 button-primary">
                                    <a href="<?= esc_url($pdf_url) ?>" target="_blank">Purpose Statement</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($baseURL): ?>
                    <div class="col-lg-6">
                        <div class="info-box">
                            <div class="inner">
                                <h2 class="h2-style-1">Qualification Guide<span>.</span></h2>
                                <ul>
                                    <li>About the Qualification</li>
                                    <li>Qualification Units</li>
                                    <li>Delivering this Qualification</li>
                                    <li>Appendices and Links</li>
                                </ul>
                                <div class="button-box-v2 button-accent">
                                    <a href="<?= esc_url($baseURL) ?>" target="_blank">Qualification Guide</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($additional_documents && is_array($additional_documents) && count($additional_documents) > 0): ?>
                    <div class="col-lg-6">
                        <div class="info-box">
                            <div class="inner">
                                <h2 class="h2-style-1">Additional documents<span>.</span></h2>
                                <ul class="additional-documents">
                                    <?php foreach ($additional_documents as $additional_document): ?>
                                    <li>
                                        <h3><?= esc_html($additional_document['document_title']) ?></h3>
                                        <?php if (isset($additional_document['document_file']) && !empty($additional_document['document_file'])): ?>
                                        <div class="button-box-v2 button-primary">
                                            <a href="<?= esc_url(wp_get_attachment_url($additional_document['document_file'])) ?>" target="_blank">View Document</a>
                                        </div>
                                        <?php else: ?>
                                        <div class="button-box-v2 button-disabled">
                                            <span>Document Unavailable</span>
                                        </div>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (get_the_content()): ?>
                    <div class="col-12">
                        <div class="info-box info-box-v2">
                            <div class="inner">
                                <div class="row align-items-center g-3">
                                    <div class="col">
                                        <?php the_content() ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="back-to mt-4">
                    <div class="button-box-v2 button-accent">
                        <a href="/qualifications/"><svg class="me-2" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-left" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0"/></svg> Back to Qualifications</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="related-qualifications archive-section archive-section-qualifications pt-0">
            <div class="container">
                <h2 class="h2-style-1">Explore our Qualifications</h2>
                <?= do_shortcode('[related_qualifications]') ?>
            </div>
        </section>
        
        <?= do_shortcode('[template template_id=2969]') ?>
    </div>
</div>

<?php get_footer() ?>