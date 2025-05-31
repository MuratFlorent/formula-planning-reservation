<?php
namespace FPR\Admin;

if (!defined('ABSPATH')) exit;

use FPR\Helpers\Logger;
use FPR\Modules\FPRUser;

/**
 * Class Migration
 * 
 * This class handles database migrations and cleanup for the Formula Planning Reservation plugin.
 */
class Migration {
    /**
     * Initialize the module
     */
    public static function init() {
        // Add admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);

        // AJAX handlers for admin
        add_action('wp_ajax_fpr_migrate_customers_to_users', [__CLASS__, 'ajax_migrate_customers_to_users']);
        add_action('wp_ajax_fpr_sync_amelia_customer_ids', [__CLASS__, 'ajax_sync_amelia_customer_ids']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'fpr-settings',
            'Migration & Cleanup',
            'Migration & Cleanup',
            'manage_options',
            'fpr-migration',
            [__CLASS__, 'render_migration_page']
        );
    }

    /**
     * Render migration page
     */
    public static function render_migration_page() {
        global $wpdb;

        // Count customers and users
        $customer_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fpr_customers");
        $user_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fpr_users");

        // Count customers without user_id
        $customers_without_user_id = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fpr_customers WHERE user_id IS NULL OR user_id = 0");

        // Count customers without amelia_customer_id
        $customers_without_amelia_id = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fpr_customers WHERE amelia_customer_id IS NULL");

        // Count users without amelia_customer_id
        $users_without_amelia_id = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fpr_users WHERE amelia_customer_id IS NULL");

        ?>
        <div class="wrap">
            <h1>Migration & Cleanup</h1>

            <div class="notice notice-info">
                <p>This page allows you to migrate data from the old fpr_customers table to the new fpr_users table, and perform cleanup operations.</p>
            </div>

            <h2>Database Statistics</h2>
            <table class="widefat" style="max-width: 600px; margin-bottom: 20px;">
                <tbody>
                    <tr>
                        <td><strong>Total customers in fpr_customers:</strong></td>
                        <td><?php echo $customer_count; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total users in fpr_users:</strong></td>
                        <td><?php echo $user_count; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Customers without user_id:</strong></td>
                        <td><?php echo $customers_without_user_id; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Customers without amelia_customer_id:</strong></td>
                        <td><?php echo $customers_without_amelia_id; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Users without amelia_customer_id:</strong></td>
                        <td><?php echo $users_without_amelia_id; ?></td>
                    </tr>
                </tbody>
            </table>

            <h2>Migration Actions</h2>
            <div class="card" style="max-width: 600px; padding: 20px; margin-bottom: 20px;">
                <h3>Migrate Customers to Users</h3>
                <p>This action will migrate all customers from the fpr_customers table to the fpr_users table, preserving all data and relationships.</p>
                <button id="migrate-customers-btn" class="button button-primary">Migrate Customers to Users</button>
                <div id="migration-progress" style="margin-top: 10px; display: none;">
                    <div class="spinner is-active" style="float: left; margin-right: 10px;"></div>
                    <span id="migration-status">Migration in progress...</span>
                </div>
            </div>

            <h2>Cleanup Actions</h2>
            <div class="card" style="max-width: 600px; padding: 20px; margin-bottom: 20px;">
                <h3>Sync Amelia Customer IDs</h3>
                <p>This action will sync Amelia customer IDs for all users in the fpr_users table.</p>
                <button id="sync-amelia-ids-btn" class="button button-primary">Sync Amelia Customer IDs</button>
                <div id="sync-progress" style="margin-top: 10px; display: none;">
                    <div class="spinner is-active" style="float: left; margin-right: 10px;"></div>
                    <span id="sync-status">Sync in progress...</span>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Migrate customers to users
                $('#migrate-customers-btn').on('click', function() {
                    if (!confirm('Are you sure you want to migrate customers to users? This action cannot be undone.')) {
                        return;
                    }

                    $('#migration-progress').show();
                    $('#migrate-customers-btn').prop('disabled', true);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fpr_migrate_customers_to_users',
                            nonce: '<?php echo wp_create_nonce('fpr_migration'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#migration-status').html('Migration completed successfully. ' + response.data.message);
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                $('#migration-status').html('Migration failed: ' + response.data);
                                $('#migrate-customers-btn').prop('disabled', false);
                            }
                        },
                        error: function() {
                            $('#migration-status').html('Migration failed due to a server error.');
                            $('#migrate-customers-btn').prop('disabled', false);
                        }
                    });
                });

                // Sync Amelia customer IDs
                $('#sync-amelia-ids-btn').on('click', function() {
                    $('#sync-progress').show();
                    $('#sync-amelia-ids-btn').prop('disabled', true);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fpr_sync_amelia_customer_ids',
                            nonce: '<?php echo wp_create_nonce('fpr_migration'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#sync-status').html('Sync completed successfully. ' + response.data.message);
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                $('#sync-status').html('Sync failed: ' + response.data);
                                $('#sync-amelia-ids-btn').prop('disabled', false);
                            }
                        },
                        error: function() {
                            $('#sync-status').html('Sync failed due to a server error.');
                            $('#sync-amelia-ids-btn').prop('disabled', false);
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX handler for migrating customers to users
     */
    public static function ajax_migrate_customers_to_users() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fpr_migration')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Get all customers from fpr_customers
            $customers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fpr_customers");

            if (empty($customers)) {
                wp_send_json_success(['message' => 'No customers found to migrate.']);
                return;
            }

            $migrated_count = 0;
            $skipped_count = 0;

            foreach ($customers as $customer) {
                // Check if user already exists in fpr_users with the same email
                $existing_user = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}fpr_users WHERE email = %s",
                    $customer->email
                ));

                if ($existing_user) {
                    // User already exists, update it if needed
                    $update_data = [];

                    // Update wp_user_id if it's empty and customer has user_id
                    if (empty($existing_user->wp_user_id) && !empty($customer->user_id)) {
                        $update_data['wp_user_id'] = $customer->user_id;
                    }

                    // Update amelia_customer_id if it's empty and customer has amelia_customer_id
                    if (empty($existing_user->amelia_customer_id) && !empty($customer->amelia_customer_id)) {
                        $update_data['amelia_customer_id'] = $customer->amelia_customer_id;
                    }

                    // Update other fields if they're empty
                    if (empty($existing_user->firstName) && !empty($customer->firstName)) {
                        $update_data['firstName'] = $customer->firstName;
                    }

                    if (empty($existing_user->lastName) && !empty($customer->lastName)) {
                        $update_data['lastName'] = $customer->lastName;
                    }

                    if (empty($existing_user->phone) && !empty($customer->phone)) {
                        $update_data['phone'] = $customer->phone;
                    }

                    if (!empty($update_data)) {
                        $result = $wpdb->update(
                            $wpdb->prefix . 'fpr_users',
                            $update_data,
                            ['id' => $existing_user->id]
                        );

                        if ($result !== false) {
                            $migrated_count++;
                            Logger::log("[Migration] Updated existing FPR user ID={$existing_user->id} with data from customer ID={$customer->id}");
                        } else {
                            Logger::log("[Migration] Failed to update existing FPR user ID={$existing_user->id}: " . $wpdb->last_error);
                            $skipped_count++;
                        }
                    } else {
                        $skipped_count++;
                        Logger::log("[Migration] Skipped customer ID={$customer->id}, email={$customer->email} - FPR user already exists with ID={$existing_user->id} and has all data");
                    }
                } else {
                    // Create new FPR user
                    $result = $wpdb->insert(
                        $wpdb->prefix . 'fpr_users',
                        [
                            'wp_user_id' => $customer->user_id,
                            'amelia_customer_id' => $customer->amelia_customer_id,
                            'email' => $customer->email,
                            'firstName' => $customer->firstName,
                            'lastName' => $customer->lastName,
                            'phone' => $customer->phone,
                            'status' => $customer->status
                        ]
                    );

                    if ($result !== false) {
                        $fpr_user_id = $wpdb->insert_id;
                        $migrated_count++;
                        Logger::log("[Migration] Created new FPR user ID={$fpr_user_id} from customer ID={$customer->id}");
                    } else {
                        Logger::log("[Migration] Failed to create FPR user from customer ID={$customer->id}: " . $wpdb->last_error);
                        $skipped_count++;
                    }
                }
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            wp_send_json_success([
                'message' => "Migration completed. Migrated: $migrated_count, Skipped: $skipped_count"
            ]);
        } catch (\Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            Logger::log("[Migration] Error: " . $e->getMessage());
            wp_send_json_error('Migration failed: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for syncing Amelia customer IDs
     */
    public static function ajax_sync_amelia_customer_ids() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fpr_migration')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Get all users from fpr_users without Amelia customer ID
            $users = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fpr_users WHERE amelia_customer_id IS NULL");

            if (empty($users)) {
                wp_send_json_success(['message' => 'No users found without Amelia customer ID.']);
                return;
            }

            $synced_count = 0;
            $failed_count = 0;

            foreach ($users as $user) {
                // Check if Amelia customer exists for this email
                $amelia_customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}amelia_customers WHERE email = %s",
                    $user->email
                ));

                if ($amelia_customer) {
                    // Amelia customer found, update FPR user
                    $result = $wpdb->update(
                        $wpdb->prefix . 'fpr_users',
                        ['amelia_customer_id' => $amelia_customer->id],
                        ['id' => $user->id]
                    );

                    if ($result !== false) {
                        $synced_count++;
                        Logger::log("[Migration] Updated FPR user ID={$user->id} with Amelia customer ID={$amelia_customer->id}");
                    } else {
                        Logger::log("[Migration] Failed to update FPR user ID={$user->id}: " . $wpdb->last_error);
                        $failed_count++;
                    }
                } else {
                    // No Amelia customer found, create one
                    $amelia_data = [
                        'firstName' => $user->firstName,
                        'lastName' => $user->lastName,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'status' => 'visible',
                        'type' => 'customer'
                    ];

                    $result = $wpdb->insert(
                        $wpdb->prefix . 'amelia_customers',
                        $amelia_data
                    );

                    if ($result !== false) {
                        $amelia_customer_id = $wpdb->insert_id;

                        // Update FPR user with new Amelia customer ID
                        $update_result = $wpdb->update(
                            $wpdb->prefix . 'fpr_users',
                            ['amelia_customer_id' => $amelia_customer_id],
                            ['id' => $user->id]
                        );

                        if ($update_result !== false) {
                            $synced_count++;
                            Logger::log("[Migration] Created Amelia customer ID={$amelia_customer_id} and updated FPR user ID={$user->id}");
                        } else {
                            Logger::log("[Migration] Created Amelia customer ID={$amelia_customer_id} but failed to update FPR user ID={$user->id}: " . $wpdb->last_error);
                            $failed_count++;
                        }
                    } else {
                        Logger::log("[Migration] Failed to create Amelia customer for FPR user ID={$user->id}: " . $wpdb->last_error);
                        $failed_count++;
                    }
                }
            }

            // Commit transaction
            $wpdb->query('COMMIT');

            wp_send_json_success([
                'message' => "Sync completed. Synced: $synced_count, Failed: $failed_count"
            ]);
        } catch (\Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            Logger::log("[Migration] Error: " . $e->getMessage());
            wp_send_json_error('Sync failed: ' . $e->getMessage());
        }
    }
}
