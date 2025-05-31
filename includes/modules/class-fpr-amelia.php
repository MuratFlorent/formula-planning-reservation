<?php
namespace FPR\Modules;

if (!defined('ABSPATH')) exit;

use FPR\Helpers\Logger;

/**
 * Class FPRAmelia
 * 
 * This class provides Amelia-like functionality for the Formula Planning Reservation plugin.
 * It handles event creation, customer registration, and booking management.
 */
class FPRAmelia {
    /**
     * Initialize the module
     */
    public static function init() {
        // Create tables if they don't exist
        add_action('init', [__CLASS__, 'create_tables']);

        // Hook into WooCommerce order status changes
        add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_order_status_change'], 10, 4);

        // Add admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);

        // AJAX handlers for admin
        add_action('wp_ajax_fpr_amelia_get_events', [__CLASS__, 'ajax_get_events']);
        add_action('wp_ajax_fpr_amelia_get_bookings', [__CLASS__, 'ajax_get_bookings']);
        add_action('wp_ajax_fpr_amelia_update_booking', [__CLASS__, 'ajax_update_booking']);
        add_action('wp_ajax_fpr_amelia_get_customers', [__CLASS__, 'ajax_get_customers']);
        add_action('wp_ajax_fpr_amelia_get_customer', [__CLASS__, 'ajax_get_customer']);
        add_action('wp_ajax_fpr_amelia_update_customer', [__CLASS__, 'ajax_update_customer']);

        // Update customer user_ids at various points
        add_action('admin_init', [__CLASS__, 'update_customer_user_ids']); // Admin pages

        // Sync user_ids from subscriptions on plugin init (with a delay to ensure all tables are created)
        add_action('init', function() {
            // Use a transient to ensure this only runs once per day
            if (get_transient('fpr_sync_user_ids_from_subscriptions') === false) {
                // Set transient to prevent running again for 24 hours
                set_transient('fpr_sync_user_ids_from_subscriptions', time(), DAY_IN_SECONDS);

                // Run the sync immediately
                self::sync_user_ids_from_subscriptions();

                // Also sync Amelia customer IDs
                self::sync_amelia_customer_ids();
            }
        }, 20); // Run after create_tables (priority 10)

        // Add custom action hook for the scheduled event
        add_action('fpr_sync_user_ids_from_subscriptions', [__CLASS__, 'sync_user_ids_from_subscriptions']);
        add_action('fpr_sync_amelia_customer_ids', [__CLASS__, 'sync_amelia_customer_ids']);

        // When user logs in, update their specific record
        add_action('wp_login', function($user_login, $user) {
            self::update_customer_user_ids($user->user_email);
        }, 10, 2);

        // When order is completed, update customer from order
        add_action('woocommerce_order_status_completed', function($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                self::update_customer_user_ids($order->get_billing_email());
            }
        });

        // When payment is completed, update customer from order
        add_action('woocommerce_payment_complete', function($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                self::update_customer_user_ids($order->get_billing_email());
            }
        });

        // When a new user is registered, update their specific record
        add_action('user_register', function($user_id) {
            $user = get_userdata($user_id);
            if ($user) {
                self::update_customer_user_ids($user->user_email);
            }
        });
    }

    /**
     * Create database tables if they don't exist
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Load SQL files
        $sql_files = [
            'create_fpr_events_table.sql',
            'create_fpr_events_tags_table.sql',
            'create_fpr_events_periods_table.sql',
            'create_fpr_customers_table.sql',
            'create_fpr_customer_bookings_table.sql',
            'add_amelia_customer_id_to_fpr_customers.sql'
        ];

        foreach ($sql_files as $sql_file) {
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

                Logger::log("[FPRAmelia] Table creation result for $sql_file: " . print_r($result, true));
            } else {
                Logger::log("[FPRAmelia] SQL file not found: $sql_path");
            }
        }
    }

    /**
     * Handle WooCommerce order status changes
     */
    public static function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        // Only process orders that are processing or completed
        if (!in_array($new_status, ['processing', 'completed'])) {
            return;
        }

        Logger::log("[FPRAmelia] Processing order #$order_id, status changed from $old_status to $new_status");

        // Get customer information
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();

        // Get selected season
        $saison_tag = get_post_meta($order_id, '_fpr_selected_saison', true);

        // If no season is selected, use a default season or create one
        if (empty($saison_tag)) {
            Logger::log("[FPRAmelia] No season selected for order #$order_id, using default season");

            // Try to get the current year for a default season tag
            $current_year = date('Y');
            $saison_tag = "saison-{$current_year}";

            // Check if this season exists
            global $wpdb;
            $season_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}fpr_saisons WHERE tag = %s",
                $saison_tag
            ));

            // If the season doesn't exist, create it
            if (!$season_exists) {
                Logger::log("[FPRAmelia] Creating default season with tag: $saison_tag");

                $result = $wpdb->insert(
                    $wpdb->prefix . 'fpr_saisons',
                    [
                        'name' => "Saison {$current_year}",
                        'tag' => $saison_tag,
                        'start_date' => date('Y-01-01'), // January 1st of current year
                        'end_date' => date('Y-12-31'),   // December 31st of current year
                        'status' => 'active'
                    ]
                );

                if ($result === false) {
                    Logger::log("[FPRAmelia] Failed to create default season: " . $wpdb->last_error);
                    // Continue anyway with the tag, it will be created when needed
                } else {
                    Logger::log("[FPRAmelia] Default season created successfully");
                }
            }
        }

        // Process each item in the order
        foreach ($order->get_items() as $item) {
            $formula = $item->get_name();

            // Look for course selections in item meta
            foreach ($item->get_meta_data() as $meta) {
                if (strpos($meta->key, 'Cours sélectionné') !== false) {
                    $course_name = $meta->value;
                    self::register_customer_for_course($first_name, $last_name, $email, $phone, $course_name, $formula, $order_id, $saison_tag);
                }
            }
        }
    }

    /**
     * Register a customer for a course
     * 
     * @param string $first_name First name
     * @param string $last_name Last name
     * @param string $email Email address
     * @param string $phone Phone number
     * @param string $course_name Course name
     * @param string $formula Formula
     * @param int $order_id Order ID
     * @param string $saison_tag Season tag
     * @return int|false Booking ID or false on failure
     */
    public static function register_customer_for_course($first_name, $last_name, $email, $phone, $course_name, $formula, $order_id, $saison_tag) {
        global $wpdb;

        Logger::log("[FPRAmelia] Registering customer for course: $course_name, season: $saison_tag");

        // Extract the course name from the full string (e.g., "Jazz Enfant 1 | Lundi | 17:30 - 18:30 | 1h | avec Vanessa")
        $parts = explode('|', $course_name);
        $short_name = trim($parts[0]);

        // Find or create FPR user
        $fpr_user_id = self::find_or_create_customer($first_name, $last_name, $email, $phone);
        if (!$fpr_user_id) {
            Logger::log("[FPRAmelia] Failed to find or create FPR user");
            return false;
        }

        // Get FPR user
        $fpr_user = FPRUser::get_by_id($fpr_user_id);
        if (!$fpr_user) {
            Logger::log("[FPRAmelia] Failed to get FPR user with ID: $fpr_user_id");
            return false;
        }

        // Get WordPress user ID if available
        $wp_user_id = $fpr_user->user_id;
        if ($wp_user_id) {
            // Store course in user metadata
            $user_courses = get_user_meta($wp_user_id, 'fpr_user_courses', true);
            if (!is_array($user_courses)) {
                $user_courses = array();
            }

            // Add the course if it doesn't already exist
            if (!in_array($course_name, $user_courses)) {
                $user_courses[] = $course_name;
                update_user_meta($wp_user_id, 'fpr_user_courses', $user_courses);
                Logger::log("[FPRAmelia] Added course to user metadata: $course_name for user ID: $wp_user_id");
            }
        }

        // Get Amelia customer ID from FPR user
        $amelia_customer_id = $fpr_user->amelia_customer_id;
        if (!$amelia_customer_id) {
            Logger::log("[FPRAmelia] FPR user has no Amelia customer ID: $fpr_user_id, création d'un client Amelia");

            // Try to find an existing Amelia customer with the same email
            $existing_amelia_customer = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}amelia_customers WHERE email = %s",
                $email
            ));

            if ($existing_amelia_customer) {
                $amelia_customer_id = $existing_amelia_customer->id;
                Logger::log("[FPRAmelia] Client Amelia existant trouvé avec le même email, ID: $amelia_customer_id");
            } else {
                // Create Amelia customer
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

                if ($result === false) {
                    Logger::log("[FPRAmelia] ERREUR: Impossible de créer un client Amelia: " . $wpdb->last_error);
                    return false;
                }

                $amelia_customer_id = $wpdb->insert_id;
                Logger::log("[FPRAmelia] Client Amelia créé avec succès, ID: $amelia_customer_id");
            }

            // Update FPR user with Amelia customer ID
            $update_result = $wpdb->update(
                $wpdb->prefix . 'fpr_users',
                ['amelia_customer_id' => $amelia_customer_id],
                ['id' => $fpr_user_id]
            );

            if ($update_result === false) {
                Logger::log("[FPRAmelia] ERREUR: Impossible de mettre à jour l'utilisateur FPR avec l'ID client Amelia: " . $wpdb->last_error);
                // Continue anyway since we have a valid Amelia customer ID
            } else {
                Logger::log("[FPRAmelia] Utilisateur FPR mis à jour avec l'ID client Amelia: $amelia_customer_id");
            }

            // Also try to find WordPress user and update Amelia customer with user_id
            $wp_user = get_user_by('email', $email);
            if ($wp_user) {
                $wp_user_id = $wp_user->ID;
                Logger::log("[FPRAmelia] WordPress user found for email: $email, ID: $wp_user_id");

                // Update Amelia customer with WordPress user ID
                $update_amelia_result = $wpdb->update(
                    $wpdb->prefix . 'amelia_customers',
                    ['user_id' => $wp_user_id],
                    ['id' => $amelia_customer_id]
                );

                if ($update_amelia_result === false) {
                    Logger::log("[FPRAmelia] ERREUR: Impossible de mettre à jour le client Amelia avec l'ID utilisateur WordPress: " . $wpdb->last_error);
                } else {
                    Logger::log("[FPRAmelia] Client Amelia mis à jour avec l'ID utilisateur WordPress: $wp_user_id");
                }
            }
        }

        // Find event by name and tag
        $event = self::find_event_by_name_and_tag($short_name, $saison_tag);
        if (!$event) {
            // Try to create the event
            $event_id = self::create_event($short_name, $saison_tag);
            if (!$event_id) {
                Logger::log("[FPRAmelia] Failed to find or create event: $short_name");
                return false;
            }

            // Get the newly created event
            $event = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_events WHERE id = %d",
                $event_id
            ));
        }

        // Find or create event period
        $period_id = self::find_or_create_period($event->id, $course_name);
        if (!$period_id) {
            Logger::log("[FPRAmelia] Failed to find or create period for event: {$event->name}");
            return false;
        }

        // Check if customer is already booked for this event period
        $existing_booking = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}fpr_customer_bookings 
             WHERE customerId = %d AND eventPeriodId = %d AND status IN ('approved', 'pending')",
            $amelia_customer_id, $period_id
        ));

        if ($existing_booking) {
            Logger::log("[FPRAmelia] Customer already booked for this event period");
            return true; // Already booked, consider it a success
        }

        // Create booking
        $result = $wpdb->insert(
            $wpdb->prefix . 'fpr_customer_bookings',
            [
                'customerId' => $amelia_customer_id,
                'eventPeriodId' => $period_id,
                'status' => 'approved', // Auto-approve bookings from WooCommerce
                'formula' => $formula,
                'order_id' => $order_id
            ]
        );

        if ($result === false) {
            Logger::log("[FPRAmelia] Failed to create booking: " . $wpdb->last_error);
            return false;
        }

        $booking_id = $wpdb->insert_id;
        Logger::log("[FPRAmelia] Booking created successfully, ID: $booking_id");

        return $booking_id;
    }

    /**
     * Find or create a customer
     * 
     * This method uses the FPRUser class to find or create an FPR user,
     * which is linked to both WordPress users and Amelia customers.
     * 
     * @param string $first_name First name
     * @param string $last_name Last name
     * @param string $email Email address
     * @param string $phone Phone number
     * @return int|false FPR user ID or false on failure
     */
    private static function find_or_create_customer($first_name, $last_name, $email, $phone) {
        // Use the FPRUser class to find or create an FPR user
        $fpr_user_id = FPRUser::find_or_create_from_email($email, $first_name, $last_name, $phone);

        if (!$fpr_user_id) {
            Logger::log("[FPRAmelia] Failed to find or create FPR user for email: $email");
            return false;
        }

        Logger::log("[FPRAmelia] Found or created FPR user ID: $fpr_user_id for email: $email");
        return $fpr_user_id;
    }

    /**
     * Find an event by name and tag
     */
    private static function find_event_by_name_and_tag($name, $tag) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT e.* FROM {$wpdb->prefix}fpr_events e
             JOIN {$wpdb->prefix}fpr_events_tags t ON e.id = t.eventId
             WHERE e.name = %s AND t.name = %s",
            $name, $tag
        ));
    }

    /**
     * Create a new event
     */
    private static function create_event($name, $tag) {
        global $wpdb;

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Create event
            $result = $wpdb->insert(
                $wpdb->prefix . 'fpr_events',
                [
                    'name' => $name,
                    'status' => 'active'
                ]
            );

            if ($result === false) {
                throw new \Exception("Failed to create event: " . $wpdb->last_error);
            }

            $event_id = $wpdb->insert_id;

            // Create event tag
            $result = $wpdb->insert(
                $wpdb->prefix . 'fpr_events_tags',
                [
                    'eventId' => $event_id,
                    'name' => $tag
                ]
            );

            if ($result === false) {
                throw new \Exception("Failed to create event tag: " . $wpdb->last_error);
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            return $event_id;
        } catch (\Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            Logger::log("[FPRAmelia] " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find or create an event period
     */
    private static function find_or_create_period($event_id, $course_name) {
        global $wpdb;

        // Extract time information from course name
        // Example: "Jazz Enfant 1 | Lundi | 17:30 - 18:30 | 1h | avec Vanessa"
        $parts = explode('|', $course_name);

        if (count($parts) < 3) {
            Logger::log("[FPRAmelia] Invalid course name format: $course_name");
            return false;
        }

        $day_of_week = trim($parts[1]); // e.g., "Lundi"
        $time_range = trim($parts[2]); // e.g., "17:30 - 18:30"

        // Map day of week to number (1 = Monday, 7 = Sunday)
        $day_map = [
            'Lundi' => 1,
            'Mardi' => 2,
            'Mercredi' => 3,
            'Jeudi' => 4,
            'Vendredi' => 5,
            'Samedi' => 6,
            'Dimanche' => 7
        ];

        $day_number = $day_map[$day_of_week] ?? 1; // Default to Monday if not found

        // Extract start and end times
        $time_parts = explode('-', $time_range);
        $start_time = trim($time_parts[0]); // e.g., "17:30"
        $end_time = trim($time_parts[1] ?? $start_time); // e.g., "18:30"

        // Find next occurrence of this day of week
        $today = new \DateTime();
        $days_to_add = ($day_number - (int)$today->format('N') + 7) % 7;
        if ($days_to_add === 0) $days_to_add = 7; // If today, use next week

        $next_date = clone $today;
        $next_date->add(new \DateInterval("P{$days_to_add}D"));

        // Set start and end times
        $start_date = clone $next_date;
        $start_time_parts = explode(':', $start_time);
        $start_date->setTime((int)$start_time_parts[0], (int)($start_time_parts[1] ?? 0));

        $end_date = clone $next_date;
        $end_time_parts = explode(':', $end_time);
        $end_date->setTime((int)$end_time_parts[0], (int)($end_time_parts[1] ?? 0));

        // Check if period exists
        $period = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_events_periods 
             WHERE eventId = %d AND DATE_FORMAT(periodStart, '%%H:%%i') = %s AND DATE_FORMAT(periodEnd, '%%H:%%i') = %s",
            $event_id, $start_time, $end_time
        ));

        if ($period) {
            return $period->id;
        }

        // Create new period
        $result = $wpdb->insert(
            $wpdb->prefix . 'fpr_events_periods',
            [
                'eventId' => $event_id,
                'periodStart' => $start_date->format('Y-m-d H:i:s'),
                'periodEnd' => $end_date->format('Y-m-d H:i:s'),
                'capacity' => 10, // Default capacity
                'status' => 'active'
            ]
        );

        if ($result === false) {
            Logger::log("[FPRAmelia] Failed to create period: " . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'options-general.php?page=fpr-settings',
            'FPR Amelia',
            'FPR Amelia',
            'manage_options',
            'fpr-amelia',
            [__CLASS__, 'render_admin_page']
        );
    }

    /**
     * Render admin page
     */
    public static function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>FPR Amelia</h1>

            <h2 class="nav-tab-wrapper">
                <a href="#events" class="nav-tab nav-tab-active">Events</a>
                <a href="#bookings" class="nav-tab">Bookings</a>
                <a href="#customers" class="nav-tab">Customers</a>
            </h2>

            <div id="events" class="tab-content">
                <h3>Events</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Tags</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="events-list">
                        <tr>
                            <td colspan="5">Loading events...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="bookings" class="tab-content" style="display:none;">
                <h3>Bookings</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Event</th>
                            <th>Date/Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bookings-list">
                        <tr>
                            <td colspan="6">Loading bookings...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="customers" class="tab-content" style="display:none;">
                <h3>Customers</h3>
                <div id="customer-edit-form" style="display:none; margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h4>Edit Customer</h4>
                    <form id="edit-customer-form">
                        <input type="hidden" id="edit-customer-id" name="customer_id">
                        <table class="form-table">
                            <tr>
                                <th><label for="edit-first-name">First Name</label></th>
                                <td><input type="text" id="edit-first-name" name="first_name" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="edit-last-name">Last Name</label></th>
                                <td><input type="text" id="edit-last-name" name="last_name" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="edit-email">Email</label></th>
                                <td><input type="email" id="edit-email" name="email" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="edit-user-id">WordPress User ID</label></th>
                                <td>
                                    <input type="text" id="edit-user-id" name="user_id" class="regular-text" readonly>
                                    <p class="description">This field is automatically updated when a WordPress user with matching email is found.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="edit-phone">Phone</label></th>
                                <td><input type="text" id="edit-phone" name="phone" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="edit-note">Notes</label></th>
                                <td><textarea id="edit-note" name="note" rows="5" class="large-text"></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="edit-status">Status</label></th>
                                <td>
                                    <select id="edit-status" name="status">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <button type="submit" class="button button-primary">Update Customer</button>
                            <button type="button" class="button cancel-edit">Cancel</button>
                        </p>
                    </form>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>WordPress User</th>
                            <th>Phone</th>
                            <th>Notes</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customers-list">
                        <tr>
                            <td colspan="8">Loading customers...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Tab navigation
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');

                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                // Show target content
                $('.tab-content').hide();
                $(target).show();

                // Load data for the tab
                if (target === '#events') {
                    loadEvents();
                } else if (target === '#bookings') {
                    loadBookings();
                } else if (target === '#customers') {
                    loadCustomers();
                }
            });

            // Load events
            function loadEvents() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fpr_amelia_get_events'
                    },
                    success: function(response) {
                        if (response.success) {
                            var events = response.data;
                            var html = '';

                            if (events.length === 0) {
                                html = '<tr><td colspan="5">No events found</td></tr>';
                            } else {
                                for (var i = 0; i < events.length; i++) {
                                    var event = events[i];
                                    html += '<tr>';
                                    html += '<td>' + event.id + '</td>';
                                    html += '<td>' + event.name + '</td>';
                                    html += '<td>' + event.tags + '</td>';
                                    html += '<td>' + event.status + '</td>';
                                    html += '<td><a href="#" class="button button-small">Edit</a></td>';
                                    html += '</tr>';
                                }
                            }

                            $('#events-list').html(html);
                        } else {
                            $('#events-list').html('<tr><td colspan="5">Error loading events</td></tr>');
                        }
                    },
                    error: function() {
                        $('#events-list').html('<tr><td colspan="5">Error loading events</td></tr>');
                    }
                });
            }

            // Load bookings
            function loadBookings() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fpr_amelia_get_bookings'
                    },
                    success: function(response) {
                        if (response.success) {
                            var bookings = response.data;
                            var html = '';

                            if (bookings.length === 0) {
                                html = '<tr><td colspan="6">No bookings found</td></tr>';
                            } else {
                                for (var i = 0; i < bookings.length; i++) {
                                    var booking = bookings[i];
                                    html += '<tr>';
                                    html += '<td>' + booking.id + '</td>';
                                    html += '<td>' + booking.customer + '</td>';
                                    html += '<td>' + booking.event + '</td>';
                                    html += '<td>' + booking.datetime + '</td>';
                                    html += '<td>' + booking.status + '</td>';
                                    html += '<td><a href="#" class="button button-small">Edit</a></td>';
                                    html += '</tr>';
                                }
                            }

                            $('#bookings-list').html(html);
                        } else {
                            $('#bookings-list').html('<tr><td colspan="6">Error loading bookings</td></tr>');
                        }
                    },
                    error: function() {
                        $('#bookings-list').html('<tr><td colspan="6">Error loading bookings</td></tr>');
                    }
                });
            }

            // Load customers
            function loadCustomers() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fpr_amelia_get_customers'
                    },
                    success: function(response) {
                        if (response.success) {
                            var customers = response.data;
                            var html = '';

                            if (customers.length === 0) {
                                html = '<tr><td colspan="7">No customers found</td></tr>';
                            } else {
                                for (var i = 0; i < customers.length; i++) {
                                    var customer = customers[i];
                                    html += '<tr>';
                                    html += '<td>' + customer.id + '</td>';
                                    html += '<td>' + customer.firstName + ' ' + customer.lastName + '</td>';
                                    html += '<td>' + customer.email + '</td>';
                                    html += '<td>' + (customer.user_id ? 'ID: ' + customer.user_id : 'Not linked') + '</td>';
                                    html += '<td>' + (customer.phone || '-') + '</td>';
                                    html += '<td>' + (customer.note ? '<div class="note-preview">' + customer.note.substring(0, 50) + (customer.note.length > 50 ? '...' : '') + '</div>' : '-') + '</td>';
                                    html += '<td>' + customer.status + '</td>';
                                    html += '<td><a href="#" class="button button-small edit-customer" data-id="' + customer.id + '">Edit</a></td>';
                                    html += '</tr>';
                                }
                            }

                            $('#customers-list').html(html);

                            // Bind edit buttons
                            $('.edit-customer').on('click', function(e) {
                                e.preventDefault();
                                var customerId = $(this).data('id');
                                editCustomer(customerId);
                            });
                        } else {
                            $('#customers-list').html('<tr><td colspan="7">Error loading customers</td></tr>');
                        }
                    },
                    error: function() {
                        $('#customers-list').html('<tr><td colspan="7">Error loading customers</td></tr>');
                    }
                });
            }

            // Edit customer
            function editCustomer(customerId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fpr_amelia_get_customer',
                        customer_id: customerId
                    },
                    success: function(response) {
                        if (response.success) {
                            var customer = response.data;

                            // Fill form fields
                            $('#edit-customer-id').val(customer.id);
                            $('#edit-first-name').val(customer.firstName);
                            $('#edit-last-name').val(customer.lastName);
                            $('#edit-email').val(customer.email);
                            $('#edit-user-id').val(customer.user_id || 'Not linked');
                            $('#edit-phone').val(customer.phone);
                            $('#edit-note').val(customer.note);
                            $('#edit-status').val(customer.status);

                            // Show form
                            $('#customer-edit-form').show();

                            // Scroll to form
                            $('html, body').animate({
                                scrollTop: $('#customer-edit-form').offset().top - 50
                            }, 500);
                        } else {
                            alert('Error loading customer data');
                        }
                    },
                    error: function() {
                        alert('Error loading customer data');
                    }
                });
            }

            // Handle form submission
            $('#edit-customer-form').on('submit', function(e) {
                e.preventDefault();

                var formData = {
                    action: 'fpr_amelia_update_customer',
                    customer_id: $('#edit-customer-id').val(),
                    first_name: $('#edit-first-name').val(),
                    last_name: $('#edit-last-name').val(),
                    email: $('#edit-email').val(),
                    phone: $('#edit-phone').val(),
                    note: $('#edit-note').val(),
                    status: $('#edit-status').val()
                };

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            // Hide form
                            $('#customer-edit-form').hide();

                            // Reload customers
                            loadCustomers();

                            // Show success message
                            alert('Customer updated successfully');
                        } else {
                            alert('Error updating customer: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Error updating customer');
                    }
                });
            });

            // Handle cancel button
            $('.cancel-edit').on('click', function() {
                $('#customer-edit-form').hide();
            });

            // Initial load
            loadEvents();
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for getting events
     */
    public static function ajax_get_events() {
        global $wpdb;

        $events = $wpdb->get_results("
            SELECT e.*, GROUP_CONCAT(t.name SEPARATOR ', ') as tags
            FROM {$wpdb->prefix}fpr_events e
            LEFT JOIN {$wpdb->prefix}fpr_events_tags t ON e.id = t.eventId
            GROUP BY e.id
            ORDER BY e.id DESC
        ");

        wp_send_json_success($events);
    }

    /**
     * AJAX handler for getting bookings
     */
    public static function ajax_get_bookings() {
        global $wpdb;

        $bookings = $wpdb->get_results("
            SELECT b.*, 
                   CONCAT(c.firstName, ' ', c.lastName) as customer,
                   e.name as event,
                   p.periodStart as datetime
            FROM {$wpdb->prefix}fpr_customer_bookings b
            JOIN {$wpdb->prefix}fpr_customers c ON b.customerId = c.id
            JOIN {$wpdb->prefix}fpr_events_periods p ON b.eventPeriodId = p.id
            JOIN {$wpdb->prefix}fpr_events e ON p.eventId = e.id
            ORDER BY b.id DESC
        ");

        wp_send_json_success($bookings);
    }

    /**
     * AJAX handler for updating booking status
     */
    public static function ajax_update_booking() {
        global $wpdb;

        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$booking_id || !in_array($status, ['pending', 'approved', 'canceled', 'rejected'])) {
            wp_send_json_error('Invalid booking ID or status');
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'fpr_customer_bookings',
            ['status' => $status],
            ['id' => $booking_id]
        );

        if ($result === false) {
            wp_send_json_error('Failed to update booking status');
        }

        wp_send_json_success('Booking status updated successfully');
    }

    /**
     * AJAX handler for getting customers
     */
    public static function ajax_get_customers() {
        global $wpdb;

        $customers = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}fpr_customers
            ORDER BY lastName ASC, firstName ASC
        ");

        wp_send_json_success($customers);
    }

    /**
     * AJAX handler for getting a specific customer
     */
    public static function ajax_get_customer() {
        global $wpdb;

        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

        if (!$customer_id) {
            wp_send_json_error('Invalid customer ID');
        }

        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_customers WHERE id = %d",
            $customer_id
        ));

        if (!$customer) {
            wp_send_json_error('Customer not found');
        }

        wp_send_json_success($customer);
    }

    /**
     * AJAX handler for updating a customer
     */
    public static function ajax_update_customer() {
        global $wpdb;

        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';

        // Validate required fields
        if (!$customer_id || empty($first_name) || empty($last_name) || empty($email)) {
            wp_send_json_error('Please fill in all required fields');
        }

        // Check if email is valid
        if (!is_email($email)) {
            wp_send_json_error('Please enter a valid email address');
        }

        // Check if email is already in use by another customer
        $existing_customer = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}fpr_customers WHERE email = %s AND id != %d",
            $email, $customer_id
        ));

        if ($existing_customer) {
            wp_send_json_error('This email address is already in use by another customer');
        }

        // Try to find WordPress user by email
        $user_id = null;
        $user = get_user_by('email', $email);
        if ($user) {
            $user_id = $user->ID;
            Logger::log("[FPRAmelia] Found WordPress user for email: $email, ID: $user_id");
        }

        // Update customer
        $result = $wpdb->update(
            $wpdb->prefix . 'fpr_customers',
            [
                'firstName' => $first_name,
                'lastName' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'note' => $note,
                'status' => $status,
                'user_id' => $user_id
            ],
            ['id' => $customer_id]
        );

        if ($result === false) {
            wp_send_json_error('Failed to update customer: ' . $wpdb->last_error);
        }

        wp_send_json_success('Customer updated successfully');
    }

    /**
     * Sync Amelia customer IDs for existing records
     * 
     * This method finds all customers in the fpr_customers table with NULL amelia_customer_id,
     * looks up Amelia customers by email, and updates the amelia_customer_id field.
     * If no Amelia customer is found, it creates one.
     * 
     * @return int Number of customers updated
     */
    public static function sync_amelia_customer_ids() {
        global $wpdb;

        Logger::log("[FPRAmelia] Starting sync_amelia_customer_ids");

        // Find all customers with NULL amelia_customer_id
        $customers = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}fpr_customers 
            WHERE amelia_customer_id IS NULL
        ");

        if (empty($customers)) {
            Logger::log("[FPRAmelia] No customers found with NULL amelia_customer_id");
            return 0;
        }

        $updated_count = 0;

        foreach ($customers as $customer) {
            // Look up Amelia customer by email
            $amelia_customer = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}amelia_customers WHERE email = %s",
                $customer->email
            ));

            $amelia_customer_id = null;

            if ($amelia_customer) {
                // Amelia customer found, update amelia_customer_id
                $amelia_customer_id = $amelia_customer->id;
                Logger::log("[FPRAmelia] Found Amelia customer ID: {$amelia_customer_id} for email: {$customer->email}");
            } else {
                // No Amelia customer found, create one
                $amelia_data = [
                    'firstName' => $customer->firstName,
                    'lastName' => $customer->lastName,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'status' => 'visible',
                    'type' => 'customer'
                ];

                $result = $wpdb->insert(
                    $wpdb->prefix . 'amelia_customers',
                    $amelia_data
                );

                if ($result !== false) {
                    $amelia_customer_id = $wpdb->insert_id;
                    Logger::log("[FPRAmelia] Created new Amelia customer with ID: {$amelia_customer_id}, email: {$customer->email}");
                } else {
                    Logger::log("[FPRAmelia] Failed to create Amelia customer: " . $wpdb->last_error);
                    continue; // Skip to next customer
                }
            }

            // Update amelia_customer_id in fpr_customers
            if ($amelia_customer_id) {
                $result = $wpdb->update(
                    $wpdb->prefix . 'fpr_customers',
                    ['amelia_customer_id' => $amelia_customer_id],
                    ['id' => $customer->id]
                );

                if ($result !== false) {
                    $updated_count++;
                    Logger::log("[FPRAmelia] Updated customer ID: {$customer->id}, email: {$customer->email} with amelia_customer_id: {$amelia_customer_id}");
                } else {
                    Logger::log("[FPRAmelia] Failed to update customer ID: {$customer->id} with amelia_customer_id: {$amelia_customer_id}: " . $wpdb->last_error);
                }
            }
        }

        Logger::log("[FPRAmelia] Completed sync_amelia_customer_ids. Updated $updated_count customers.");

        return $updated_count;
    }

    /**
     * Sync user_id from fpr_customer_subscriptions to fpr_customers
     * 
     * This is a one-time operation that can be called to sync user_id from
     * fpr_customer_subscriptions to fpr_customers for all existing records.
     * 
     * @return int Number of customers updated
     */
    public static function sync_user_ids_from_subscriptions() {
        global $wpdb;

        Logger::log("[FPRAmelia] Starting sync_user_ids_from_subscriptions");

        // First, find customers with null user_id in fpr_customers but with a user_id in fpr_customer_subscriptions
        // where the email matches exactly
        $query = "
            SELECT c.id as customer_id, cs.user_id, c.email
            FROM {$wpdb->prefix}fpr_customers c
            JOIN {$wpdb->prefix}fpr_customer_subscriptions cs ON cs.user_id IS NOT NULL
            JOIN {$wpdb->prefix}users u ON u.ID = cs.user_id AND u.user_email = c.email
            WHERE c.user_id IS NULL OR c.user_id = 0
        ";

        $results = $wpdb->get_results($query);
        $updated_count = 0;

        if (!empty($results)) {
            foreach ($results as $result) {
                // Update customer with user_id from subscription
                $update_result = $wpdb->update(
                    $wpdb->prefix . 'fpr_customers',
                    ['user_id' => $result->user_id],
                    ['id' => $result->customer_id]
                );

                if ($update_result !== false) {
                    $updated_count++;
                    Logger::log("[FPRAmelia] Updated customer ID {$result->customer_id} with user_id {$result->user_id} from subscription (exact email match)");
                }
            }
        }

        // Next, find all customers with null user_id
        $null_user_id_customers = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}fpr_customers 
            WHERE user_id IS NULL OR user_id = 0
        ");

        if (!empty($null_user_id_customers)) {
            // Get all subscriptions with user_id
            $subscriptions = $wpdb->get_results("
                SELECT cs.user_id, u.user_email 
                FROM {$wpdb->prefix}fpr_customer_subscriptions cs
                JOIN {$wpdb->prefix}users u ON u.ID = cs.user_id
                WHERE cs.user_id IS NOT NULL
                GROUP BY cs.user_id
            ");

            if (!empty($subscriptions)) {
                foreach ($null_user_id_customers as $customer) {
                    // Skip if we already updated this customer
                    if (!empty($customer->user_id)) {
                        continue;
                    }

                    // Try to find a matching user_id from subscriptions
                    foreach ($subscriptions as $subscription) {
                        // Check if this is the same user by comparing email domains or patterns
                        $customer_email_parts = explode('@', $customer->email);
                        $subscription_email_parts = explode('@', $subscription->user_email);

                        // If same domain and similar username, consider it a match
                        if ($customer_email_parts[1] === $subscription_email_parts[1] && 
                            (strpos($customer_email_parts[0], $subscription_email_parts[0]) !== false || 
                             strpos($subscription_email_parts[0], $customer_email_parts[0]) !== false)) {

                            // Update customer with user_id from subscription
                            $update_result = $wpdb->update(
                                $wpdb->prefix . 'fpr_customers',
                                ['user_id' => $subscription->user_id],
                                ['id' => $customer->id]
                            );

                            if ($update_result !== false) {
                                $updated_count++;
                                Logger::log("[FPRAmelia] Updated customer ID {$customer->id} with user_id {$subscription->user_id} from subscription (similar email pattern)");
                                break; // Move to next customer
                            }
                        }
                    }
                }
            }
        }

        Logger::log("[FPRAmelia] Completed sync_user_ids_from_subscriptions. Updated $updated_count customers.");

        return $updated_count;
    }

    /**
     * Update user_id for customers with matching email
     * 
     * @param string $specific_email Optional. If provided, only update the customer with this email.
     * @return int Number of customers updated
     */
    public static function update_customer_user_ids($specific_email = null) {
        global $wpdb;

        // Log the start of the update process
        Logger::log("[FPRAmelia] Starting update_customer_user_ids" . ($specific_email ? " for email: $specific_email" : ""));

        // Build the query based on whether we're updating a specific customer or all customers
        if ($specific_email !== null) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_customers WHERE email = %s",
                $specific_email
            );
        } else {
            $query = "SELECT * FROM {$wpdb->prefix}fpr_customers";
        }

        // Get customers
        $customers = $wpdb->get_results($query);

        if (empty($customers)) {
            return 0;
        }

        $updated_count = 0;
        $updated_user_ids = []; // Track which user_ids we've updated

        foreach ($customers as $customer) {
            $user_id = null;

            // Try to find WordPress user by email
            $user = get_user_by('email', $customer->email);
            if ($user) {
                $user_id = $user->ID;
            }

            // If we couldn't find a WordPress user, try to find user_id in fpr_customer_subscriptions
            if (empty($user_id) && empty($customer->user_id)) {
                // First try to find a subscription with the same email
                $subscription_user_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT cs.user_id 
                     FROM {$wpdb->prefix}fpr_customer_subscriptions cs
                     JOIN {$wpdb->prefix}users u ON u.ID = cs.user_id
                     WHERE cs.user_id IS NOT NULL 
                     AND u.user_email = %s
                     LIMIT 1",
                    $customer->email
                ));

                if ($subscription_user_id) {
                    $user_id = $subscription_user_id;
                    Logger::log("[FPRAmelia] Found user_id {$user_id} in fpr_customer_subscriptions for email {$customer->email}");
                } else {
                    // If not found, try to find any subscription with a user_id
                    $subscription_query = $wpdb->prepare(
                        "SELECT cs.user_id, u.user_email
                         FROM {$wpdb->prefix}fpr_customer_subscriptions cs
                         JOIN {$wpdb->prefix}users u ON u.ID = cs.user_id
                         WHERE cs.user_id IS NOT NULL
                         LIMIT 100"
                    );
                    $subscriptions = $wpdb->get_results($subscription_query);

                    if ($subscriptions) {
                        foreach ($subscriptions as $subscription) {
                            // Check if this user has any other customers with the same email
                            $other_customer = $wpdb->get_row($wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}fpr_customers 
                                 WHERE email = %s AND user_id = %d",
                                $customer->email, $subscription->user_id
                            ));

                            if ($other_customer) {
                                $user_id = $subscription->user_id;
                                Logger::log("[FPRAmelia] Found user_id {$user_id} from another customer with same email {$customer->email}");
                                break;
                            }
                        }
                    }
                }
            }

            // If we found a user_id and it's different from the current one, update it
            if ($user_id && (empty($customer->user_id) || $customer->user_id != $user_id)) {
                // Update customer with user_id
                $result = $wpdb->update(
                    $wpdb->prefix . 'fpr_customers',
                    ['user_id' => $user_id],
                    ['id' => $customer->id]
                );

                if ($result !== false) {
                    $updated_count++;
                    $updated_user_ids[] = $user_id; // Track this user_id
                    // Only log if updating a specific customer or if there are few updates
                    if ($specific_email !== null || $updated_count <= 5) {
                        Logger::log("[FPRAmelia] Updated customer ID {$customer->id} with user_id {$user_id}");
                    }
                }
            } elseif (!empty($customer->user_id)) {
                // If customer already has a user_id, track it
                $updated_user_ids[] = $customer->user_id;
            }
        }

        // For each user_id we've updated, check if there are other customers with different emails
        // that should be linked to the same user_id
        foreach ($updated_user_ids as $user_id) {
            // Get the user's email
            $user = get_userdata($user_id);
            if (!$user) continue;

            // Find all customers with the same user_id
            $customers_with_user_id = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_customers WHERE user_id = %d",
                $user_id
            ));

            if (empty($customers_with_user_id)) continue;

            // Get all emails associated with this user_id
            $emails = [];
            foreach ($customers_with_user_id as $c) {
                $emails[] = $c->email;
            }
            $emails[] = $user->user_email; // Add the user's primary email

            // Find all customers with any of these emails but without this user_id
            $placeholders = implode(',', array_fill(0, count($emails), '%s'));
            $query = $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_customers 
                 WHERE email IN ($placeholders) AND (user_id IS NULL OR user_id != %d)",
                array_merge($emails, [$user_id])
            );
            $customers_to_update = $wpdb->get_results($query);

            // Update these customers with the correct user_id
            foreach ($customers_to_update as $c) {
                $result = $wpdb->update(
                    $wpdb->prefix . 'fpr_customers',
                    ['user_id' => $user_id],
                    ['id' => $c->id]
                );

                if ($result !== false) {
                    $updated_count++;
                    Logger::log("[FPRAmelia] Updated customer ID {$c->id} with user_id {$user_id} (linked via email association)");
                }
            }
        }

        // Log a summary instead of individual updates if there are many
        if ($updated_count > 5 && $specific_email === null) {
            Logger::log("[FPRAmelia] Updated $updated_count customers with user_id");
        }

        return $updated_count;
    }
}
