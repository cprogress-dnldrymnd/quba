<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

get_header() 

?>
<?php
// Try to get ID_Alpha from Carbon
$id = carbon_get_the_post_meta('id');


// If Carbon is empty, try WordPress native meta (as a backup)
if (empty($id)) {
    $id = get_post_meta(get_the_ID(), 'ID_Alpha', true);
}

// Debug: show what we're actually passing to the API
echo "<!-- DEBUG: unitIDAlpha passed to API = $id -->";

// Now call the API
$unitDetails = QUBA_UnitSearchById($id);
    $unitPdf = QUBA_GetUnitListingDocument($id);
//     $unitQualification = QUBA_QualificationSearchForUnit(8848);
// echo "<pre>";
// var_dump($unitQualification);


function key_info($key, $label, $type = 'string')
{
    $keyinfo = carbon_get_the_post_meta($key);

    if ($type == 'date' && $keyinfo) {
        $originalDate = $keyinfo;
        $keyinfo = date("d F Y", strtotime($originalDate));
    } else {
        $keyinfo = false;
    }
    if ($keyinfo) {
        return "<div class='key-info-item'><strong>$label:</strong> $keyinfo</div>";
    }
}



$additional_documents = carbon_get_the_post_meta('additional_documents');

?>
<div id="primary" class="row-fluid">
    <div id="content" role="main" class="span8 offset2">
        <section class="hero-style-1"
            style="background-image: url(https://openawards.theprogressteam.com/wp-content/uploads/2024/12/qual-hero-bg.png)">
            <div class="container">
                <div class="title-box">
                    <h1>
                        <?php the_title() ?>
                    </h1>
                </div>
           <div class="key-information-box">
    <h3>Key Information</h3>
    <div class="key-information-holder">
        <div class="row">
            <?php if (!isset($unitDetails->error)) { ?>
                <div class="col-sm-6">
                    <div class="key-info-items">
                        <div class="key-info-item"><strong>Unit Code:</strong> <?= !empty($unitDetails->NationalCode) ? htmlentities($unitDetails->NationalCode) : 'N/A'; ?></div>
                        <div class="key-info-item"><strong>Open Awards Unit ID:</strong> <?= !empty($unitDetails->ID_Alpha) ? htmlentities($unitDetails->ID_Alpha) : 'N/A'; ?></div>
                            <?php
                            $level = $unitDetails->Level ?? '';
                            
                            if (!empty($level)) {
                                if (strpos($level, 'E') === 0) {
                                    $level = 'Entry Level ' . substr($level, 1);
                                } elseif (strpos($level, 'L') === 0) {
                                    $level = 'Level ' . substr($level, 1);
                                }
                            }
                            ?>
                            
                            <div class="key-info-item">
                                <strong>Level:</strong> <?= $level ? htmlentities($level) : 'N/A'; ?>
                            </div>
                        <div class="key-info-item"><strong>Sector:</strong> <?= !empty($unitDetails->QCASector) ? htmlentities($unitDetails->QCASector) : 'N/A'; ?></div>
                        <div class="key-info-item"><strong>Credit Value:</strong> <?= !empty($unitDetails->Credits) ? htmlentities($unitDetails->Credits) : 'N/A'; ?></div>
                        <div class="key-info-item"><strong>Risk Rating:</strong> <?= !empty($unitDetails->Classification2) ? htmlentities($unitDetails->Classification2) : 'N/A'; ?>
                        </div>
                    </div>
                </div>
               <div class="col-sm-6">
                    <div class="key-info-items">
                        <div class="key-info-item"><strong>Unit Type:</strong>  <?= !empty($unitDetails->Classification3) ? htmlentities($unitDetails->Classification3) : 'N/A'; ?></div>
                        <div class="key-info-item"><strong>Start Date:</strong> <?= !empty($unitDetails->RecognitionDate) ? date('d F Y', strtotime($unitDetails->RecognitionDate)) : 'N/A';?></div>
                        <div class="key-info-item"><strong>Review Date:</strong>
                        <?= !empty($unitDetails->ReviewDate) ? date('d F Y', strtotime($unitDetails->ReviewDate)) : 'N/A';?>
                        </div>
                            <div class="key-info-item"><strong>End Date:</strong> <?= !empty($unitDetails->ExpiryDate) ? date('d F Y', strtotime($unitDetails->ExpiryDate)) : 'N/A'; ?>
                            </div>
                            <div class="key-info-item">
                                <strong>Guided Learning Hours:</strong> <?= !empty($unitDetails->GLH) ? htmlentities($unitDetails->GLH) : 'N/A'; ?>
                            </div>
                       
                    </div>
                </div>
            <?php } else { ?>
                <div class="col-12">
                    <p><strong>Error:</strong> <?= htmlentities($unitDetails->error); ?></p>
                </div>
            <?php } ?>
        </div>
    </div>
</div>


            </div>
        </section>


        <section class="info-boxes">
            <div class="container">
                <div class="row g-4">
				<div class="col-12">
					<div class="info-box">
						<div class="inner justify-content-start mx-0 mw-100">
							<h2 class="h3-style-1">Unit Content (PDF)</h2>

							<div class="button-box-v2 button-primary">
								<a href="<?php echo $unitPdf; ?>" target="_blank" rel="noopener noreferrer">Download PDF</a>
							</div>
						</div>
					</div>
				</div>

                    <div class="col-12">
                        <div class="info-box">
                            <div class="inner mx-0 mw-100">
							<div class="related-qualifications-container mx-0 mw-100">
							<h2 class="h3-style-1">RELATED QUALIFICATIONS</h2>
							<?php
							// Your API data
							$unitQualification = QUBA_QualificationSearchForUnit($id);

							// Create an array with the qualification data
							$qualifications = [];
							// Add the existing qualification from the API
							foreach ($unitQualification as $qual) {
								$qualifications[] = [
									'title' => (string)$qual->Title,
									'code' => (string)$qual->QualificationReferenceNumber,
									'id' => (string)$qual->ID,
									'level' => (string)$qual->Level,
									'credits' => (string)$qual->TotalCreditsRequired,
									'expanded' => false  // All start closed by default
								];
							}
							// Check if there are any qualifications
							if (empty($qualifications)) {
								echo '<div class="">No related qualifications are currently available for this unit.</div>';
							} else {
								foreach ($qualifications as $index => $qualification) {
									$expandedClass = '';  // All start collapsed
									$expandButton = '+';  // All start with plus sign

									// Try to find the post ID based on the qualification ID from the API
									$post_id = get_post_id_by_meta_field('_id', $qualification['id']);

									// If we can't find it by ID, try to find it by qualification reference number
									if (!$post_id) {
										$post_id = get_post_id_by_meta_field('_qualificationreferencenumber', $qualification['code']);
									}

									// If we still don't have a post ID, create a slug that matches the pattern in qual_grid
									if (!$post_id) {
										$title_slug = sanitize_title($qualification['title']);
										$code_part = preg_replace('/[^a-zA-Z0-9]+/', '', $qualification['code']);
										$slug = $title_slug . '-' . $code_part;
										// Try to get post by slug
										$post = get_page_by_path($slug, OBJECT, 'qualifications');
										if ($post) {
											$post_id = $post->ID;
										}
									}
							?>
							<?php
                                if ($post_id) {
                                    $qualification_link = get_permalink($post_id);
                                } else {
                                    $title_slug = sanitize_title($qualification['title']);
                                    $code_part = preg_replace('/[^a-zA-Z0-9]+/', '', $qualification['code']);
                                    $fallback_slug = $title_slug . '-' . $code_part;
                                
                                    $qualification_link = home_url('/qualifications/' . $fallback_slug);
                                }
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
										<span class="detail-value"><?php echo $qualification['code']; ?></span>
									</div>
									<?php
                                    $level = $qualification['level'];
                                    
                                    if (!empty($level)) {
                                        if (strpos($level, 'E') === 0) {
                                            $num = str_replace('E', '', $level);
                                            $level = 'Entry Level ' . $num;
                                        } elseif (strpos($level, 'L') === 0) {
                                            $num = str_replace('L', '', $level);
                                            $level = 'Level ' . $num;
                                        }
                                    }
                                    ?>

									<div class="detail-row">
										<span class="detail-label">Level:</span>
									    <span class="detail-value"><?php echo $level; ?></span>
									</div>
									<div class="detail-row">
										<span class="detail-label">Minimum Credits:</span>
										<span class="detail-value"><?php echo $qualification['credits']; ?></span>
									</div>
									<div class="button-box-v2 button-accent">
										<?php if ($post_id): ?>
											<a href="<?php echo get_the_permalink($post_id); ?>">View Qualification</a>
										<?php else: ?>
											<!-- Fallback for qualifications that don't have a post yet -->
											<?php
											$title_slug = sanitize_title($qualification['title']);
											$code_part = preg_replace('/[^a-zA-Z0-9]+/', '', $qualification['code']);
											$fallback_slug = $title_slug . '-' . $code_part;
											?>
											<a href="<?php echo home_url('/qualifications/' . $fallback_slug); ?>">View Qualification</a>
										<?php endif; ?>
									</div>
								</div>
							</div>
							<?php 
								}
							}
							?>
						</div>
								
<!--                                 <h2 class="h3-style-1">Related Qualifications</h2>
                                <ul>
                                    <li>About the Qualifitcation</li>
                                    <li>Qualification Units</li>
                                    <li>Delivering this Qualification</li>
                                    <li>Appendices and Links</li>
                                </ul>
                                <div class="button-box-v2 button-accent">
                                    <a href="" target="_blank">Qualification Guide</a>
                                </div> -->
                            </div>
                        </div>
                    </div>
                    <?php if ($additional_documents) { ?>
                        <div class="col-lg-6">
                            <div class="info-box">
                                <div class="inner">
                                    <h2 class="h2-style-1">Additional documents<span>.</span></h2>
										<?php foreach ($additional_documents as $additional_documents) {  ?>
                                    <ul class="additional-documents">

                                        
                                            <li>

                                                <h3><?= $additional_documents['document_title'] ?></h3>


                                            </li>

                                    </ul>
												<div class="button-box-v2 button-primary">
                                                    <a href="<?= wp_get_attachment_url($additional_documents['document_file']) ?>" target="_blank">View Document</a>
                                                </div>
                                        <?php } ?>

                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if (get_the_content()) { ?>
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
                    <?php } ?>
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
<?php get_footer() ?>