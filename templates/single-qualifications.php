<?php 
/**
 * Template Name: Qualification Single
 * * Loaded securely via Quba_Controllers hook from plugin root.
 */
get_header(); 

// Retrieve base ID metadata from DB
$id = carbon_get_the_post_meta('id');
error_log('Loading qualification page for ID: ' . $id);

// Retrieve using OOP Wrapper
$qualificationDetails = Quba_API::qualification_search_by_id($id);

// Safely generate SoapClient explicitly as requested for retrieving PDF data arrays
$baseURL = false;
$pdf_url = false;

try {
    // Utilize centralized SOAP object creation methodology
    $client = Quba_API::get_client();
    
    // Attempt PDF Extractions
    $request = ['qualificationID' => $id];
    $response = $client ? clone $client->QUBA_GetQualificationGuide($request) : null;
    $response_doc = $client ? clone $client->QUBA_GetQualificationDocuments($request) : null;
    
    // Extract base64 encoded document stream locally onto plugin storage
    if ($response_doc && isset($response_doc->QUBA_GetQualificationDocumentsResult->any)) {
        $any_data = (string) $response_doc->QUBA_GetQualificationDocumentsResult->any;
        $pdf_start_pos = strpos($any_data, 'JVBERi0x');
        
        if ($pdf_start_pos !== false) {
            $base64_pdf = substr($any_data, $pdf_start_pos);
            $pdf_data = base64_decode($base64_pdf);
            
            if ($pdf_data) {
                $file_name = "PurposeStatement_" . time() . ".pdf";
                $file_path = get_template_directory() . "/includes/" . $file_name;
                
                if (!file_exists(get_template_directory() . "/includes/")) mkdir(get_template_directory() . "/includes/", 0755, true);
                if (file_put_contents($file_path, $pdf_data) !== false) $pdf_url = get_template_directory_uri() . "/includes/" . $file_name;
            }
        }
    }

    // Extract Guide data Stream
    if ($response && isset($response->QUBA_GetQualificationGuideResult)) {
        $pdfContent = $response->QUBA_GetQualificationGuideResult;
        $fileName = "QualificationGuide_" . time() . ".pdf";
        $filePath = get_template_directory() . "/includes/" . $fileName; 
        
        if (!file_exists(get_template_directory() . "/includes/")) mkdir(get_template_directory() . "/includes/", 0755, true);
        if (file_put_contents($filePath, $pdfContent) !== false) $baseURL = get_template_directory_uri() . "/includes/" . $fileName;
    }

} catch (Exception $e) {
    error_log('SOAP Guide Retrieval Error: ' . $e->getMessage());
}

/**
 * Renders Meta Field Strings accurately.
 * * @param string $key DB meta key target.
 * @param string $label Visual output mapping string.
 * @param string $type Value casting behavior mapping.
 * @return string Computed UI String.
 */
function key_info($key, $label, $type = 'string') {
    $keyinfo = carbon_get_the_post_meta($key);
    if ($key == 'level' && !empty($keyinfo)) {
        if (strpos($keyinfo, 'E') === 0) $keyinfo = 'Entry Level ' . str_replace('E', '', $keyinfo);
        elseif (strpos($keyinfo, 'L') === 0) $keyinfo = 'Level ' . str_replace('L', '', $keyinfo);
    }
    if ($type == 'date' && !empty($keyinfo)) $keyinfo = date("d F Y", strtotime($keyinfo));
    return $keyinfo ? "<div class='key-info-item'><strong>$label:</strong> $keyinfo</div>" : '';
}

$additional_documents = carbon_get_the_post_meta('additional_documents');
?>

<style>
.button-disabled { opacity: 0.6; cursor: not-allowed; }
.button-disabled span { color: #888; }
.debug-info { margin: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; }
</style>

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
                                    <?php
                                    echo key_info('type', 'Qualification Type');
                                    // Use OO mapping explicitly
                                    $sector = isset($qualificationDetails->Classification1) ? $qualificationDetails->Classification1 : '';
                                    echo "<div class='key-info-item'><strong>Sector:</strong> " . (!empty($sector) ? esc_html($sector) : 'N/A') . "</div>";
                                    echo key_info('level', 'Level');
                                    ?>
                                </div>
                            </div>
                        </div>
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
    </div>
</div>
<?php get_footer(); ?>