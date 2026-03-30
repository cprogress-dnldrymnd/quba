<?php

/**
 * Plugin Name: QUBA API Integration
 * Description: Object-Oriented implementation of the QUBA SOAP API, Unit/Qualification sync, and WordPress shortcode integration.
 * Version: 1.1.0
 * Author: Digitally Disruptive - Donald Raymundo
 * Author URI: https://digitallydisruptive.co.uk/
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (! class_exists('DigitallyDisruptive_Quba_Integration')) {

    /**
     * Main Core Integration Class for QUBA SOAP API.
     */
    class DigitallyDisruptive_Quba_Integration
    {

        /**
         * @var SoapClient|null Holds the instantiated global SOAP client to avoid recreation.
         */
        private $soap_client = null;

        /**
         * @var string The WSDL endpoint URL.
         */
        private $wsdl_url = 'https://quba.quartz-system.com/QuartzWSExtra/OCNNWR/WSQUBA_UB_V3.asmx?WSDL';

        /**
         * Constructor. Initializes hooks and shortcodes.
         */
        public function __construct()
        {
            $this->init_hooks();
        }

        /**
         * Registers all WordPress AJAX actions and Shortcodes.
         * * @return void
         */
        private function init_hooks()
        {
            // AJAX Cache Clear Hooks
            add_action('wp_ajax_clear_quba_cache', [$this, 'clear_quba_cache']);
            add_action('wp_ajax_nopriv_clear_quba_cache', [$this, 'clear_quba_cache']);

            // AJAX Qualification Archive Hooks
            add_action('wp_ajax_nopriv_archive_ajax_qualifications', [$this, 'archive_ajax_qualifications']);
            add_action('wp_ajax_archive_ajax_qualifications', [$this, 'archive_ajax_qualifications']);

            // AJAX Unit Archive Hooks
            add_action('wp_ajax_nopriv_archive_ajax_units', [$this, 'archive_ajax_units']);
            add_action('wp_ajax_archive_ajax_units', [$this, 'archive_ajax_units']);

            // Shortcodes
            add_shortcode('related_qualifications', [$this, 'related_qualifications_shortcode']);
            add_shortcode('related_units', [$this, 'related_units_shortcode']);
        }

        /**
         * Initializes and returns the global SOAP client.
         * Configures timeouts, headers, and disables WSDL caching.
         *
         * @return SoapClient|bool SoapClient instance, or false on failure.
         */
        private function get_soap_client()
        {
            if (! $this->soap_client) {
                // Increase PHP limits for heavy API calls
                ini_set('default_socket_timeout', 300);
                ini_set('max_execution_time', 300);

                try {
                    $context = stream_context_create([
                        'http' => [
                            'timeout'          => 300,
                            'protocol_version' => 1.1,
                            'header'           => "Connection: close\r\n"
                        ]
                    ]);

                    $this->soap_client = new SoapClient(
                        $this->wsdl_url,
                        [
                            'trace'              => true,
                            'exceptions'         => true,
                            'cache_wsdl'         => WSDL_CACHE_NONE, // disable caching
                            'connection_timeout' => 180,
                            'stream_context'     => $context,
                            // temporarily disable compression for testing
                            //'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP
                        ]
                    );
                } catch (Exception $e) {
                    error_log('QUBA SOAP Client Error: ' . $e->getMessage());
                    return false;
                }
            }

            return $this->soap_client;
        }

        /**
         * Fetches QCA Sectors via the QUBA SOAP API.
         *
         * @return array|Exception Returns an array of XML Elements or Exception on failure.
         */
        public function get_qca_sectors()
        {
            $client = $this->get_soap_client();
            if (! $client) return false;

            try {
                // Call the SOAP method
                $response = $client->QUBA_GetQCASectors();

                // Assuming $response is the object returned from the SOAP call
                $xmlString = $response->QUBA_GetQCASectorsResult->any;

                $responseString = $this->build_soap_envelope('QUBA_GetQCASectors', $xmlString);

                $xml = new SimpleXMLElement($responseString);
                return $xml->xpath('//QubaGetSSAReferenceData');
            } catch (Exception $e) {
                return $e; // Handle errors (e.g., invalid XML, data extraction issues)
            }
        }

        /**
         * Retrieves qualification documents by ID.
         *
         * @param int|string $qualificationID The ID of the qualification.
         * @return array|Exception Returns array of document nodes or Exception on failure.
         */
        public function get_qualification_documents($qualificationID)
        {
            $client = $this->get_soap_client();
            if (! $client) return false;

            $request = [
                'qualificationID' => $qualificationID,
            ];

            try {
                // Call the SOAP method
                $response = $client->QUBA_GetQualificationDocuments($request);

                $xmlString = $response->QUBA_GetQualificationDocumentsResult->any;
                $responseString = $this->build_soap_envelope('QUBA_GetQualificationDocuments', $xmlString);

                $xml = new SimpleXMLElement($responseString);
                return $xml->xpath('//QubaQualificationDocuments');
            } catch (Exception $e) {
                return $e;
            }
        }

        /**
         * Searches for a specific unit by its ID.
         *
         * @param int|string $unitID The Unit ID.
         * @return object Unit object representation or error object.
         */
        public function unit_search_by_id($unitID)
        {
            ob_start();

            try {
                $client = $this->get_soap_client();
                if (! $client) throw new Exception("SOAP Client not initialized.");

                // Optional: Get unitType from query param if provided
                $unitType = isset($_GET['unitType']) ? sanitize_text_field($_GET['unitType']) : '';

                // Create the SOAP request
                $request = [
                    'unitID'              => (int) $unitID, // Ensure unitID is an integer
                    'unitIdAlpha'         => '',
                    'unitTitle'           => '',
                    'allOrPartTitle'      => false,
                    'unitLevel'           => '',
                    'unitCredits'         => 0,
                    'qcaSector'           => '',
                    'learnDirectCode'     => '',
                    'qcaCode'             => '',
                    'unitType'            => $unitType, // Pass filtered unitType
                    'provisionType'       => '',
                    'includeHub'          => true,
                    'moduleID'            => '',
                    'alternativeUnitCode' => '',
                ];

                $response = $client->QUBA_UnitSearch($request);
                $xmlString = isset($response->QUBA_UnitSearchResult->any) ? $response->QUBA_UnitSearchResult->any : '';

                if (! $xmlString) {
                    return (object) ['error' => 'Empty response from SOAP API'];
                }

                $responseString = $this->build_soap_envelope('QUBA_UnitSearch', $xmlString);

                // Parse XML safely
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
            } finally {
                ob_get_clean();
            }
        }

        /**
         * Searches for a qualification by ID.
         *
         * @param int|string $qualificationID The qualification ID.
         * @return object Qualification data object or error object.
         */
        public function qualification_search_by_id($qualificationID)
        {
            ob_start();

            try {
                if (empty($qualificationID) || ! is_numeric($qualificationID)) {
                    return (object) ['error' => 'Invalid Qualification ID provided'];
                }

                $client = $this->get_soap_client();
                if (! $client) throw new Exception("SOAP Client not initialized.");

                $request = [
                    'qualificationID'     => (int) $qualificationID,
                    'qualificationTitle'  => '',
                    'qualificationLevel'  => '',
                    'qualificationNumber' => '',
                    'qcaSector'           => '',
                    'provisionType'       => '',
                    'unitID'              => '',
                    'includeHub'          => false,
                    'centreID'            => ''
                ];

                $response = $client->QUBA_QualificationSearch($request);
                $xmlString = isset($response->QUBA_QualificationSearchResult->any) ? $response->QUBA_QualificationSearchResult->any : '';

                if (! $xmlString) {
                    return (object) ['error' => 'Empty response from SOAP API'];
                }

                $responseString = $this->build_soap_envelope('QUBA_QualificationSearch', $xmlString);

                libxml_use_internal_errors(true);
                $xml = new SimpleXMLElement($responseString);
                $qualifications = $xml->xpath('//QubaQualification');

                if (empty($qualifications)) {
                    return (object) ['error' => 'No qualification found with ID: ' . $qualificationID];
                }

                $qualificationObject = new stdClass();
                foreach ($qualifications[0]->children() as $child) {
                    if ($child->getName() != 'Classifications') {
                        $qualificationObject->{$child->getName()} = htmlentities((string) $child);
                    }
                }

                if (isset($qualifications[0]->Classifications)) {
                    $classifications = $qualifications[0]->Classifications;
                    if (isset($classifications->Classification1)) {
                        $qualificationObject->Classification1 = htmlentities((string) $classifications->Classification1);
                    }
                }

                return $qualificationObject;
            } catch (Exception $e) {
                return (object) ['error' => 'SOAP Request Failed: ' . $e->getMessage()];
            } finally {
                ob_get_clean();
            }
        }

        /**
         * Retrieves, saves, and returns the URL for a Unit Listing Document (PDF).
         *
         * @param int $qualificationID Qualification ID acting as unit.
         * @return string PDF File URL or Error message.
         */
        public function get_unit_listing_document($qualificationID)
        {
            try {
                if (empty($qualificationID) || ! is_numeric($qualificationID)) {
                    return "Invalid Qualification ID provided.";
                }

                $client = $this->get_soap_client();
                if (! $client) throw new Exception("SOAP Client not initialized.");

                $request = ['unitID' => $qualificationID];
                $response = $client->QUBA_GetUnitContent($request);

                if (! isset($response->QUBA_GetUnitContentResult) || empty($response->QUBA_GetUnitContentResult)) {
                    return "No data found for this qualification.";
                }

                $pdfContent = $response->QUBA_GetUnitContentResult;
                $uploadDir = 'uploads/pdf/';

                if (! file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $filename = 'unit_' . $qualificationID . '_' . date('YmdHis') . '.pdf';
                $filePath = $uploadDir . $filename;

                file_put_contents($filePath, $pdfContent);

                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                return $baseUrl . '/' . $filePath;
            } catch (SoapFault $e) {
                return "SOAP Error: " . $e->getMessage();
            } catch (Exception $e) {
                return "General Error: " . $e->getMessage();
            }
        }

        /**
         * Search for a qualification assigned to a specific unit.
         *
         * @param int|string $unitID Unit ID to search by.
         * @return array Array of SimpleXMLElements matching criteria.
         */
        public function qualification_search_for_unit($unitID)
        {
            ob_start();

            try {
                $client = $this->get_soap_client();
                if (! $client) throw new Exception("SOAP Client not initialized.");

                $request = [
                    'qualificationID'     => 0,
                    'qualificationTitle'  => '',
                    'qualificationLevel'  => '',
                    'qualificationNumber' => '',
                    'qcaSector'           => '',
                    'provisionType'       => '',
                    'unitID'              => $unitID,
                    'includeHub'          => false,
                    'centreID'            => ''
                ];

                $response = $client->QUBA_QualificationSearch($request);
                $xmlString = $response->QUBA_QualificationSearchResult->any;

                $responseString = $this->build_soap_envelope('QUBA_QualificationSearch', $xmlString);

                $xml = new SimpleXMLElement($responseString);
                return $xml->xpath('//QubaQualification');
            } catch (Exception $e) {
                error_log('QUBA Search Error: ' . $e->getMessage());
                echo 'QUBA Search Error' . $e->getMessage();
            }

            return ob_get_clean();
        }

        /**
         * Conducts a general qualification search with filters.
         * Automatically filters expired and restricted content.
         *
         * @param array $data Parameters mapping to search attributes.
         * @return string HTML output of the search result grid.
         */
        public function qualification_search($data)
        {
            ob_start();
            try {
                $client = $this->get_soap_client();
                if (! $client) throw new Exception("SOAP Client not initialized.");

                $request = [
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
                ];

                $response = $client->QUBA_QualificationSearch($request);
                $xmlString = $response->QUBA_QualificationSearchResult->any;

                $responseString = $this->build_soap_envelope('QUBA_QualificationSearch', $xmlString);
                $xml = new SimpleXMLElement($responseString);
                $qualifications = $xml->xpath('//QubaQualification');

                if (isset($data['debug']) && $data['debug']) {
                    echo "<pre>";
                    var_dump($qualifications);
                    echo "</pre>";
                }

                $restrictedIds = ['127141', '127142', '127651', '127256'];
                $currentDate = new DateTime();
                $resultArray = [];

                foreach ($qualifications as $qualification) {
                    $qualificationArray = [];
                    $isRestricted = false;
                    $isExpired = false;

                    foreach ($qualification->children() as $child) {
                        if ($child->getName() != 'Classifications') {
                            $qualificationArray[$child->getName()] = trim((string) $child);
                        }
                    }

                    if (isset($qualificationArray['ID']) && in_array($qualificationArray['ID'], $restrictedIds)) {
                        continue; // Skip restricted
                    }

                    if (! empty($qualificationArray['OperationalEndDate'])) {
                        if (new DateTime($qualificationArray['OperationalEndDate']) < $currentDate) {
                            $isExpired = true;
                        }
                    }

                    if (! empty($qualificationArray['RegulationEndDate'])) {
                        if (new DateTime($qualificationArray['RegulationEndDate']) < $currentDate) {
                            $isExpired = true;
                        }
                    }

                    if ($isExpired) continue;

                    if (isset($qualification->Classifications)) {
                        $classifications = $qualification->Classifications;
                        if (isset($classifications->Classification1)) {
                            $classificationValue = trim((string) $classifications->Classification1);
                            $qualificationArray['Classification1'] = $classificationValue;

                            if (stripos($classificationValue, 'Restricted Delivery') !== false) {
                                $isRestricted = true;
                            }
                        }
                    }

                    if (! empty($qualificationArray) && ! $isRestricted) {
                        $resultArray[] = $qualificationArray;
                    }
                }

                $resultArray_final = [];
                foreach ($resultArray as $result) {
                    $typeMatch = true;
                    if (! empty($data['qualificationType'])) {
                        $typeMatch = isset($result['Type']) && strtolower(trim($result['Type'])) == strtolower(trim($data['qualificationType']));
                    }

                    if ($typeMatch) {
                        $resultArray_final[] = $result;
                    }
                }

                if (! empty($resultArray_final)) {
                    echo '<div class="row row-results g-5">';
                    foreach ($resultArray_final as $item) {
                        echo $this->qual_grid($item);
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

        /**
         * Fetches random registered WordPress post qualifications as a fallback list.
         *
         * @return string HTML rendering of qualification grid.
         */
        public function qualification_search_post()
        {
            ob_start();
            $posts = get_posts([
                'post_type'   => 'qualifications',
                'numberposts' => 15,
                'orderby'     => 'rand',
            ]);

            echo "<div class='row row-results g-5'>";
            foreach ($posts as $post) {
                $level = function_exists('carbon_get_post_meta') ? carbon_get_post_meta($post->ID, 'level') : get_post_meta($post->ID, 'level', true);
                $data = [
                    'Level'   => $level,
                    'Title'   => $post->post_title,
                    'post_id' => $post->ID,
                ];
                echo $this->qual_grid($data, 'qualifications', true);
            }
            echo "</div>";
            return ob_get_clean();
        }

        /**
         * Finds a unit Post ID by a specific meta key/value pair.
         *
         * @param string $meta_key The meta key to evaluate.
         * @param string $meta_value The meta value to evaluate.
         * @return string|null Post ID if found, else null.
         */
        public function get_unit_post_id_by_meta($meta_key, $meta_value)
        {
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

        /**
         * Synchronizes raw units from QUBA to WordPress Posts/Custom Table.
         *
         * @param array $units Collection of units extracted from SOAP payload.
         * @return void
         */
        public function sync_units_to_posts($units)
        {
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

                if (empty($unit_id)) continue;

                $existing_post_id = $this->get_unit_post_id_by_meta('_id', $unit_id);

                $meta_data = [
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
                ];

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

                if (! $post_id) continue;

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
                        '%d',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s',
                        '%s'
                    ]
                );

                if ($result === false) {
                    error_log("DB ERROR: " . $wpdb->last_error);
                }
            }
            error_log("Units synced successfully.");
        }

        /**
         * Retrieves an array of units via post meta filtering.
         *
         * @param array $filters Applied search filters.
         * @return array Populated list of unit metadata arrays.
         */
        public function get_units_from_postmeta($filters = [])
        {
            $args = [
                'post_type'      => 'units',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ];

            $post_ids = get_posts($args);
            $units = [];

            foreach ($post_ids as $post_id) {
                $unit = [
                    'id'                 => $post_id,
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
                    'Classification1'    => get_post_meta($post_id, '_classification1', true),
                ];

                if (! $this->passes_unit_filters($unit, $filters)) continue;

                $units[] = $unit;
            }

            return $units;
        }

        /**
         * Initiates a Unit Search request directly via SOAP endpoint.
         * Outputs result grid and controls localized caching natively.
         *
         * @param array $data Input filters for unit search.
         * @return string HTML Output.
         */
        public function unit_search($data)
        {
            ob_start();

            try {
                $cache_key = 'quba_units_AllDataTotal35' . md5(serialize($data));
                $cached_results = get_transient($cache_key);

                if ($cached_results !== false) {
                    echo $cached_results;
                    return ob_get_clean();
                }

                $client = $this->get_soap_client();
                if (! $client) throw new Exception('Failed to initialize SOAP client');

                $request = [
                    'unitID'              => isset($data['unitID']) ? (int) $data['unitID'] : 0,
                    'unitIdAlpha'         => isset($data['unitID']) ? $data['unitID'] : '',
                    'unitTitle'           => '%',
                    'allOrPartTitle'      => true,
                    'unitLevel'           => isset($data['unitLevel']) ? $data['unitLevel'] : '',
                    'unitCredits'         => 0,
                    'qcaSector'           => isset($data['qcaSector']) ? $data['qcaSector'] : '',
                    'learnDirectCode'     => '',
                    'qcaCode'             => '',
                    'unitType'            => isset($data['unitType']) ? $data['unitType'] : '',
                    'provisionType'       => '',
                    'includeHub'          => true,
                    'moduleID'            => 0,
                    'alternativeUnitCode' => '',
                ];

                $response = $client->QUBA_UnitSearch($request);
                if (! $response || ! isset($response->QUBA_UnitSearchResult->any)) {
                    throw new Exception('Invalid response from QUBA service');
                }

                $xmlString = $response->QUBA_UnitSearchResult->any;

                // Wrap securely in utf-8 specifier for processing
                $responseString = '<?xml version="1.0" encoding="utf-8"?>' . $this->build_soap_envelope('QUBA_UnitSearch', $xmlString);

                libxml_use_internal_errors(true);
                $xml = new SimpleXMLElement($responseString);
                $units = $xml->xpath('//QubaUnit');

                if (! $units) $units = [];

                $resultArray = $this->process_and_filter_units($units, $data);
                $output = $this->generate_search_results_output($resultArray, $data);

                set_transient($cache_key, $output, DAY_IN_SECONDS); // 24hr cache retention
                echo $output;
            } catch (Exception $e) {
                error_log('QUBA Search Error: ' . $e->getMessage());
                echo $this->generate_error_output($e->getMessage());
            }

            return ob_get_clean();
        }

        /**
         * Search for units from the local WPDB replicated table cache instead of live SOAP API.
         *
         * @param array $data Filtering inputs.
         * @return string Output HTML string.
         */
        public function unit_search_from_post($data)
        {
            global $wpdb;
            ob_start();

            try {
                $cache_key = 'quba_units_db5_' . md5(serialize($data));
                $cached_results = get_transient($cache_key);

                if ($cached_results !== false) {
                    echo $cached_results;
                    return ob_get_clean();
                }

                $table = $wpdb->prefix . 'quba_units_index';
                $params = [];

                $query = "
                    SELECT post_id, unit_id_alpha, title, national_code, recognition_date, 
                           level, review_date, qca_sector, expiry_date, credits, glh, 
                           unit_type, risk_rating, classification1
                    FROM $table
                    ORDER BY title ASC
                ";

                if (! empty($params)) {
                    $query = $wpdb->prepare($query, $params);
                }

                $units = $wpdb->get_results($query, ARRAY_A);
                if (empty($units)) $units = [];

                $resultArray = $this->process_and_filter_units($units, $data);
                $output = $this->generate_search_results_output($resultArray, $data);

                set_transient($cache_key, $output, 6 * HOUR_IN_SECONDS);
                echo $output;
            } catch (Exception $e) {
                error_log('QUBA Search DB Error: ' . $e->getMessage());
                echo $this->generate_error_output($e->getMessage());
            }

            return ob_get_clean();
        }

        /**
         * Compiles the filtered results into an HTML structured output.
         *
         * @param array $resultArray Processed results array.
         * @param array $data Request parameters used to generate fallback query states.
         * @return string Valid HTML string map.
         */
        private function generate_search_results_output($resultArray, $data)
        {
            $output = '';

            if (! empty($resultArray)) {
                $filteredResults = array_filter($resultArray, function ($unitData) {
                    if (! isset($unitData['Classification1']) || trim($unitData['Classification1']) === '') {
                        return false;
                    }
                    if (isset($unitData['Classification1']) && trim($unitData['Classification1']) === 'Restricted Unit') {
                        return false;
                    }
                    if (! isset($unitData['RecognitionDate']) || trim($unitData['RecognitionDate']) === '') {
                        return false;
                    }
                    return true;
                });

                $total_results = count($filteredResults);

                if ($total_results > 0) {
                    $output .= '<div class="search-results-summary mb-4">';
                    $output .= '<div class="results-count-display">';
                    $output .= '<span class="results-number">' . number_format($total_results) . '</span>';
                    $output .= '<span class="results-text"> Unit' . ($total_results !== 1 ? 's' : '') . ' Found</span>';
                    $output .= '</div></div>';

                    $output .= '<div class="row row-results g-5">';
                    foreach ($filteredResults as $unitData) {
                        $output .= $this->unit_grid($unitData, 'units');
                    }
                    $output .= '</div>';
                } else {
                    $output .= $this->generate_no_results_output($data);
                }
            } else {
                $output .= $this->generate_no_results_output($data);
            }

            return $output;
        }

        /**
         * Iterates array maps/XML to conform entity boundaries & triggers secondary checks.
         *
         * @param array|SimpleXMLElement $units Unprocessed units list.
         * @param array $data Applied query map array.
         * @return array Standardized array map.
         */
        private function process_and_filter_units($units, $data)
        {
            $resultArray = [];

            foreach ($units as $unit) {
                $unitArray = [];
                if ($unit instanceof SimpleXMLElement) {
                    foreach ($unit->children() as $child) {
                        $unitArray[$child->getName()] = htmlentities((string) $child, ENT_QUOTES, 'UTF-8');
                    }
                } else {
                    $unitArray = $unit;
                }

                if ($this->passes_unit_filters($unitArray, $data)) {
                    $resultArray[] = $unitArray;
                }
            }

            return $resultArray;
        }

        /**
         * Validates unit properties against specific subset rules.
         *
         * @param array $unitArray Data mapping for evaluating single unit schema.
         * @param array $data Input dataset criteria.
         * @return bool Returns true if it fulfills constraint evaluation, false if excluded.
         */
        private function passes_unit_filters($unitArray, $data)
        {
            if (! empty($data['unitTitle'])) {
                $searchTitle = strtolower(trim($data['unitTitle']));
                if ($searchTitle !== '%' && $searchTitle !== '*') {
                    $unitTitle = strtolower(trim($unitArray['Title']));
                    if (strpos($unitTitle, $searchTitle) === false) {
                        return false;
                    }
                }
            }

            if (! empty($data['unitLevel'])) {
                $searchLevel = strtoupper(trim($data['unitLevel']));
                $unitLevel = strtoupper(trim($unitArray['Level']));
                if ($unitLevel !== $searchLevel) return false;
            }

            if (! empty($data['qcaSector'])) {
                $searchSector = strtolower(trim($data['qcaSector']));
                if ($searchSector !== '%' && $searchSector !== '*') {
                    $unitSector = strtolower(trim($unitArray['QCASector']));
                    if (strpos($unitSector, $searchSector) === false) return false;
                }
            }

            if (! empty($data['qcaCode'])) {
                $searchCode   = strtoupper(trim($data['qcaCode']));
                $nationalCode = strtoupper(trim($unitArray['NationalCode'] ?? ''));
                $idAlpha      = strtoupper(trim($unitArray['ID_Alpha'] ?? ''));

                if ($nationalCode !== $searchCode && $idAlpha !== $searchCode) {
                    return false;
                }
            }

            return true;
        }

        /**
         * Generates the empty state warning output HTML based on input queries.
         *
         * @param array $data Missing target variables map.
         * @return string HTML empty block sequence.
         */
        private function generate_no_results_output($data)
        {
            $output = '<div class="no-results-message"><p>No units found matching your search criteria.</p>';

            $search_info = [];
            if (! empty($data['unitTitle'])) $search_info[] = 'Title: "' . esc_html($data['unitTitle']) . '"';
            if (! empty($data['unitLevel'])) $search_info[] = 'Level: ' . esc_html($data['unitLevel']);
            if (! empty($data['qcaSector'])) $search_info[] = 'Sector: ' . esc_html($data['qcaSector']);
            if (! empty($data['qcaCode'])) $search_info[]   = 'Code: ' . esc_html($data['qcaCode']);

            if (! empty($search_info)) {
                $output .= '<p><small>Searched for: ' . implode(', ', $search_info) . '</small></p>';
            }

            $output .= '<p><small>Try adjusting your search terms or removing some filters.</small></p></div>';
            return $output;
        }

        /**
         * Formulates HTML block denoting server or API execution errors.
         *
         * @param string $error_message Ignored locally but can be echoed conditionally for deep logging.
         * @return string Fixed generic user-friendly HTML structure string.
         */
        private function generate_error_output($error_message)
        {
            return '<div class="error-message">
                <p>An error occurred while searching for units.</p>
                <p><small>Please try again in a few moments.</small></p>
            </div>';
        }

        /**
         * AJAX Callback: Purges internal transient system caching for Units specifically.
         *
         * @return void
         */
        public function clear_quba_cache()
        {
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_quba_units_%' 
                 OR option_name LIKE '_transient_timeout_quba_units_%'"
            );
            wp_die();
        }

        /**
         * Obtains and saves locally the Unit Listing Document for referencing in frontend forms.
         *
         * @param string|int $unitID Search criteria ID mapping directly to Unit.
         * @return string Saved file HTTP resource location URL.
         */
        public function get_unit_listing_doc($unitID)
        {
            try {
                $client = $this->get_soap_client();
                if (! $client) throw new Exception("SOAP Client not initialized.");

                $params = ["qualificationID" => (int) $unitID];
                $response = $client->QUBA_GetUnitListingDocument($params);

                if (! isset($response->QUBA_GetUnitListingDocumentResult) || empty($response->QUBA_GetUnitListingDocumentResult)) {
                    return "No listing document available for this unit.";
                }

                $pdfContent = $response->QUBA_GetUnitListingDocumentResult;
                if (base64_decode($pdfContent, true) !== false) {
                    $pdfContent = base64_decode($pdfContent);
                }

                $upload_dir = wp_upload_dir();
                $fileName = "UnitListing_$unitID.pdf";
                $filePath = $upload_dir['path'] . "/" . $fileName;
                $fileUrl  = $upload_dir['url'] . "/" . $fileName;

                file_put_contents($filePath, $pdfContent);

                return $fileUrl;
            } catch (Exception $e) {
                $errorMsg = $e->getMessage();
                if (strpos($errorMsg, "QubaUnitListingDocumentTypeID") !== false && strpos($errorMsg, "DBNull") !== false) {
                    return "No listing document available for this unit.";
                }
                return "Error: " . $errorMsg;
            }
        }

        /**
         * AJAX Callback: Fetches archive array string map matching custom POST triggers for qualifications.
         *
         * @return void
         */
        public function archive_ajax_qualifications()
        {
            $source                 = isset($_POST['source']) && $_POST['source'] != '' ? $_POST['source'] : '';
            $qualificationLevel     = isset($_POST['qualificationLevel']) && $_POST['qualificationLevel'] != '' ? $_POST['qualificationLevel'] : ' ';
            $qualificationNumber    = isset($_POST['qualificationNumber']) && $_POST['qualificationNumber'] != '' ? $_POST['qualificationNumber'] : '';
            $qualificationTitle     = isset($_POST['qualificationTitle']) && $_POST['qualificationTitle'] != '' ? $_POST['qualificationTitle'] : 'e';
            $qualificationType      = isset($_POST['qualificationType']) && $_POST['qualificationType'] != '' ? $_POST['qualificationType'] : '';
            $qcaSector              = isset($_POST['qcaSector']) && $_POST['qcaSector'] != '' ? $_POST['qcaSector'] : '';

            $data = [
                'qualificationLevel'  => $qualificationLevel,
                'qcaSector'           => $qcaSector,
                'qualificationNumber' => $qualificationNumber,
                'qualificationTitle'  => $qualificationTitle,
                'qualificationType'   => $qualificationType,
            ];

            if ($source == 'quba') {
                echo $this->qualification_search($data);
            } else {
                echo $this->qualification_search_post();
            }
            wp_die();
        }

        /**
         * AJAX Callback: Fetches unit archives specifically and sanitizes mapping.
         *
         * @return void
         */
        public function archive_ajax_units()
        {
            $data = [
                'qcaCode'     => sanitize_text_field($_POST['qcaCode'] ?? ''),
                'qcaSector'   => sanitize_text_field($_POST['qcaSector'] ?? ''),
                'unitLevel'   => sanitize_text_field($_POST['unitLevel'] ?? ''),
                'unitTitle'   => sanitize_text_field($_POST['unitTitle'] ?? ''),
                'unitID'      => sanitize_text_field($_POST['unitID'] ?? 0),
                'unitIdAlpha' => sanitize_text_field($_POST['unitID'] ?? ''),
                'unitType'    => sanitize_text_field($_POST['unitType'] ?? '')
            ];

            $data = array_filter($data, function ($value) {
                return $value !== '' && $value !== 0;
            });

            echo $this->unit_search($data);
            wp_die();
        }

        /**
         * Scans core WP DB tables looking to match strict unique field properties per mapped instance constraints.
         *
         * @param string $meta_key Identifier mapping string in query schema.
         * @param string $meta_value Raw value assignment criteria mapping string.
         * @return string|null Resolved Post ID identifier.
         */
        public function get_post_id_by_meta_field($meta_key, $meta_value)
        {
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

        /**
         * Cleans HTML entity data blocks removing illegal tag patterns mapped historically to SOAP text anomalies.
         *
         * @param string $html The unsanitized HTML map instance wrapper block.
         * @return string Processed string variable layout blocks.
         */
        private function sanitize_html($html)
        {
            $html = str_replace('SPANstyle;', 'span style', $html);
            $html = html_entity_decode($html);
            $html = str_replace('&nbsp;', '', $html);
            $html = preg_replace('/<([a-z][a-z0-9]*)([^>]*?)>/i', '<$1>', $html);
            $html = preg_replace("/<[^\/>]*>([\s]?)*<\/[^>]*>/", '', $html);
            return $html;
        }

        /**
         * Emits structured qualification grid format layouts mapping to internal theme standard layouts for qualification post schemas.
         *
         * @param array $data Target mapping dataset payload.
         * @param string $post_type Defined post structure fallback schema string.
         * @param bool $post Signifies if mapped object implies existing WP query object scope mappings.
         * @return string Generated structural template map block HTML representation map.
         */
        public function qual_grid($data, $post_type = 'qualifications', $post = false)
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
                $check_qual = $this->get_post_id_by_meta_field('_id', $data['ID']);
                $post_content = !empty($data['QualificationSummary']) ? $this->sanitize_html($data['QualificationSummary']) : '';

                $post_data = [
                    'post_type'    => $post_type,
                    'post_title'   => $data['Title'],
                    'post_status'  => 'publish',
                    'post_content' => $post_content,
                    'meta_input'   => [
                        '_id'                           => $data['ID'],
                        '_level'                        => $data['Level'],
                        '_type'                         => $data['Type'],
                        '_regulationstartdate'          => $data['RegulationStartDate'],
                        '_operationalstartdate'         => $data['OperationalStartDate'],
                        '_regulationenddate'            => $data['RegulationEndDate'],
                        '_reviewdate'                   => $data['ReviewDate'],
                        '_totalcreditsrequired'         => $data['TotalCreditsRequired'],
                        '_minimumcreditsatorabove'      => $data['MinimumCreditsAtOrAbove'],
                        '_qualificationreferencenumber' => $data['QualificationReferenceNumber'],
                        '_contactdetails'               => $data['ContactDetails'],
                        '_minage'                       => $data['MinAge'],
                        '_tqt'                          => $data['TQT'],
                        '_glh'                          => $data['GLH'],
                        '_alternativequalificationtitle' => $data['AlternativeQualificationTitle'],
                        '_classification1'              => $data['Classification1'],
                    ]
                ];

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

            $icon = '<i class="fa fa-check " aria-hidden="true"></i>';

            if (in_array($data['Level'], ['E1', 'E2', 'E3'])) {
                $level_val = str_replace('E', $icon . ' Entry Level ', $data['Level']);
            } else {
                $level_val = str_replace('L', $icon . ' Level ', $data['Level']);
            }
            ?>
            <div class="col-lg-4 post-item">
                <div class="post-box h-100">
                    <div class="image-box image-box-placeholder">
                        <img src="https://openawards.theprogressteam.com/wp-content/uploads/2023/10/logo-new.svg">
                        <span class="level <?= esc_attr($data['Level']) ?>">
                            <span class="level-badge">
                                &#10004; <?= wp_kses_post($level_val) ?>
                            </span>
                        </span>
                    </div>
                    <div class="content-box content-box-v1">
                        <div class="heading-excerpt-box">
                            <div class="heading-box">
                                <h4><?= esc_html($data['Title']) ?></h4>
                            </div>
                            <div class="description-box d-none"></div>
                        </div>
                    </div>
                    <div class="button-group-box row g-0 align-items-center">
                        <div class="button-box-v2 button-accent col">
                            <a class="w-100 text-center" href="<?= esc_url(get_the_permalink($post_id)) ?>">
                                <?= $post_type == "units" ? "View Unit" : "View Course" ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php
            return ob_get_clean();
        }

        /**
         * Defines formatting structure schema instances mapped primarily locally toward core generated unit items representations.
         *
         * @param array $data Unit object payload array structures mapped schema maps dynamically.
         * @param string $post_type Identifier string mapping constraint targets internal schema values map block sets layout array list structures.
         * @param bool $post Checks external state flags array objects maps representations list structures schema targets layouts string payload maps lists identifiers format constraint array structures items variables internal constraints dynamically generated object fields mapped parameters dynamic internal flags mapping format.
         * @return string Compiled template HTML representation framework component blocks format map elements mapping.
         */
        public function unit_grid($data, $post_type, $post = false)
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
                $check_qual = $this->get_post_id_by_meta_field('_id', $data['ID']);
                $post_content = !empty($data['QualificationSummary']) ? $this->sanitize_html($data['QualificationSummary']) : '';

                $post_data = [
                    'post_type'    => $post_type,
                    'post_title'   => $data['Title'],
                    'post_status'  => 'publish',
                    'post_content' => $post_content,
                    'meta_input'   => [
                        '_id'                           => $data['ID'],
                        '_level'                        => $data['Level'],
                        '_type'                         => isset($data['Type']) ? $data['Type'] : '',
                        '_regulationstartdate'          => isset($data['RegulationStartDate']) ? $data['RegulationStartDate'] : '',
                        '_operationalstartdate'         => isset($data['OperationalStartDate']) ? $data['OperationalStartDate'] : '',
                        '_regulationenddate'            => isset($data['RegulationEndDate']) ? $data['RegulationEndDate'] : '',
                        '_reviewdate'                   => isset($data['ReviewDate']) ? $data['ReviewDate'] : '',
                        '_totalcreditsrequired'         => isset($data['TotalCreditsRequired']) ? $data['TotalCreditsRequired'] : '',
                        '_minimumcreditsatorabove'      => isset($data['MinimumCreditsAtOrAbove']) ? $data['MinimumCreditsAtOrAbove'] : '',
                        '_qualificationreferencenumber' => isset($data['QualificationReferenceNumber']) ? $data['QualificationReferenceNumber'] : '',
                        '_contactdetails'               => isset($data['ContactDetails']) ? $data['ContactDetails'] : '',
                        '_minage'                       => isset($data['MinAge']) ? $data['MinAge'] : '',
                        '_tqt'                          => isset($data['TQT']) ? $data['TQT'] : '',
                        '_glh'                          => isset($data['GLH']) ? $data['GLH'] : '',
                        '_alternativequalificationtitle' => isset($data['AlternativeQualificationTitle']) ? $data['AlternativeQualificationTitle'] : '',
                        '_classification1'              => isset($data['Classification1']) ? $data['Classification1'] : '',
                    ]
                ];

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
                            <div class="description-box d-none"></div>
                        </div>
                    </div>
                    <div class="button-group-box row g-0 align-items-center">
                        <div class="button-box-v2 button-accent col">
                            <a class="w-100 text-center" href="<?= esc_url(get_the_permalink($post_id)) ?>">
                                View Unit
                            </a>
                        </div>
                    </div>
                </div>
            </div>
<?php
            return ob_get_clean();
        }

        /**
         * Core shortcode rendering sequence targeting qualification schemas dynamically retrieved via associated post layouts dynamically structured via levels formatting array queries mappings constraints maps definitions values items fields parameters mappings parameters.
         *
         * @return string Embedded map formatted HTML component frameworks definitions string payload layouts parameters values layouts block elements fields values mappings payload format mappings string representations mappings dynamically dynamically layouts lists queries variables string parameters dynamic formats variables parameters definitions formats string items format schemas mappings parameters dynamically strings parameters dynamically values formatted definitions formatting items variables layouts payload layouts mappings layouts values.
         */
        public function related_qualifications_shortcode()
        {
            ob_start();
            $level = function_exists('carbon_get_the_post_meta') ? carbon_get_the_post_meta('level') : get_post_meta(get_the_ID(), 'level', true);

            $args = [
                'post_type'   => 'qualifications',
                'numberposts' => 3,
                'orderby'     => 'rand',
                'meta_query'  => ['relation' => 'AND']
            ];

            if ($level) {
                $args['meta_query'][] = [
                    'key'     => '_level',
                    'value'   => [$level],
                    'compare' => 'IN',
                ];
            }

            $posts = get_posts($args);
            echo '<div class="row row-results g-5">';

            foreach ($posts as $post) {
                $data = [
                    'Level'   => $level,
                    'Title'   => $post->post_title,
                    'post_id' => $post->ID,
                ];
                echo $this->qual_grid($data, 'qualifications', true);
            }
            echo '</div>';

            return ob_get_clean();
        }

        /**
         * Corresponding shortcode mapped identically executing equivalent internal queries mapped string mappings array frameworks arrays lists target array parameter variable formats blocks dynamically formats array layout lists mapped definitions string schemas objects array object components mapped structures lists definitions definitions mappings parameters variables mapping dynamically.
         *
         * @return string Framework HTML layouts representations format components framework maps array definitions strings elements items format array components values formats arrays payload dynamically layout definitions mappings strings arrays blocks strings definitions mappings mapping items formatting fields.
         */
        public function related_units_shortcode()
        {
            ob_start();
            $level = function_exists('carbon_get_the_post_meta') ? carbon_get_the_post_meta('level') : get_post_meta(get_the_ID(), 'level', true);

            $args = [
                'post_type'   => 'units',
                'numberposts' => 3,
                'orderby'     => 'rand',
                'meta_query'  => ['relation' => 'AND']
            ];

            if ($level) {
                $args['meta_query'][] = [
                    'key'     => '_level',
                    'value'   => [$level],
                    'compare' => 'IN',
                ];
            }

            $posts = get_posts($args);
            echo '<div class="row row-results g-5">';

            foreach ($posts as $post) {
                $data = [
                    'Level'   => $level,
                    'Title'   => $post->post_title,
                    'post_id' => $post->ID,
                ];
                echo $this->unit_grid($data, 'units', true);
            }
            echo '</div>';

            return ob_get_clean();
        }

        /**
         * Helper string compilation block utilized for consistent mock SOAP string schema generations formatting payload objects parameter formatting dynamic elements variables.
         *
         * @param string $action Identifier operation map strings elements blocks parameters payload definition components layouts schemas values definitions arrays items parameters layout arrays string formats definitions payload mapped layout items variables payload format layouts fields payload format values mapped lists format formatting formatting formatting payload arrays.
         * @param string $xmlString XML entity layout block formats variables elements payload mapped elements maps arrays schemas dynamic mapping format mappings formats schemas array items mapping parameter string values formatting items parameter mappings strings dynamic schemas.
         * @return string Resulting framework formatting payload mapping definition blocks values array variables array dynamic formats format.
         */
        private function build_soap_envelope($action, $xmlString)
        {
            return '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                <soap:Body>
                    <' . $action . 'Response xmlns="http://tempuri.org/">
                        <' . $action . 'Result namespace="" tableTypeName="">
                            ' . $xmlString . '
                        </' . $action . 'Result>
                    </' . $action . 'Response>
                </soap:Body>
            </soap:Envelope>';
        }
    }

    // Initialize core class maps definitions formats values string components strings layout formats string variables formats mappings objects format format mapping mapped structures variables mapped maps string definitions items structures schemas.
    $GLOBALS['digitallydisruptive_quba_integration'] = new DigitallyDisruptive_Quba_Integration();
}