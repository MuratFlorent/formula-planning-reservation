<?php
/**
 * Invoice Generator Class
 *
 * This class handles the generation and display of invoices.
 *
 * @package Formula_Planning_Reservation
 * @subpackage Modules
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Invoice Generator Class
 */
class FPR_Invoice_Generator {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_endpoints'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_generate_invoice', array($this, 'ajax_generate_invoice'));
        add_action('wp_ajax_nopriv_generate_invoice', array($this, 'ajax_generate_invoice'));
    }

    /**
     * Register custom endpoints
     */
    public function register_endpoints() {
        add_rewrite_endpoint('invoice', EP_PAGES);
        
        // Flush rewrite rules only once
        if (get_option('fpr_invoice_endpoint_flushed') != 'yes') {
            flush_rewrite_rules();
            update_option('fpr_invoice_endpoint_flushed', 'yes');
        }
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script('fpr-invoice', FPR_PLUGIN_URL . 'assets/js/invoice.js', array('jquery'), FPR_VERSION, true);
        wp_localize_script('fpr-invoice', 'fpr_invoice', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fpr_invoice_nonce')
        ));
    }

    /**
     * Generate invoice via AJAX
     */
    public function ajax_generate_invoice() {
        check_ajax_referer('fpr_invoice_nonce', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (empty($order_id)) {
            // For demo purposes, generate a demo invoice
            $this->generate_demo_invoice();
        } else {
            // Generate invoice for a specific order
            $this->generate_invoice_for_order($order_id);
        }
        
        wp_die();
    }

    /**
     * Generate a demo invoice
     */
    public function generate_demo_invoice() {
        // Demo data
        $data = array(
            'firstName' => 'Murat',
            'lastName' => '',
            'order_number' => '1313',
            'order_date' => '29 mai 2025',
            'formula' => '4 cours/sem',
            'price' => 88.00,
            'payment_method' => 'Carte de crédit/débit',
            'events' => array(
                'Jazz Enfant 1 | 17:30 - 18:30 | 1h | avec Vanessa',
                'Modern\'Jazz Enfant 3 | 17:30 - 18:30 | 1h | avec Vanessa',
                'Modern Jazz Ado 3 (16 / 18 ans) | 18:30 - 19:30 | 1h | avec Vanessa',
                'Pilates | 18:30 - 19:30 | 1h | avec Vanessa'
            )
        );
        
        $this->display_invoice($data);
    }

    /**
     * Generate invoice for a specific order
     *
     * @param int $order_id Order ID
     */
    public function generate_invoice_for_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
            return;
        }
        
        $courses = array();
        foreach ($order->get_items() as $item) {
            foreach ($item->get_meta_data() as $meta) {
                if (strpos($meta->key, 'Cours') !== false) {
                    $courses[] = $meta->value;
                }
            }
        }
        
        // Get the first product name as the formula
        $formula = '';
        foreach ($order->get_items() as $item) {
            $formula = $item->get_name();
            break;
        }
        
        $data = array(
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'order_number' => $order->get_order_number(),
            'order_date' => date_i18n('j F Y', strtotime($order->get_date_created())),
            'formula' => $formula,
            'price' => $order->get_total(),
            'payment_method' => $order->get_payment_method_title(),
            'events' => $courses
        );
        
        $this->display_invoice($data);
    }

    /**
     * Display the invoice
     *
     * @param array $data Invoice data
     */
    public function display_invoice($data) {
        ob_start();
        include FPR_PLUGIN_DIR . 'templates/emails/invoice-template.php';
        $invoice_html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $invoice_html
        ));
    }

    /**
     * Display invoice button in user account
     *
     * @param int $order_id Order ID
     */
    public static function display_invoice_button($order_id) {
        echo '<button class="button generate-invoice" data-order-id="' . esc_attr($order_id) . '">' . esc_html__('Générer la facture', 'formula-planning-reservation') . '</button>';
    }

    /**
     * Display demo invoice button
     */
    public static function display_demo_invoice_button() {
        echo '<button class="button generate-invoice" data-order-id="0">' . esc_html__('Générer une facture de démo', 'formula-planning-reservation') . '</button>';
    }
}

// Initialize the class
new FPR_Invoice_Generator();