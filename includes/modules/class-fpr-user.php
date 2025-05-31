<?php
namespace FPR\Modules;

if (!defined('ABSPATH')) exit;

use FPR\Helpers\Logger;

/**
 * Class FPRUser
 * 
 * This class manages the FPR-specific user entities.
 * It provides methods for creating, retrieving, and updating FPR users.
 */
class FPRUser {
    /**
     * Initialize the module
     */
    public static function init() {
        // Create tables if they don't exist
        add_action('init', [__CLASS__, 'create_tables']);

        // Sync WordPress users to FPR users
        add_action('init', function() {
            // Use a transient to ensure this only runs once per day
            if (get_transient('fpr_sync_wp_users_to_fpr_users') === false) {
                // Set transient to prevent running again for 24 hours
                set_transient('fpr_sync_wp_users_to_fpr_users', time(), DAY_IN_SECONDS);

                // Run the sync immediately
                self::sync_wp_users_to_fpr_users();
            }
        }, 20); // Run after create_tables (priority 10)

        // Add custom action hook for the scheduled event
        add_action('fpr_sync_wp_users_to_fpr_users', [__CLASS__, 'sync_wp_users_to_fpr_users']);

        // When user logs in, update their specific record
        add_action('wp_login', function($user_login, $user) {
            self::find_or_create_from_wp_user($user->ID);
        }, 10, 2);

        // When a new user is registered, create an FPR user
        add_action('user_register', function($user_id) {
            self::find_or_create_from_wp_user($user_id);
        });
    }

    /**
     * Create database tables if they don't exist
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Load SQL file
        $sql_file = 'create_fpr_users_table.sql';
        $sql_path = FPR_PLUGIN_DIR . 'sql/' . $sql_file;

        if (file_exists($sql_path)) {
            // Read SQL file content
            $sql_content = file_get_contents($sql_path);

            // Replace variables
            $sql_content = str_replace('{prefix}', $wpdb->prefix, $sql_content);
            $sql_content = str_replace('{charset_collate}', $charset_collate, $sql_content);

            // Execute SQL
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql_content);

            Logger::log("[FPRUser] Table creation result for $sql_file: " . print_r($result, true));
        } else {
            Logger::log("[FPRUser] SQL file not found: $sql_path");
        }
    }

    /**
     * Find or create an FPR user from a WordPress user
     * 
     * @param int $wp_user_id WordPress user ID
     * @return int|false FPR user ID or false on failure
     */
    public static function find_or_create_from_wp_user($wp_user_id) {
        global $wpdb;

        // Get WordPress user data
        $wp_user = get_userdata($wp_user_id);
        if (!$wp_user) {
            Logger::log("[FPRUser] WordPress user not found: ID=$wp_user_id");
            return false;
        }

        // Check if FPR user already exists for this WordPress user
        $fpr_user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_users WHERE wp_user_id = %d",
            $wp_user_id
        ));

        if ($fpr_user) {
            Logger::log("[FPRUser] FPR user already exists for WordPress user ID=$wp_user_id: FPR user ID={$fpr_user->id}");
            return $fpr_user->id;
        }

        // Check if FPR user exists with the same email
        $fpr_user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_users WHERE email = %s",
            $wp_user->user_email
        ));

        if ($fpr_user) {
            // Update FPR user with WordPress user ID
            $result = $wpdb->update(
                $wpdb->prefix . 'fpr_users',
                ['wp_user_id' => $wp_user_id],
                ['id' => $fpr_user->id]
            );

            if ($result !== false) {
                Logger::log("[FPRUser] Updated FPR user ID={$fpr_user->id} with WordPress user ID=$wp_user_id");
            } else {
                Logger::log("[FPRUser] Failed to update FPR user ID={$fpr_user->id} with WordPress user ID=$wp_user_id: " . $wpdb->last_error);
            }

            return $fpr_user->id;
        }

        // Create new FPR user
        $first_name = $wp_user->first_name;
        $last_name = $wp_user->last_name;

        // If first_name or last_name is empty, try to extract from display_name
        if (empty($first_name) && empty($last_name)) {
            $name_parts = explode(' ', $wp_user->display_name, 2);
            $first_name = $name_parts[0] ?? '';
            $last_name = $name_parts[1] ?? '';
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'fpr_users',
            [
                'wp_user_id' => $wp_user_id,
                'email' => $wp_user->user_email,
                'firstName' => $first_name,
                'lastName' => $last_name,
                'phone' => get_user_meta($wp_user_id, 'billing_phone', true),
                'status' => 'active'
            ]
        );

        if ($result === false) {
            Logger::log("[FPRUser] Failed to create FPR user for WordPress user ID=$wp_user_id: " . $wpdb->last_error);
            return false;
        }

        $fpr_user_id = $wpdb->insert_id;
        Logger::log("[FPRUser] Created new FPR user ID=$fpr_user_id for WordPress user ID=$wp_user_id");

        return $fpr_user_id;
    }

    /**
     * Find or create an FPR user from an email address
     * 
     * @param string $email Email address
     * @param string $first_name First name
     * @param string $last_name Last name
     * @param string $phone Phone number
     * @return int|false FPR user ID or false on failure
     */
    public static function find_or_create_from_email($email, $first_name = '', $last_name = '', $phone = '') {
        global $wpdb;

        // Check if FPR user already exists for this email
        $fpr_user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_users WHERE email = %s",
            $email
        ));

        if ($fpr_user) {
            Logger::log("[FPRUser] FPR user already exists for email=$email: FPR user ID={$fpr_user->id}");
            return $fpr_user->id;
        }

        // Check if WordPress user exists for this email
        $wp_user = get_user_by('email', $email);
        $wp_user_id = $wp_user ? $wp_user->ID : null;

        if ($wp_user_id) {
            // Create FPR user from WordPress user
            return self::find_or_create_from_wp_user($wp_user_id);
        }

        // Check if Amelia customer exists for this email
        $amelia_customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}amelia_customers WHERE email = %s",
            $email
        ));

        $amelia_customer_id = $amelia_customer ? $amelia_customer->id : null;

        // If no Amelia customer exists, create one
        if (!$amelia_customer_id && !empty($email)) {
            // Prepare data for Amelia customer creation
            $amelia_data = [
                'firstName' => $first_name,
                'lastName' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'status' => 'visible',
                'type' => 'customer'
            ];

            // Insert into Amelia customers table
            $result = $wpdb->insert(
                $wpdb->prefix . 'amelia_customers',
                $amelia_data
            );

            if ($result !== false) {
                $amelia_customer_id = $wpdb->insert_id;
                Logger::log("[FPRUser] Created new Amelia customer with ID: {$amelia_customer_id}, email: {$email}");
            } else {
                Logger::log("[FPRUser] Failed to create Amelia customer: " . $wpdb->last_error);
            }
        }

        // Create new FPR user
        $result = $wpdb->insert(
            $wpdb->prefix . 'fpr_users',
            [
                'wp_user_id' => $wp_user_id,
                'amelia_customer_id' => $amelia_customer_id,
                'email' => $email,
                'firstName' => $first_name,
                'lastName' => $last_name,
                'phone' => $phone,
                'status' => 'active'
            ]
        );

        if ($result === false) {
            Logger::log("[FPRUser] Failed to create FPR user for email=$email: " . $wpdb->last_error);
            return false;
        }

        $fpr_user_id = $wpdb->insert_id;
        Logger::log("[FPRUser] Created new FPR user ID=$fpr_user_id for email=$email");

        return $fpr_user_id;
    }

    /**
     * Get an FPR user by ID
     * 
     * @param int $fpr_user_id FPR user ID
     * @return object|false FPR user object or false if not found
     */
    public static function get_by_id($fpr_user_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_users WHERE id = %d",
            $fpr_user_id
        ));
    }

    /**
     * Get an FPR user by email
     * 
     * @param string $email Email address
     * @return object|false FPR user object or false if not found
     */
    public static function get_by_email($email) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_users WHERE email = %s",
            $email
        ));
    }

    /**
     * Get an FPR user by WordPress user ID
     * 
     * @param int $wp_user_id WordPress user ID
     * @return object|false FPR user object or false if not found
     */
    public static function get_by_wp_user_id($wp_user_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_users WHERE wp_user_id = %d",
            $wp_user_id
        ));
    }

    /**
     * Get an FPR user by Amelia customer ID
     * 
     * @param int $amelia_customer_id Amelia customer ID
     * @return object|false FPR user object or false if not found
     */
    public static function get_by_amelia_customer_id($amelia_customer_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_users WHERE amelia_customer_id = %d",
            $amelia_customer_id
        ));
    }

    /**
     * Sync WordPress users to FPR users
     * 
     * This method finds all WordPress users and creates corresponding FPR users if they don't exist.
     * 
     * @return int Number of FPR users created
     */
    public static function sync_wp_users_to_fpr_users() {
        global $wpdb;

        Logger::log("[FPRUser] Starting sync_wp_users_to_fpr_users");

        // Get all WordPress users
        $wp_users = get_users(['fields' => 'ID']);

        if (empty($wp_users)) {
            Logger::log("[FPRUser] No WordPress users found");
            return 0;
        }

        $created_count = 0;

        foreach ($wp_users as $wp_user_id) {
            $fpr_user_id = self::find_or_create_from_wp_user($wp_user_id);
            if ($fpr_user_id) {
                $created_count++;
            }
        }

        Logger::log("[FPRUser] Completed sync_wp_users_to_fpr_users. Created $created_count FPR users.");

        return $created_count;
    }
}