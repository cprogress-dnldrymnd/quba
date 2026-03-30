<?php

/**
 * Plugin Name: Quba System Integration
 * Description: Integrates QUBA SOAP API, synchronizes units/qualifications via batched processes, and provides custom native templates & meta boxes.
 * Version: 2.3.0
 * Author: Digitally Disruptive - Donald Raymundo
 * Author URI: https://digitallydisruptive.co.uk/
 * Text Domain: quba-integration
 */

if (! defined('ABSPATH')) {
    exit;
}
/**
 * Retrieves a list of 'units' posts that possess a non-empty '_related_qualifications' meta value.
 *
 * This function utilizes WP_Query with a targeted meta_query clause. It explicitly 
 * filters out posts where the target meta key either doesn't exist or is stored as 
 * an empty string in the database schema.
 *
 * @param int $posts_per_page The number of posts to retrieve. Defaults to -1 (unlimited).
 * @return WP_Post[] Array of instantiated post objects matching the criteria.
 */
function get_units_with_qualifications( int $posts_per_page = -1 ): array {
    
    // Define the strict query parameters for the database transaction.
    $args = [
        'post_type'      => 'units',
        'posts_per_page' => $posts_per_page,
        'post_status'    => 'publish',
        // By skipping 'no_found_rows' => true, we allow pagination. Set to true if pagination isn't needed for performance.
        'meta_query'     => [
            [
                'key'     => '_related_qualifications',
                'value'   => '',
                'compare' => '!=', // Evaluates against an empty string to ensure genuine data presence.
            ],
        ],
    ];

    // Execute the object query.
    $unit_query = new WP_Query( $args );

    // Extract and return the array of post objects, returning an empty array if the query is hollow.
    return $unit_query->posts;
}

/**
 * Example execution wrapper to demonstrate iteration.
 * * @return void
 */
function display_qualified_units(): void {
    $units = get_units_with_qualifications();

    if ( empty( $units ) ) {
        return 'n/a';
    }

    foreach ( $units as $unit ) {
       echo $unit->post_title;
       echo '<br>';
    }
}

add_shortcode('display_qualified_units', 'display_qualified_units');
/**
 * Class Quba_API
 * Handles SOAP client connections and data retrieval.
 */
class Quba_API
{
    private static $soap_client = null;

    /**
     * Initializes and returns the QUBA SOAP client.
     * @return SoapClient|false Returns the SoapClient instance or false on failure.
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
     * Retrieves QCA Sectors from the API.
     * @return SimpleXMLElement|Exception array of sectors or Exception on failure.
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
     * Internal helper to wrap raw XML in a SOAP Envelope for parsing.
     * @param string $action SOAP Action namespace string.
     * @param string $xmlString Raw inner XML data.
     * @return string Formatted complete SOAP XML.
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
 * Class Quba_Cron_Sync
 * Orchestrates batched automated mapping of API data into persistent local WP Post Types.
 */
class Quba_Cron_Sync
{
    /**
     * Registers cron event hooks for background processing.
     */
    public static function init()
    {
        add_action('quba_daily_sync_build_queue', [__CLASS__, 'build_sync_queue']);
        add_action('quba_process_sync_queue', [__CLASS__, 'process_batch_cron']);
    }

    /**
     * Schedules the cron events upon plugin activation.
     */
    public static function activate()
    {
        if (!wp_next_scheduled('quba_daily_sync_build_queue')) {
            wp_schedule_event(time(), 'daily', 'quba_daily_sync_build_queue');
        }
        if (!wp_next_scheduled('quba_process_sync_queue')) {
            wp_schedule_event(time(), 'hourly', 'quba_process_sync_queue');
        }
    }

    /**
     * Clears scheduled cron events upon plugin deactivation.
     */
    public static function deactivate()
    {
        wp_clear_scheduled_hook('quba_daily_sync_build_queue');
        wp_clear_scheduled_hook('quba_process_sync_queue');
    }

    /**
     * Extracts full API lists via an extraction matrix and stores them in a transient queue.
     * @param string $sync_type The targeted data type to sync ('both', 'qualifications', 'units'). Defaults to 'both'.
     * @return int|bool Returns the total count of items queued, or false if the client fails to instantiate.
     */
    public static function build_sync_queue($sync_type = 'both')
    {
        $client = Quba_API::get_client();
        if (!$client) return false;

        $queue = [];
        $processed_quals = [];
        $processed_units = [];

        if ($sync_type === 'both' || $sync_type === 'qualifications') {
            $sectors = Quba_API::get_qca_sectors();
            $search_queries = [];

            if (!is_wp_error($sectors) && !($sectors instanceof Exception) && !empty($sectors)) {
                foreach ($sectors as $sector) {
                    $search_queries[] = ['qcaSector' => (string)$sector->Code, 'qualificationTitle' => ''];
                }
            }

            $wildcards = ['%', 'a', 'e', 'i', 'o', 'u'];
            foreach ($wildcards as $char) {
                $search_queries[] = ['qcaSector' => '', 'qualificationTitle' => $char];
            }

            foreach ($search_queries as $sq) {
                try {
                    $req = [
                        'qualificationID'     => 0,
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
                    error_log('Qual Queue Error on Sector/Char (' . $sq['qcaSector'] . $sq['qualificationTitle'] . '): ' . $e->getMessage());
                    continue;
                }
            }
        }

        if ($sync_type === 'both' || $sync_type === 'units') {
            try {
                $req = [
                    'unitID'              => 0,
                    'unitIdAlpha'         => '',
                    'unitTitle'           => '%',
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
                    $xml = new SimpleXMLElement(Quba_API::wrap_soap_envelope('QUBA_UnitSearch', $xmlString));
                    $units = $xml->xpath('//QubaUnit');
                    if ($units) {
                        foreach ($units as $unit) {
                            $data = [];
                            foreach ($unit->children() as $child) {
                                $data[$child->getName()] = trim((string) $child);
                            }

                            $id = $data['ID_Alpha'] ?? ($data['ID'] ?? '');
                            if ($id && !isset($processed_units[$id])) {
                                $processed_units[$id] = true;
                                $queue[] = ['type' => 'units', 'data' => $data];
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Unit Queue Error: ' . $e->getMessage());
            }
        }

        update_option('quba_sync_queue', $queue, false);
        return count($queue);
    }

    /**
     * Processes chunks of the queue. Can be fired by cron or AJAX.
     * @param int $batch_size The volume of items to process in a single execution thread.
     * @return int The remaining count of items in the queue.
     */
    public static function process_batch($batch_size = 5)
    {
        $queue = get_option('quba_sync_queue', []);
        if (empty($queue)) return 0;

        $client = Quba_API::get_client();
        if (!$client) return count($queue);

        $batch = array_splice($queue, 0, $batch_size);

        foreach ($batch as $item) {
            if ($item['type'] === 'qualifications') {
                self::process_single_qualification($client, $item['data']);
            } else {
                self::process_single_unit($client, $item['data']);
            }
        }

        update_option('quba_sync_queue', $queue, false);
        return count($queue);
    }

    /**
     * Triggered strictly by the background WP-Cron engine.
     */
    public static function process_batch_cron()
    {
        self::process_batch(20);
    }

    /**
     * Maps an individual qualification dataset to the WP DB and requests related documents.
     * @param SoapClient $client The active SOAP endpoint instance.
     * @param array $data The mapped XML entity parameters.
     */
    private static function process_single_qualification($client, $data)
    {
        if (!isset($data['ID'])) return;
        $post_id = self::save_post_data($data, 'qualifications');
        $req_doc = ['qualificationID' => (int)$data['ID']];

        try {
            $res_doc = $client->QUBA_GetQualificationDocuments($req_doc);
            $any_data = $res_doc->QUBA_GetQualificationDocumentsResult->any ?? '';
            $pdf_start_pos = strpos($any_data, 'JVBERi0x');
            if ($pdf_start_pos !== false) {
                $pdf_data = base64_decode(substr($any_data, $pdf_start_pos));
                $url = self::store_document($pdf_data, 'qualifications/purpose-statement', 'PurposeStatement_' . $data['ID']);
                if ($url) update_post_meta($post_id, '_purpose_statement_url', $url);
            }
        } catch (Exception $e) {
        }

        try {
            $res_guide = $client->QUBA_GetQualificationGuide($req_doc);
            $pdf_data = $res_guide->QUBA_GetQualificationGuideResult ?? '';
            if ($pdf_data) {
                $url = self::store_document($pdf_data, 'qualifications/qualification-guide', 'QualificationGuide_' . $data['ID']);
                if ($url) update_post_meta($post_id, '_qualification_guide_url', $url);
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Maps an individual unit dataset to the WP DB, maps relational data, and requests related documents.
     * @param SoapClient $client The active SOAP endpoint instance.
     * @param array $data The mapped XML entity parameters.
     */
    private static function process_single_unit($client, $data)
    {
        if (!isset($data['ID_Alpha']) && !isset($data['ID'])) return;
        $post_id = self::save_post_data($data, 'units');
        $unit_id = $data['ID_Alpha'] ?? $data['ID'];

        try {
            $pdf_res = $client->QUBA_GetUnitListingDocument(['qualificationID' => (int) $data['ID']]);
            $pdfContent = $pdf_res->QUBA_GetUnitListingDocumentResult ?? '';
            if ($pdfContent) {
                if (base64_decode($pdfContent, true) !== false) $pdfContent = base64_decode($pdfContent);
                $url = self::store_document($pdfContent, 'units/unit-listing', 'UnitListing_' . $unit_id);
                if ($url) update_post_meta($post_id, '_unit_listing_url', $url);
            }
        } catch (Exception $e) {
        }

        try {
            $qual_req = ['qualificationID' => 0, 'qualificationTitle' => '', 'qualificationLevel' => '', 'qualificationNumber' => '', 'qcaSector' => '', 'provisionType' => '', 'unitID' => $unit_id, 'includeHub' => false, 'centreID' => ''];
            $qual_res = $client->QUBA_QualificationSearch($qual_req);
            $q_xmlString = $qual_res->QUBA_QualificationSearchResult->any ?? '';
            if ($q_xmlString) {
                $q_xml = new SimpleXMLElement(Quba_API::wrap_soap_envelope('QUBA_QualificationSearch', $q_xmlString));
                $related = $q_xml->xpath('//QubaQualification');
                $related_array = [];
                foreach ($related as $r_qual) {
                    $related_array[] = ['title' => (string)$r_qual->Title, 'code' => (string)$r_qual->QualificationReferenceNumber, 'id' => (string)$r_qual->ID, 'level' => (string)$r_qual->Level, 'credits' => (string)$r_qual->TotalCreditsRequired];
                }
                update_post_meta($post_id, '_related_qualifications', $related_array);
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Prepares and inserts or updates the structured array into the WP DB.
     * @param array $data Formatted metadata properties.
     * @param string $post_type The target custom post type.
     * @return int Post ID generated or updated.
     */
    private static function save_post_data($data, $post_type)
    {
        $meta_id_key = $post_type === 'units' ? ($data['ID_Alpha'] ?? $data['ID']) : $data['ID'];
        $check_id = Quba_Render::get_post_id_by_meta_field('_id', $meta_id_key);

        $post_content = isset($data['QualificationSummary']) ? Quba_Render::santize_html($data['QualificationSummary']) : '';
        if (empty($post_content) && isset($data['Summary'])) $post_content = Quba_Render::santize_html($data['Summary']);

        $meta_input = [];
        foreach ($data as $key => $val) {
            $meta_input['_' . strtolower($key)] = $val;
        }
        $meta_input['_id'] = $meta_id_key;

        $post_data = ['post_type' => $post_type, 'post_title' => $data['Title'], 'post_status' => 'publish', 'post_content' => $post_content, 'meta_input' => $meta_input];

        if ($check_id) {
            $post_data['ID'] = $check_id;
            wp_update_post($post_data);
            return $check_id;
        } else {
            return wp_insert_post($post_data);
        }
    }

    /**
     * Decodes and stores binary stream data to the local server disk.
     * @param string $file_data The raw binary or decoded base64 string.
     * @param string $path_suffix The sub-directory to construct.
     * @param string $filename The constructed file name.
     * @return string|bool URL endpoint to the generated file, or false on failure.
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
     * Bootstraps UI and AJAX bindings to the WP Core.
     */
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);

        add_action('wp_ajax_quba_init_sync', [__CLASS__, 'ajax_init_sync']);
        add_action('wp_ajax_quba_process_batch', [__CLASS__, 'ajax_process_batch']);
    }

    /**
     * Mounts the management view to the WP Toolbar.
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
     * Provisions JS dependencies exclusively on the management screen.
     */
    public static function enqueue_admin_scripts($hook)
    {
        if ($hook !== 'tools_page_quba-sync') return;

        wp_enqueue_script('quba-admin-sync', plugin_dir_url(__FILE__) . 'assets/js/admin-sync.js', ['jquery'], '2.3.0', true);
        wp_localize_script('quba-admin-sync', 'qubaAdminAjax', [
            'nonce' => wp_create_nonce('quba_admin_nonce')
        ]);
    }

    /**
     * Renders the HTML DOM structure for the backend sync interface.
     */
    public static function render_admin_page()
    {
?>
        <div class="wrap">
            <h1>QUBA Manual Synchronization</h1>
            <p>Use this tool to manually trigger a full synchronization of Qualifications and Units from the QUBA SOAP API.</p>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; max-width: 600px; margin-top: 20px;">

                <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #2271b1;">
                    <label style="display: block; font-size: 14px; margin-bottom: 10px;"><strong>Select Data Entity to Synchronize:</strong></label>
                    <label style="display: block; margin-bottom: 8px;"><input type="radio" name="quba_sync_type" value="both" checked> Both (Qualifications & Units)</label>
                    <label style="display: block; margin-bottom: 8px;"><input type="radio" name="quba_sync_type" value="qualifications"> Qualifications Only</label>
                    <label style="display: block;"><input type="radio" name="quba_sync_type" value="units"> Units Only</label>
                </div>

                <button id="quba-start-sync" class="button button-primary button-large">Start Manual Sync</button>

                <div style="margin-top: 20px;">
                    <strong>Status:</strong> <span id="quba-sync-status">Idle. Ready to sync.</span>
                </div>

                <div style="width: 100%; background-color: #f0f0f1; border-radius: 3px; margin-top: 15px; height: 30px; border: 1px solid #c3c4c7; overflow: hidden;">
                    <div id="quba-sync-progress-bar" style="width: 0%; height: 100%; background-color: #2271b1; transition: width 0.3s ease; text-align: center; color: white; line-height: 30px; font-weight: bold;">0%</div>
                </div>
            </div>
        </div>
    <?php
    }

    /**
     * Translates JS initializations into the PHP queue builder method mapping the sync scope.
     */
    public static function ajax_init_sync()
    {
        check_ajax_referer('quba_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        $sync_type = isset($_POST['sync_type']) ? sanitize_text_field($_POST['sync_type']) : 'both';
        $total = Quba_Cron_Sync::build_sync_queue($sync_type);

        if ($total === false) wp_send_json_error('Failed to connect to QUBA API.');
        wp_send_json_success(['total' => $total]);
    }

    /**
     * Directs batched extraction loops recursively requested by the frontend interface.
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
 * Includes a native advanced repeater with duplication, reordering, collapsing, and deletion capabilities via a tabbed interface.
 */
class Quba_Admin_Meta
{
    /**
     * Initializes WP Action hooks for meta box UI injections.
     */
    public static function init()
    {
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_meta_boxes']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('admin_footer', [__CLASS__, 'render_inline_js_css']);
    }

    /**
     * Enqueues media uploading tools and jQuery sortable for drag-and-drop mechanics.
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
     * Registers the unified meta box layout block on the target post types.
     */
    public static function register_meta_boxes()
    {
        add_meta_box('quba_meta_data', 'QUBA Data & Documents', [__CLASS__, 'render_meta_box'], ['qualifications', 'units'], 'normal', 'high');
    }

    /**
     * Renders the tabbed HTML Layout mapping API inputs to read-only constraints and mounting the Document repeater.
     * @param WP_Post $post Current Post Object
     */
    public static function render_meta_box($post)
    {
        wp_nonce_field('quba_meta_nonce_action', 'quba_meta_nonce');

        // Dynamically define required readonly UI mapping based on context.
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
            '_nationalcode' => 'Unit Code',
            '_qcasector' => 'Sector',
            '_level' => 'Level',
            '_credits' => 'Credit Value',
            '_classification2' => 'Risk Rating',
            '_classification3' => 'Unit Type',
            '_recognitiondate' => 'Start Date',
            '_reviewdate' => 'Review Date',
            '_expirydate' => 'End Date',
            '_glh' => 'Guided Learning Hours (GLH)',
            '_unit_listing_url' => 'Unit Listing PDF URL',
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
     * Validates Nonce context and serializes updated repeatable properties to the WP DB.
     * @param int $post_id Contextual Target Post.
     */
    public static function save_meta_boxes($post_id)
    {
        if (!isset($_POST['quba_meta_nonce']) || !wp_verify_nonce($_POST['quba_meta_nonce'], 'quba_meta_nonce_action')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Note: API fields are ignored here to enforce the read-only directive.
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
            update_post_meta($post_id, 'additional_documents', array_values($docs)); // Reset indices sequentially
        } else {
            delete_post_meta($post_id, 'additional_documents');
        }
    }

    /**
     * Mounts specialized localized DOM scripting to orchestrate tabs and advanced repeater UI requirements.
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
                // Tab Logic
                $('.quba-tab-nav a').on('click', function(e) {
                    e.preventDefault();
                    $('.quba-tab-nav li').removeClass('active');
                    $(this).parent().addClass('active');
                    $('.quba-tab-content').removeClass('active');
                    $($(this).attr('href')).addClass('active');
                });

                // Repeater State Orchestration
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

    public static function unit_grid($post_id, $post_type = 'units')
    {
        return self::qual_grid($post_id, $post_type);
    }

    public static function generate_error_output($msg)
    {
        return '<div class="error-message"><p>An error occurred: ' . esc_html($msg) . '</p></div>';
    }

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

    public static function enqueue_assets()
    {
        if (
            is_post_type_archive('qualifications') || is_post_type_archive('units') ||
            is_singular('qualifications') || is_singular('units') || is_tax('qualifications_cat')
        ) {
            wp_enqueue_style('quba-main-css', plugin_dir_url(__FILE__) . 'assets/css/main.css', [], '2.3.0', 'all');
            wp_enqueue_script('quba-main-js', plugin_dir_url(__FILE__) . 'assets/js/main.js', ['jquery'], '2.3.0', true);
            wp_localize_script('quba-main-js', 'qubaAjaxObj', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('quba_ajax_nonce')
            ]);
        }
    }

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

    public static function archive_ajax_qualifications()
    {
        $args = [
            'post_type'      => 'qualifications',
            'posts_per_page' => -1,
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
            echo '<div class="search-results-summary mb-4"><div class="results-count-display">';
            echo '<span class="results-number">' . number_format($query->found_posts) . '</span>';
            echo '<span class="results-text"> Qualification' . ($query->found_posts !== 1 ? 's' : '') . ' Found</span></div></div>';
            echo '<div class="row row-results g-5">';
            while ($query->have_posts()) {
                $query->the_post();
                echo Quba_Render::qual_grid(get_the_ID(), 'qualifications');
            }
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<div class="no-results-message"><p>No qualifications found matching your criteria.</p></div>';
        }
        wp_die();
    }

    public static function archive_ajax_units()
    {
        $args = [
            'post_type'      => 'units',
            'posts_per_page' => -1,
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

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            echo '<div class="search-results-summary mb-4"><div class="results-count-display">';
            echo '<span class="results-number">' . number_format($query->found_posts) . '</span>';
            echo '<span class="results-text"> Unit' . ($query->found_posts !== 1 ? 's' : '') . ' Found</span></div></div>';
            echo '<div class="row row-results g-5">';
            while ($query->have_posts()) {
                $query->the_post();
                echo Quba_Render::unit_grid(get_the_ID(), 'units');
            }
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<div class="no-results-message"><p>No units found matching your criteria.</p></div>';
        }
        wp_die();
    }

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
Quba_Cron_Sync::init();
Quba_Admin::init();
Quba_Admin_Meta::init();
register_activation_hook(__FILE__, ['Quba_Cron_Sync', 'activate']);
register_deactivation_hook(__FILE__, ['Quba_Cron_Sync', 'deactivate']);
add_action('plugins_loaded', ['Quba_Controllers', 'init']);
