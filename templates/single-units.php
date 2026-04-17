<?php 
/**
 * Template Name: Unit Single
 * Description: Renders individual unit pages utilizing localized WP Meta architecture.
 * Author: Digitally Disruptive - Donald Raymundo
 * Author URI: https://digitallydisruptive.co.uk/
 */

get_header(); 

$post_id = get_the_ID();
$id = get_post_meta($post_id, '_id_alpha', true) ?: get_post_meta($post_id, '_id', true);

$unitPdf = get_post_meta($post_id, '_unit_content_url', true);
$unitQualification = get_post_meta($post_id, '_related_qualifications', true);

$additional_documents = get_post_meta($post_id, 'additional_documents', true);
?>
<pre>
    <?php var_dump(get_post_meta(get_the_ID())) ?>
</pre>
<div id="primary" class="row-fluid">
    <div id="content" role="main" class="span8 offset2">
        
        <?php 
        if (current_user_can('administrator')) {
            echo '<div class="debug-info">';
            echo '<h3>Debug Information (Admin Only)</h3>';
            echo '<p>Mapped WP Post ID: ' . esc_html($post_id) . '</p>';
            echo '<p>Internal Unit API ID (_id): ' . esc_html(get_post_meta($post_id, '_id', true)) . '</p>';
            echo '<p>Open Awards Unit ID (_id_alpha): ' . esc_html(get_post_meta($post_id, '_id_alpha', true)) . '</p>';
            echo '<p>Unit Document Local PDF: ' . ($unitPdf ? 'Available' : 'Not Available') . '</p>';
            echo '<p>Related Qualifications Cached Count: ' . (is_array($unitQualification) ? count($unitQualification) : 0) . '</p>';
            echo '</div>';
        }
        ?>

        <section class="hero-style-1"
            style="background-image: url(https://openawards.theprogressteam.com/wp-content/uploads/2024/12/qual-hero-bg.png)">
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
                                        $national_code = get_post_meta($post_id, '_nationalcode', true);
                                        $unit_ref = get_post_meta($post_id, '_unitreferencenumber', true);

                                        $unit_code = $national_code ?: $unit_ref ?: 'N/A';
                                    ?>

                                    <div class="key-info-item">
                                        <strong>Unit Code:</strong> <?= esc_html($unit_code) ?>
                                    </div>
                                    <div class="key-info-item"><strong>Open Awards Unit ID:</strong> <?= esc_html(get_post_meta($post_id, '_id_alpha', true) ?: 'N/A') ?></div>
                                        <?php
                                        $level = get_post_meta($post_id, '_level', true);
                                        if (!empty($level)) {
                                            if (strpos($level, 'E') === 0) $level = 'Entry Level ' . substr($level, 1);
                                            elseif (strpos($level, 'L') === 0) $level = 'Level ' . substr($level, 1);
                                        }
                                        ?>
                                    <div class="key-info-item"><strong>Level:</strong> <?= $level ? esc_html($level) : 'N/A' ?></div>
                                    <div class="key-info-item"><strong>Sector:</strong> <?= esc_html(get_post_meta($post_id, '_qcasector', true) ?: 'N/A') ?></div>
                                    <div class="key-info-item"><strong>Credit Value:</strong> <?= esc_html(get_post_meta($post_id, '_credits', true) ?: 'N/A') ?></div>
                                    <div class="key-info-item"><strong>Risk Rating:</strong> <?= esc_html(get_post_meta($post_id, '_classification2', true) ?: 'N/A') ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="key-info-items">
                                    <div class="key-info-item"><strong>Unit Type:</strong> <?= esc_html(get_post_meta($post_id, '_classification3', true) ?: 'N/A') ?></div>
                                    <div class="key-info-item"><strong>Start Date:</strong> <?= get_post_meta($post_id, '_recognitiondate', true) ? date('d F Y', strtotime(get_post_meta($post_id, '_recognitiondate', true))) : 'N/A' ?></div>
                                    <div class="key-info-item"><strong>Review Date:</strong> <?= get_post_meta($post_id, '_reviewdate', true) ? date('d F Y', strtotime(get_post_meta($post_id, '_reviewdate', true))) : 'N/A' ?></div>
                                    <div class="key-info-item"><strong>End Date:</strong> <?= get_post_meta($post_id, '_expirydate', true) ? date('d F Y', strtotime(get_post_meta($post_id, '_expirydate', true))) : 'N/A' ?></div>
                                    <div class="key-info-item"><strong>Guided Learning Hours:</strong> <?= esc_html(get_post_meta($post_id, '_glh', true) ?: 'N/A') ?></div>
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
                                    <a href="<?php echo esc_url($unitPdf); ?>" target="_blank" rel="noopener noreferrer">Download PDF</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <div class="info-box">
                            <div class="inner mx-0 mw-100">
                                <div class="related-qualifications-container mx-0 mw-100">
                                    <h2 class="h3-style-1">RELATED QUALIFICATIONS</h2>
                                    <?php
                                    if (empty($unitQualification)) {
                                        echo '<div>No related qualifications are currently available for this unit.</div>';
                                    } else {
                                        foreach ($unitQualification as $index => $qualification) {
                                            $expandButton = '+';

                                            $mapped_post_id = Quba_Render::get_post_id_by_meta_field('_id', $qualification['id']);
                                            if (!$mapped_post_id) {
                                                $mapped_post_id = Quba_Render::get_post_id_by_meta_field('_qualificationreferencenumber', $qualification['code']);
                                            }
                                            if (!$mapped_post_id) {
                                                $title_slug = sanitize_title($qualification['title']);
                                                $code_part = preg_replace('/[^a-zA-Z0-9]+/', '', $qualification['code']);
                                                $post = get_page_by_path($title_slug . '-' . $code_part, OBJECT, 'qualifications');
                                                if ($post) $mapped_post_id = $post->ID;
                                            }
                                            
                                            $qualification_link = $mapped_post_id ? get_permalink($mapped_post_id) : home_url('/qualifications/' . sanitize_title($qualification['title']) . '-' . preg_replace('/[^a-zA-Z0-9]+/', '', $qualification['code']));
                                    ?>
                                    <div class="qualification-box" data-index="<?php echo $index; ?>">
                                        <div class="qualification-header">
                                            <span> 
                                                <a href="<?php echo esc_url($qualification_link); ?>" target="_blank" style="color: #000;">
                                                    <?php echo esc_html($qualification['title']); ?>
                                                </a>
                                            </span>
                                            <button class="expand-btn"><?php echo $expandButton; ?></button>
                                        </div>
                                        <div class="qualification-details" style="display: none;">
                                            <div class="detail-row">
                                                <span class="detail-label">Qualification Code:</span>
                                                <span class="detail-value"><?php echo esc_html($qualification['code']); ?></span>
                                            </div>
                                            <?php
                                            $level = $qualification['level'];
                                            if (!empty($level)) {
                                                if (strpos($level, 'E') === 0) $level = 'Entry Level ' . str_replace('E', '', $level);
                                                elseif (strpos($level, 'L') === 0) $level = 'Level ' . str_replace('L', '', $level);
                                            }
                                            ?>
                                            <div class="detail-row">
                                                <span class="detail-label">Level:</span>
                                                <span class="detail-value"><?php echo esc_html($level); ?></span>
                                            </div>
                                            <div class="detail-row">
                                                <span class="detail-label">Minimum Credits:</span>
                                                <span class="detail-value"><?php echo esc_html($qualification['credits']); ?></span>
                                            </div>
                                            <div class="button-box-v2 button-accent">
                                                <a href="<?php echo esc_url($qualification_link); ?>">View Qualification</a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php 
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($additional_documents && is_array($additional_documents) && count($additional_documents) > 0): ?>
                        <div class="col-lg-6">
                            <div class="info-box">
                                <div class="inner">
                                    <h2 class="h2-style-1">Additional documents<span>.</span></h2>
                                    <?php foreach ($additional_documents as $doc): ?>
                                        <ul class="additional-documents">
                                            <li>
                                                <h3><?= esc_html($doc['document_title']) ?></h3>
                                            </li>
                                        </ul>
                                        <?php if (!empty($doc['document_file'])): ?>
                                        <div class="button-box-v2 button-primary">
                                            <a href="<?= esc_url(wp_get_attachment_url($doc['document_file'])) ?>" target="_blank">View Document</a>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
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
                        <a href="/units/"><svg class="me-2" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                fill="currentColor" class="bi bi-chevron-left" viewBox="0 0 16 16">
                                <path fill-rule="evenodd"
                                    d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0" />
                            </svg> Back to Units</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="related-qualifications archive-section archive-section-qualifications pt-0">
            <div class="container">
                <h2 class="h2-style-1">
                    Explore our Units
                </h2>
                <?= do_shortcode('[related_units]') ?>
            </div>
        </section>
        
        <?= do_shortcode('[template template_id=2969]') ?>
    </div>
</div>
<?php get_footer(); ?>