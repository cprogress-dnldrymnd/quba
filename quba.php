<?php

/**
 * Plugin Name: Quba System Integration
 * Description: Integrates QUBA SOAP API, synchronizes units/qualifications via batched processes, and provides custom native templates & meta boxes. Includes persistent background sync logging.
 * Version: 2.8.0
 * Author: Digitally Disruptive - Donald Raymundo
 * Author URI: https://digitallydisruptive.co.uk/
 * Text Domain: quba-integration
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class Quba_API
 * Handles SOAP client connections and data retrieval.
 */
class Quba_API
{
    private static $soap_client = null;

    /**
     * Instantiates and caches the global SOAP client configuration.
     * @return SoapClient|false The configured SOAP Client or false on failure.
     */
    public static function get_client()
    {
        if (! self::$soap_client) {
            ini_set('default_socket_timeout', 300);

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
                        'cache_wsdl'         => WSDL_CACHE_NONE,
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
     * Executes the SOAP request to extract active QCA sectors.
     * @return SimpleXMLElement|Exception SimpleXML object containing sector arrays.
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
     * Wraps raw inner XML response payloads into valid SOAP Envelopes for standardized parsing.
     * @param string $action The executing SOAP Action.
     * @param string $xmlString Raw inner XML payload.
     * @return string Re-constructed SOAP Envelope.
     */
    public static function wrap_soap_envelope($action, $xmlString)
    {
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
 * Class Quba_Post_Types
 * Registers and maintains custom post types natively within the plugin container.
 */
class Quba_Post_Types
{
    /**
     * Boots the class and hooks post type registration to WP Init.
     */
    public static function init()
    {
        add_action('init', [__CLASS__, 'register_post_types']);
    }

    /**
     * Configures properties and provisions schemas for Qualifications and Units post types.
     */
    public static function register_post_types()
    {
        $qual_labels = [
            'name'               => 'Qualifications',
            'singular_name'      => 'Qualification',
            'menu_name'          => 'Qualifications',
            'name_admin_bar'     => 'Qualification',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Qualification',
            'new_item'           => 'New Qualification',
            'edit_item'          => 'Edit Qualification',
            'view_item'          => 'View Qualification',
            'all_items'          => 'All Qualifications',
            'search_items'       => 'Search Qualifications',
            'not_found'          => 'No qualifications found.',
            'not_found_in_trash' => 'No qualifications found in Trash.'
        ];

        $qual_args = [
            'labels'             => $qual_labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'qualifications'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-welcome-learn-more',
            'supports'           => ['title', 'editor', 'thumbnail', 'revisions', 'custom-fields']
        ];
        register_post_type('qualifications', $qual_args);

        $unit_labels = [
            'name'               => 'Units',
            'singular_name'      => 'Unit',
            'menu_name'          => 'Units',
            'name_admin_bar'     => 'Unit',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Unit',
            'new_item'           => 'New Unit',
            'edit_item'          => 'Edit Unit',
            'view_item'          => 'View Unit',
            'all_items'          => 'All Units',
            'search_items'       => 'Search Units',
            'not_found'          => 'No units found.',
            'not_found_in_trash' => 'No units found in Trash.'
        ];

        $unit_args = [
            'labels'             => $unit_labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'units'],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 21,
            'menu_icon'          => 'dashicons-media-document',
            'supports'           => ['title', 'editor', 'thumbnail', 'revisions', 'custom-fields']
        ];
        register_post_type('units', $unit_args);
    }
}

/**
 * Class Quba_Cron_Sync
 * Orchestrates batched automated mapping of API data into persistent local WP Post Types.
 */
class Quba_Cron_Sync
{
    /**
     * Bootstraps the automated WP-Cron sync sequence mappings.
     */
    public static function init()
    {
        add_filter('cron_schedules', [__CLASS__, 'add_custom_cron_intervals']);
        add_action('quba_daily_sync_build_queue', [__CLASS__, 'build_sync_queue']);
        add_action('quba_process_sync_queue', [__CLASS__, 'process_batch_cron']);
    }

    /**
     * Registers a custom 30-minute interval block in seconds (1800s).
     * @param array $schedules Existing WordPress cron schedules.
     * @return array Updated WordPress cron schedules.
     */
    public static function add_custom_cron_intervals($schedules)
    {
        $schedules['thirty_minutes'] = [
            'interval' => 1800,
            'display'  => 'Every 30 Minutes'
        ];
        return $schedules;
    }

    /**
     * Injects the CRON scheduler mappings on plugin activation.
     */
    public static function activate()
    {
        // Calculate timestamp for the next 2:00 AM (Server Time)
        $next_2am = strtotime('02:00:00');
        if ($next_2am <= time()) {
            // If 2 AM has already passed today, schedule for 2 AM tomorrow
            $next_2am = strtotime('tomorrow 02:00:00');
        }

        if (!wp_next_scheduled('quba_daily_sync_build_queue')) {
            wp_schedule_event($next_2am, 'daily', 'quba_daily_sync_build_queue');
        }

        if (!wp_next_scheduled('quba_process_sync_queue')) {
            // The batch processor can start immediately so manual queues process instantly
            wp_schedule_event(time(), 'thirty_minutes', 'quba_process_sync_queue');
        }
    }

    /**
     * Purges background scheduled events on plugin deactivation.
     */
    public static function deactivate()
    {
        wp_clear_scheduled_hook('quba_daily_sync_build_queue');
        wp_clear_scheduled_hook('quba_process_sync_queue');
    }

    /**
     * Writes operational execution data natively resolving to file disk mapping variables defining properties safely.
     * @param string $message Operational status or execution result format sequence.
     */
    public static function log_action($message)
    {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/quba-logs';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            // Secure directory
            file_put_contents($log_dir . '/.htaccess', 'Deny from all');
        }

        $log_file = $log_dir . '/sync.log';
        $timestamp = current_time('mysql');
        $formatted_message = "[{$timestamp}] {$message}" . PHP_EOL;

        // Prevent log file from exceeding 2MB
        if (file_exists($log_file) && filesize($log_file) > 2097152) {
            $existing = file($log_file);
            $existing = array_slice($existing, -1000); // Keep last 1000 lines
            file_put_contents($log_file, implode("", $existing));
        }

        file_put_contents($log_file, $formatted_message, FILE_APPEND);
    }

    /**
     * Traverses the SOAP API matrix iteratively compiling all remote items into a transient queue buffer.
     * @param string $sync_type Data matrix targeted for aggregation ('both', 'qualifications', 'units').
     * @param array $specific_ids Array of numerical target IDs for precise localized fetching.
     * @return int|bool Valid count integer of queue size or false on failure.
     */
    public static function build_sync_queue($sync_type = 'both', $specific_ids = [])
    {
        $client = Quba_API::get_client();
        if (!$client) {
            self::log_action("ERROR: Failed to establish SOAP Client connection.");
            return false;
        }

        $queue = [];
        $processed_quals = [];
        $processed_units = [];

        self::log_action("INFO: Rebuilding Sync Queue (Type: {$sync_type}, Specific IDs: " . implode(',', $specific_ids) . ")");

        if (!is_array($specific_ids)) {
            $specific_ids = (int)$specific_ids > 0 ? [(int)$specific_ids] : [];
        }

        // --- 1. QUALIFICATIONS EXTRACTION ---
        if ($sync_type === 'both' || $sync_type === 'qualifications') {
            $search_queries = [];

            if (!empty($specific_ids)) {
                foreach ($specific_ids as $id) {
                    $search_queries[] = ['qcaSector' => '', 'qualificationTitle' => '', 'qualificationID' => $id];
                }
            } else {
                $sectors = Quba_API::get_qca_sectors();
                if (!is_wp_error($sectors) && !($sectors instanceof Exception) && !empty($sectors)) {
                    foreach ($sectors as $sector) {
                        $search_queries[] = ['qcaSector' => (string)$sector->Code, 'qualificationTitle' => '', 'qualificationID' => 0];
                    }
                }
                $wildcards = ['%', 'a', 'e', 'i', 'o', 'u'];
                foreach ($wildcards as $char) {
                    $search_queries[] = ['qcaSector' => '', 'qualificationTitle' => $char, 'qualificationID' => 0];
                }
            }

            foreach ($search_queries as $sq) {
                try {
                    $req = [
                        'qualificationID'     => $sq['qualificationID'],
                        'qualificationTitle'  => $sq['qualificationTitle'],
                        'qualificationLevel'  => '',
                        'qualificationNumber' => '',
                        'qcaSector'           => $sq['qcaSector'],
                        'provisionType'       => '',
                        'unitID'              => '',
                        'includeHub'          => false,
                        'centreID'            => ''
                    ];
                    $res = $client->QUBA_QualificationSearch($req);
                    $xmlString = $res->QUBA_QualificationSearchResult->any ?? '';

                    if ($xmlString) {
                        libxml_use_internal_errors(true);
                        $xml = new SimpleXMLElement(Quba_API::wrap_soap_envelope('QUBA_QualificationSearch', $xmlString));
                        $quals = $xml->xpath('//QubaQualification');
                        if ($quals) {
                            foreach ($quals as $qual) {
                                $data = [];
                                foreach ($qual->children() as $child) {
                                    if ($child->getName() != 'Classifications') {
                                        $data[$child->getName()] = trim((string) $child);
                                    }
                                }
                                if (isset($qual->Classifications->Classification1)) {
                                    $data['Classification1'] = trim((string) $qual->Classifications->Classification1);
                                }
                                if (isset($qual->Classifications->Classification2)) {
                                    $data['Classification2'] = trim((string) $qual->Classifications->Classification2);
                                }

                                $id = $data['ID'] ?? '';
                                if ($id && !isset($processed_quals[$id])) {
                                    $processed_quals[$id] = true;
                                    $queue[] = ['type' => 'qualifications', 'data' => $data];
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    self::log_action("ERROR: Qualification Queue Failure - " . $e->getMessage());
                    continue;
                }
            }
        }

        // --- 2. UNITS EXTRACTION ---
        if ($sync_type === 'both' || $sync_type === 'units') {
            $unit_queries = [];

            if (!empty($specific_ids)) {
                foreach ($specific_ids as $id) {
                    $unit_queries[] = ['unitID' => $id, 'unitTitle' => ''];
                }
            } else {
                $unit_queries[] = ['unitID' => 0, 'unitTitle' => '%'];
            }

            foreach ($unit_queries as $uq) {
                try {
                    $req = [
                        'unitID'              => $uq['unitID'],
                        'unitIdAlpha'         => '',
                        'unitTitle'           => $uq['unitTitle'],
                        'allOrPartTitle'      => true,
                        'unitLevel'           => '',
                        'unitCredits'         => 0,
                        'qcaSector'           => '',
                        'learnDirectCode'     => '',
                        'qcaCode'             => '',
                        'unitType'            => '',
                        'provisionType'       => '',
                        'includeHub'          => true,
                        'moduleID'            => 0,
                        'alternativeUnitCode' => '',
                    ];
                    $res = $client->QUBA_UnitSearch($req);
                    $xmlString = $res->QUBA_UnitSearchResult->any ?? '';

                    if ($xmlString) {
                        libxml_use_internal_errors(true);
                        $xml = new SimpleXMLElement(Quba_API::wrap_soap_envelope('QUBA_UnitSearch', $xmlString));
                        $units = $xml->xpath('//QubaUnit');
                        if ($units) {
                            foreach ($units as $unit) {
                                $data = [];
                                foreach ($unit->children() as $child) {
                                    $data[$child->getName()] = trim((string) $child);
                                }

                                $id = $data['ID'] ?? '';
                                if ($id && !isset($processed_units[$id])) {
                                    $processed_units[$id] = true;
                                    $queue[] = ['type' => 'units', 'data' => $data];
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    self::log_action("ERROR: Unit Queue Failure - " . $e->getMessage());
                }
            }
        }

        self::log_action("SUCCESS: Queue rebuilt. Total Items Pending: " . count($queue));
        update_option('quba_sync_queue', $queue, false);
        return count($queue);
    }

    /**
     * Consumes and processes a chunk of items strictly sourced from the transient queue block.
     * @param int $batch_size Maximum execution ceiling for array chunk processing.
     * @return int Size remaining sequentially in the processing queue.
     */
    public static function process_batch($batch_size = 5)
    {
        $start_time = microtime(true); // Start the timer

        $queue = get_option('quba_sync_queue', []);
        $total_in_queue = count($queue);

        if (empty($queue)) {
            return 0;
        }

        $client = Quba_API::get_client();
        if (!$client) {
            self::log_action("ERROR: Batch Processing failed. Could not instantiate SOAP Client.");
            return $total_in_queue;
        }

        // Calculate actual batch size (in case there are fewer items left than the limit)
        $actual_batch_size = min($batch_size, $total_in_queue);
        self::log_action("INFO: Processing new batch of {$actual_batch_size} items. ({$total_in_queue} items remaining in queue).");

        $batch = array_splice($queue, 0, $batch_size);

        foreach ($batch as $item) {
            if ($item['type'] === 'qualifications') {
                self::process_single_qualification($client, $item['data']);
            } else {
                self::process_single_unit($client, $item['data']);
            }
        }

        update_option('quba_sync_queue', $queue, false);

        // Stop the timer and calculate the duration
        $end_time = microtime(true);
        $duration = round($end_time - $start_time, 2);

        // Format into Minutes and Seconds for cleaner reading
        $mins = floor($duration / 60);
        $secs = $duration % 60;
        $time_string = $mins > 0 ? "{$mins}m {$secs}s" : "{$duration}s";

        self::log_action("SUCCESS: Batch of {$actual_batch_size} items completed in {$time_string}.");

        return count($queue);
    }

    /**
     * WP-Cron delegator method to invoke standard batch iterations background-only.
     */
    public static function process_batch_cron()
    {
        self::log_action("INFO: Auto-Cron execution triggered.");
        self::process_batch(100); 
    }

    /**
     * Validates and formats Base64 vs native binary streams dynamically for disk commitment.
     * @param string $base_content Raw API target string.
     * @param string $path_suffix Mapped physical subdirectory location.
     * @param string $filename Final generated local file identification.
     * @return string|bool URL endpoint or false indicating critical failure.
     */
    private static function save_pdf_stream($base_content, $path_suffix, $filename)
    {
        if (empty($base_content) || !is_string($base_content)) return false;

        $pdf_binary = '';

        if (strpos($base_content, '%PDF') === 0) {
            $pdf_binary = $base_content;
        } else {
            $decoded = base64_decode($base_content, true);
            if ($decoded !== false && strpos($decoded, '%PDF') === 0) {
                $pdf_binary = $decoded;
            } else {
                $pos = strpos($base_content, 'JVBERi0x');
                if ($pos !== false) {
                    $pdf_binary = base64_decode(substr($base_content, $pos));
                }
            }
        }

        if ($pdf_binary) {
            return self::store_document($pdf_binary, $path_suffix, $filename);
        }

        $aggressive_decode = base64_decode($base_content);
        if ($aggressive_decode && strpos($aggressive_decode, '%PDF') === 0) {
            return self::store_document($aggressive_decode, $path_suffix, $filename);
        }

        return false;
    }

    /**
     * Coordinates specific logical assignments converting API payload into local custom Post instances.
     * @param SoapClient $client Passed Active Resource.
     * @param array $data Sanitized attribute matrix dictating insertion variables.
     */
    private static function process_single_qualification($client, $data)
    {
        if (!isset($data['ID'])) return;
        $post_id = self::save_post_data($data, 'qualifications');
        $req_doc = ['qualificationID' => (int)$data['ID']];

        try {
            $res_doc = $client->QUBA_GetQualificationDocuments($req_doc);
            $any_data = $res_doc->QUBA_GetQualificationDocumentsResult->any ?? '';
            $url = self::save_pdf_stream($any_data, 'qualifications/purpose-statement', 'PurposeStatement_' . $data['ID']);
            if ($url) update_post_meta($post_id, '_purpose_statement_url', $url);
        } catch (Exception $e) {
        }

        try {
            $res_guide = $client->QUBA_GetQualificationGuide($req_doc);
            $pdf_data = $res_guide->QUBA_GetQualificationGuideResult ?? '';
            $url = self::save_pdf_stream($pdf_data, 'qualifications/qualification-guide', 'QualificationGuide_' . $data['ID']);
            if ($url) update_post_meta($post_id, '_qualification_guide_url', $url);
        } catch (Exception $e) {
        }
    }

    /**
     * Coordinates specific logical assignments converting API payload into local Units custom Post instances.
     * @param SoapClient $client Passed Active Resource.
     * @param array $data Sanitized attribute matrix dictating insertion variables.
     */
    private static function process_single_unit($client, $data)
    {
        if (!isset($data['ID'])) return;

        $post_id = self::save_post_data($data, 'units');
        $numeric_id = (int)$data['ID'];

        $pdfContent = '';

        try {
            $pdf_res = $client->QUBA_GetUnitContent(['unitID' => $numeric_id]);
            if (isset($pdf_res->QUBA_GetUnitContentResult) && !empty($pdf_res->QUBA_GetUnitContentResult)) {
                $pdfContent = $pdf_res->QUBA_GetUnitContentResult;
            }
        } catch (Exception $e) {
        }

        if ($pdfContent) {
            $url = self::store_document($pdfContent, 'units/unit-content', 'UnitContent_' . $numeric_id);
            if ($url) update_post_meta($post_id, '_unit_content_url', $url);
        }

        try {
            $qual_req = [
                'qualificationID'     => 0,
                'qualificationTitle'  => '',
                'qualificationLevel'  => '',
                'qualificationNumber' => '',
                'qcaSector'           => '',
                'provisionType'       => '',
                'unitID'              => $numeric_id,
                'includeHub'          => false,
                'centreID'            => ''
            ];
            $qual_res = $client->QUBA_QualificationSearch($qual_req);
            $q_xmlString = $qual_res->QUBA_QualificationSearchResult->any ?? '';

            if ($q_xmlString) {
                libxml_use_internal_errors(true);
                $q_xml = new SimpleXMLElement(Quba_API::wrap_soap_envelope('QUBA_QualificationSearch', $q_xmlString));
                $related = $q_xml->xpath('//QubaQualification');

                $related_array = [];
                if ($related) {
                    foreach ($related as $r_qual) {
                        $related_array[] = [
                            'title'   => (string)$r_qual->Title,
                            'code'    => (string)$r_qual->QualificationReferenceNumber,
                            'id'      => (string)$r_qual->ID,
                            'level'   => (string)$r_qual->Level,
                            'credits' => (string)$r_qual->TotalCreditsRequired
                        ];
                    }
                }
                update_post_meta($post_id, '_related_qualifications', $related_array);
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Executes WordPress insertion/update directives abstracting existing validation mappings dynamically.
     * @param array $data Input payload representing parsed data parameters dynamically formatted.
     * @param string $post_type The explicit CPT database key parameter mapped to logic execution.
     * @return int Mapped generated post ID derived natively within WP environment blocks.
     */
    private static function save_post_data($data, $post_type)
    {
        $check_id = 0;

        if ($post_type === 'units') {
            $num_id = $data['ID'] ?? '';
            $alpha_id = $data['ID_Alpha'] ?? '';

            if ($num_id) {
                $check_id = Quba_Render::get_post_id_by_meta_field('_id', $num_id);
            }
            if (!$check_id && $alpha_id) {
                $check_id = Quba_Render::get_post_id_by_meta_field('_id_alpha', $alpha_id);
            }
            if (!$check_id && $alpha_id) {
                $check_id = Quba_Render::get_post_id_by_meta_field('_id', $alpha_id);
            }
        } else {
            $check_id = Quba_Render::get_post_id_by_meta_field('_id', $data['ID'] ?? '');
        }

        $post_content = isset($data['QualificationSummary']) ? Quba_Render::santize_html($data['QualificationSummary']) : '';
        if (empty($post_content) && isset($data['Summary'])) $post_content = Quba_Render::santize_html($data['Summary']);

        $meta_input = [];
        foreach ($data as $key => $val) {
            $meta_input['_' . strtolower($key)] = $val;
        }

        if ($post_type === 'units') {
            if (isset($data['ID'])) $meta_input['_id'] = $data['ID'];
            if (isset($data['ID_Alpha'])) $meta_input['_id_alpha'] = $data['ID_Alpha'];
        } else {
            if (isset($data['ID'])) $meta_input['_id'] = $data['ID'];
        }

        $post_title = $data['Title'] ?? 'Untitled';
        $item_id = $data['ID'] ?? 'Unknown';

        $post_data = ['post_type' => $post_type, 'post_title' => $post_title, 'post_status' => 'publish', 'post_content' => $post_content, 'meta_input' => $meta_input];

        if ($check_id) {
            $post_data['ID'] = $check_id;
            wp_update_post($post_data);
            self::log_action("UPDATED {$post_type}: '{$post_title}' (WP_ID: {$check_id} | API_ID: {$item_id})");
            return $check_id;
        } else {
            $new_post_id = wp_insert_post($post_data);
            self::log_action("CREATED {$post_type}: '{$post_title}' (WP_ID: {$new_post_id} | API_ID: {$item_id})");
            return $new_post_id;
        }
    }

    /**
     * Executes raw file system IO writing variables securely within plugin contexts structure blocks.
     * @param string $file_data Buffer containing payload array for disk execution block parameters.
     * @param string $path_suffix Variable routing structure defining output file path dynamically mapping constraints.
     * @param string $filename Native execution file title target variable mapping logic blocks.
     * @return string|bool Correct localized mapping block for final document array extraction sequence.
     */
    private static function store_document($file_data, $path_suffix, $filename)
    {
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/documents/' . $path_suffix;
        $target_url = $upload_dir['baseurl'] . '/documents/' . $path_suffix;

        if (!file_exists($target_dir)) wp_mkdir_p($target_dir);

        $filepath = $target_dir . '/' . $filename . '.pdf';
        if (file_put_contents($filepath, $file_data) !== false) return $target_url . '/' . $filename . '.pdf';
        return false;
    }
}

/**
 * Class Quba_Admin
 * Manages the backend UI for manual synchronization.
 */
class Quba_Admin
{
    /**
     * Enqueues actions mapped to administrative backend execution schemas contextually limiting footprint properties.
     */
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);

        add_action('wp_ajax_quba_init_sync', [__CLASS__, 'ajax_init_sync']);
        add_action('wp_ajax_quba_process_batch', [__CLASS__, 'ajax_process_batch']);
        add_action('wp_ajax_quba_clear_logs', [__CLASS__, 'ajax_clear_logs']);
    }

    /**
     * Constructs localized layout logic mapping within default options menu parameter scope blocks dynamically generating layout matrices.
     */
    public static function register_menu()
    {
        add_submenu_page(
            'tools.php',
            'QUBA Data Sync',
            'QUBA Sync',
            'manage_options',
            'quba-sync',
            [__CLASS__, 'render_admin_page']
        );
    }

    /**
     * Assures JS constraints dynamically isolated to strictly admin menu targets isolating frontend footprint logic dynamically.
     * @param string $hook Reference to executing view parameter dynamically filtering context arrays logic states.
     */
    public static function enqueue_admin_scripts($hook)
    {
        if ($hook !== 'tools_page_quba-sync') return;

        wp_enqueue_script('quba-admin-sync', plugin_dir_url(__FILE__) . 'assets/js/admin-sync.js', ['jquery'], '2.8.0', true);
        wp_localize_script('quba-admin-sync', 'qubaAdminAjax', [
            'nonce' => wp_create_nonce('quba_admin_nonce')
        ]);
    }

    /**
     * Manages HTML view layout structures injecting variables parameters rendering visual interactive interface constructs.
     */
    public static function render_admin_page()
    {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/quba-logs/sync.log';
        $log_content = file_exists($log_file) ? esc_html(file_get_contents($log_file)) : 'No logs generated yet. Run a sync to begin monitoring.';

?>
        <div class="wrap">
            <h1>QUBA Manual Synchronization</h1>
            <p>Use this tool to manually trigger a full synchronization of Qualifications and Units from the QUBA SOAP API.</p>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; max-width: 600px; margin-top: 20px; display: inline-block; vertical-align: top;">

                <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #2271b1;">
                    <label style="display: block; font-size: 14px; margin-bottom: 10px;"><strong>1. Select Data Entity to Synchronize:</strong></label>
                    <label style="display: block; margin-bottom: 8px;"><input type="radio" name="quba_sync_type" value="both" checked> Both (Qualifications & Units)</label>
                    <label style="display: block; margin-bottom: 8px;"><input type="radio" name="quba_sync_type" value="qualifications"> Qualifications Only</label>
                    <label style="display: block; margin-bottom: 15px;"><input type="radio" name="quba_sync_type" value="units"> Units Only</label>

                    <hr style="border-top: 1px solid #ddd; margin-bottom: 15px;">

                    <label style="display: block; font-size: 14px; margin-bottom: 10px;"><strong>2. Optional: Sync Specific Target ID(s)</strong></label>
                    <input type="text" id="quba_sync_specific_id" placeholder="e.g. 1234, 5678, 9101" style="width: 100%; max-width: 300px;">
                    <p class="description" style="font-size: 12px; color: #666; margin-top: 5px;">Leave blank to run a full synchronization. If you enter comma-separated IDs here, the tool will instantly bypass the extraction loops and sync exclusively those items.</p>
                </div>

                <button id="quba-start-sync" class="button button-primary button-large">Start Manual Sync</button>

                <div style="margin-top: 20px;">
                    <strong>Status:</strong> <span id="quba-sync-status">Idle. Ready to sync.</span>
                </div>

                <div style="width: 100%; background-color: #f0f0f1; border-radius: 3px; margin-top: 15px; height: 30px; border: 1px solid #c3c4c7; overflow: hidden;">
                    <div id="quba-sync-progress-bar" style="width: 0%; height: 100%; background-color: #2271b1; transition: width 0.3s ease; text-align: center; color: white; line-height: 30px; font-weight: bold;">0%</div>
                </div>
            </div>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; max-width: 600px; margin-top: 20px; display: inline-block; vertical-align: top; margin-left: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h2 style="margin:0;">Live Sync Log</h2>
                    <button class="button" onclick="clearQubaLogs(event)">Clear Log</button>
                </div>
                <p class="description" style="margin-top: 0;">Logs record created and updated records from both Manual Syncs and Background Cron tasks.</p>
                <textarea id="quba-log-viewer" readonly style="width: 100%; height: 350px; font-family: monospace; font-size: 12px; background: #f0f0f1; color: #3c434a; white-space: pre; overflow-wrap: normal; overflow-x: scroll; border: 1px solid #c3c4c7; padding: 10px;"><?= $log_content ?></textarea>
                <script>
                    // Auto-scroll to bottom of log viewer
                    var logViewer = document.getElementById('quba-log-viewer');
                    logViewer.scrollTop = logViewer.scrollHeight;

                    function clearQubaLogs(e) {
                        e.preventDefault();
                        if (confirm('Are you sure you want to delete the sync log history?')) {
                            jQuery.post(ajaxurl, {
                                action: 'quba_clear_logs',
                                nonce: qubaAdminAjax.nonce
                            }, function(res) {
                                document.getElementById('quba-log-viewer').value = 'Log cleared.';
                            });
                        }
                    }
                </script>
            </div>
        </div>
    <?php
    }

    /**
     * Clears physical log file upon admin request natively handling AJAX permissions structurally.
     */
    public static function ajax_clear_logs()
    {
        check_ajax_referer('quba_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/quba-logs/sync.log';
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
        }
        wp_send_json_success();
    }

    /**
     * Formats API execution strings delegating execution target context dependencies mapping queue schemas dynamically.
     */
    public static function ajax_init_sync()
    {
        check_ajax_referer('quba_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $sync_type = isset($_POST['sync_type']) ? sanitize_text_field($_POST['sync_type']) : 'both';

        $specific_ids = [];
        if (!empty($_POST['specific_id'])) {
            $raw_ids = explode(',', sanitize_text_field($_POST['specific_id']));
            foreach ($raw_ids as $id) {
                $val = intval(trim($id));
                if ($val > 0) {
                    $specific_ids[] = $val;
                }
            }
        }

        $total = Quba_Cron_Sync::build_sync_queue($sync_type, $specific_ids);

        if ($total === false) wp_send_json_error('Failed to connect to QUBA API.');
        wp_send_json_success(['total' => $total]);
    }

    /**
     * Executes process batches iteratively rendering output properties mapping values sequentially triggering internal cron execution.
     */
    public static function ajax_process_batch()
    {
        check_ajax_referer('quba_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $remaining = Quba_Cron_Sync::process_batch(5);
        wp_send_json_success(['remaining' => $remaining]);
    }
}

/**
 * Class Quba_Admin_Meta
 * Manages the generation of native WordPress Meta Boxes completely removing Carbon Fields dependencies.
 */
class Quba_Admin_Meta
{
    /**
     * Hooks into the initial WordPress execution queue mapping properties rendering dynamic variables logic schemas properties.
     */
    public static function init()
    {
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_meta_boxes']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('admin_footer', [__CLASS__, 'render_inline_js_css']);
    }

    /**
     * Validates localized hook string mappings enforcing native media execution contexts isolated directly parameter sequences array schema execution.
     * @param string $hook Identifies target logic page validation schemas isolating dependency bloat executing conditions.
     */
    public static function enqueue_scripts($hook)
    {
        global $post_type;
        if (in_array($hook, ['post.php', 'post-new.php']) && in_array($post_type, ['qualifications', 'units'])) {
            wp_enqueue_media();
            wp_enqueue_script('jquery-ui-sortable');
        }
    }

    /**
     * Defines interactive meta block configurations triggering structural array injection rendering logic instances natively within post formats block layouts.
     */
    public static function register_meta_boxes()
    {
        add_meta_box('quba_meta_data', 'QUBA Data & Documents', [__CLASS__, 'render_meta_box'], ['qualifications', 'units'], 'normal', 'high');
    }

    /**
     * Extracts values locally building structural layout parameters defining context array definitions executing variable schemas blocks mappings properties.
     * @param WP_Post $post Internal variable context targeting execution object block definition schemas defining.
     */
    public static function render_meta_box($post)
    {
        wp_nonce_field('quba_meta_nonce_action', 'quba_meta_nonce');

        $api_fields = $post->post_type === 'qualifications' ? [
            '_id' => 'Qualification ID',
            '_qualificationreferencenumber' => 'Qualification Code',
            '_type' => 'Type',
            '_classification1' => 'Sector',
            '_classification2' => 'Risk Rating',
            '_level' => 'Level',
            '_regulationstartdate' => 'Start Date',
            '_reviewdate' => 'Review Date',
            '_regulationenddate' => 'Certification End Date',
            '_minage' => 'Minimum Age',
            '_glh' => 'Guided Learning Hours (GLH)',
            '_tqt' => 'Total Qualification Time (TQT)',
            '_totalcreditsrequired' => 'Total Credits Required',
            '_minimumcreditsatorabove' => 'Minimum Credits At/Above',
            '_purpose_statement_url' => 'Purpose Statement PDF URL',
            '_qualification_guide_url' => 'Qualification Guide PDF URL'
        ] : [
            '_id_alpha' => 'Open Awards Unit ID',
            '_id' => 'Internal Unit API ID',
            '_nationalcode' => 'Unit Code',
            '_qcasector' => 'Sector',
            '_level' => 'Level',
            '_credits' => 'Credit Value',
            '_classification2' => 'Risk Rating',
            '_unittype' => 'Unit Type',
            '_recognitiondate' => 'Start Date',
            '_reviewdate' => 'Review Date',
            '_expirydate' => 'End Date',
            '_glh' => 'Guided Learning Hours (GLH)',
            '_unit_content_url' => 'Unit Content PDF URL',
            '_related_qualifications' => 'Related Qualifications (JSON)'
        ];

        $additional_documents = get_post_meta($post->ID, 'additional_documents', true);
        if (!is_array($additional_documents)) $additional_documents = [];

    ?>
        <div class="quba-tabs">
            <ul class="quba-tab-nav">
                <li class="active"><a href="#quba-tab-api">API Sync Data (Read-Only)</a></li>
                <li><a href="#quba-tab-docs">Additional Documents</a></li>
            </ul>

            <div id="quba-tab-api" class="quba-tab-content active">
                <p><em>These fields are synchronized automatically via the QUBA API Cron. Manual edits are disabled.</em></p>
                <div class="quba-readonly-grid">
                    <?php foreach ($api_fields as $meta_key => $label):
                        $value = get_post_meta($post->ID, $meta_key, true);
                        if (is_array($value)) $value = json_encode($value);
                    ?>
                        <div class="quba-field-group">
                            <label><strong><?= esc_html($label) ?></strong></label>
                            <input type="text" value="<?= esc_attr($value) ?>" readonly style="width: 100%; background: #f0f0f1; border-color: #ccd0d4;">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="quba-tab-docs" class="quba-tab-content">
                <div id="quba-repeater-container">
                    <?php
                    $index = 0;
                    foreach ($additional_documents as $doc):
                        $doc_title = $doc['document_title'] ?? '';
                        $doc_file = $doc['document_file'] ?? '';
                        $filename = $doc_file ? basename(get_attached_file($doc_file)) : 'No file selected';
                    ?>
                        <div class="quba-repeater-row">
                            <div class="quba-row-header">
                                <span class="quba-row-title">Document: <span class="doc-live-title"><?= esc_html($doc_title) ?></span></span>
                                <div class="quba-row-actions">
                                    <button type="button" class="quba-collapse-row" title="Collapse/Expand">▼</button>
                                    <button type="button" class="quba-duplicate-row" title="Duplicate Row">⧉</button>
                                    <button type="button" class="quba-delete-row" title="Delete Row">✖</button>
                                    <span class="quba-drag-handle" title="Drag to Reorder">☰</span>
                                </div>
                            </div>
                            <div class="quba-row-body">
                                <div class="quba-field-group">
                                    <label>Document Title</label>
                                    <input type="text" class="quba-doc-title-input" name="additional_documents[<?= $index ?>][document_title]" value="<?= esc_attr($doc_title) ?>" style="width: 100%;" />
                                </div>
                                <div class="quba-field-group">
                                    <label>Attached File</label>
                                    <div class="quba-file-wrapper">
                                        <input type="hidden" class="quba-file-id" name="additional_documents[<?= $index ?>][document_file]" value="<?= esc_attr($doc_file) ?>" />
                                        <span class="quba-file-name" style="margin-right: 15px;"><em><?= esc_html($filename) ?></em></span>
                                        <button type="button" class="button quba-upload-file">Select File</button>
                                        <button type="button" class="button quba-remove-file" style="<?= $doc_file ? '' : 'display:none;' ?>">Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php
                        $index++;
                    endforeach;
                    ?>
                </div>
                <button type="button" id="quba-add-row" class="button button-primary" style="margin-top: 15px;">Add Document</button>
            </div>
        </div>
    <?php
    }

    /**
     * Isolates form save events triggering updates context mapping arrays verifying definitions mapping variables natively.
     * @param int $post_id Native identifier logic parameter extracting execution values definition schemas.
     */
    public static function save_meta_boxes($post_id)
    {
        if (!isset($_POST['quba_meta_nonce']) || !wp_verify_nonce($_POST['quba_meta_nonce'], 'quba_meta_nonce_action')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['additional_documents']) && is_array($_POST['additional_documents'])) {
            $docs = [];
            foreach ($_POST['additional_documents'] as $doc) {
                if (!empty($doc['document_title']) || !empty($doc['document_file'])) {
                    $docs[] = [
                        'document_title' => sanitize_text_field($doc['document_title']),
                        'document_file' => intval($doc['document_file'])
                    ];
                }
            }
            update_post_meta($post_id, 'additional_documents', array_values($docs));
        } else {
            delete_post_meta($post_id, 'additional_documents');
        }
    }

    /**
     * Embeds internal javascript properties logic context rendering constraints block mapping executions styling dependencies.
     */
    public static function render_inline_js_css()
    {
        global $post_type;
        if (!in_array($post_type, ['qualifications', 'units'])) return;
    ?>
        <style>
            .quba-tabs {
                border: 1px solid #ccd0d4;
                background: #fff;
                margin-top: 15px;
            }

            .quba-tab-nav {
                margin: 0;
                padding: 0;
                list-style: none;
                display: flex;
                border-bottom: 1px solid #ccd0d4;
                background: #f1f1f1;
            }

            .quba-tab-nav li {
                margin: 0;
            }

            .quba-tab-nav a {
                display: block;
                padding: 12px 20px;
                text-decoration: none;
                color: #3c434a;
                font-weight: 600;
                border-right: 1px solid #ccd0d4;
            }

            .quba-tab-nav li.active a {
                background: #fff;
                color: #2271b1;
                margin-bottom: -1px;
                border-bottom: 1px solid #fff;
            }

            .quba-tab-content {
                padding: 20px;
                display: none;
            }

            .quba-tab-content.active {
                display: block;
            }

            .quba-readonly-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }

            .quba-field-group {
                margin-bottom: 15px;
            }

            .quba-field-group label {
                display: block;
                margin-bottom: 5px;
            }

            .quba-repeater-row {
                border: 1px solid #dfdfdf;
                margin-bottom: 15px;
                background: #fafafa;
            }

            .quba-row-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 15px;
                background: #fff;
                border-bottom: 1px solid #dfdfdf;
                cursor: move;
            }

            .quba-row-title {
                font-weight: 600;
            }

            .quba-row-actions button,
            .quba-drag-handle {
                background: none;
                border: none;
                cursor: pointer;
                margin-left: 10px;
                font-size: 16px;
                color: #a7aaad;
                transition: color 0.2s;
            }

            .quba-row-actions button:hover,
            .quba-drag-handle:hover {
                color: #2271b1;
            }

            .quba-delete-row:hover {
                color: #d63638 !important;
            }

            .quba-row-body {
                padding: 15px;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                $('.quba-tab-nav a').on('click', function(e) {
                    e.preventDefault();
                    $('.quba-tab-nav li').removeClass('active');
                    $(this).parent().addClass('active');
                    $('.quba-tab-content').removeClass('active');
                    $($(this).attr('href')).addClass('active');
                });

                var repeaterContainer = $('#quba-repeater-container');
                var frame;

                function reindexRows() {
                    repeaterContainer.find('.quba-repeater-row').each(function(index) {
                        $(this).find('input').each(function() {
                            var name = $(this).attr('name');
                            if (name) {
                                $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                            }
                        });
                    });
                }

                repeaterContainer.sortable({
                    handle: '.quba-drag-handle',
                    update: reindexRows
                });

                $('#quba-add-row').on('click', function(e) {
                    e.preventDefault();
                    var newRow = `
                    <div class="quba-repeater-row">
                        <div class="quba-row-header">
                            <span class="quba-row-title">Document: <span class="doc-live-title">New Document</span></span>
                            <div class="quba-row-actions">
                                <button type="button" class="quba-collapse-row">▼</button>
                                <button type="button" class="quba-duplicate-row">⧉</button>
                                <button type="button" class="quba-delete-row">✖</button>
                                <span class="quba-drag-handle">☰</span>
                            </div>
                        </div>
                        <div class="quba-row-body">
                            <div class="quba-field-group">
                                <label>Document Title</label>
                                <input type="text" class="quba-doc-title-input" name="additional_documents[0][document_title]" value="" style="width: 100%;" />
                            </div>
                            <div class="quba-field-group">
                                <label>Attached File</label>
                                <div class="quba-file-wrapper">
                                    <input type="hidden" class="quba-file-id" name="additional_documents[0][document_file]" value="" />
                                    <span class="quba-file-name" style="margin-right: 15px;"><em>No file selected</em></span>
                                    <button type="button" class="button quba-upload-file">Select File</button>
                                    <button type="button" class="button quba-remove-file" style="display:none;">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>`;
                    repeaterContainer.append(newRow);
                    reindexRows();
                });

                repeaterContainer.on('click', '.quba-delete-row', function(e) {
                    e.preventDefault();
                    if (confirm('Are you sure you want to remove this document?')) {
                        $(this).closest('.quba-repeater-row').remove();
                        reindexRows();
                    }
                });

                repeaterContainer.on('click', '.quba-collapse-row', function(e) {
                    e.preventDefault();
                    $(this).closest('.quba-repeater-row').find('.quba-row-body').slideToggle();
                    $(this).text($(this).text() === '▼' ? '▲' : '▼');
                });

                repeaterContainer.on('click', '.quba-duplicate-row', function(e) {
                    e.preventDefault();
                    var clone = $(this).closest('.quba-repeater-row').clone();
                    repeaterContainer.append(clone);
                    reindexRows();
                });

                repeaterContainer.on('keyup', '.quba-doc-title-input', function() {
                    var title = $(this).val() || 'New Document';
                    $(this).closest('.quba-repeater-row').find('.doc-live-title').text(title);
                });

                repeaterContainer.on('click', '.quba-upload-file', function(e) {
                    e.preventDefault();
                    var btn = $(this);
                    var wrapper = btn.closest('.quba-file-wrapper');

                    if (frame) {
                        frame.open();
                        return;
                    }

                    frame = wp.media({
                        title: 'Select Document',
                        button: {
                            text: 'Use this document'
                        },
                        multiple: false
                    });

                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        wrapper.find('.quba-file-id').val(attachment.id);
                        wrapper.find('.quba-file-name').html('<em>' + attachment.filename + '</em>');
                        wrapper.find('.quba-remove-file').show();
                    });
                    frame.open();
                });

                repeaterContainer.on('click', '.quba-remove-file', function(e) {
                    e.preventDefault();
                    var wrapper = $(this).closest('.quba-file-wrapper');
                    wrapper.find('.quba-file-id').val('');
                    wrapper.find('.quba-file-name').html('<em>No file selected</em>');
                    $(this).hide();
                });
            });
        </script>
    <?php
    }
}

/**
 * Class Quba_Render
 * Manages the generation of UI HTML.
 */
class Quba_Render
{
    /**
     * Orchestrates visual component generation formatting specific data execution parameters schemas dynamically context rendering.
     * @param int $post_id Active query loop item referencing parameter target context properties matrix sequence schemas.
     * @param string $post_type Fallback defining context parameters visual block dependencies structure variables rendering string formats logic target matrix instances array properties sequences schemas defining scope.
     * @return string HTML logic node formatting variables blocks parameters sequences matrix elements properties natively.
     */
    public static function qual_grid($post_id, $post_type = 'qualifications')
    {
        ob_start();
        $level = get_post_meta($post_id, '_level', true);
        $title = get_the_title($post_id);

        if (in_array($level, ['E1', 'E2', 'E3'])) {
            $level_val = str_replace('E', ' Entry Level ', $level);
        } else {
            $level_val = str_replace('L', ' Level ', $level);
        }
    ?>
        <div class="col-lg-4 post-item">
            <div class="post-box h-100">
                <div class="image-box image-box-placeholder">
                    <img src="<?= get_site_url() ?>/wp-content/uploads/2023/10/logo-new.svg" alt="Logo">
                    <span class="level <?= esc_attr($level) ?>">
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
                            <h4><?= esc_html($title) ?></h4>
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
     * Invokes visual node creation format strings context mapping blocks array targeting.
     * @param int $post_id Post Identifier defining target context logic schema blocks parameter execution sequence variables properties dynamically array strings elements parameters defining block structures sequences variables mapping parameters format structure definitions matrix properties defining array values elements.
     * @param string $post_type Defines explicit scope format layout instances logic parameter schema values array mapping definition parameters structure mapping instance formats matrices logic blocks dynamically.
     * @return string Validated parameter properties formatted execution properties arrays strings mapping dynamic values logic properties formats parameter variable structural nodes formats variables properties sequences natively.
     */
    public static function unit_grid($post_id, $post_type = 'units')
    {
        return self::qual_grid($post_id, $post_type);
    }

    /**
     * Constructs standardized error reporting constraints isolating sequence parameters formatting variables schemas properties.
     * @param string $msg Fallback reporting payload defining variables context schema parameters instances property sequences format properties target values block structures dynamic.
     * @return string Safe validated array structures string format parameters definitions execution logic variables schemas elements constraints parameters defining instances schemas.
     */
    public static function generate_error_output($msg)
    {
        return '<div class="error-message"><p>An error occurred: ' . esc_html($msg) . '</p></div>';
    }

    /**
     * Navigates native DB indices natively translating values mapping target blocks properties execution schema contexts elements parameters sequences matrices schemas logic definitions instances format sequences defining property elements schemas mappings target sequences elements dynamically properties natively mapping structures constraints parameters mapping properties definitions instances arrays structure variable logic properties formats instances arrays.
     * @param string $meta_key Defining table parameter value constraints isolating structures values property formats elements array values constraints schema.
     * @param string $meta_value Schema mapping sequence extracting values format definitions context dynamically array variables parameters mapping property schemas instances string variables logic formatting sequence structures.
     * @return int Extracted parameter ID defining instances context values properties definition variable arrays execution logic formats definitions target formats parameters format.
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
     * Refines and validates extraction node constraints block properties formatting parameters properties natively mapping blocks values variables logic formatting array values variables properties format schema variables.
     * @param string $html Parameter values schema string format parameters properties definition execution values parameter schema properties natively structures target mapping instance strings constraints.
     * @return string Validated parameter format properties properties variable strings mapping definition formats matrices mapping values instances target elements values mapping.
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
 * Class Quba_Controllers
 * Intercepts WP actions natively prioritizing local caching architecture.
 */
class Quba_Controllers
{
    /**
     * Embeds internal property instances formatting array parameters dynamically variable formats sequence matrix structure blocks values mapping elements target properties string variable values target block constraints.
     */
    public static function init()
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        add_action('wp_ajax_nopriv_archive_ajax_qualifications', [__CLASS__, 'archive_ajax_qualifications']);
        add_action('wp_ajax_archive_ajax_qualifications', [__CLASS__, 'archive_ajax_qualifications']);

        add_action('wp_ajax_nopriv_archive_ajax_units', [__CLASS__, 'archive_ajax_units']);
        add_action('wp_ajax_archive_ajax_units', [__CLASS__, 'archive_ajax_units']);

        add_shortcode('related_qualifications', [__CLASS__, 'shortcode_related_qualifications']);
        add_shortcode('related_units', [__CLASS__, 'shortcode_related_units']);

        add_filter('template_include', [__CLASS__, 'route_templates'], 99);
    }

    /**
     * Contextual target rendering dependencies isolating string formats matrix array values block elements values formatting parameter variables format parameters property schema variables formats property parameters mapping natively values formatting property string parameters.
     */
    public static function enqueue_assets()
    {
        if (
            is_post_type_archive('qualifications') || is_post_type_archive('units') ||
            is_singular('qualifications') || is_singular('units') || is_tax('qualifications_cat')
        ) {
            wp_enqueue_style('quba-main-css', plugin_dir_url(__FILE__) . 'assets/css/main.css', [], '2.8.0', 'all');
            wp_enqueue_script('quba-main-js', plugin_dir_url(__FILE__) . 'assets/js/main.js', ['jquery'], '2.8.0', true);
            wp_localize_script('quba-main-js', 'qubaAjaxObj', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('quba_ajax_nonce')
            ]);
        }
    }

    /**
     * Maps parameter formats definition execution instances constraints variables formats string elements dynamically structure matrices schema targets block variables values variables target string context schemas properties parameters definitions mapping parameters mapping format mapping context defining array strings.
     * @param string $template Internal path value sequence constraint mapping property strings elements array logic schema targets format values matrix property variables schemas values structure.
     * @return string Variable formatted schema defining context format instance targets mapping property definition schemas strings mapping target constraints defining schema string matrices structure property elements format target parameters context properties array schemas formatting block execution instance parameters parameter array.
     */
    public static function route_templates($template)
    {
        if (is_post_type_archive('qualifications') || is_post_type_archive('units') || is_tax('qualifications_cat')) {
            $plugin_archive = plugin_dir_path(__FILE__) . 'templates/archive-qualifications.php';
            if (file_exists($plugin_archive)) return $plugin_archive;
        }

        if (is_singular('qualifications')) {
            $plugin_single = plugin_dir_path(__FILE__) . 'templates/single-qualifications.php';
            if (file_exists($plugin_single)) return $plugin_single;
        }

        if (is_singular('units')) {
            $plugin_single_unit = plugin_dir_path(__FILE__) . 'templates/single-units.php';
            if (file_exists($plugin_single_unit)) return $plugin_single_unit;
        }

        return $template;
    }

    /**
     * Processes string variable formats structure property elements mapping properties defining values parameters string instance mapping schema instances variables formatting definitions parameters formatting sequence matrix schemas parameters sequences defining formats parameters values variable array target sequence formats parameters elements.
     */
    public static function archive_ajax_qualifications()
    {
        $paged = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;
        $is_load_more = isset($_POST['is_load_more']) && $_POST['is_load_more'] === 'true';

        $args = [
            'post_type'      => 'qualifications',
            'posts_per_page' => 20,
            'paged'          => $paged,
            'post_status'    => 'publish',
            'meta_query'     => ['relation' => 'AND']
        ];

        if (!empty($_POST['qualificationTitle']) && $_POST['qualificationTitle'] !== 'e') {
            $args['s'] = sanitize_text_field($_POST['qualificationTitle']);
        }
        if (!empty($_POST['qualificationLevel']) && trim($_POST['qualificationLevel']) !== '') {
            $args['meta_query'][] = ['key' => '_level', 'value' => sanitize_text_field($_POST['qualificationLevel']), 'compare' => '='];
        }
        if (!empty($_POST['qcaSector'])) {
            $args['meta_query'][] = ['key' => '_classification1', 'value' => sanitize_text_field($_POST['qcaSector']), 'compare' => 'LIKE'];
        }
        if (!empty($_POST['qualificationNumber'])) {
            $args['meta_query'][] = ['key' => '_qualificationreferencenumber', 'value' => sanitize_text_field($_POST['qualificationNumber']), 'compare' => 'LIKE'];
        }
        if (!empty($_POST['qualificationType'])) {
            $args['meta_query'][] = ['key' => '_type', 'value' => sanitize_text_field($_POST['qualificationType']), 'compare' => 'LIKE'];
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            if (!$is_load_more) {
                echo '<div class="search-results-summary mb-4"><div class="results-count-display">';
                echo '<span class="results-number">' . number_format($query->found_posts) . '</span>';
                echo '<span class="results-text"> Qualification' . ($query->found_posts !== 1 ? 's' : '') . ' Found</span></div></div>';
                echo '<div class="row row-results g-5" id="quba-grid-container">';
            }

            while ($query->have_posts()) {
                $query->the_post();
                echo Quba_Render::qual_grid(get_the_ID(), 'qualifications');
            }

            if ($query->max_num_pages > $paged) {
                echo '<div class="load-more-container w-100 text-center mt-5" id="quba-load-more-container" style="flex: 0 0 100%;">';
                echo '<button class="quba-load-more button-box-v2 button-accent" style="border:none; padding:15px 30px; cursor:pointer; background-color:var(--sun-orange); color:#fff; border-radius:5px;" data-page="' . ($paged + 1) . '">Load More</button>';
                echo '</div>';
            }

            if (!$is_load_more) {
                echo '</div>';
            }
            wp_reset_postdata();
        } else {
            if (!$is_load_more) {
                echo '<div class="no-results-message"><p>No qualifications found matching your criteria.</p></div>';
            }
        }
        wp_die();
    }

    /**
     * Executes sequence format parameter array block property values format values variables schema target definition structures format schemas strings formatting parameter elements mapping array instances context formatting property definitions sequences properties structure block mappings instances format targets property sequences.
     */
    public static function archive_ajax_units()
    {
        $paged = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;
        $is_load_more = isset($_POST['is_load_more']) && $_POST['is_load_more'] === 'true';

        $args = [
            'post_type'      => 'units',
            'posts_per_page' => 20,
            'paged'          => $paged,
            'post_status'    => 'publish',
            'meta_query'     => ['relation' => 'AND']
        ];

        if (!empty($_POST['unitTitle'])) {
            $args['s'] = sanitize_text_field($_POST['unitTitle']);
        }
        if (!empty($_POST['unitLevel'])) {
            $args['meta_query'][] = ['key' => '_level', 'value' => sanitize_text_field($_POST['unitLevel']), 'compare' => '='];
        }
        if (!empty($_POST['qcaSector'])) {
            $args['meta_query'][] = ['key' => '_qcasector', 'value' => sanitize_text_field($_POST['qcaSector']), 'compare' => 'LIKE'];
        }
        if (!empty($_POST['qcaCode'])) {
            $args['meta_query'][] = [
                'relation' => 'OR',
                ['key' => '_nationalcode', 'value' => sanitize_text_field($_POST['qcaCode']), 'compare' => 'LIKE'],
                ['key' => '_id_alpha', 'value' => sanitize_text_field($_POST['qcaCode']), 'compare' => 'LIKE']
            ];
        }
        if (!empty($_POST['unitID'])) {
            $args['meta_query'][] = ['key' => '_id_alpha', 'value' => sanitize_text_field($_POST['unitID']), 'compare' => '='];
        }
        if (!empty($_POST['unitType'])) {
            $args['meta_query'][] = [
                'relation' => 'OR',
                ['key' => '_unittype', 'value' => sanitize_text_field($_POST['unitType']), 'compare' => 'LIKE'],
                ['key' => '_classification3', 'value' => sanitize_text_field($_POST['unitType']), 'compare' => 'LIKE']
            ];
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            if (!$is_load_more) {
                echo '<div class="search-results-summary mb-4"><div class="results-count-display">';
                echo '<span class="results-number">' . number_format($query->found_posts) . '</span>';
                echo '<span class="results-text"> Unit' . ($query->found_posts !== 1 ? 's' : '') . ' Found</span></div></div>';
                echo '<div class="row row-results g-5" id="quba-grid-container">';
            }

            while ($query->have_posts()) {
                $query->the_post();
                echo Quba_Render::unit_grid(get_the_ID(), 'units');
            }

            if ($query->max_num_pages > $paged) {
                echo '<div class="load-more-container w-100 text-center mt-5" id="quba-load-more-container" style="flex: 0 0 100%;">';
                echo '<button class="quba-load-more button-box-v2 button-accent" style="border:none; padding:15px 30px; cursor:pointer; background-color:var(--sun-orange); color:#fff; border-radius:5px;" data-page="' . ($paged + 1) . '">Load More</button>';
                echo '</div>';
            }

            if (!$is_load_more) {
                echo '</div>';
            }
            wp_reset_postdata();
        } else {
            if (!$is_load_more) {
                echo '<div class="no-results-message"><p>No units found matching your criteria.</p></div>';
            }
        }
        wp_die();
    }

    /**
     * Executes variables mapping array formats schemas target sequence property format parameter variables instances values variable mapping sequence structures constraints properties mapping parameters formatting string mapping target sequence strings formatting.
     * @return string Variable formats schemas mapping property sequence blocks execution format parameters context array logic constraints format values sequences matrix string formatting array schemas strings target variables formatting defining parameter structures instance target format.
     */
    public static function shortcode_related_qualifications()
    {
        ob_start();
        $level = get_post_meta(get_the_ID(), '_level', true);
        $args = [
            'post_type'   => 'qualifications',
            'numberposts' => 3,
            'orderby'     => 'rand',
            'meta_query'  => ['relation' => 'AND']
        ];

        if ($level) {
            $args['meta_query'][] = ['key' => '_level', 'value' => $level, 'compare' => '='];
        }

        $posts = get_posts($args);
        echo '<div class="row row-results g-5">';
        foreach ($posts as $post) {
            echo Quba_Render::qual_grid($post->ID, 'qualifications');
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Sequences parameters values properties variable logic matrix mapping context values sequence formatting instance strings format defining schema format property targets array mapping constraints array structure definition parameters formatting formatting target.
     * @return string Validated parameter sequence blocks schemas formats mapping defining format string values schema sequence formatting variables definition mapping target format structures parameters schemas execution parameter instances context schema properties target instance values definitions context mapping constraints string array schemas values variable format variables parameter properties values.
     */
    public static function shortcode_related_units()
    {
        ob_start();
        $level = get_post_meta(get_the_ID(), '_level', true);
        $args = [
            'post_type'   => 'units',
            'numberposts' => 3,
            'orderby'     => 'rand',
            'meta_query'  => ['relation' => 'AND']
        ];

        if ($level) {
            $args['meta_query'][] = ['key' => '_level', 'value' => $level, 'compare' => '='];
        }

        $posts = get_posts($args);
        echo '<div class="row row-results g-5">';
        foreach ($posts as $post) {
            echo Quba_Render::unit_grid($post->ID, 'units');
        }
        echo '</div>';
        return ob_get_clean();
    }
}

// Bootstrap
Quba_Post_Types::init();
Quba_Cron_Sync::init();
Quba_Admin::init();
Quba_Admin_Meta::init();
register_activation_hook(__FILE__, ['Quba_Cron_Sync', 'activate']);
register_deactivation_hook(__FILE__, ['Quba_Cron_Sync', 'deactivate']);
add_action('plugins_loaded', ['Quba_Controllers', 'init']);
