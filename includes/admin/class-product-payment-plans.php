<?php
namespace FPR\Admin;

if (!defined('ABSPATH')) exit;

/**
 * Class ProductPaymentPlans
 * 
 * Handles the integration of payment plans with WooCommerce products
 * Allows setting different prices for each payment plan
 */
class ProductPaymentPlans {
    /**
     * Initialize the class
     */
    public static function init() {
        // Add meta box to product edit page
        add_action('add_meta_boxes', [__CLASS__, 'add_payment_plans_meta_box']);

        // Save product meta
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_payment_plans_meta']);

        // Filter product price based on selected payment plan
        add_filter('woocommerce_product_get_price', [__CLASS__, 'filter_product_price'], 10, 2);
        add_filter('woocommerce_product_get_regular_price', [__CLASS__, 'filter_product_price'], 10, 2);

        // Add AJAX handler for updating prices on cart page
        add_action('wp_ajax_fpr_update_product_prices', [__CLASS__, 'ajax_update_product_prices']);
        add_action('wp_ajax_nopriv_fpr_update_product_prices', [__CLASS__, 'ajax_update_product_prices']);

        // Enqueue scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_scripts']);
    }

    /**
     * Add meta box to product edit page
     */
    public static function add_payment_plans_meta_box() {
        add_meta_box(
            'fpr-payment-plans-prices',
            __('Prix par plan de paiement', 'formula-planning-reservation'),
            [__CLASS__, 'render_payment_plans_meta_box'],
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Render the payment plans meta box
     * 
     * @param WP_Post $post The post object
     */
    public static function render_payment_plans_meta_box($post) {
        // Get all active payment plans
        $payment_plans = self::get_active_payment_plans();

        // Get saved prices for this product
        $saved_prices = get_post_meta($post->ID, '_fpr_payment_plan_prices', true);
        if (!is_array($saved_prices)) {
            $saved_prices = [];
        }

        // Add nonce for security
        wp_nonce_field('fpr_save_payment_plan_prices', 'fpr_payment_plan_prices_nonce');

        echo '<div class="fpr-payment-plans-prices-wrapper">';
        echo '<p>' . __('Définissez des prix spécifiques pour chaque plan de paiement. Si aucun prix n\'est défini pour un plan, le prix standard du produit sera utilisé.', 'formula-planning-reservation') . '</p>';

        if (empty($payment_plans)) {
            echo '<p>' . __('Aucun plan de paiement actif n\'a été trouvé. Veuillez créer des plans de paiement dans les paramètres du plugin.', 'formula-planning-reservation') . '</p>';
        } else {
            echo '<table class="widefat">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Plan de paiement', 'formula-planning-reservation') . '</th>';
            echo '<th>' . __('Prix', 'formula-planning-reservation') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($payment_plans as $plan) {
                $price = isset($saved_prices[$plan->id]) ? $saved_prices[$plan->id] : '';
                $frequency_label = [
                    'hourly' => '/heure',
                    'daily' => '/jour',
                    'weekly' => '/sem',
                    'monthly' => '/mois',
                    'quarterly' => '/trim',
                    'annual' => '/an'
                ];
                $frequency_text = isset($frequency_label[$plan->frequency]) ? $frequency_label[$plan->frequency] : '';
                $plan_label = sprintf('%s (%d versements%s)', $plan->name, $plan->installments, $frequency_text ? ' ' . $frequency_text : '');

                echo '<tr>';
                echo '<td>' . esc_html($plan_label) . '</td>';
                echo '<td><input type="text" name="fpr_payment_plan_prices[' . esc_attr($plan->id) . ']" value="' . esc_attr($price) . '" class="wc_input_price short" placeholder="' . __('Laisser vide pour utiliser le prix standard', 'formula-planning-reservation') . '" /></td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        }
        echo '</div>';
    }

    /**
     * Save payment plan prices
     * 
     * @param int $post_id The post ID
     */
    public static function save_payment_plans_meta($post_id) {
        // Check if nonce is set
        if (!isset($_POST['fpr_payment_plan_prices_nonce'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['fpr_payment_plan_prices_nonce'], 'fpr_save_payment_plan_prices')) {
            return;
        }

        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save payment plan prices
        $prices = [];
        if (isset($_POST['fpr_payment_plan_prices']) && is_array($_POST['fpr_payment_plan_prices'])) {
            foreach ($_POST['fpr_payment_plan_prices'] as $plan_id => $price) {
                if (!empty($price)) {
                    $prices[$plan_id] = wc_format_decimal($price);
                }
            }
        }

        update_post_meta($post_id, '_fpr_payment_plan_prices', $prices);
    }

    /**
     * Filter product price based on selected payment plan
     * 
     * @param string $price The product price
     * @param WC_Product $product The product object
     * @return string The filtered price
     */
    public static function filter_product_price($price, $product) {
        // Only filter on frontend
        if (is_admin() && !wp_doing_ajax()) {
            return $price;
        }

        // Get selected payment plan from session
        $selected_plan_id = WC()->session ? WC()->session->get('fpr_selected_payment_plan') : null;

        if (!$selected_plan_id) {
            return $price;
        }

        // Get payment plan prices for this product
        $payment_plan_prices = get_post_meta($product->get_id(), '_fpr_payment_plan_prices', true);

        // If a price is set for the selected payment plan, use it
        if (is_array($payment_plan_prices) && isset($payment_plan_prices[$selected_plan_id]) && $payment_plan_prices[$selected_plan_id] !== '') {
            return $payment_plan_prices[$selected_plan_id];
        }

        return $price;
    }

    /**
     * AJAX handler for updating product prices
     */
    public static function ajax_update_product_prices() {
        // Verify nonce
        check_ajax_referer('fpr-update-prices', 'security');

        // Get selected payment plan
        $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;

        if ($plan_id > 0) {
            // Save selected plan to session
            WC()->session->set('fpr_selected_payment_plan', $plan_id);

            // Get cart items and their updated prices
            $updated_prices = [];
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $product_id = $cart_item['product_id'];
                $product = wc_get_product($product_id);

                // Get the updated price
                $price = $product->get_price();

                $updated_prices[$cart_item_key] = [
                    'price' => wc_price($price),
                    'subtotal' => wc_price($price * $cart_item['quantity']),
                    'total' => wc_price($price * $cart_item['quantity'])
                ];
            }

            // Get updated cart totals
            WC()->cart->calculate_totals();

            wp_send_json_success([
                'items' => $updated_prices,
                'total' => WC()->cart->get_cart_total(),
                'subtotal' => WC()->cart->get_cart_subtotal()
            ]);
        } else {
            wp_send_json_error(['message' => __('ID de plan de paiement invalide', 'formula-planning-reservation')]);
        }

        wp_die();
    }

    /**
     * Enqueue admin scripts
     * 
     * @param string $hook The current admin page
     */
    public static function enqueue_admin_scripts($hook) {
        global $post;

        // Only enqueue on product edit page
        if ($hook == 'post.php' && $post && $post->post_type == 'product') {
            wp_enqueue_style(
                'fpr-admin-product-payment-plans',
                FPR_PLUGIN_URL . 'assets/css/admin-product-payment-plans.css',
                [],
                FPR_VERSION
            );
        }
    }

    /**
     * Enqueue frontend scripts
     */
    public static function enqueue_frontend_scripts() {
        if (is_cart() || is_checkout()) {
            wp_enqueue_script(
                'fpr-product-payment-plans',
                FPR_PLUGIN_URL . 'assets/js/product-payment-plans.js',
                ['jquery'],
                FPR_VERSION,
                true
            );

            wp_localize_script('fpr-product-payment-plans', 'fprProductPaymentPlans', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'security' => wp_create_nonce('fpr-update-prices')
            ]);
        }
    }

    /**
     * Get all active payment plans
     * 
     * @return array List of active payment plans
     */
    private static function get_active_payment_plans() {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}fpr_payment_plans 
             WHERE active = 1
             ORDER BY name ASC"
        );

        return $results;
    }
}
