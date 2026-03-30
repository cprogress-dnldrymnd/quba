<?php


// Global SOAP client to avoid recreation
global $quba_soap_client;

function get_quba_soap_client() {
    global $quba_soap_client;

    if (!$quba_soap_client) {

        // Increase PHP limits
        ini_set('default_socket_timeout', 300);
        ini_set('max_execution_time', 300);

        try {

            $context = stream_context_create([
                'http' => [
                    'timeout' => 300,
                    'protocol_version' => 1.1,
                    'header' => "Connection: close\r\n"
                ]
            ]);

            $quba_soap_client = new SoapClient(
                'https://quba.quartz-system.com/QuartzWSExtra/OCNNWR/WSQUBA_UB_V3.asmx?WSDL',
                [
                    'trace' => true,
                    'exceptions' => true,
                    'cache_wsdl' => WSDL_CACHE_NONE, // disable caching
                    'connection_timeout' => 180,
                    'stream_context' => $context,
                    // temporarily disable compression for testing
                    //'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP
                ]
            );

        } catch (Exception $e) {
            error_log('QUBA SOAP Client Error: ' . $e->getMessage());
            return false;
        }
    }

    return $quba_soap_client;
}
/** Quba Functions */
function QUBA_GetQCASectors()
{
  $client = new SoapClient('https://quba.quartz-system.com/QuartzWSExtra/OCNNWR/WSQUBA_UB_V3.asmx?WSDL');
  // Set the SOAP action


  // Call the SOAP method
  $response = $client->QUBA_GetQCASectors();

  // Assuming $response is the object returned from the SOAP call:
  $xmlString = $response->QUBA_GetQCASectorsResult->any; // Assuming XML is in the "any" field

  $responseString = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <QUBA_GetQCASectorsResponse xmlns="http://tempuri.org/">
      <QUBA_GetQCASectorsResult namespace="" tableTypeName="">
        ' . $xmlString . '
      </QUBA_GetQCASectorsResult>
    </QUBA_GetQCASectorsResponse>
  </soap:Body>
</soap:Envelope>';


  try {
    $xml = new SimpleXMLElement($responseString);
    $QubaGetSSAReferenceData = $xml->xpath('//QubaGetSSAReferenceData');
    return $QubaGetSSAReferenceData;
  } catch (Exception $e) {
    return $e;
    // Handle errors (e.g., invalid XML, data extraction issues)
  }
}
function QUBA_GetQualificationDocuments($qualificationID)
{
  $client = new SoapClient('https://quba.quartz-system.com/QuartzWSExtra/OCNNWR/WSQUBA_UB_V3.asmx?WSDL');
  // Set the SOAP action


  // Call the SOAP method
  $request = array(
    'qualificationID'     => $qualificationID,
  );

  $response = $client->QUBA_GetQualificationDocuments($request);

  // Assuming $response is the object returned from the SOAP call:
  $xmlString = $response->QUBA_GetQualificationDocumentsResult->any; // Assuming XML is in the "any" field

  $responseString = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <QUBA_GetQualificationDocumentsResponse xmlns="http://tempuri.org/">
      <QUBA_GetQualificationDocumentsResult namespace="" tableTypeName="">' . $xmlString . '</QUBA_GetQualificationDocumentsResult>
    </QUBA_GetQualificationDocumentsResponse>
  </soap:Body>
</soap:Envelope>';


  try {
    $xml = new SimpleXMLElement($responseString);
    $QubaQualificationDocuments = $xml->xpath('//QubaQualificationDocuments');
	  
    return $QubaQualificationDocuments;
  } catch (Exception $e) {
    return $e;
    // Handle errors (e.g., invalid XML, data extraction issues)
  }
}



function QUBA_UnitSearchById($unitID) {
    ob_start();

    try {
        // Define the SOAP client
        $client = new SoapClient('https://quba.quartz-system.com/QuartzWSExtra/OCNNWR/WSQUBA_UB_V3.asmx?WSDL');

        // Optional: Get unitType from query param if provided
        $unitType = isset($_GET['unitType']) ? sanitize_text_field($_GET['unitType']) : '';

        // Create the SOAP request
        $request = array(
            'unitID' => (int) $unitID, // Ensure unitID is an integer
            'unitIdAlpha' => '',
            'unitTitle' => '',
            'allOrPartTitle' => false,
            'unitLevel' => '',
            'unitCredits' => 0,
            'qcaSector' => '',
            'learnDirectCode' => '',
            'qcaCode' => '',
            'unitType' => $unitType, // ✅ Pass filtered unitType
            'provisionType' => '',
            'includeHub' => true,
            'moduleID' => '',
            'alternativeUnitCode' => '',
        );

        // Call the SOAP method
        $response = $client->QUBA_UnitSearch($request);

        // Extract XML response
        $xmlString = isset($response->QUBA_UnitSearchResult->any) ? $response->QUBA_UnitSearchResult->any : '';

        if (!$xmlString) {
            return (object) ['error' => 'Empty response from SOAP API'];
        }

        // Wrap response for parsing
        $responseString = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <QUBA_UnitSearchResponse xmlns="http://tempuri.org/">
              <QUBA_UnitSearchResult namespace="" tableTypeName="">
              ' . $xmlString . '
              </QUBA_UnitSearchResult>
            </QUBA_UnitSearchResponse>
          </soap:Body>
        </soap:Envelope>';

        // Parse XML
        libxml_use_internal_errors(true);
        $xml = new SimpleXMLElement($responseString);
        $units = $xml->xpath('//QubaUnit');

        if (empty($units)) {
            return (object) ['error' => 'No results found'];
        }

        // Extract the first unit as an object
        $unitObject = new stdClass();
        foreach ($units[0]->children() as $child) {
            $unitObject->{$child->getName()} = htmlentities((string) $child);
        }

        return $unitObject;

    } catch (Exception $e) {
        return (object) ['error' => 'SOAP Request Failed: ' . $e->getMessage()];
    }

    return ob_get_clean();
}

function QUBA_QualificationSearchById($qualificationID) {
    ob_start();

    try {
        // Check if qualificationID is valid
        if (empty($qualificationID) || !is_numeric($qualificationID)) {
            return (object) ['error' => 'Invalid Qualification ID provided'];
        }

        // Define the SOAP client
        $client = new SoapClient('https://quba.quartz-system.com/QuartzWSExtra/OCNNWR/WSQUBA_UB_V3.asmx?WSDL');

        // Create the SOAP request
        $request = array(
            'qualificationID'     => (int) $qualificationID, // Ensure qualificationID is an integer
            'qualificationTitle'  => '',
            'qualificationLevel'  => '',
            'qualificationNumber' => '',
            'qcaSector'           => '',
            'provisionType'       => '',
            'unitID'              => '',
            'includeHub'          => false,
            'centreID'            => ''
        );

        // Call the SOAP method
        $response = $client->QUBA_QualificationSearch($request);

        // Extract XML response
        $xmlString = isset($response->QUBA_QualificationSearchResult->any) ? $response->QUBA_QualificationSearchResult->any : '';

        if (!$xmlString) {
            return (object) ['error' => 'Empty response from SOAP API'];
        }

        // Wrap response in SOAP envelope for parsing
        $responseString = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
          <soap:Body>
            <QUBA_QualificationSearchResponse xmlns="http://tempuri.org/">
              <QUBA_QualificationSearchResult namespace="" tableTypeName="">
              ' . $xmlString . '
              </QUBA_QualificationSearchResult>
            </QUBA_QualificationSearchResponse>
          </soap:Body>
        </soap:Envelope>';

        // Parse XML
        libxml_use_internal_errors(true);
        $xml = new SimpleXMLElement($responseString);
        $qualifications = $xml->xpath('//QubaQualification'); // Extract qualification nodes

        if (empty($qualifications)) {
            return (object) ['error' => 'No qualification found with ID: ' . $qualificationID];
        }

        // Extract the first qualification as an object
        $qualificationObject = new stdClass();
        foreach ($qualifications[0]->children() as $child) {
            if ($child->getName() != 'Classifications') {
                $qualificationObject->{$child->getName()} = htmlentities((string) $child);
            }
        }
        
        // Specifically extract Classifications if they exist
        if (isset($qualifications[0]->Classifications)) {
            $classifications = $qualifications[0]->Classifications;
            if (isset($classifications->Classification1)) {
                $qualificationObject->Classification1 = htmlentities((string) $classifications->Classification1);
            }
        }
        
        return $qualificationObject;

    } catch (Exception $e) {
        return (object) ['error' => 'SOAP Request Failed: ' . $e->getMessage()];
    }

    return ob_get_clean();
}


function QUBA_GetUnitListingDocument($qualificationID)
{
    try {
        // Ensure qualificationID is valid
        if (empty($qualificationID) || !is_numeric($qualificationID)) {
            return "Invalid Qualification ID provided.";
        }
        
        $client = new SoapClient('https://quba.quartz-system.com/QuartzWSExtra/OCNNWR/WSQUBA_UB_V3.asmx?WSDL');
        $request = array('unitID' => $qualificationID);
        $response = $client->QUBA_GetUnitContent($request);
		
		
        
        // Check if response contains data
        if (!isset($response->QUBA_GetUnitContentResult) || empty($response->QUBA_GetUnitContentResult)) {
            return "No data found for this qualification.";
        }
        
        // Get PDF content from the response
        $pdfContent = $response->QUBA_GetUnitContentResult;
        
        // Create directory if it doesn't exist
        $uploadDir = 'uploads/pdf/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $filename = 'unit_' . $qualificationID . '_' . date('YmdHis') . '.pdf';
        $filePath = $uploadDir . $filename;
        
        // Save PDF to file
        file_put_contents($filePath, $pdfContent);
        
        // Return the URL to the saved PDF
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $pdfUrl = $baseUrl . '/' . $filePath;
        
        return $pdfUrl;
        
    } catch (SoapFault $e) {
        return "SOAP Error: " . $e->getMessage();
    } catch (Exception $e) {
        return "General Error: " . $e->getMessage();
    }
}


function QUBA_QualificationSearchForUnit($unitID)
{

	ob_start();


	try {

		$client = new SoapClient('https://quba.quartz-system.com/QuartzWSExtra/OCNNWR/WSQUBA_UB_V3.asmx?WSDL');

		$request = array(
			'qualificationID'     => 0,
			'qualificationTitle'  => '',
			'qualificationLevel'  => '',
			'qualificationNumber' => '',
			'qcaSector'           => '',
			'provisionType'       => '',
			'unitID'              => $unitID,
			'includeHub'          => false,
			'centreID'            => ''
		);

		$response = $client->QUBA_QualificationSearch($request);
		$xmlString = $response->QUBA_QualificationSearchResult->any;

		$responseString = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
            <soap:Body>
                <QUBA_QualificationSearchResponse xmlns="http://tempuri.org/">
                    <QUBA_QualificationSearchResult namespace="" tableTypeName="">
                        ' . $xmlString . '
                    </QUBA_QualificationSearchResult>
                </QUBA_QualificationSearchResponse>
            </soap:Body>
        </soap:Envelope>';

		$xml = new SimpleXMLElement($responseString);

		// Update the xpath query to specifically target QubaQualification elements
		$qualifications = $xml->xpath('//QubaQualification');



     return $qualifications;
	} catch (Exception $e) {
		error_log('QUBA Search Error: ' . $e->getMessage());

		echo 'QUBA Search Error' . $e->getMessage();
	}

	return ob_get_clean();
}


function QUBA_QualificationSearch($data) {
    ob_start();
    try {
        $client = new SoapClient('https://quba.quartz-system.com/QuartzWSExtra/OCNNWR/WSQUBA_UB_V3.asmx?WSDL');
        $request = array(
            'qualificationID'     => 0,
            'qualificationTitle'  => $data['qualificationTitle'] ?? '',
            'qualificationLevel'  => $data['qualificationLevel'] ?? '',
            'qualificationNumber' => $data['qualificationNumber'] ?? '',
            'qcaSector'           => $data['qcaSector'] ?? '',
            'Type'                => $data['qualificationType'] ?? '',
            'provisionType'       => '',
            'unitID'              => '',
            'includeHub'          => false,
            'centreID'            => ''
        );
        $response = $client->QUBA_QualificationSearch($request);
        $xmlString = $response->QUBA_QualificationSearchResult->any;
        
        $responseString = '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
            <soap:Body>
                <QUBA_QualificationSearchResponse xmlns="http://tempuri.org/">
                    <QUBA_QualificationSearchResult namespace="" tableTypeName="">
                        ' . $xmlString . '
                    </QUBA_QualificationSearchResult>
                </QUBA_QualificationSearchResponse>
            </soap:Body>
        </soap:Envelope>';
        $xml = new SimpleXMLElement($responseString);
        
        // Update the xpath query to specifically target QubaQualification elements
        $qualifications = $xml->xpath('//QubaQualification');
        
        if (isset($data['debug']) && $data['debug']) {
            echo "<pre>";
            var_dump($qualifications);
        }
        
        // List of restricted qualification IDs to exclude
        $restrictedIds = ['127141', '127142', '127651', '127256'];
        
        // Get current date for expiration comparison
        $currentDate = new DateTime();
        
        $resultArray = [];
        foreach ($qualifications as $qualification) {
            $qualificationArray = [];
            $isRestricted = false;
            $isExpired = false;
            
            // Extract basic qualification details
            foreach ($qualification->children() as $child) {
                if ($child->getName() != 'Classifications') {
                    // Convert SimpleXMLElement to string and trim whitespace
                    $qualificationArray[$child->getName()] = trim((string) $child);
                }
            }
            
            // Check if this is one of the specific restricted IDs
            if (isset($qualificationArray['ID']) && 
                in_array($qualificationArray['ID'], $restrictedIds)) {
                continue; // Skip this qualification
            }

            // Check if qualification is expired based on OperationalEndDate or RegulationEndDate
            if (isset($qualificationArray['OperationalEndDate']) && !empty($qualificationArray['OperationalEndDate'])) {
                $endDate = new DateTime($qualificationArray['OperationalEndDate']);
                if ($endDate < $currentDate) {
                    $isExpired = true;
                }
            }
            
            if (isset($qualificationArray['RegulationEndDate']) && !empty($qualificationArray['RegulationEndDate'])) {
                $regEndDate = new DateTime($qualificationArray['RegulationEndDate']);
                if ($regEndDate < $currentDate) {
                    $isExpired = true;
                }
            }
            
            // Skip if qualification is expired
            if ($isExpired) {
                continue;
            }
            
            // Specifically extract Classifications
            if (isset($qualification->Classifications)) {
                $classifications = $qualification->Classifications;
                if (isset($classifications->Classification1)) {
                    $classificationValue = trim((string) $classifications->Classification1);
                    $qualificationArray['Classification1'] = $classificationValue;
                    
                    // Check if this is a restricted delivery qualification
                    if (stripos($classificationValue, 'Restricted Delivery') !== false) {
                        $isRestricted = true;
                    }
                }
            }
            
            // Only add non-restricted qualifications
            if (!empty($qualificationArray) && !$isRestricted) {
                $resultArray[] = $qualificationArray;
            }
        }
        
        // Debug info for type filtering
        if (!empty($data['qualificationType']) && isset($data['debug']) && $data['debug']) {
            echo "<pre>Filtering by qualification type: " . $data['qualificationType'] . "\n";
            echo "Available types in results: \n";
            foreach ($resultArray as $idx => $item) {
                if (isset($item['Type'])) {
                    echo "[$idx] Type: '" . $item['Type'] . "'\n";
                }
            }
            echo "</pre>";
        }
        
        // Apply filters
        $resultArray_final = [];
        
        if (isset($data['debug']) && $data['debug']) {
            echo "<pre>";
            var_dump($resultArray);
            echo "</pre>";
        }
//         echo "<pre>";
// 		var_dump($resultArray);
        foreach ($resultArray as $result) {
            $typeMatch = true;
            $regulatorMatch = true;
            
            // Check qualification type filter
            if (!empty($data['qualificationType'])) {
                $typeMatch = isset($result['Type']) && 
                             strtolower(trim($result['Type'])) == strtolower(trim($data['qualificationType']));
            }
            
            // Only add to final results if it matches all applied filters
            if ($typeMatch && $regulatorMatch) {
                $resultArray_final[] = $result;
            }
        }
        
        // Display Results
        if (!empty($resultArray_final)) {
            echo '<div class="row row-results g-5">';
            foreach ($resultArray_final as $data) {
                echo qual_grid($data);
            }
            echo '</div>';
        } else {
            echo 'No results found';
        }
        
    } catch (Exception $e) {
        error_log('QUBA Search Error: ' . $e->getMessage());
        echo 'QUBA Search Error: ' . $e->getMessage();
    }
    
    return ob_get_clean();
}

function QUBA_QualificationSearchPost()
{
  ob_start();
  $posts = get_posts(array(
    'post_type' => 'qualifications',
    'numberposts' => 15,
    'orderby' => 'rand',
  ));

  echo "<div class='row row-results g-5'>";
  foreach ($posts as $post) {
    $level = carbon_get_post_meta($post->ID, 'level');
    $data = array(
      'Level'   => $level,
      'Title'   => $post->post_title,
      'post_id' => $post->ID,
    );
    echo qual_grid($data, 'qualifications', true);
  }
  echo "</div>";
  return ob_get_clean();
}


//get unit using post id
function get_unit_post_id_by_meta($meta_key, $meta_value) {
    global $wpdb;

    return $wpdb->get_var($wpdb->prepare(
        "SELECT post_id 
         FROM {$wpdb->postmeta} 
         WHERE meta_key = %s 
         AND meta_value = %s 
         LIMIT 1",
        $meta_key,
        $meta_value
    ));
}

function sync_quba_units_to_posts($units) {

    if (empty($units)) {
        error_log("No units to process.");
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'quba_units_index';

    foreach ($units as $unit) {

        $data = json_decode(json_encode($unit), true);

        $unit_id = $data['ID'] ?? '';
        $unit_id = preg_replace('/[^0-9]/', '', $unit_id);

        if (empty($unit_id)) {
            continue;
        }

        // Check if post already exists
        $existing_post_id = get_unit_post_id_by_meta('_id', $unit_id);

        $meta_data = array(
            '_id'                   => $unit_id,
            '_unitcode'             => $data['NationalCode'] ?? '',
            '_oaunitid'             => $data['ID_Alpha'] ?? '',
            '_level'                => $data['Level'] ?? '',
            '_reviewdate'           => $data['ReviewDate'] ?? '',
            '_sector'               => $data['QCASector'] ?? '',
            '_totalcreditsrequired' => $data['Credits'] ?? '',
            '_glh'                  => $data['GLH'] ?? '',
            '_unittype'             => $data['UnitType'] ?? '',
            '_riskrating'           => $data['RiskRating'] ?? '',
            '_classification1'      => $data['Classification1'] ?? '',
            '_startdate'            => $data['RecognitionDate'] ?? '',
            '_enddate'              => $data['ExpiryDate'] ?? '',
        );

        if ($existing_post_id) {

            wp_update_post([
                'ID'         => $existing_post_id,
                'post_title' => $data['Title'] ?? 'Untitled Unit',
            ]);

            $post_id = $existing_post_id;

        } else {

            $post_id = wp_insert_post([
                'post_type'   => 'units',
                'post_title'  => $data['Title'] ?? 'Untitled Unit',
                'post_status' => 'publish',
            ]);
        }

        if (!$post_id) {
            continue;
        }

        // Update post meta
        foreach ($meta_data as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

      $result = $wpdb->replace(
            $table,
            [
                'post_id'          => $post_id,
                'unit_id_alpha'    => $data['ID_Alpha'] ?? '',
                'title'            => $data['Title'] ?? '',
                'national_code'    => $data['NationalCode'] ?? '',
                'recognition_date' => $data['RecognitionDate'] ?? null,
                'level'            => $data['Level'] ?? '',
                'review_date'      => $data['ReviewDate'] ?? null,
                'qca_sector'       => $data['QCASector'] ?? '',
                'expiry_date'      => $data['ExpiryDate'] ?? null,
                'credits'          => $data['Credits'] ?? '',
                'glh'              => $data['GLH'] ?? '',
                'unit_type'        => $data['UnitType'] ?? '',
                'risk_rating'      => $data['RiskRating'] ?? '',
                'classification1'  => $data['Classification1'] ?? '',
            ],
            [
                '%d','%s','%s','%s','%s','%s','%s','%s',
                '%s','%s','%s','%s','%s','%s'
            ]
        );
        
        if ($result === false) {
            error_log("DB ERROR: " . $wpdb->last_error);
        }
    }

    error_log("Units synced successfully.");
}

function get_units_from_postmeta($filters = []) {
    $args = [
        'post_type'      => 'units',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids', // Only get IDs, we'll fetch meta
    ];

    $post_ids = get_posts($args);
    $units = [];

    foreach ($post_ids as $post_id) {
        $unit = [
            'id'                 => $post_id, // Add WordPress post ID here
            'ID_Alpha'           => get_post_meta($post_id, '_oaunitid', true),
            'Title'              => get_the_title($post_id),
            'NationalCode'       => get_post_meta($post_id, '_unitcode', true),
            'RecognitionDate'    => get_post_meta($post_id, '_regulationstartdate', true),
            'Level'              => get_post_meta($post_id, '_level', true),
            'ReviewDate'         => get_post_meta($post_id, '_reviewdate', true),
            'QCASector'          => get_post_meta($post_id, '_sector', true),
            'ExpiryDate'         => get_post_meta($post_id, '_regulationenddate', true),
            'Credits'            => get_post_meta($post_id, '_totalcreditsrequired', true),
            'GLH'                => get_post_meta($post_id, '_glh', true),
            'UnitType'           => get_post_meta($post_id, '_unittype', true),
            'RiskRating'         => get_post_meta($post_id, '_riskrating', true),
            'Classification1'    => get_post_meta($post_id, '_classification1', true), // Optional if stored separately
        ];

        // Apply filters if any (like unitLevel, qcaSector)
        if (!passes_unit_filters($unit, $filters)) {
            continue;
        }

        $units[] = $unit;
    }

    return $units;
}

function QUBA_UnitSearch($data) {
    ob_start();
    
    try {
        // Generate cache key based on search parameters
        $cache_key = 'quba_units_AllDataTotal35' . md5(serialize($data));
        // Try to get cached results first
        $cached_results = get_transient($cache_key);
        if ($cached_results !== false) {

            echo $cached_results;
            return ob_get_clean();
        }
        
        // Get SOAP client
        $client = get_quba_soap_client();
        if (!$client) {
            throw new Exception('Failed to initialize SOAP client');
        }
        
        // Create the SOAP request
        $request = array(
            'unitID' => isset($data['unitID']) ? (int)$data['unitID'] : 0,
             'unitIdAlpha' => isset($data['unitID']) ? $data['unitID'] : '',
            'unitTitle' => '%', // Always use wildcard for server-side search
            'allOrPartTitle' => true,
            'unitLevel' => isset($data['unitLevel']) ? $data['unitLevel'] : '',
            'unitCredits' => 0,
            'qcaSector' => isset($data['qcaSector']) ? $data['qcaSector'] : '',
            'learnDirectCode' => '',
            'qcaCode' => '',
            'unitType' => isset($data['unitType']) ? $data['unitType'] : '',
            'provisionType' => '',
            'includeHub' => true,
            'moduleID' => 0,
            'alternativeUnitCode' => '',
        );
        
        // Call the SOAP method
        $response = $client->QUBA_UnitSearch($request);
        if (!$response || !isset($response->QUBA_UnitSearchResult->any)) {
            throw new Exception('Invalid response from QUBA service');
        }
    
        
        // Parse XML response
        $xmlString = $response->QUBA_UnitSearchResult->any;
        $responseString = '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
            <soap:Body>
                <QUBA_UnitSearchResponse xmlns="http://tempuri.org/">
                    <QUBA_UnitSearchResult namespace="" tableTypeName="">
                        ' . $xmlString . '
                    </QUBA_UnitSearchResult>
                </QUBA_UnitSearchResponse>
            </soap:Body>
        </soap:Envelope>';
        
        // Use libxml to suppress warnings and improve performance
        libxml_use_internal_errors(true);
        $xml = new SimpleXMLElement($responseString);
        $units = $xml->xpath('//QubaUnit');

        if (!$units) {
            $units = array(); // Empty array if no units found
        }
        // sync_quba_units_to_posts($units);
        // Process and filter units
        $resultArray = process_and_filter_units($units, $data);
        
        // Generate output
        $output = generate_search_results_output($resultArray, $data);
        // Cache the results for 6 hours (21600 seconds)
        // set_transient($cache_key, $output, 6 * HOUR_IN_SECONDS);
        set_transient($cache_key, $output, DAY_IN_SECONDS); //24hr 
        echo $output;
        
    } catch (Exception $e) {
        error_log('QUBA Search Error: ' . $e->getMessage());
        echo generate_error_output($e->getMessage());
    }
    
    return ob_get_clean();
}

//From table 
function QUBA_UnitSearch_FromPost($data) {
    global $wpdb;

    ob_start();

    try {

        // ✅ Generate cache key
        $cache_key = 'quba_units_db5_' . md5(serialize($data));
        $cached_results = get_transient($cache_key);

        if ($cached_results !== false) {
            echo $cached_results;
            return ob_get_clean();
        }

        // ✅ Your custom table name
        $table = $wpdb->prefix . 'quba_units_index';

        // ✅ Start building WHERE conditions
        $where = [];
        $params = [];

        // Filter by Unit ID
        // if (!empty($data['unitID'])) {
        //     $where[] = "unit_id = %d";
        //     $params[] = (int)$data['unitID'];
        // }

        // Filter by Alpha Code
        // if (!empty($data['unitIdAlpha'])) {
        //     $where[] = "unit_id_alpha = %s";
        //     $params[] = $data['unitIdAlpha'];
        // }

        // // Filter by Level
        // if (!empty($data['unitLevel'])) {
        //     $where[] = "level = %s";
        //     $params[] = $data['unitLevel'];
        // }

        // Filter by Sector
        // if (!empty($data['qcaSector'])) {
        //     $where[] = "qca_sector = %s";
        //     $params[] = $data['qcaSector'];
        // }

        // Default condition
        $where_sql = '';
        if (!empty($where)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where);
        }

        // ✅ Final Query
       $query = "
    SELECT 
        post_id,
        unit_id_alpha,
        title,
        national_code,
        recognition_date,
        level,
        review_date,
        qca_sector,
        expiry_date,
        credits,
        glh,
        unit_type,
        risk_rating,
        classification1
    FROM $table
    ORDER BY title ASC
";

        // Prepare query safely
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        // Debug (optional)
        // error_log($query);

        // ✅ Get Results
        $units = $wpdb->get_results($query, ARRAY_A);

        if (empty($units)) {
            $units = [];
        }
        error_log("Query");
        error_log($query);
        // ✅ Process same as before
        $resultArray = process_and_filter_units($units, $data);

        $output = generate_search_results_output($resultArray, $data);

        // Cache 6 hours
        set_transient($cache_key, $output, 6 * HOUR_IN_SECONDS);

        echo $output;

    } catch (Exception $e) {
        error_log('QUBA Search DB Error: ' . $e->getMessage());
        echo generate_error_output($e->getMessage());
    }

    return ob_get_clean();
}

function generate_search_results_output($resultArray, $data) {
    $output = '';
    
    if (!empty($resultArray)) {

        // filter Restricted Units AND missing RecognitionDate
        $filteredResults = array_filter($resultArray, function ($unitData) {
          error_log(print_r($unitData, true));
             // Skip if Classification1 missing or empty
            if (!isset($unitData['Classification1']) 
                || trim($unitData['Classification1']) === '') {
                return false;
            }
            
            // Skip Restricted Unit
            if (isset($unitData['Classification1']) 
                && trim($unitData['Classification1']) === 'Restricted Unit') {
                return false;
            }

            // Skip if RecognitionDate missing or empty
            if (!isset($unitData['RecognitionDate']) 
                || trim($unitData['RecognitionDate']) === '') {
                return false;
            }

            return true;
        });

        $total_results = count($filteredResults);

        if ($total_results > 0) {

            // Results summary
            $output .= '<div class="search-results-summary mb-4">';
            $output .= '<div class="results-count-display">';
            $output .= '<span class="results-number">' . number_format($total_results) . '</span>';
            $output .= '<span class="results-text"> Unit' . ($total_results !== 1 ? 's' : '') . ' Found</span>';
            $output .= '</div>';
            $output .= '</div>';

            // Results grid
            $output .= '<div class="row row-results g-5">';
            foreach ($filteredResults as $unitData) {
                $output .= unit_grid($unitData, 'units');
            }
            $output .= '</div>';

        } else {
            $output .= generate_no_results_output($data);
        }

    } else {
        $output .= generate_no_results_output($data);
    }
    
    return $output;
}

function process_and_filter_units($units, $data) {
    $resultArray = array();
    
    foreach ($units as $unit) {
        // Extract unit data
        $unitArray = array();
           // $unit is already an array from post meta
        // $unitArray = $unit;
        foreach ($unit->children() as $child) {
            $unitArray[$child->getName()] = htmlentities((string)$child, ENT_QUOTES, 'UTF-8');
        }
        $class = trim(preg_replace('/\s+/u', ' ', $unitArray['classification1'] ?? ''));
    
        // if (strcasecmp($class, 'Restricted Units') === 0) {
        //     continue;
        // }
        // Apply client-side filtering
        if (passes_unit_filters($unitArray, $data)) {
            $resultArray[] = $unitArray;
        }
    }
    
    return $resultArray;
}

function passes_unit_filters($unitArray, $data) {
    // Filter by unit title (case-insensitive partial match)
	if (!empty($data['unitTitle'])) {
        $searchTitle = strtolower(trim($data['unitTitle']));
        if ($searchTitle !== '%' && $searchTitle !== '*') {
            $unitTitle = strtolower(trim($unitArray['Title']));
            if (strpos($unitTitle, $searchTitle) === false) {
                return false;
            }
        }
    }
    
    // Filter by unit level (exact match)
    if (!empty($data['unitLevel'])) {
        $searchLevel = strtoupper(trim($data['unitLevel']));
        $unitLevel = strtoupper(trim($unitArray['Level']));
        if ($unitLevel !== $searchLevel) {
            return false;
        }
    }
    
    // Filter by QCA sector (case-insensitive partial match)
    if (!empty($data['qcaSector'])) {
        $searchSector = strtolower(trim($data['qcaSector']));
        if ($searchSector !== '%' && $searchSector !== '*') {
            $unitSector = strtolower(trim($unitArray['QCASector']));
            if (strpos($unitSector, $searchSector) === false) {
                return false;
            }
        }
    }
    
    // Filter by QCA code (exact match)
    if (!empty($data['qcaCode'])) {
        $searchCode = strtoupper(trim($data['qcaCode']));
        $nationalCode = strtoupper(trim($unitArray['NationalCode'] ?? ''));
        $idAlpha      = strtoupper(trim($unitArray['ID_Alpha'] ?? ''));
     
        // Only fail if BOTH do not match
        if ($nationalCode !== $searchCode && $idAlpha !== $searchCode) {
            return false;
        }
    }
    
    return true;
}

function generate_no_results_output($data) {
    $output = '<div class="no-results-message">';
    $output .= '<p>No units found matching your search criteria.</p>';
    
    // Show search criteria
    $search_info = array();
    if (!empty($data['unitTitle'])) {
        $search_info[] = 'Title: "' . esc_html($data['unitTitle']) . '"';
    }
    if (!empty($data['unitLevel'])) {
        $search_info[] = 'Level: ' . esc_html($data['unitLevel']);
    }
    if (!empty($data['qcaSector'])) {
        $search_info[] = 'Sector: ' . esc_html($data['qcaSector']);
    }
    if (!empty($data['qcaCode'])) {
        $search_info[] = 'Code: ' . esc_html($data['qcaCode']);
    }
    
    if (!empty($search_info)) {
        $output .= '<p><small>Searched for: ' . implode(', ', $search_info) . '</small></p>';
    }
    
    $output .= '<p><small>Try adjusting your search terms or removing some filters.</small></p>';
    $output .= '</div>';
    
    return $output;
}

function generate_error_output($error_message) {
    return '<div class="error-message">
        <p>An error occurred while searching for units.</p>
        <p><small>Please try again in a few moments.</small></p>
    </div>';
}

// Clear cache function (call this when you need to refresh data)
function clear_quba_cache() {
    global $wpdb;
    
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_quba_units_%' 
         OR option_name LIKE '_transient_timeout_quba_units_%'"
    );
}
// Hook to clear cache periodically or on demand
add_action('wp_ajax_clear_quba_cache', 'clear_quba_cache');
add_action('wp_ajax_nopriv_clear_quba_cache', 'clear_quba_cache');

function getUnitListingDocument($unitID) {
    try {
        $wsdl = "https://quba.quartz-system.com/QuartzWSExtra/OCNNWR/WSQUBA_UB_V3.asmx?WSDL";
        $client = new SoapClient($wsdl, ['trace' => 1, 'exceptions' => 1]);

        $params = ["qualificationID" => (int)$unitID];
        $response = $client->QUBA_GetUnitListingDocument($params);

        if (!isset($response->QUBA_GetUnitListingDocumentResult) || empty($response->QUBA_GetUnitListingDocumentResult)) {
            return "No listing document available for this unit.";
        }

        $pdfContent = $response->QUBA_GetUnitListingDocumentResult;

        // Check if response is Base64 encoded
        if (base64_decode($pdfContent, true) !== false) {
            $pdfContent = base64_decode($pdfContent);
        }

        // Get WordPress uploads directory
        $upload_dir = wp_upload_dir();
        $fileName = "UnitListing_$unitID.pdf";
        $filePath = $upload_dir['path'] . "/" . $fileName;
        $fileUrl = $upload_dir['url'] . "/" . $fileName;

        // Save the PDF file
        file_put_contents($filePath, $pdfContent);

        return $fileUrl;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();

        // Handle DBNull error for missing documents
        if (strpos($errorMsg, "QubaUnitListingDocumentTypeID") !== false && 
            strpos($errorMsg, "DBNull") !== false) {
            return "No listing document available for this unit.";
        }

        return "Error: " . $errorMsg;
    }
}


add_action('wp_ajax_nopriv_archive_ajax_qualifications', 'archive_ajax_qualifications'); // for not logged in users
add_action('wp_ajax_archive_ajax_qualifications', 'archive_ajax_qualifications');
function archive_ajax_qualifications()
{
  $source = isset($_POST['source']) && $_POST['source'] != '' ? $_POST['source'] : '';
  $qualificationLevel = isset($_POST['qualificationLevel']) && $_POST['qualificationLevel'] != '' ? $_POST['qualificationLevel'] : ' ';
  $qualificationNumber = isset($_POST['qualificationNumber']) && $_POST['qualificationNumber'] != '' ? $_POST['qualificationNumber'] : '';
  $qualificationTitle = isset($_POST['qualificationTitle']) && $_POST['qualificationTitle'] != '' ? $_POST['qualificationTitle'] : 'e';
  $qualificationType = isset($_POST['qualificationType']) && $_POST['qualificationType'] != '' ? $_POST['qualificationType'] : '';
  $qualificationRegulator = isset($_POST['qualificationRegulator']) && $_POST['qualificationRegulator'] != '' ? $_POST['qualificationRegulator'] : '';
  $qcaSector = isset($_POST['qcaSector']) && $_POST['qcaSector'] != '' ? $_POST['qcaSector'] : '';
  
  // Set default qcaSector to "01" if qualificationType is provided but qcaSector is not
//   if ($qualificationType != '' && $qcaSector == '') {
//     $qcaSector = '01';
//   }
  
  $data = array(
    'qualificationLevel'  => $qualificationLevel,
    'qcaSector'           => $qcaSector,
    'qualificationNumber' => $qualificationNumber,
    'qualificationTitle'  => $qualificationTitle,
    'qualificationType'   => $qualificationType,
//     'qualificationRegulator' => $qualificationRegulator
  );


  if ($source == 'quba') {
    echo QUBA_QualificationSearch($data);
//     echo QUBA_GetQualificationGuide(1388780);
  } else {
    echo QUBA_QualificationSearchPost($data);
  }
  die();
}


add_action('wp_ajax_nopriv_archive_ajax_units', 'archive_ajax_units'); // for not logged in users
add_action('wp_ajax_archive_ajax_units', 'archive_ajax_units');

function archive_ajax_units() {
    // Sanitize and validate input
    $data = array(
        'qcaCode' => sanitize_text_field($_POST['qcaCode'] ?? ''),
        'qcaSector' => sanitize_text_field($_POST['qcaSector'] ?? ''),
        'unitLevel' => sanitize_text_field($_POST['unitLevel'] ?? ''),
        'unitTitle' => sanitize_text_field($_POST['unitTitle'] ?? ''),
        'unitID' => sanitize_text_field($_POST['unitID'] ?? 0),
		'unitIdAlpha' => sanitize_text_field($_POST['unitID'] ?? ''),
		'unitType' => sanitize_text_field($_POST['unitType'] ?? '')
    );

    // Remove empty values
    $data = array_filter($data, function($value) {
        return $value !== '' && $value !== 0;
    });
//         var_dump($data);exit;
    echo QUBA_UnitSearch($data);
    wp_die();
}


// Rest of your existing functions remain the same...
function get_post_id_by_meta_field($meta_key, $meta_value) {
    global $wpdb;

    $query = $wpdb->prepare(
        "SELECT pm.post_id FROM $wpdb->postmeta pm
         JOIN $wpdb->posts p ON pm.post_id = p.ID
         WHERE pm.meta_key = %s AND pm.meta_value = %s
         AND p.post_status = 'publish' LIMIT 1",
        $meta_key,
        $meta_value
    );

    return $wpdb->get_var($query);
}

function santize_html($html) {
    $html = str_replace('SPANstyle;', 'span style', $html);
    $html = html_entity_decode($html);
    $html = str_replace('&nbsp;', '', $html);
    $html = preg_replace('/<([a-z][a-z0-9]*)([^>]*?)>/i', '<$1>', $html);
    $html = preg_replace("/<[^\/>]*>([\s]?)*<\/[^>]*>/", '', $html);
    return $html;
}

function qual_grid($data, $post_type = 'qualifications', $post = false)
{
  ob_start();
  ?>
  <style>
    img.emoji {
      display: none !important;
    }
    .level-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  border-radius: 999px;
  color: #ffffff;

  font-size: 14px;
  font-weight: 600;
  line-height: 1;

  white-space: nowrap;
}

.level-badge i.fa {
  font-size: 12px;
}

  </style>
  <?php
  if ($post == false) {
    $check_qual = get_post_id_by_meta_field('_id', $data['ID']);
    if ($data['QualificationSummary']) {
      $post_content = santize_html($data['QualificationSummary']);
    } else {
      $post_content = '';
    }
    $post_data['post_type'] = $post_type;
    $post_data['post_title'] = $data['Title'];
    $post_data['post_status'] = 'publish';
    $post_data['post_content'] = $post_content;

    $post_data['meta_input'] = array(
      '_id' => $data['ID'],
      '_level' => $data['Level'],
      '_type' => $data['Type'],
      '_regulationstartdate' => $data['RegulationStartDate'],
      '_operationalstartdate' => $data['OperationalStartDate'],
      '_regulationenddate' => $data['RegulationEndDate'],
      '_reviewdate' => $data['ReviewDate'],
      '_totalcreditsrequired' => $data['TotalCreditsRequired'],
      '_minimumcreditsatorabove' => $data['MinimumCreditsAtOrAbove'],
      '_qualificationreferencenumber' => $data['QualificationReferenceNumber'],
      '_contactdetails' => $data['ContactDetails'],
      '_minage' => $data['MinAge'],
      '_tqt' => $data['TQT'],
      '_glh' => $data['GLH'],
      '_alternativequalificationtitle' => $data['AlternativeQualificationTitle'],
      '_classification1' => $data['Classification1'],
    );

    if ($check_qual) {
      $post_id = $check_qual;
      $post_data['ID'] = $post_id;
      wp_update_post($post_data);
    } else {
      // Insert the post into the database
      $post_id = wp_insert_post($post_data);
    }
  } else {
    $post_id = $data['post_id'];
  }
  $icon = '<i class="fa fa-check " aria-hidden="true"></i>';

  if ($data['Level'] == 'E1' || $data['Level'] == 'E2' || $data['Level'] == 'E3') {
    $level_val = str_replace('E', $icon.' Entry Level ', $data['Level']);
  } else {
    $level_val = str_replace('L', $icon.' Level ', subject: $data['Level']);

  }
?>
  <div class="col-lg-4 post-item">
    <div class="post-box h-100">
      <div class="image-box image-box-placeholder">
        <img src="https://openawards.theprogressteam.com/wp-content/uploads/2023/10/logo-new.svg">
        <span class="level <?= $data['Level'] ?>">
            <span class="level-badge">
          &#10004; <?= $level_val ?>
        </span>
        </span>
      </div>
      <div class="content-box content-box-v1">
        <div class="heading-excerpt-box">
          <div class="heading-box">
            <h4><?= $data['Title'] ?></h4>
          </div>
          <div class="description-box d-none">
            <?php ?>
          </div>
        </div>
      </div>
      <div class="button-group-box row g-0 align-items-center">
        <div class="button-box-v2 button-accent col">
			
         <a class="w-100 text-center" href="<?= get_the_permalink($post_id) ?>">
    <?= $post_type == "units" ? "View Unit" : "View Course" ?>
</a>

        </div>
      </div>
    </div>
  </div>
<?php
  return ob_get_clean();
}

function unit_grid($data, $post_type, $post = false) {
    ob_start();
  ?>
  <style>
    img.emoji {
      display: none !important;
    }
    .level-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    
      border-radius: 999px;
      color: #ffffff;
    
      font-size: 14px;
      font-weight: 600;
      line-height: 1;
    
      white-space: nowrap;
    }
    
    .level-badge i.fa {
      font-size: 12px;
    }
    
      </style>
     <?php
    if ($post == false) {
        $check_qual = get_post_id_by_meta_field('_id', $data['ID']);
        $post_content = $data['QualificationSummary'] ? santize_html($data['QualificationSummary']) : '';
        
        $post_data = array(
            'post_type' => $post_type,
            'post_title' => $data['Title'],
            'post_status' => 'publish',
            'post_content' => $post_content,
            'meta_input' => array(
                '_id' => $data['ID'],
                '_level' => $data['Level'],
                '_type' => $data['Type'],
                '_regulationstartdate' => $data['RegulationStartDate'],
                '_operationalstartdate' => $data['OperationalStartDate'],
                '_regulationenddate' => $data['RegulationEndDate'],
                '_reviewdate' => $data['ReviewDate'],
                '_totalcreditsrequired' => $data['TotalCreditsRequired'],
                '_minimumcreditsatorabove' => $data['MinimumCreditsAtOrAbove'],
                '_qualificationreferencenumber' => $data['QualificationReferenceNumber'],
                '_contactdetails' => $data['ContactDetails'],
                '_minage' => $data['MinAge'],
                '_tqt' => $data['TQT'],
                '_glh' => $data['GLH'],
                '_alternativequalificationtitle' => $data['AlternativeQualificationTitle'],
                '_classification1' => $data['Classification1'],
            )
        );

        if ($check_qual) {
            $post_data['ID'] = $check_qual;
            wp_update_post($post_data);
            $post_id = $check_qual;
        } else {
            $post_id = wp_insert_post($post_data);
        }
    } else {
        $post_id = $data['post_id'];
    }
    // $level_val = in_array($data['Level'], ['E1', 'E2', 'E3']) 
    //     ? str_replace('E',  $icon.' Entry Level ', $data['Level'])
    //     : str_replace('L',  $icon.' Level ', $data['Level']);
    
    $icon = '<i class="fa fa-check" aria-hidden="true"></i>';

    if (in_array($data['Level'], ['E1', 'E2', 'E3'])) {
        $level_number = str_replace('E', '', $data['Level']);
        $level_val = $icon . ' Entry Level ' . $level_number;
    } else {
        $level_number = str_replace('L', '', $data['Level']);
        $level_val = $icon . ' Level ' . $level_number;
    }

    ?>
    <div class="col-lg-4 post-item">
        <div class="post-box h-100">
            <div class="image-box image-box-placeholder">
                <img src="https://openawards.theprogressteam.com/wp-content/uploads/2023/10/logo-new.svg" alt="Unit Logo">
                <span class="level <?= esc_attr($data['Level']) ?>">
                    <span class="level-badge">
                        <?= wp_kses_post($level_val) ?>
                    </span>
                </span>

            </div>
            <div class="content-box content-box-v1">
                <div class="heading-excerpt-box">
                    <div class="heading-box">
                        <h4><?= esc_html($data['Title']) ?></h4>
                      <p class="unit-code">
							<?= isset($data['ID_Alpha']) && $data['ID_Alpha'] ? 'Unit ID: ' . esc_html($data['ID_Alpha']) : '' ?>
						</p>
                    </div>
                    <div class="description-box d-none">
                        <!-- Description content -->
                    </div>
                </div>
            </div>
            <div class="button-group-box row g-0 align-items-center">
                <div class="button-box-v2 button-accent col">
                    <a class="w-100 text-center" href="<?= get_the_permalink($post_id) ?>">
                        View Unit
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function related_qualifications()
{
  ob_start();
  $level = carbon_get_the_post_meta('level');
  $args['post_type'] = 'qualifications';
  $args['numberposts'] = 3;
  $args['orderby'] = 'rand';
  $args['meta_query']['relation'] = 'AND';

  if ($level) {
    $args['meta_query'][] = array(
      'key'     => '_level',
      'value'   => array($level),
      'compare' => 'IN',
    );
  }
  $posts = get_posts($args);
  echo '<div class="row row-results g-5">';

  foreach ($posts as $post) {
    $data = array(
      'Level'   => $level,
      'Title'   => $post->post_title,
      'post_id' => $post->ID,
    );
    echo qual_grid($data, 'qualifications', true);
  }
  echo '</div>';

  return ob_get_clean();
}

add_shortcode('related_qualifications', 'related_qualifications');

function related_units() {
    ob_start();
    $level = carbon_get_the_post_meta('level');
    
    $args = array(
        'post_type' => 'units',
        'numberposts' => 3,
        'orderby' => 'rand',
        'meta_query' => array(
            'relation' => 'AND'
        )
    );

    if ($level) {
        $args['meta_query'][] = array(
            'key' => '_level',
            'value' => array($level),
            'compare' => 'IN',
        );
    }
    
    $posts = get_posts($args);
    echo '<div class="row row-results g-5">';

    foreach ($posts as $post) {
        $data = array(
            'Level' => $level,
            'Title' => $post->post_title,
            'post_id' => $post->ID,
        );
        echo unit_grid($data, 'units', true);
    }
    echo '</div>';

    return ob_get_clean();
}

add_shortcode('related_units', 'related_units');
