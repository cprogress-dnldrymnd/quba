<?php 
/**
 * Template Name: Unit Single
 * Description: Renders individual unit pages utilizing the Quba_API OOP wrapper.
 * Author: Digitally Disruptive - Donald Raymundo
 * Author URI: https://digitallydisruptive.co.uk/
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

get_header(); 

// Try to get ID_Alpha from Carbon
$id = carbon_get_the_post_meta('id');

// If Carbon is empty, try WordPress native meta (as a backup)
if (empty($id)) {
    $id = get_post_meta(get_the_ID(), 'ID_Alpha', true);
}

// Debug: show what we're actually passing to the API
echo "";

/**
 * Fetch dynamic unit data from the QUBA SOAP API via the Quba_API wrapper.
 * Returns a stdClass object containing unit parameters or an error state.
 */
$unitDetails = Quba_API::unit_search_by_id($id);

// Establish execution states for secondary SOAP Extractions
$unitPdf = false;
$unitQualification = [];

try {
    // Utilize centralized SOAP object creation methodology
    $client = Quba_API::get_client();
    
    if ( $client && !empty($id) ) {
        
        // 1. Fetch Unit Listing Document stream locally onto plugin storage
        try {
            $pdf_response = $client->QUBA_GetUnitListingDocument(['qualificationID' => (int) $id]);
            
            if (isset($pdf_response->QUBA_GetUnitListingDocumentResult) && !empty($pdf_response->QUBA_GetUnitListingDocumentResult)) {
                $pdfContent = $pdf_response->QUBA_GetUnitListingDocumentResult;
                
                // Decode payload dynamically avoiding strictly encoded base64 drops
                if (base64_decode($pdfContent, true) !== false) {
                    $pdfContent = base64_decode($pdfContent);
                }
                
                $upload_dir = wp_upload_dir();
                $fileName = "UnitListing_" . (int)$id . ".pdf";
                $filePath = $upload_dir['path'] . "/" . $fileName;
                
                if (file_put_contents($filePath, $pdfContent) !== false) {
                    $unitPdf = $upload_dir['url'] . "/" . $fileName;
                }
            }
        } catch (Exception $e) {
            // Failsafe suppressing DBNull errors for missing documents
            if (strpos($e->getMessage(), "DBNull") === false) {
                error_log('SOAP Unit Document Retrieval Error: ' . $e->getMessage());
            }
        }

        // 2. Fetch Related Qualifications mapped to this Unit ID
        try {
            $qual_request = [
                'qualificationID'     => 0,
                'qualificationTitle'  => '',
                'qualificationLevel'  => '',
                'qualificationNumber' => '',
                'qcaSector'           => '',
                'provisionType'       => '',
                'unitID'              => $id,
                'includeHub'          => false,
                'centreID'            => ''
            ];
            $qual_response = $client->QUBA_QualificationSearch($qual_request);
            $xmlString = $qual_response->QUBA_QualificationSearchResult->any ?? '';
            
            if ($xmlString) {
                // Wrapper construct mapping identical to the API handler scope
                $responseString = '<?xml version="1.0" encoding="utf-8"?>
                <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                    <soap:Body>
                        <QUBA_QualificationSearchResponse xmlns="http://tempuri.org/">
                            <QUBA_QualificationSearchResult namespace="" tableTypeName="">
                                ' . $xmlString . '
                            </QUBA_QualificationSearchResult>
                        </QUBA_QualificationSearchResponse>
                    </soap:Body>
                </soap:Envelope>';
                
                libxml_use_internal_errors(true);
                $xml = new SimpleXMLElement($responseString);
                $unitQualification = $xml->xpath('//QubaQualification') ?: [];
            }
        } catch (Exception $e) {
            error_log('QUBA Qualification Search Error: ' . $e->getMessage());
        }
    }
} catch (Exception $e) {
    error_log('SOAP Client Instantiation Error: ' . $e->getMessage());
}

/**
 * Helper function to output formatted key info nodes.
 * * @param string $key Meta field target.
 * @param string $label Visual output mapping string.
 * @param string $type Output evaluation type formatting.
 * @return string Valid HTML string DOM structure.
 */
function key_info($key, $label, $type = 'string') {
    $keyinfo = carbon_get_the_post_meta($key);

    if ($type == 'date' && $keyinfo) {
        $originalDate = $keyinfo;
        $keyinfo = date("d F Y", strtotime($originalDate));
    } else if (!$keyinfo) {
        $keyinfo = false;
    }
    
    if ($keyinfo) {
        return "<div class='key-info-item'><strong>" . esc_html($label) . ":</strong> " . esc_html($keyinfo) . "</div>";
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
                    <h1><?php the_title() ?></h1>
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
                                        <div class="key-info-item"><strong>Review Date:</strong> <?= !empty($unitDetails->ReviewDate) ? date('d F Y', strtotime($unitDetails->ReviewDate)) : 'N/A';?></div>
                                        <div class="key-info-item"><strong>End Date:</strong> <?= !empty($unitDetails->ExpiryDate) ? date('d F Y', strtotime($unitDetails->ExpiryDate)) : 'N/A'; ?></div>
                                        <div class="key-info-item"><strong>Guided Learning Hours:</strong> <?= !empty($unitDetails->GLH) ? htmlentities($unitDetails->GLH) : 'N/A'; ?></div>
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
                                    $qualifications = [];
                                    
                                    // Translate SimpleXMLElement outputs down to standardized associative arrays
                                    if (!empty($unitQualification)) {
                                        foreach ($unitQualification as $qual) {
                                            $qualifications[] = [
                                                'title'    => (string)$qual->Title,
                                                'code'     => (string)$qual->QualificationReferenceNumber,
                                                'id'       => (string)$qual->ID,
                                                'level'    => (string)$qual->Level,
                                                'credits'  => (string)$qual->TotalCreditsRequired,
                                                'expanded' => false
                                            ];
                                        }
                                    }

                                    if (empty($qualifications)) {
                                        echo '<div>No related qualifications are currently available for this unit.</div>';
                                    } else {
                                        foreach ($qualifications as $index => $qualification) {
                                            $expandButton = '+';

                                            // Access WP DB utilizing our scoped Quba_Render method abstraction
                                            $post_id = Quba_Render::get_post_id_by_meta_field('_id', $qualification['id']);

                                            if (!$post_id) {
                                                $post_id = Quba_Render::get_post_id_by_meta_field('_qualificationreferencenumber', $qualification['code']);
                                            }

                                            if (!$post_id) {
                                                $title_slug = sanitize_title($qualification['title']);
                                                $code_part = preg_replace('/[^a-zA-Z0-9]+/', '', $qualification['code']);
                                                $slug = $title_slug . '-' . $code_part;
                                                $post = get_page_by_path($slug, OBJECT, 'qualifications');
                                                if ($post) {
                                                    $post_id = $post->ID;
                                                }
                                            }
                                            
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
                                                <span class="detail-value"><?php echo esc_html($qualification['code']); ?></span>
                                            </div>
                                            <?php
                                            $level = $qualification['level'];
                                            if (!empty($level)) {
                                                if (strpos($level, 'E') === 0) {
                                                    $level = 'Entry Level ' . str_replace('E', '', $level);
                                                } elseif (strpos($level, 'L') === 0) {
                                                    $level = 'Level ' . str_replace('L', '', $level);
                                                }
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

                    <?php if ($additional_documents): ?>
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
                                        <div class="button-box-v2 button-primary">
                                            <a href="<?= esc_url(wp_get_attachment_url($doc['document_file'])) ?>" target="_blank">View Document</a>
                                        </div>
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