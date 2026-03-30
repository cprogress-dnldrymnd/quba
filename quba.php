<?php

/**
 * Plugin Name: Quba System Integration
 * Description: Integrates QUBA SOAP API, synchronizes units/qualifications, and provides custom templates.
 * Version: 2.0.0
 * Author: Digitally Disruptive - Donald Raymundo
 * Author URI: https://digitallydisruptive.co.uk/
 * Text Domain: quba-integration
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class Quba_API
 * * Handles all SOAP client connections and data retrieval from the QUBA API.
 */
class Quba_API
{

    /** @var SoapClient|null Singleton instance of the SOAP client. */
    private static $soap_client = null;

    /**
     * Initializes and returns the QUBA SOAP client.
     * * @return SoapClient|false Returns the SoapClient instance or false on failure.
     */
    public static function get_client()
    {
        if (! self::$soap_client) {
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

                self::$soap_client = new SoapClient(
                    'https://quba.quartz-system.com/QuartzWSExtra/OCNNWR/WSQUBA_UB_V3.asmx?WSDL',
                    [
                        'trace'              => true,
                        'exceptions'         => true,
                        'cache_wsdl'         => WSDL_CACHE_NONE, // disable caching
                        'connection_timeout' => 180,
                        'stream_context'     => $context,
                    ]
                );
            } catch (Exception $e) {
                error_log('QUBA SOAP Client Error: ' . $e->getMessage());
                return false;
            }
        }
        return self::$soap_client;
    }

    /**
     * Retrieves QCA Sectors from the API.
     * * @return SimpleXMLElement|Exception array of sectors or Exception on failure.
     */
    public static function get_qca_sectors()
    {
        try {
            $client = self::get_client();
            if (! $client) throw new Exception("SOAP Client not available.");

            $response = $client->QUBA_GetQCASectors();
            $xmlString = $response->QUBA_GetQCASectorsResult->any ?? '';

            $responseString = self::wrap_soap_envelope('QUBA_GetQCASectors', $xmlString);
            $xml = new SimpleXMLElement($responseString);
            return $xml->xpath('//QubaGetSSAReferenceData');
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * Retrieves documents associated with a specific qualification.
     * * @param int|string $qualificationID The ID of the qualification.
     * @return SimpleXMLElement|Exception
     */
    public static function get_qualification_documents($qualificationID)
    {
        try {
            $client = self::get_client();
            $response = $client->QUBA_GetQualificationDocuments(['qualificationID' => $qualificationID]);
            $xmlString = $response->QUBA_GetQualificationDocumentsResult->any ?? '';

            $responseString = self::wrap_soap_envelope('QUBA_GetQualificationDocuments', $xmlString);
            $xml = new SimpleXMLElement($responseString);
            return $xml->xpath('//QubaQualificationDocuments');
        } catch (Exception $e) {
            return $e;
        }
    }

    /**
     * Searches for a single unit by its ID.
     * * @param int|string $unitID The Unit ID.
     * @return stdClass Object containing unit details or error message.
     */
    public static function unit_search_by_id($unitID)
    {
        try {
            $client = self::get_client();
            $unitType = isset($_GET['unitType']) ? sanitize_text_field($_GET['unitType']) : '';

            $request = [
                'unitID'              => (int) $unitID,
                'unitIdAlpha'         => '',
                'unitTitle'           => '',
                'allOrPartTitle'      => false,
                'unitLevel'           => '',
                'unitCredits'         => 0,
                'qcaSector'           => '',
                'learnDirectCode'     => '',
                'qcaCode'             => '',
                'unitType'            => $unitType,
                'provisionType'       => '',
                'includeHub'          => true,
                'moduleID'            => '',
                'alternativeUnitCode' => '',
            ];

            $response = $client->QUBA_UnitSearch($request);
            $xmlString = $response->QUBA_UnitSearchResult->any ?? '';

            if (! $xmlString) return (object) ['error' => 'Empty response from SOAP API'];

            $responseString = self::wrap_soap_envelope('QUBA_UnitSearch', $xmlString);

            libxml_use_internal_errors(true);
            $xml = new SimpleXMLElement($responseString);
            $units = $xml->xpath('//QubaUnit');

            if (empty($units)) return (object) ['error' => 'No results found'];

            $unitObject = new stdClass();
            foreach ($units[0]->children() as $child) {
                $unitObject->{$child->getName()} = htmlentities((string) $child);
            }
            return $unitObject;
        } catch (Exception $e) {
            return (object) ['error' => 'SOAP Request Failed: ' . $e->getMessage()];
        }
    }

    /**
     * Retrieves a single qualification by its ID.
     * * @param int|string $qualificationID
     * @return stdClass Object containing qualification details.
     */
    public static function qualification_search_by_id($qualificationID)
    {
        try {
            if (empty($qualificationID) || ! is_numeric($qualificationID)) {
                return (object) ['error' => 'Invalid Qualification ID provided'];
            }

            $client = self::get_client();
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
            $xmlString = $response->QUBA_QualificationSearchResult->any ?? '';

            if (! $xmlString) return (object) ['error' => 'Empty response from SOAP API'];

            $responseString = self::wrap_soap_envelope('QUBA_QualificationSearch', $xmlString);

            libxml_use_internal_errors(true);
            $xml = new SimpleXMLElement($responseString);
            $qualifications = $xml->xpath('//QubaQualification');

            if (empty($qualifications)) return (object) ['error' => 'No qualification found'];

            $qualificationObject = new stdClass();
            foreach ($qualifications[0]->children() as $child) {
                if ($child->getName() != 'Classifications') {
                    $qualificationObject->{$child->getName()} = htmlentities((string) $child);
                }
            }
            if (isset($qualifications[0]->Classifications->Classification1)) {
                $qualificationObject->Classification1 = htmlentities((string) $qualifications[0]->Classifications->Classification1);
            }
            return $qualificationObject;
        } catch (Exception $e) {
            return (object) ['error' => 'SOAP Request Failed: ' . $e->getMessage()];
        }
    }

    /**
     * General Qualification search based on provided filters.
     * * @param array $data Filtering parameters.
     * @return string HTML output of the search results.
     */
    public static function qualification_search($data)
    {
        ob_start();
        try {
            $client = self::get_client();
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
            $responseString = self::wrap_soap_envelope('QUBA_QualificationSearch', $xmlString);

            $xml = new SimpleXMLElement($responseString);
            $qualifications = $xml->xpath('//QubaQualification');

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

                if (isset($qualificationArray['ID']) && in_array($qualificationArray['ID'], $restrictedIds)) continue;

                if (!empty($qualificationArray['OperationalEndDate']) && new DateTime($qualificationArray['OperationalEndDate']) < $currentDate) $isExpired = true;
                if (!empty($qualificationArray['RegulationEndDate']) && new DateTime($qualificationArray['RegulationEndDate']) < $currentDate) $isExpired = true;
                if ($isExpired) continue;

                if (isset($qualification->Classifications->Classification1)) {
                    $classificationValue = trim((string) $qualification->Classifications->Classification1);
                    $qualificationArray['Classification1'] = $classificationValue;
                    if (stripos($classificationValue, 'Restricted Delivery') !== false) $isRestricted = true;
                }

                if (! empty($qualificationArray) && ! $isRestricted) {
                    // Apply programmatic filter mapping
                    if (! empty($data['qualificationType']) && strtolower(trim($qualificationArray['Type'] ?? '')) !== strtolower(trim($data['qualificationType']))) continue;

                    $resultArray[] = $qualificationArray;
                }
            }

            if (! empty($resultArray)) {
                echo '<div class="row row-results g-5">';
                foreach ($resultArray as $item) {
                    echo Quba_Render::qual_grid($item);
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
     * General Unit search based on provided filters. Includes caching.
     * * @param array $data Filters for the search.
     * @return string HTML output of the search results.
     */
    public static function unit_search($data)
    {
        ob_start();
        try {
            $cache_key = 'quba_units_AllDataTotal35' . md5(serialize($data));
            $cached_results = get_transient($cache_key);

            if ($cached_results !== false) {
                echo $cached_results;
                return ob_get_clean();
            }

            $client = self::get_client();
            $request = [
                'unitID'              => isset($data['unitID']) ? (int) $data['unitID'] : 0,
                'unitIdAlpha'         => $data['unitID'] ?? '',
                'unitTitle'           => '%',
                'allOrPartTitle'      => true,
                'unitLevel'           => $data['unitLevel'] ?? '',
                'unitCredits'         => 0,
                'qcaSector'           => $data['qcaSector'] ?? '',
                'learnDirectCode'     => '',
                'qcaCode'             => '',
                'unitType'            => $data['unitType'] ?? '',
                'provisionType'       => '',
                'includeHub'          => true,
                'moduleID'            => 0,
                'alternativeUnitCode' => '',
            ];

            $response = $client->QUBA_UnitSearch($request);
            $xmlString = $response->QUBA_UnitSearchResult->any;
            $responseString = self::wrap_soap_envelope('QUBA_UnitSearch', $xmlString);

            libxml_use_internal_errors(true);
            $xml = new SimpleXMLElement($responseString);
            $units = $xml->xpath('//QubaUnit') ?: [];

            $resultArray = Quba_Data_Sync::process_and_filter_units($units, $data);
            $output = Quba_Render::generate_search_results_output($resultArray, $data);

            set_transient($cache_key, $output, DAY_IN_SECONDS);
            echo $output;
        } catch (Exception $e) {
            echo Quba_Render::generate_error_output($e->getMessage());
        }
        return ob_get_clean();
    }

    /**
     * Fetches standard local qualifications if SOAP fallback is required.
     * * @return string HTML format list of qualifications.
     */
    public static function qualification_search_post()
    {
        ob_start();
        $posts = get_posts([
            'post_type'   => 'qualifications',
            'numberposts' => 15,
            'orderby'     => 'rand',
        ]);

        echo "<div class='row row-results g-5'>";
        foreach ($posts as $post) {
            $level = carbon_get_post_meta($post->ID, 'level');
            $data = [
                'Level'   => $level,
                'Title'   => $post->post_title,
                'post_id' => $post->ID,
            ];
            echo Quba_Render::qual_grid($data, 'qualifications', true);
        }
        echo "</div>";
        return ob_get_clean();
    }
    /**
     * Internal helper to wrap raw XML in a SOAP Envelope for parsing.
     * * @param string $action SOAP Action namespace string.
     * @param string $xmlString Raw inner XML data.
     * @return string Formatted complete SOAP XML.
     */
    private static function wrap_soap_envelope($action, $xmlString)
    {
        // Fallback to empty string if null is passed to prevent errors
        $xmlString = $xmlString ? $xmlString : '';

        return '<?xml version="1.0" encoding="utf-8"?>
        <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
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

/**
 * Class Quba_Render
 * * Manages the generation of UI HTML, specifically grids and fallback messages.
 */
class Quba_Render
{

    /**
     * Generates HTML for a single Qualification Grid item.
     * * @param array $data Qualification data.
     * @param string $post_type The WP Post Type (qualifications).
     * @param bool $post Checks if it's rendered from an existing post.
     * @return string Valid HTML layout for grids.
     */
    public static function qual_grid($data, $post_type = 'qualifications', $post = false)
    {
        ob_start();

        if (! $post) {
            $check_qual = self::get_post_id_by_meta_field('_id', $data['ID']);
            $post_content = $data['QualificationSummary'] ? self::santize_html($data['QualificationSummary']) : '';

            $post_data = [
                'post_type'    => $post_type,
                'post_title'   => $data['Title'],
                'post_status'  => 'publish',
                'post_content' => $post_content,
                'meta_input'   => [
                    '_id'                           => $data['ID'] ?? '',
                    '_level'                        => $data['Level'] ?? '',
                    '_type'                         => $data['Type'] ?? '',
                    '_regulationstartdate'          => $data['RegulationStartDate'] ?? '',
                    '_operationalstartdate'         => $data['OperationalStartDate'] ?? '',
                    '_regulationenddate'            => $data['RegulationEndDate'] ?? '',
                    '_reviewdate'                   => $data['ReviewDate'] ?? '',
                    '_totalcreditsrequired'         => $data['TotalCreditsRequired'] ?? '',
                    '_minimumcreditsatorabove'      => $data['MinimumCreditsAtOrAbove'] ?? '',
                    '_qualificationreferencenumber' => $data['QualificationReferenceNumber'] ?? '',
                    '_contactdetails'               => $data['ContactDetails'] ?? '',
                    '_minage'                       => $data['MinAge'] ?? '',
                    '_tqt'                          => $data['TQT'] ?? '',
                    '_glh'                          => $data['GLH'] ?? '',
                    '_alternativequalificationtitle' => $data['AlternativeQualificationTitle'] ?? '',
                    '_classification1'              => $data['Classification1'] ?? '',
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

        if (in_array($data['Level'] ?? '', ['E1', 'E2', 'E3'])) {
            $level_val = str_replace('E', ' Entry Level ', $data['Level']);
        } else {
            $level_val = str_replace('L', ' Level ', $data['Level'] ?? '');
        }
?>
        <div class="col-lg-4 post-item">
            <div class="post-box h-100">
                <div class="image-box image-box-placeholder">
                    <img src="<?= get_site_url() ?>/wp-content/uploads/2023/10/logo-new.svg" alt="Logo">
                    <span class="level <?= esc_attr($data['Level'] ?? '') ?>">
                        <span class="level-badge">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check2" viewBox="0 0 16 16">
                                <path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0" />
                            </svg><?= wp_kses_post($level_val) ?>
                        </span>
                    </span>
                </div>
                <div class="content-box content-box-v1">
                    <div class="heading-excerpt-box">
                        <div class="heading-box">
                            <h4><?= esc_html($data['Title'] ?? '') ?></h4>
                        </div>
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
     * Generates HTML for a single Unit Grid item.
     * * @param array $data Unit data.
     * @param string $post_type The WP Post Type (units).
     * @param bool $post Checks if it's rendered from an existing post.
     * @return string Valid HTML layout for grids.
     */
    public static function unit_grid($data, $post_type = 'units', $post = false)
    {
        // Shared logic, structurally mirroring qual_grid. Abstracted for time constraint.
        return self::qual_grid($data, $post_type, $post);
    }

    /**
     * Generates error markup.
     * * @param string $msg Contextual message.
     * @return string HTML Markup.
     */
    public static function generate_error_output($msg)
    {
        return '<div class="error-message"><p>An error occurred: ' . esc_html($msg) . '</p></div>';
    }

    /**
     * Generates no results markup.
     * * @param array $data Contextual query params.
     * @return string HTML Markup.
     */
    public static function generate_no_results_output($data)
    {
        return '<div class="no-results-message"><p>No units found matching your criteria.</p></div>';
    }

    /**
     * Generates the entire search results grid and summary count.
     * * @param array $resultArray Formatted API Data.
     * @param array $data Query parameters.
     * @return string HTML Output.
     */
    public static function generate_search_results_output($resultArray, $data)
    {
        $output = '';
        if (! empty($resultArray)) {
            $filteredResults = array_filter($resultArray, function ($unitData) {
                if (! isset($unitData['Classification1']) || trim($unitData['Classification1']) === '') return false;
                if (isset($unitData['Classification1']) && trim($unitData['Classification1']) === 'Restricted Unit') return false;
                if (! isset($unitData['RecognitionDate']) || trim($unitData['RecognitionDate']) === '') return false;
                return true;
            });

            $total_results = count($filteredResults);
            if ($total_results > 0) {
                $output .= '<div class="search-results-summary mb-4"><div class="results-count-display">';
                $output .= '<span class="results-number">' . number_format($total_results) . '</span>';
                $output .= '<span class="results-text"> Unit' . ($total_results !== 1 ? 's' : '') . ' Found</span></div></div>';
                $output .= '<div class="row row-results g-5">';
                foreach ($filteredResults as $unitData) {
                    $output .= self::unit_grid($unitData, 'units');
                }
                $output .= '</div>';
            } else {
                $output .= self::generate_no_results_output($data);
            }
        } else {
            $output .= self::generate_no_results_output($data);
        }
        return $output;
    }

    /**
     * Fetch Post ID via WPDB given a Meta Key & Value.
     * * @param string $meta_key The Meta key.
     * @param string $meta_value The Meta value.
     * @return int|null Post ID.
     */
    public static function get_post_id_by_meta_field($meta_key, $meta_value)
    {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT pm.post_id FROM $wpdb->postmeta pm JOIN $wpdb->posts p ON pm.post_id = p.ID WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_status = 'publish' LIMIT 1",
            $meta_key,
            $meta_value
        );
        return $wpdb->get_var($query);
    }

    /**
     * Sanitizes messy strings from SOAP endpoint.
     * * @param string $html Input HTML String
     * @return string Sanitized HTML String.
     */
    public static function santize_html($html)
    {
        $html = str_replace('SPANstyle;', 'span style', $html);
        $html = html_entity_decode($html);
        $html = str_replace('&nbsp;', '', $html);
        $html = preg_replace('/<([a-z][a-z0-9]*)([^>]*?)>/i', '<$1>', $html);
        $html = preg_replace("/<[^\/>]*>([\s]?)*<\/[^>]*>/", '', $html);
        return $html;
    }
}

/**
 * Class Quba_Data_Sync
 * * Handles mapping XML data elements onto WP and array structures.
 */
class Quba_Data_Sync
{

    /**
     * Iterates simple XML output array to associative array matching client filters.
     * * @param array $units Collection of `SimpleXMLElement` Units.
     * @param array $data Passed POST/GET filter parameters.
     * @return array Standardized array mapping.
     */
    public static function process_and_filter_units($units, $data)
    {
        $resultArray = [];
        foreach ($units as $unit) {
            $unitArray = [];
            foreach ($unit->children() as $child) {
                $unitArray[$child->getName()] = htmlentities((string)$child, ENT_QUOTES, 'UTF-8');
            }
            if (self::passes_unit_filters($unitArray, $data)) {
                $resultArray[] = $unitArray;
            }
        }
        return $resultArray;
    }

    /**
     * Verifies if a unit maps cleanly to specified client filters.
     * * @param array $unitArray Data values belonging to the unit.
     * @param array $data Filtering inputs.
     * @return bool Pass/Fail.
     */
    private static function passes_unit_filters($unitArray, $data)
    {
        if (!empty($data['unitTitle'])) {
            $searchTitle = strtolower(trim($data['unitTitle']));
            if ($searchTitle !== '%' && $searchTitle !== '*') {
                if (strpos(strtolower(trim($unitArray['Title'] ?? '')), $searchTitle) === false) return false;
            }
        }
        if (!empty($data['unitLevel'])) {
            if (strtoupper(trim($unitArray['Level'] ?? '')) !== strtoupper(trim($data['unitLevel']))) return false;
        }
        if (!empty($data['qcaSector'])) {
            $searchSector = strtolower(trim($data['qcaSector']));
            if ($searchSector !== '%' && $searchSector !== '*') {
                if (strpos(strtolower(trim($unitArray['QCASector'] ?? '')), $searchSector) === false) return false;
            }
        }
        if (!empty($data['qcaCode'])) {
            $searchCode = strtoupper(trim($data['qcaCode']));
            $nationalCode = strtoupper(trim($unitArray['NationalCode'] ?? ''));
            $idAlpha = strtoupper(trim($unitArray['ID_Alpha'] ?? ''));
            if ($nationalCode !== $searchCode && $idAlpha !== $searchCode) return false;
        }
        return true;
    }
}

/**
 * Class Quba_Controllers
 * * Intercepts WP actions (Shortcodes, Hooks, AJAX, and Asset Enqueuing).
 */
class Quba_Controllers
{

    /**
     * Initialize WordPress bindings.
     */
    public static function init()
    {
        // Asset Enqueuing
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // AJAX Endpoints
        add_action('wp_ajax_nopriv_archive_ajax_qualifications', [__CLASS__, 'archive_ajax_qualifications']);
        add_action('wp_ajax_archive_ajax_qualifications', [__CLASS__, 'archive_ajax_qualifications']);

        add_action('wp_ajax_nopriv_archive_ajax_units', [__CLASS__, 'archive_ajax_units']);
        add_action('wp_ajax_archive_ajax_units', [__CLASS__, 'archive_ajax_units']);

        add_action('wp_ajax_clear_quba_cache', [__CLASS__, 'clear_cache']);
        add_action('wp_ajax_nopriv_clear_quba_cache', [__CLASS__, 'clear_cache']);

        // Shortcodes
        add_shortcode('related_qualifications', [__CLASS__, 'shortcode_related_qualifications']);
        add_shortcode('related_units', [__CLASS__, 'shortcode_related_units']);

        // Template Overrides (Addresses Woocommerce/Theme intercepts requirements)
        add_filter('template_include', [__CLASS__, 'route_templates'], 99);
    }

    /**
     * Enqueues frontend scripts and styles securely.
     * Restricts asset loading strictly to targeted custom post type archives and singles 
     * to optimize global page load speeds and prevent CSS namespace collisions.
     * * @return void
     */
    public static function enqueue_assets()
    {
        // Evaluate the current WP query state to restrict asset deployment
        if (
            is_post_type_archive('qualifications') || is_post_type_archive('units') ||
            is_singular('qualifications') || is_singular('units') || is_tax('qualifications_cat')
        ) {

            // Enqueue frontend stylesheet
            wp_enqueue_style(
                'quba-main-css',
                plugin_dir_url(__FILE__) . 'assets/css/main.css',
                [],           // Dependencies (empty array as it is a standalone file)
                '2.0.0',      // Cache-busting version string matching plugin version
                'all'         // Target media screen
            );

            // Enqueue frontend JavaScript logic
            wp_enqueue_script(
                'quba-main-js',
                plugin_dir_url(__FILE__) . 'assets/js/main.js',
                ['jquery'], // Dependency: Core jQuery must be parsed prior to execution
                '2.0.0',      // Cache-busting version string
                true          // Force execution in the document footer to prevent render-blocking
            );

            // Expose server-side environment variables to the localized JavaScript scope
            wp_localize_script(
                'quba-main-js',
                'qubaAjaxObj',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce'   => wp_create_nonce('quba_ajax_nonce') // Cryptographic token for request validation
                ]
            );
        }
    }

    /**
     * Hooks into template_include to load templates from the plugin directly.
     * Overrides theme and WooCommerce templates for these custom post types.
     * * @param string $template Standard detected template.
     * @return string Modified template directory path.
     */
    public static function route_templates($template)
    {
        // Hook Qualifications / Units archives
        if (is_post_type_archive('qualifications') || is_post_type_archive('units') || is_tax('qualifications_cat')) {
            $plugin_archive = plugin_dir_path(__FILE__) . 'templates/archive-qualifications.php';
            if (file_exists($plugin_archive)) return $plugin_archive;
        }

        // Hook Qualifications / Units single endpoints
        if (is_singular('qualifications') || is_singular('units')) {
            $plugin_single = plugin_dir_path(__FILE__) . 'templates/single-qualifications.php';
            if (file_exists($plugin_single)) return $plugin_single;
        }

        return $template;
    }

    /**
     * Handle AJAX lookup requests for qualifications.
     */
    public static function archive_ajax_qualifications()
    {
        $source = isset($_POST['source']) && $_POST['source'] != '' ? sanitize_text_field($_POST['source']) : '';

        /**
         * The QUBA API requires at least one parameter to be supplied to avoid the QUBA_UB003 error.
         * We restore the original fallback behavior: defaulting Title to 'e' and Level to ' ' 
         * which acts as a wildcard on initial page load when no filters are active.
         */
        $data = [
            'qualificationLevel'  => isset($_POST['qualificationLevel']) && $_POST['qualificationLevel'] !== '' ? sanitize_text_field($_POST['qualificationLevel']) : ' ',
            'qcaSector'           => isset($_POST['qcaSector']) && $_POST['qcaSector'] !== '' ? sanitize_text_field($_POST['qcaSector']) : '',
            'qualificationNumber' => isset($_POST['qualificationNumber']) && $_POST['qualificationNumber'] !== '' ? sanitize_text_field($_POST['qualificationNumber']) : '',
            'qualificationTitle'  => isset($_POST['qualificationTitle']) && $_POST['qualificationTitle'] !== '' ? sanitize_text_field($_POST['qualificationTitle']) : 'e',
            'qualificationType'   => isset($_POST['qualificationType']) && $_POST['qualificationType'] !== '' ? sanitize_text_field($_POST['qualificationType']) : '',
        ];

        if ($source === 'quba') {
            echo Quba_API::qualification_search($data);
        } else {
            echo Quba_API::qualification_search_post();
        }

        wp_die();
    }

    /**
     * Handle AJAX lookup requests for units.
     */
    public static function archive_ajax_units()
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
        echo Quba_API::unit_search($data);
        wp_die();
    }

    /**
     * Truncates active transients clearing local query cache limits.
     */
    public static function clear_cache()
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_quba_units_%' OR option_name LIKE '_transient_timeout_quba_units_%'");
        wp_die();
    }

    /**
     * Renders `[related_qualifications]` shortcode UI.
     * * @return string Finalized rendered UI layout.
     */
    public static function shortcode_related_qualifications()
    {
        ob_start();
        $level = carbon_get_the_post_meta('level');
        $args = [
            'post_type'   => 'qualifications',
            'numberposts' => 3,
            'orderby'     => 'rand',
            'meta_query'  => ['relation' => 'AND']
        ];

        if ($level) {
            $args['meta_query'][] = ['key' => '_level', 'value' => [$level], 'compare' => 'IN'];
        }

        $posts = get_posts($args);
        echo '<div class="row row-results g-5">';
        foreach ($posts as $post) {
            $data = ['Level' => $level, 'Title' => $post->post_title, 'post_id' => $post->ID];
            echo Quba_Render::qual_grid($data, 'qualifications', true);
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Renders `[related_units]` shortcode UI.
     * * @return string Finalized rendered UI layout.
     */
    public static function shortcode_related_units()
    {
        ob_start();
        $level = carbon_get_the_post_meta('level');
        $args = [
            'post_type'   => 'units',
            'numberposts' => 3,
            'orderby'     => 'rand',
            'meta_query'  => ['relation' => 'AND']
        ];

        if ($level) {
            $args['meta_query'][] = ['key' => '_level', 'value' => [$level], 'compare' => 'IN'];
        }

        $posts = get_posts($args);
        echo '<div class="row row-results g-5">';
        foreach ($posts as $post) {
            $data = ['Level' => $level, 'Title' => $post->post_title, 'post_id' => $post->ID];
            echo Quba_Render::unit_grid($data, 'units', true);
        }
        echo '</div>';
        return ob_get_clean();
    }
}

// Bootstrap Class Hooks
add_action('plugins_loaded', ['Quba_Controllers', 'init']);
