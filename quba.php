<?php

/**
 * Plugin Name: Quba System Integration
 * Description: Integrates QUBA SOAP API, synchronizes units/qualifications via batched processes, and provides custom templates.
 * Version: 2.2.0
 * Author: Digitally Disruptive - Donald Raymundo
 * Author URI: https://digitallydisruptive.co.uk/
 * Text Domain: quba-integration
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class Quba_API
 * Handles SOAP client connections and data retrieval.
 */
class Quba_API
{
    private static $soap_client = null;

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
    public static function init() {
        // Daily trigger to build the queue
        add_action('quba_daily_sync_build_queue', [__CLASS__, 'build_sync_queue']);
        // Frequent trigger to process the queue in small chunks
        add_action('quba_process_sync_queue', [__CLASS__, 'process_batch_cron']);
    }

    public static function activate() {
        if (!wp_next_scheduled('quba_daily_sync_build_queue')) {
            wp_schedule_event(time(), 'daily', 'quba_daily_sync_build_queue');
        }
        if (!wp_next_scheduled('quba_process_sync_queue')) {
            wp_schedule_event(time(), 'hourly', 'quba_process_sync_queue');
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('quba_daily_sync_build_queue');
        wp_clear_scheduled_hook('quba_process_sync_queue');
    }

    /**
     * Extracts full API lists and stores them in a transient queue to prevent timeouts.
     */
    public static function build_sync_queue() {
        $client = Quba_API::get_client();
        if (!$client) return false;

        $queue = [];

        // 1. Fetch Qualifications
        try {
            $req = ['qualificationID' => 0, 'qualificationTitle' => '', 'qualificationLevel' => '', 'qualificationNumber' => '', 'qcaSector' => '', 'provisionType' => '', 'unitID' => '', 'includeHub' => false, 'centreID' => ''];
            $res = $client->QUBA_QualificationSearch($req);
            $xmlString = $res->QUBA_QualificationSearchResult->any ?? '';
            if ($xmlString) {
                $xml = new SimpleXMLElement(Quba_API::wrap_soap_envelope('QUBA_QualificationSearch', $xmlString));
                $quals = $xml->xpath('//QubaQualification');
                foreach ($quals as $qual) {
                    $data = [];
                    foreach ($qual->children() as $child) {
                        if ($child->getName() != 'Classifications') $data[$child->getName()] = trim((string) $child);
                    }
                    if (isset($qual->Classifications->Classification1)) $data['Classification1'] = trim((string) $qual->Classifications->Classification1);
                    if (isset($qual->Classifications->Classification2)) $data['Classification2'] = trim((string) $qual->Classifications->Classification2);
                    
                    $queue[] = ['type' => 'qualifications', 'data' => $data];
                }
            }
        } catch (Exception $e) { error_log('Qual Queue Error: ' . $e->getMessage()); }

        // 2. Fetch Units
        try {
            $req = ['unitID' => 0, 'unitIdAlpha' => '', 'unitTitle' => '%', 'allOrPartTitle' => true, 'unitLevel' => '', 'unitCredits' => 0, 'qcaSector' => '', 'learnDirectCode' => '', 'qcaCode' => '', 'unitType' => '', 'provisionType' => '', 'includeHub' => true, 'moduleID' => 0, 'alternativeUnitCode' => ''];
            $res = $client->QUBA_UnitSearch($req);
            $xmlString = $res->QUBA_UnitSearchResult->any ?? '';
            if ($xmlString) {
                $xml = new SimpleXMLElement(Quba_API::wrap_soap_envelope('QUBA_UnitSearch', $xmlString));
                $units = $xml->xpath('//QubaUnit');
                foreach ($units as $unit) {
                    $data = [];
                    foreach ($unit->children() as $child) {
                        $data[$child->getName()] = trim((string) $child);
                    }
                    $queue[] = ['type' => 'units', 'data' => $data];
                }
            }
        } catch (Exception $e) { error_log('Unit Queue Error: ' . $e->getMessage()); }

        update_option('quba_sync_queue', $queue, false);
        return count($queue);
    }

    /**
     * Processes chunks of the queue. Can be fired by cron or AJAX.
     */
    public static function process_batch($batch_size = 5) {
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

    public static function process_batch_cron() {
        self::process_batch(20); // Process 20 items per hourly cron run
    }

    private static function process_single_qualification($client, $data) {
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
        } catch (Exception $e) {}

        try {
            $res_guide = $client->QUBA_GetQualificationGuide($req_doc);
            $pdf_data = $res_guide->QUBA_GetQualificationGuideResult ?? '';
            if ($pdf_data) {
                $url = self::store_document($pdf_data, 'qualifications/qualification-guide', 'QualificationGuide_' . $data['ID']);
                if ($url) update_post_meta($post_id, '_qualification_guide_url', $url);
            }
        } catch (Exception $e) {}
    }

    private static function process_single_unit($client, $data) {
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
        } catch (Exception $e) {}

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
        } catch (Exception $e) {}
    }

    private static function save_post_data($data, $post_type) {
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

    private static function store_document($file_data, $path_suffix, $filename) {
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
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
        
        // AJAX Endpoints for Manual Sync
        add_action('wp_ajax_quba_init_sync', [__CLASS__, 'ajax_init_sync']);
        add_action('wp_ajax_quba_process_batch', [__CLASS__, 'ajax_process_batch']);
    }

    public static function register_menu() {
        add_submenu_page(
            'tools.php',
            'QUBA Data Sync',
            'QUBA Sync',
            'manage_options',
            'quba-sync',
            [__CLASS__, 'render_admin_page']
        );
    }

    public static function enqueue_admin_scripts($hook) {
        if ($hook !== 'tools_page_quba-sync') return;
        
        wp_enqueue_script('quba-admin-sync', plugin_dir_url(__FILE__) . 'assets/js/admin-sync.js', ['jquery'], '2.2.0', true);
        wp_localize_script('quba-admin-sync', 'qubaAdminAjax', [
            'nonce' => wp_create_nonce('quba_admin_nonce')
        ]);
    }

    public static function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>QUBA Manual Synchronization</h1>
            <p>Use this tool to manually trigger a full synchronization of Qualifications and Units from the QUBA SOAP API.</p>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; max-width: 600px; margin-top: 20px;">
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

    public static function ajax_init_sync() {
        check_ajax_referer('quba_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        // Trigger queue build
        $total = Quba_Cron_Sync::build_sync_queue();
        
        if ($total === false) wp_send_json_error('Failed to connect to QUBA API.');
        wp_send_json_success(['total' => $total]);
    }

    public static function ajax_process_batch() {
        check_ajax_referer('quba_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

        // Process chunk (5 items at a time for safety)
        $remaining = Quba_Cron_Sync::process_batch(5);
        wp_send_json_success(['remaining' => $remaining]);
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
            wp_enqueue_style('quba-main-css', plugin_dir_url(__FILE__) . 'assets/css/main.css', [], '2.2.0', 'all');
            wp_enqueue_script('quba-main-js', plugin_dir_url(__FILE__) . 'assets/js/main.js', ['jquery'], '2.2.0', true);
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
register_activation_hook(__FILE__, ['Quba_Cron_Sync', 'activate']);
register_deactivation_hook(__FILE__, ['Quba_Cron_Sync', 'deactivate']);
add_action('plugins_loaded', ['Quba_Controllers', 'init']);