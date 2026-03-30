<?php get_header() ?>
<?php
$id = carbon_get_the_post_meta('id');

// Add debug logging to start of page
error_log('Loading qualification page for ID: ' . $id);

$qualificationDetails = QUBA_QualificationSearchById($id);

// Enable this for debugging
// echo "<pre>"; var_dump($qualificationDetails); echo "</pre>";

// Create SOAP client with better error handling
try {
    $client = new SoapClient('https://quba.quartz-system.com/QuartzWSExtra/OCNNWR/WSQUBA_UB_V3.asmx?WSDL', array(
        'exceptions' => true,
        'trace' => 1,
        'connection_timeout' => 30
    ));

    // Call the SOAP methods with error handling
    $request = array('qualificationID' => $id);
    
    // Get Qualification Guide
    try {
        $response = $client->QUBA_GetQualificationGuide($request);
        error_log('Guide API call successful');
    } catch (Exception $e) {
        error_log('Guide API Error: ' . $e->getMessage());
        $response = null;
    }
    
    // Get Qualification Documents
    try {
        $response_doc = $client->QUBA_GetQualificationDocuments($request);
        error_log('Documents API call successful');
    } catch (Exception $e) {
        error_log('Documents API Error: ' . $e->getMessage());
        $response_doc = null;
    }
    
} catch (Exception $e) {
    error_log('SOAP Client creation failed: ' . $e->getMessage());
    $client = null;
    $response = null;
    $response_doc = null;
}

// Improved debug function with more checks
function debug_api_response($response_doc) {
    if (!$response_doc) {
        error_log('Debug: API response is null');
        return;
    }
    
    if (!isset($response_doc->QUBA_GetQualificationDocumentsResult->any)) {
        error_log('Debug: API response missing expected structure');
        return;
    }
    
    $any_data = (string) $response_doc->QUBA_GetQualificationDocumentsResult->any;
    $has_pdf_header = strpos($any_data, 'JVBERi0x') !== false;
    
    error_log('Debug: Response length: ' . strlen($any_data));
    error_log('Debug: Contains PDF header: ' . ($has_pdf_header ? 'Yes' : 'No'));
    
    // Check for other common formats
    $has_xml = strpos($any_data, '<?xml') !== false;
    $has_json = strpos($any_data, '{') !== false;
    
    error_log('Debug: Contains XML: ' . ($has_xml ? 'Yes' : 'No'));
    error_log('Debug: Contains JSON: ' . ($has_json ? 'Yes' : 'No'));
    
    // If it's XML, try parsing it to see if there's useful info
    if ($has_xml) {
        try {
            $xml = simplexml_load_string($any_data);
            error_log('Debug: XML parsed successfully');
        } catch (Exception $e) {
            error_log('Debug: XML parsing failed: ' . $e->getMessage());
        }
    }
}

// Improved function to extract and save PDF from API response
function save_api_response_as_pdf($response_doc) {
    if (!$response_doc) {
        error_log('PDF extraction failed: API response is null');
        return false;
    }
    
    // Check if the response has the expected structure
    if (!isset($response_doc->QUBA_GetQualificationDocumentsResult->any)) {
        error_log('PDF extraction failed: Invalid API response format.');
        return false;
    }
    
    // Extract the "any" field
    $any_data = (string) $response_doc->QUBA_GetQualificationDocumentsResult->any;
    
    // Find the position where the base64 PDF data starts
    $pdf_start_pos = strpos($any_data, 'JVBERi0x');
    
    if ($pdf_start_pos === false) {
        error_log('PDF extraction failed: Could not find PDF content in response.');
        return false;
    }
    
    // Extract everything from the PDF header onwards
    $base64_pdf = substr($any_data, $pdf_start_pos);
    
    // Decode the base64 data
    $pdf_data = base64_decode($base64_pdf);
    if (!$pdf_data) {
        error_log('PDF extraction failed: Failed to decode base64 PDF data.');
        return false;
    }
    
    $time = time();
    $file_name = "PurposeStatement_$time.pdf";
    $file_path = get_template_directory() . "/includes/" . $file_name;
    
    // Ensure the includes directory exists
    if (!file_exists(get_template_directory() . "/includes/")) {
        if (!mkdir(get_template_directory() . "/includes/", 0755, true)) {
            error_log('PDF extraction failed: Could not create includes directory.');
            return false;
        }
    }
    
    // Save the PDF file
    if (file_put_contents($file_path, $pdf_data) === false) {
        error_log('PDF extraction failed: Failed to save PDF file.');
        return false;
    }
    
    // Return the file URL
    $file_url = get_template_directory_uri() . "/includes/" . $file_name;
    error_log('PDF saved successfully at: ' . $file_url);
    return $file_url;
}

// Debug the API response before extraction
if ($response_doc) {
    debug_api_response($response_doc);
    
    // Try to extract and save the PDF
    $pdf_url = save_api_response_as_pdf($response_doc);
    error_log('Purpose Statement PDF URL: ' . ($pdf_url ? $pdf_url : 'Not available'));
} else {
    error_log('Skipping document extraction - response_doc is null');
    $pdf_url = false;
}

// Extract the binary PDF content from the guide response
$baseURL = false;
if ($response && isset($response->QUBA_GetQualificationGuideResult)) {
    // The API is returning raw PDF binary data
    $pdfContent = $response->QUBA_GetQualificationGuideResult;
    
    // Define the PDF file path inside the theme's 'includes' folder
    $fileName = "QualificationGuide_" . time() . ".pdf";
    $filePath = get_template_directory() . "/includes/" . $fileName; // Physical path
    
    // Ensure the includes directory exists
    if (!file_exists(get_template_directory() . "/includes/")) {
        mkdir(get_template_directory() . "/includes/", 0755, true);
    }
    
    // Save the binary content as a PDF file
    $save_result = file_put_contents($filePath, $pdfContent);
    if ($save_result === false) {
        error_log('Guide PDF save failed: Could not write to file');
    } else {
        error_log('Guide PDF saved successfully: ' . $filePath . ' (' . $save_result . ' bytes)');
        
        // Get the public URL of the file
        $baseURL = get_template_directory_uri() . "/includes/" . $fileName; // Public URL
        error_log('Guide PDF URL: ' . $baseURL);
    }
} else {
    $baseURL = false;
    error_log('Guide PDF extraction failed: Missing expected result structure or response is null');
}

function key_info($key, $label, $type = 'string')
{
    $keyinfo = carbon_get_the_post_meta($key);
     // Convert Level Codes
    if ($key == 'level' && !empty($keyinfo)) {
        if (strpos($keyinfo, 'E') === 0) {
            $num = str_replace('E', '', $keyinfo);
            $keyinfo = 'Entry Level ' . $num;
        } elseif (strpos($keyinfo, 'L') === 0) {
            $num = str_replace('L', '', $keyinfo);
            $keyinfo = 'Level ' . $num;
        }
    }
    if ($type == 'date') {
        $originalDate = $keyinfo;
        if (!empty($originalDate)) {
            $keyinfo = date("d F Y", strtotime($originalDate));
        }
    }
    if ($keyinfo) {
        return "<div class='key-info-item'><strong>$label:</strong> $keyinfo</div>";
    }
    return '';
}

$additional_documents = carbon_get_the_post_meta('additional_documents');
?>

<!-- Add CSS for disabled buttons -->
<style>
.button-disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.button-disabled span {
    color: #888;
}

/* Add debug box for admins */
.debug-info {
    margin: 20px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 5px;
}
</style>

<div id="primary" class="row-fluid">
    <div id="content" role="main" class="span8 offset2">
        <?php 
        // Show debug info for admins
        if (current_user_can('administrator')) {
            echo '<div class="debug-info">';
            echo '<h3>Debug Information (Admin Only)</h3>';
            echo '<p>Qualification ID: ' . esc_html($id) . '</p>';
            echo '<p>Purpose Statement PDF: ' . ($pdf_url ? 'Available' : 'Not Available') . '</p>';
            echo '<p>Qualification Guide PDF: ' . ($baseURL ? 'Available' : 'Not Available') . '</p>';
            echo '</div>';
        }
        ?>

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
                            <div class="col-sm-6">
                                <div class="key-info-items">
                                    <?php
        echo key_info('type', 'Qualification Type');
        

        // echo "<pre>"; var_dump($qualificationDetails); echo "</pre>";
        // Sector with fallback
        
        $sector = $qualificationDetails->Classification1;
        
        // $sector = carbon_get_the_post_meta('type');
        echo "<div class='key-info-item'><strong>Sector:</strong> " . (!empty($sector) ? $sector : 'N/A') . "</div>";

        // Qualification Code with fallback
        $code = carbon_get_the_post_meta('qualificationreferencenumber');
        echo "<div class='key-info-item'><strong>Qualification Code:</strong> " . (!empty($code) ? $code : 'N/A') . "</div>";

        // Risk with fallback
        $risk =  $qualificationDetails->Classification2;
        // $risk = carbon_get_the_post_meta('risk');
        echo "<div class='key-info-item'><strong>Risk:</strong> " . (!empty($risk) ? $risk : 'N/A') . "</div>";

						
        echo key_info('level', 'Level');

        ?>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="key-info-items">
                                    <?php
					echo key_info('regulationstartdate', 'Start Date', 'date');

					// Review Date (+1 day)
					$review_date = carbon_get_the_post_meta('reviewdate');        
					if ($review_date) {
						$adjusted_date = date('d F Y', strtotime($review_date . ' +1 day'));
						echo "<div class='key-info-item'><strong>Review Date:</strong> $adjusted_date</div>";
					}

					// End Date with fallback
					$end_date = carbon_get_the_post_meta('regulationenddate');
					echo "<div class='key-info-item'><strong>Certification End Date:</strong> " . 
						(!empty($end_date) ? date('d F Y', strtotime($end_date)) : 'NA') . "</div>";
									
                    // Min Age with fallback
                    $min_age = carbon_get_the_post_meta('minage');
                    echo "<div class='key-info-item'><strong>Min Age:</strong> " . 
                        (!empty($min_age) ? $min_age : 'NA') . "</div>";

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
                    <!-- Purpose Statement Box - Always show, but may have disabled button -->
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
                                    <a href="<?= $pdf_url ?>" target="_blank">Purpose Statement</a>
                                </div>

                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php endif; ?>

                    <!-- Qualification Guide Box -->
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
                                    <a href="<?= $baseURL ?>" target="_blank">Qualification Guide</a>
                                </div>

                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php endif; ?>

                    <!-- Additional Documents Box -->
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
                                            <a href="<?= wp_get_attachment_url($additional_document['document_file']) ?>"
                                                target="_blank">View Document</a>
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

                    <!-- Content Box -->
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
                        <a href="/qualifications/"><svg class="me-2" xmlns="http://www.w3.org/2000/svg" width="16"
                                height="16" fill="currentColor" class="bi bi-chevron-left" viewBox="0 0 16 16">
                                <path fill-rule="evenodd"
                                    d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0" />
                            </svg> Back to Qualifications</a>
                    </div>
                </div>
            </div>
        </section>
        <section class="related-qualifications archive-section archive-section-qualifications pt-0">
            <div class="container">
                <h2 class="h2-style-1">
                    Explore our Qualifications
                </h2>
                <?= do_shortcode('[related_qualifications]') ?>
            </div>
        </section>
        <?= do_shortcode('[template template_id=2969]') ?>
    </div>
</div>

<?php get_footer() ?>