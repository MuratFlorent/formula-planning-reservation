<?php
namespace FPR\Modules;

if (!defined('ABSPATH')) exit;

/**
 * Class InvoiceHandler
 * Handles invoice generation, storage, and retrieval
 */
class InvoiceHandler {
    /**
     * Initialize the module
     */
    public static function init() {
        // Generate invoice when subscription is created
        add_action('fpr_subscription_created', [self::class, 'generate_invoice_for_subscription'], 10, 2);

        // Generate invoice for installment payments
        add_action('fpr_installment_payment_processed', [self::class, 'generate_invoice_for_installment'], 10, 2);

        // Add invoice to order emails
        add_filter('woocommerce_email_attachments', [self::class, 'attach_invoice_to_email'], 10, 3);

        // AJAX handlers for admin
        add_action('wp_ajax_fpr_get_user_invoices', [self::class, 'ajax_get_user_invoices']);
        add_action('wp_ajax_fpr_download_invoice', [self::class, 'ajax_download_invoice']);

        // AJAX handlers for frontend
        add_action('wp_ajax_generate_invoice', [self::class, 'ajax_generate_invoice']);
        add_action('wp_ajax_nopriv_generate_invoice', [self::class, 'ajax_generate_invoice']);

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);

        // Add invoice button to user account
        add_action('woocommerce_order_details_after_order_table', [self::class, 'display_invoice_button'], 10, 1);

        // Add demo invoice button to user account
        add_action('woocommerce_account_dashboard', [self::class, 'display_demo_invoice_button']);
    }

    /**
     * Enqueue scripts and styles
     */
    public static function enqueue_assets() {
        wp_enqueue_script('fpr-invoice', FPR_PLUGIN_URL . 'assets/js/invoice.js', ['jquery'], FPR_VERSION, true);
        wp_localize_script('fpr-invoice', 'fpr_invoice', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fpr_invoice_nonce')
        ]);

        wp_enqueue_style('fpr-invoice', FPR_PLUGIN_URL . 'assets/css/invoice.css', [], FPR_VERSION);
    }

    /**
     * Generate invoice for a new subscription
     * 
     * @param int $subscription_id Subscription ID
     * @param array $subscription_data Subscription data
     */
    public static function generate_invoice_for_subscription($subscription_id, $subscription_data) {
        global $wpdb;

        \FPR\Helpers\Logger::log("[InvoiceHandler] 🔍 Génération de facture pour l'abonnement #$subscription_id");

        // Get subscription details
        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_customer_subscriptions WHERE id = %d",
                $subscription_id
            )
        );

        if (!$subscription) {
            \FPR\Helpers\Logger::log("[InvoiceHandler] ❌ Abonnement #$subscription_id introuvable");
            return;
        }

        // Generate invoice number
        $invoice_number = self::generate_invoice_number($subscription->user_id);

        // Set invoice date and due date
        $invoice_date = current_time('mysql');
        $due_date = current_time('mysql'); // Due immediately for first payment

        // Create invoice record
        $invoice_data = [
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription_id,
            'order_id' => $subscription->order_id,
            'invoice_number' => $invoice_number,
            'invoice_date' => $invoice_date,
            'due_date' => $due_date,
            'amount' => $subscription->installment_amount,
            'status' => 'paid'
        ];

        $result = $wpdb->insert(
            $wpdb->prefix . 'fpr_invoices',
            $invoice_data
        );

        if ($result === false) {
            \FPR\Helpers\Logger::log("[InvoiceHandler] ❌ Erreur lors de la création de la facture: " . $wpdb->last_error);
            return;
        }

        $invoice_id = $wpdb->insert_id;
        \FPR\Helpers\Logger::log("[InvoiceHandler] ✅ Facture #$invoice_id créée pour l'abonnement #$subscription_id");

        // Generate PDF invoice
        $pdf_path = self::generate_pdf_invoice($invoice_id);

        if ($pdf_path) {
            // Update invoice record with PDF path
            $wpdb->update(
                $wpdb->prefix . 'fpr_invoices',
                ['pdf_path' => $pdf_path],
                ['id' => $invoice_id]
            );

            \FPR\Helpers\Logger::log("[InvoiceHandler] ✅ PDF de facture généré: $pdf_path");
        }

        return $invoice_id;
    }

    /**
     * Generate invoice for an installment payment
     * 
     * @param int $subscription_id Subscription ID
     * @param int $installment_number Installment number
     */
    public static function generate_invoice_for_installment($subscription_id, $installment_number) {
        global $wpdb;

        \FPR\Helpers\Logger::log("[InvoiceHandler] 🔍 Génération de facture pour le versement #$installment_number de l'abonnement #$subscription_id");

        // Get subscription details
        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_customer_subscriptions WHERE id = %d",
                $subscription_id
            )
        );

        if (!$subscription) {
            \FPR\Helpers\Logger::log("[InvoiceHandler] ❌ Abonnement #$subscription_id introuvable");
            return;
        }

        // Generate invoice number
        $invoice_number = self::generate_invoice_number($subscription->user_id, $installment_number);

        // Set invoice date and due date
        $invoice_date = current_time('mysql');
        $due_date = current_time('mysql'); // Due immediately for installment payments

        // Create invoice record
        $invoice_data = [
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription_id,
            'order_id' => $subscription->order_id,
            'invoice_number' => $invoice_number,
            'invoice_date' => $invoice_date,
            'due_date' => $due_date,
            'amount' => $subscription->installment_amount,
            'status' => 'paid'
        ];

        $result = $wpdb->insert(
            $wpdb->prefix . 'fpr_invoices',
            $invoice_data
        );

        if ($result === false) {
            \FPR\Helpers\Logger::log("[InvoiceHandler] ❌ Erreur lors de la création de la facture: " . $wpdb->last_error);
            return;
        }

        $invoice_id = $wpdb->insert_id;
        \FPR\Helpers\Logger::log("[InvoiceHandler] ✅ Facture #$invoice_id créée pour le versement #$installment_number de l'abonnement #$subscription_id");

        // Generate PDF invoice
        $pdf_path = self::generate_pdf_invoice($invoice_id);

        if ($pdf_path) {
            // Update invoice record with PDF path
            $wpdb->update(
                $wpdb->prefix . 'fpr_invoices',
                ['pdf_path' => $pdf_path],
                ['id' => $invoice_id]
            );

            \FPR\Helpers\Logger::log("[InvoiceHandler] ✅ PDF de facture généré: $pdf_path");
        }

        return $invoice_id;
    }

    /**
     * Generate a unique invoice number
     * 
     * @param int $user_id User ID
     * @param int $installment_number Optional installment number
     * @return string Invoice number
     */
    private static function generate_invoice_number($user_id, $installment_number = 1) {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        // Get user details
        $user = get_userdata($user_id);
        $user_initials = '';

        if ($user) {
            // Get first letter of first name and last name
            $first_name = $user->first_name;
            $last_name = $user->last_name;

            if (!empty($first_name) && !empty($last_name)) {
                $user_initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
            }
        }

        // Generate random suffix
        $random = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4);

        // Combine all parts
        $invoice_number = $prefix . '-' . $year . $month . $day . '-' . $user_initials . $random;

        if ($installment_number > 1) {
            $invoice_number .= '-' . $installment_number;
        }

        return $invoice_number;
    }

    /**
     * Generate PDF invoice
     * 
     * @param int $invoice_id Invoice ID
     * @return string|false Path to PDF file or false on failure
     */
    private static function generate_pdf_invoice($invoice_id) {
        global $wpdb;

        // Get invoice details
        $invoice = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT i.*, u.display_name, u.user_email, cs.payment_plan_id, cs.saison_id, cs.total_amount, cs.installments_paid
                FROM {$wpdb->prefix}fpr_invoices i
                JOIN {$wpdb->users} u ON i.user_id = u.ID
                JOIN {$wpdb->prefix}fpr_customer_subscriptions cs ON i.subscription_id = cs.id
                WHERE i.id = %d",
                $invoice_id
            )
        );

        if (!$invoice) {
            \FPR\Helpers\Logger::log("[InvoiceHandler] ❌ Facture #$invoice_id introuvable");
            return false;
        }

        // Get customer details
        $customer = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_customers WHERE user_id = %d",
                $invoice->user_id
            )
        );

        // Get order details
        $order = wc_get_order($invoice->order_id);

        if (!$order) {
            \FPR\Helpers\Logger::log("[InvoiceHandler] ⚠️ Commande #{$invoice->order_id} introuvable");
        }

        // Get payment plan details
        $payment_plan = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
                $invoice->payment_plan_id
            )
        );

        // Get saison details
        $saison = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_saisons WHERE id = %d",
                $invoice->saison_id
            )
        );

        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $invoice_dir = $upload_dir['basedir'] . '/fpr-invoices/' . date('Y/m');

        if (!file_exists($invoice_dir)) {
            wp_mkdir_p($invoice_dir);
        }

        // Generate PDF filename
        $filename = 'facture-' . $invoice->invoice_number . '.pdf';
        $pdf_path = $invoice_dir . '/' . $filename;
        $pdf_url = $upload_dir['baseurl'] . '/fpr-invoices/' . date('Y/m') . '/' . $filename;

        // Check if we have TCPDF or similar library available
        if (!class_exists('TCPDF') && !class_exists('FPDF')) {
            \FPR\Helpers\Logger::log("[InvoiceHandler] ⚠️ Aucune bibliothèque PDF trouvée. Utilisation du template HTML.");

            // If no PDF library is available, generate HTML invoice and save it
            $html_content = self::generate_html_invoice($invoice, $customer, $order, $payment_plan, $saison);

            // Save HTML to file
            $html_filename = 'facture-' . $invoice->invoice_number . '.html';
            $html_path = $invoice_dir . '/' . $html_filename;

            if (file_put_contents($html_path, $html_content)) {
                \FPR\Helpers\Logger::log("[InvoiceHandler] ✅ Facture HTML générée: $html_path");
                return $html_path;
            } else {
                \FPR\Helpers\Logger::log("[InvoiceHandler] ❌ Erreur lors de la génération de la facture HTML");
                return false;
            }
        }

        // If TCPDF is available, use it to generate PDF
        if (class_exists('TCPDF')) {
            // TCPDF implementation would go here
            // For now, we'll just return the path where the PDF would be saved
            return $pdf_path;
        }

        // If FPDF is available, use it as fallback
        if (class_exists('FPDF')) {
            // FPDF implementation would go here
            // For now, we'll just return the path where the PDF would be saved
            return $pdf_path;
        }

        return false;
    }

    /**
     * Generate HTML invoice
     * 
     * @param object $invoice Invoice object
     * @param object $customer Customer object
     * @param WC_Order $order Order object
     * @param object $payment_plan Payment plan object
     * @param object $saison Saison object
     * @return string HTML content
     */
    private static function generate_html_invoice($invoice, $customer, $order, $payment_plan, $saison) {
        // Get company information from settings
        $company_name = get_option('fpr_company_name', get_bloginfo('name'));
        $company_address = get_option('fpr_company_address', '');
        $company_phone = get_option('fpr_company_phone', '');
        $company_email = get_option('fpr_company_email', get_bloginfo('admin_email'));
        $company_logo = get_option('fpr_company_logo', '');

        // Get customer billing information
        $billing_first_name = $customer ? $customer->firstName : '';
        $billing_last_name = $customer ? $customer->lastName : '';
        $billing_email = $customer ? $customer->email : '';
        $billing_phone = $customer ? $customer->phone : '';

        // Get billing address from order if available
        $billing_address = '';
        $billing_city = '';
        $billing_postcode = '';
        $billing_country = '';

        if ($order) {
            $billing_address = $order->get_billing_address_1();
            $billing_city = $order->get_billing_city();
            $billing_postcode = $order->get_billing_postcode();
            $billing_country = $order->get_billing_country();
        }

        // Start building HTML
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Facture ' . esc_html($invoice->invoice_number) . '</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    color: #333;
                }
                .invoice-header {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 40px;
                }
                .company-info {
                    width: 50%;
                }
                .invoice-info {
                    width: 50%;
                    text-align: right;
                }
                .customer-info {
                    margin-bottom: 40px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                th, td {
                    padding: 10px;
                    border-bottom: 1px solid #ddd;
                    text-align: left;
                }
                th {
                    background-color: #f2f2f2;
                }
                .total-row {
                    font-weight: bold;
                }
                .footer {
                    margin-top: 40px;
                    text-align: center;
                    font-size: 12px;
                    color: #777;
                }
            </style>
        </head>
        <body>
            <div class="invoice-header">
                <div class="company-info">
                    <h2>' . esc_html($company_name) . '</h2>
                    <p>' . nl2br(esc_html($company_address)) . '</p>
                    <p>Téléphone: ' . esc_html($company_phone) . '</p>
                    <p>Email: ' . esc_html($company_email) . '</p>
                </div>
                <div class="invoice-info">
                    <h1>FACTURE</h1>
                    <p>Numéro: ' . esc_html($invoice->invoice_number) . '</p>
                    <p>Date: ' . date('d/m/Y', strtotime($invoice->invoice_date)) . '</p>
                    <p>Échéance: ' . date('d/m/Y', strtotime($invoice->due_date)) . '</p>
                    <p>Statut: ' . esc_html(ucfirst($invoice->status)) . '</p>
                </div>
            </div>

            <div class="customer-info">
                <h3>Facturé à:</h3>
                <p>' . esc_html($billing_first_name . ' ' . $billing_last_name) . '</p>
                <p>' . esc_html($billing_address) . '</p>
                <p>' . esc_html($billing_postcode . ' ' . $billing_city) . '</p>
                <p>' . esc_html($billing_country) . '</p>
                <p>Email: ' . esc_html($billing_email) . '</p>
                <p>Téléphone: ' . esc_html($billing_phone) . '</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Période</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>';

        // Add subscription details
        $description = 'Abonnement';
        if ($payment_plan) {
            $description .= ' - ' . $payment_plan->name;
        }
        if ($saison) {
            $description .= ' - ' . $saison->name;
        }

        $period = '';
        if ($saison) {
            $period = date('d/m/Y', strtotime($saison->start_date)) . ' - ' . date('d/m/Y', strtotime($saison->end_date));
        }

        $html .= '<tr>
                    <td>' . esc_html($description) . '</td>
                    <td>' . esc_html($period) . '</td>
                    <td>' . esc_html(number_format($invoice->amount, 2, ',', ' ') . ' €') . '</td>
                </tr>';

        // Add payment details
        $html .= '<tr>
                    <td colspan="2" class="total-row">Total</td>
                    <td class="total-row">' . esc_html(number_format($invoice->amount, 2, ',', ' ') . ' €') . '</td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <p>Merci pour votre confiance!</p>
            <p>' . esc_html($company_name) . ' - ' . esc_html($company_address) . '</p>
        </div>
        </body>
        </html>';

        return $html;
    }

    /**
     * Attach invoice to WooCommerce emails
     * 
     * @param array $attachments Existing attachments
     * @param string $email_id Email ID
     * @param WC_Order $order Order object
     * @return array Modified attachments
     */
    public static function attach_invoice_to_email($attachments, $email_id, $order) {
        // Only attach to specific email types
        $email_types = ['customer_completed_order', 'customer_invoice', 'customer_processing_order'];

        if (!in_array($email_id, $email_types) || !is_a($order, 'WC_Order')) {
            return $attachments;
        }

        $order_id = $order->get_id();
        $user_id = $order->get_user_id();

        if (!$user_id) {
            return $attachments;
        }

        global $wpdb;

        // Get invoices for this order
        $invoices = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_invoices WHERE order_id = %d AND user_id = %d",
                $order_id, $user_id
            )
        );

        if (!$invoices) {
            return $attachments;
        }

        foreach ($invoices as $invoice) {
            if (!empty($invoice->pdf_path) && file_exists($invoice->pdf_path)) {
                $attachments[] = $invoice->pdf_path;
                \FPR\Helpers\Logger::log("[InvoiceHandler] ✅ Facture #{$invoice->id} jointe à l'email $email_id pour la commande #$order_id");
            }
        }

        return $attachments;
    }

    /**
     * AJAX handler to get user invoices
     */
    public static function ajax_get_user_invoices() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fpr_admin_nonce')) {
            wp_send_json_error(['message' => 'Nonce verification failed']);
            return;
        }

        // Check user ID
        if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
            wp_send_json_error(['message' => 'User ID is required']);
            return;
        }

        $user_id = intval($_POST['user_id']);

        global $wpdb;

        // Get invoices for this user
        $invoices = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT i.*, cs.payment_plan_id, cs.saison_id, pp.name as payment_plan_name, s.name as saison_name
                FROM {$wpdb->prefix}fpr_invoices i
                LEFT JOIN {$wpdb->prefix}fpr_customer_subscriptions cs ON i.subscription_id = cs.id
                LEFT JOIN {$wpdb->prefix}fpr_payment_plans pp ON cs.payment_plan_id = pp.id
                LEFT JOIN {$wpdb->prefix}fpr_saisons s ON cs.saison_id = s.id
                WHERE i.user_id = %d
                ORDER BY i.invoice_date DESC",
                $user_id
            )
        );

        $formatted_invoices = [];

        foreach ($invoices as $invoice) {
            $invoice_url = admin_url('admin-ajax.php?action=fpr_download_invoice&invoice_id=' . $invoice->id . '&nonce=' . wp_create_nonce('fpr_download_invoice'));

            $formatted_invoices[] = [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => date('d/m/Y', strtotime($invoice->invoice_date)),
                'amount' => number_format($invoice->amount, 2, ',', ' ') . ' €',
                'status' => ucfirst($invoice->status),
                'payment_plan' => $invoice->payment_plan_name ?: 'N/A',
                'saison' => $invoice->saison_name ?: 'N/A',
                'download_url' => $invoice_url
            ];
        }

        wp_send_json_success(['invoices' => $formatted_invoices]);
    }

    /**
     * AJAX handler to download invoice
     */
    public static function ajax_download_invoice() {
        // Check nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'fpr_download_invoice')) {
            wp_die('Nonce verification failed');
            return;
        }

        // Check invoice ID
        if (!isset($_GET['invoice_id']) || empty($_GET['invoice_id'])) {
            wp_die('Invoice ID is required');
            return;
        }

        $invoice_id = intval($_GET['invoice_id']);

        global $wpdb;

        // Get invoice details
        $invoice = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_invoices WHERE id = %d",
                $invoice_id
            )
        );

        if (!$invoice) {
            wp_die('Invoice not found');
            return;
        }

        // Check if PDF exists
        if (empty($invoice->pdf_path) || !file_exists($invoice->pdf_path)) {
            // If PDF doesn't exist, generate it
            $pdf_path = self::generate_pdf_invoice($invoice_id);

            if (!$pdf_path || !file_exists($pdf_path)) {
                wp_die('Failed to generate invoice PDF');
                return;
            }

            // Update invoice record with PDF path
            $wpdb->update(
                $wpdb->prefix . 'fpr_invoices',
                ['pdf_path' => $pdf_path],
                ['id' => $invoice_id]
            );

            $invoice->pdf_path = $pdf_path;
        }

        // Determine file type
        $file_extension = pathinfo($invoice->pdf_path, PATHINFO_EXTENSION);
        $content_type = 'application/pdf';

        if ($file_extension === 'html') {
            $content_type = 'text/html';
        }

        // Set headers for download
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="facture-' . $invoice->invoice_number . '.' . $file_extension . '"');
        header('Content-Length: ' . filesize($invoice->pdf_path));

        // Output file
        readfile($invoice->pdf_path);
        exit;
    }

    /**
     * Generate invoice via AJAX
     */
    public static function ajax_generate_invoice() {
        check_ajax_referer('fpr_invoice_nonce', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (empty($order_id)) {
            // For demo purposes, generate a demo invoice
            self::generate_demo_invoice();
        } else {
            // Generate invoice for a specific order
            self::generate_invoice_for_order($order_id);
        }

        wp_die();
    }

    /**
     * Generate a demo invoice
     */
    public static function generate_demo_invoice() {
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

        self::display_invoice($data);
    }

    /**
     * Generate invoice for a specific order
     *
     * @param int $order_id Order ID
     */
    public static function generate_invoice_for_order($order_id) {
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

        self::display_invoice($data);
    }

    /**
     * Display the invoice
     *
     * @param array $data Invoice data
     */
    public static function display_invoice($data) {
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
     * @param WC_Order $order Order object
     */
    public static function display_invoice_button($order) {
        echo '<button class="button generate-invoice" data-order-id="' . esc_attr($order->get_id()) . '">' . esc_html__('Générer la facture', 'formula-planning-reservation') . '</button>';
    }

    /**
     * Display demo invoice button
     */
    public static function display_demo_invoice_button() {
        echo '<div class="demo-invoice-section">';
        echo '<h3>' . esc_html__('Facture de démonstration', 'formula-planning-reservation') . '</h3>';
        echo '<p>' . esc_html__('Cliquez sur le bouton ci-dessous pour générer une facture de démonstration.', 'formula-planning-reservation') . '</p>';
        echo '<button class="button generate-invoice" data-order-id="0">' . esc_html__('Générer une facture de démo', 'formula-planning-reservation') . '</button>';
        echo '</div>';
    }
}
