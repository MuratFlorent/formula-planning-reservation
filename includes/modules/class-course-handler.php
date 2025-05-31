<?php
namespace FPR\Modules;

if (!defined('ABSPATH')) exit;

use FPR\Helpers\Logger;

class CourseHandler {
    public static function init() {
        // Hook into WooCommerce order processing to store courses in subscriptions
        add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_order_courses'], 9, 4);
        
        // Add AJAX handler for importing courses to Amelia
        add_action('wp_ajax_fpr_import_courses_to_amelia', [__CLASS__, 'import_courses_to_amelia']);
        
        // Add admin menu item for course management
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
    }
    
    /**
     * Add admin menu item for course management
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'fpr-settings',
            'Gestion des cours',
            'Cours',
            'manage_options',
            'fpr-courses',
            [__CLASS__, 'render_courses_page']
        );
    }
    
    /**
     * Render the courses management page
     */
    public static function render_courses_page() {
        include FPR_PLUGIN_DIR . 'templates/admin/courses.php';
    }
    
    /**
     * Handle order courses when order status changes
     * 
     * @param int $order_id Order ID
     * @param string $old_status Old order status
     * @param string $new_status New order status
     * @param object $order Order object
     */
    public static function handle_order_courses($order_id, $old_status, $new_status, $order) {
        // Only process orders that are being completed or processed
        if (!in_array($new_status, ['processing', 'completed'])) return;
        
        // Get subscription ID if it exists
        global $wpdb;
        $user_id = $order->get_user_id();
        
        if (!$user_id) return;
        
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_customer_subscriptions 
            WHERE user_id = %d AND order_id = %d
            ORDER BY id DESC LIMIT 1",
            $user_id, $order_id
        ));
        
        if (!$subscription) return;
        
        // Get selected courses from order meta
        $selected_courses = [];
        
        foreach ($order->get_items() as $item) {
            foreach ($item->get_meta_data() as $meta) {
                if (strpos($meta->key, 'Cours') !== false) {
                    $selected_courses[] = $meta->value;
                }
            }
        }
        
        if (empty($selected_courses)) return;
        
        // Process each selected course
        foreach ($selected_courses as $course_name) {
            // Find or create course
            $course_id = self::find_or_create_course($course_name);
            
            if (!$course_id) continue;
            
            // Link course to subscription
            self::link_course_to_subscription($subscription->id, $course_id);
        }
    }
    
    /**
     * Find or create a course
     * 
     * @param string $course_name Course name
     * @return int|false Course ID or false on failure
     */
    public static function find_or_create_course($course_name) {
        global $wpdb;
        
        // Extract course details from name
        $course_details = self::extract_course_details($course_name);
        
        if (!$course_details) {
            Logger::log("Impossible d'extraire les détails du cours: $course_name");
            return false;
        }
        
        // Check if course already exists
        $course = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_courses 
            WHERE name = %s",
            $course_details['name']
        ));
        
        if ($course) {
            return $course->id;
        }
        
        // Create new course
        $result = $wpdb->insert(
            $wpdb->prefix . 'fpr_courses',
            [
                'name' => $course_details['name'],
                'description' => '',
                'duration' => $course_details['duration'],
                'instructor' => $course_details['instructor'],
                'day_of_week' => '',
                'start_time' => $course_details['start_time'],
                'end_time' => $course_details['end_time'],
                'capacity' => 0,
                'status' => 'active'
            ]
        );
        
        if ($result === false) {
            Logger::log("Erreur lors de la création du cours: " . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Extract course details from course name
     * 
     * @param string $course_name Course name
     * @return array|false Course details or false on failure
     */
    private static function extract_course_details($course_name) {
        // Example: "Pilates | 12:30 - 13:30 | 1h | avec Vanessa"
        $parts = explode('|', $course_name);
        
        if (count($parts) < 3) {
            return false;
        }
        
        $name = trim($parts[0]);
        
        // Extract time
        $time_part = trim($parts[1]);
        $time_matches = [];
        if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $time_part, $time_matches)) {
            $start_time = $time_matches[1];
            $end_time = $time_matches[2];
        } else {
            $start_time = null;
            $end_time = null;
        }
        
        // Extract duration
        $duration = trim($parts[2]);
        
        // Extract instructor if available
        $instructor = '';
        if (isset($parts[3]) && strpos($parts[3], 'avec') !== false) {
            $instructor = trim(str_replace('avec', '', $parts[3]));
        }
        
        return [
            'name' => $name,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'duration' => $duration,
            'instructor' => $instructor
        ];
    }
    
    /**
     * Link a course to a subscription
     * 
     * @param int $subscription_id Subscription ID
     * @param int $course_id Course ID
     * @return bool Success or failure
     */
    public static function link_course_to_subscription($subscription_id, $course_id) {
        global $wpdb;
        
        // Check if link already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}fpr_subscription_courses 
            WHERE subscription_id = %d AND course_id = %d",
            $subscription_id, $course_id
        ));
        
        if ($existing) {
            return true;
        }
        
        // Create new link
        $result = $wpdb->insert(
            $wpdb->prefix . 'fpr_subscription_courses',
            [
                'subscription_id' => $subscription_id,
                'course_id' => $course_id,
                'status' => 'active'
            ]
        );
        
        if ($result === false) {
            Logger::log("Erreur lors de la liaison du cours à l'abonnement: " . $wpdb->last_error);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get all courses
     * 
     * @return array Courses
     */
    public static function get_all_courses() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}fpr_courses 
            ORDER BY name ASC"
        );
    }
    
    /**
     * Get courses for a subscription
     * 
     * @param int $subscription_id Subscription ID
     * @return array Courses
     */
    public static function get_subscription_courses($subscription_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.* 
            FROM {$wpdb->prefix}fpr_courses c
            JOIN {$wpdb->prefix}fpr_subscription_courses sc ON c.id = sc.course_id
            WHERE sc.subscription_id = %d AND sc.status = 'active'
            ORDER BY c.name ASC",
            $subscription_id
        ));
    }
    
    /**
     * Import courses to Amelia
     * AJAX handler for admin action
     */
    public static function import_courses_to_amelia() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fpr_import_courses')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Get subscription ID
        $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;
        
        if (!$subscription_id) {
            wp_send_json_error('Invalid subscription ID');
        }
        
        // Get subscription details
        global $wpdb;
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_customer_subscriptions WHERE id = %d",
            $subscription_id
        ));
        
        if (!$subscription) {
            wp_send_json_error('Subscription not found');
        }
        
        // Get user details
        $user = get_userdata($subscription->user_id);
        
        if (!$user) {
            wp_send_json_error('User not found');
        }
        
        // Get courses for this subscription
        $courses = self::get_subscription_courses($subscription_id);
        
        if (empty($courses)) {
            wp_send_json_error('No courses found for this subscription');
        }
        
        // Get saison
        $saison = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_saisons WHERE id = %d",
            $subscription->saison_id
        ));
        
        if (!$saison) {
            wp_send_json_error('Saison not found');
        }
        
        // Import each course to Amelia
        $results = [];
        
        foreach ($courses as $course) {
            // Format course name for Amelia
            $course_name = $course->name;
            if ($course->instructor) {
                $course_name .= " avec " . $course->instructor;
            }
            
            // Call WooToAmelia to register the user
            $result = WooToAmelia::register_in_amelia_by_tag(
                $user->first_name,
                $user->last_name,
                $user->user_email,
                $user->billing_phone ?? '',
                $course_name,
                'Importé depuis abonnement #' . $subscription_id,
                $subscription->total_amount,
                $saison->tag,
                $subscription->stripe_subscription_id,
                $subscription->payment_plan_id,
                $subscription->order_id
            );
            
            $results[] = [
                'course' => $course_name,
                'success' => $result !== false,
                'message' => $result !== false ? 'Success' : 'Failed'
            ];
        }
        
        wp_send_json_success([
            'message' => 'Import completed',
            'results' => $results
        ]);
    }
}