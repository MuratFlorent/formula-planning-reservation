<?php
namespace FPR\Modules;

if (!defined('ABSPATH')) exit;

/**
 * Class UserRegistration
 * Handles user registration during checkout
 */
class UserRegistration {
    /**
     * Initialize the module
     */
    public static function init() {
        // Ensure user accounts are created during checkout
        add_action('woocommerce_checkout_order_processed', [self::class, 'ensure_user_registration'], 10, 3);

        // Log user registration events
        add_action('user_register', [self::class, 'log_user_registration'], 10, 1);
    }

    /**
     * Ensure user registration during checkout
     * 
     * @param int $order_id Order ID
     * @param array $posted_data Posted data
     * @param WC_Order $order Order object
     */
    public static function ensure_user_registration($order_id, $posted_data, $order) {
        // Check if user is already logged in
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            return;
        }

        // Get customer email from order
        $email = $order->get_billing_email();
        if (empty($email)) {
            return;
        }

        // Check if user already exists
        $user_id = email_exists($email);
        if ($user_id) {
            // Update order with user ID if not already set
            if (!$order->get_user_id()) {
                $order->set_customer_id($user_id);
                $order->save();
            }

            // Ensure customer record exists in fpr_customers table with correct user_id
            $customer_id = self::ensure_customer_record($user_id);
            return;
        }

        // Create new user
        // Generate username from email
        $username = self::generate_username_from_email($email);

        // Generate random password
        $password = wp_generate_password();

        // Get customer data from order
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();

        // Create user
        $user_id = wc_create_new_customer($email, $username, $password);

        if (is_wp_error($user_id)) {
            return;
        }

        // Update user meta
        if (!empty($first_name)) {
            update_user_meta($user_id, 'first_name', $first_name);
        }

        if (!empty($last_name)) {
            update_user_meta($user_id, 'last_name', $last_name);
        }

        // Update billing and shipping address
        self::update_customer_addresses($user_id, $order);

        // Update order with user ID
        $order->set_customer_id($user_id);
        $order->save();

        // Ensure customer record exists in fpr_customers table
        self::ensure_customer_record($user_id);
    }

    /**
     * Generate username from email
     * 
     * @param string $email Email address
     * @return string Username
     */
    private static function generate_username_from_email($email) {
        $username = sanitize_user(current(explode('@', $email)), true);

        // Ensure username is unique
        $counter = 1;
        $new_username = $username;

        while (username_exists($new_username)) {
            $new_username = $username . $counter;
            $counter++;
        }

        return $new_username;
    }

    /**
     * Update customer addresses
     * 
     * @param int $user_id User ID
     * @param WC_Order $order Order object
     */
    private static function update_customer_addresses($user_id, $order) {
        // Billing address
        update_user_meta($user_id, 'billing_first_name', $order->get_billing_first_name());
        update_user_meta($user_id, 'billing_last_name', $order->get_billing_last_name());
        update_user_meta($user_id, 'billing_company', $order->get_billing_company());
        update_user_meta($user_id, 'billing_address_1', $order->get_billing_address_1());
        update_user_meta($user_id, 'billing_address_2', $order->get_billing_address_2());
        update_user_meta($user_id, 'billing_city', $order->get_billing_city());
        update_user_meta($user_id, 'billing_state', $order->get_billing_state());
        update_user_meta($user_id, 'billing_postcode', $order->get_billing_postcode());
        update_user_meta($user_id, 'billing_country', $order->get_billing_country());
        update_user_meta($user_id, 'billing_email', $order->get_billing_email());
        update_user_meta($user_id, 'billing_phone', $order->get_billing_phone());

        // Shipping address
        update_user_meta($user_id, 'shipping_first_name', $order->get_shipping_first_name());
        update_user_meta($user_id, 'shipping_last_name', $order->get_shipping_last_name());
        update_user_meta($user_id, 'shipping_company', $order->get_shipping_company());
        update_user_meta($user_id, 'shipping_address_1', $order->get_shipping_address_1());
        update_user_meta($user_id, 'shipping_address_2', $order->get_shipping_address_2());
        update_user_meta($user_id, 'shipping_city', $order->get_shipping_city());
        update_user_meta($user_id, 'shipping_state', $order->get_shipping_state());
        update_user_meta($user_id, 'shipping_postcode', $order->get_shipping_postcode());
        update_user_meta($user_id, 'shipping_country', $order->get_shipping_country());
    }

    /**
     * Handle user registration
     * 
     * @param int $user_id User ID
     */
    public static function log_user_registration($user_id) {
        $user = get_userdata($user_id);
        if ($user) {
            // Ensure customer record exists in fpr_customers table
            self::ensure_customer_record($user_id);
        }
    }

    /**
     * Ensure customer record exists in fpr_customers table
     * 
     * @param int $user_id User ID
     * @return int|false Customer ID or false on failure
     */
    private static function ensure_customer_record($user_id) {
        global $wpdb;

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $email = $user->user_email;

        // Check if customer already exists
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_customers WHERE email = %s",
            $email
        );
        $customer = $wpdb->get_row($query);

        if ($customer) {
            // If customer exists but user_id is null or different, update it
            if (empty($customer->user_id) || $customer->user_id != $user_id) {
                $update_data = ['user_id' => $user_id];

                $result = $wpdb->update(
                    $wpdb->prefix . 'fpr_customers',
                    $update_data,
                    ['id' => $customer->id]
                );

                if ($result === false) {
                    // Error during update
                } elseif ($result === 0) {
                    if ($customer->user_id != $user_id) {
                        // Try a direct update with SQL query
                        $wpdb->query(
                            $wpdb->prepare(
                                "UPDATE {$wpdb->prefix}fpr_customers SET user_id = %d WHERE id = %d",
                                $user_id, $customer->id
                            )
                        );
                    }
                }
            }
            return $customer->id;
        }

        // Get user details
        $first_name = $user->first_name;
        $last_name = $user->last_name;

        // If first_name or last_name is empty, try to extract from display_name
        if (empty($first_name) && empty($last_name)) {
            $name_parts = explode(' ', $user->display_name, 2);
            $first_name = $name_parts[0] ?? '';
            $last_name = $name_parts[1] ?? '';
        }

        // Create new customer
        $data = [
            'user_id' => $user_id,
            'firstName' => $first_name,
            'lastName' => $last_name,
            'email' => $email,
            'phone' => get_user_meta($user_id, 'billing_phone', true),
            'status' => 'active'
        ];

        // Vérifier que la table existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}fpr_customers'") === $wpdb->prefix . 'fpr_customers';
        if (!$table_exists) {
            return false;
        }

        // Insérer le client
        $result = $wpdb->insert(
            $wpdb->prefix . 'fpr_customers',
            $data
        );

        if ($result === false) {
            // Try a direct insert with SQL query
            $columns = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '%s'));
            $values = array_values($data);

            $direct_result = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$wpdb->prefix}fpr_customers ($columns) VALUES ($placeholders)",
                    $values
                )
            );

            if ($direct_result === false) {
                return false;
            } else {
                return $wpdb->insert_id;
            }
        }

        $customer_id = $wpdb->insert_id;
        return $customer_id;
    }
}
