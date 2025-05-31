<?php
namespace FPR\Admin;

if (!defined('ABSPATH')) exit;

class Subscribers {
    public static function init() {
        add_action('admin_post_fpr_create_subscriber', [__CLASS__, 'handle_create_subscriber']);
        add_action('admin_post_fpr_update_subscriber', [__CLASS__, 'handle_update_subscriber']);
        add_action('admin_post_fpr_delete_subscriber', [__CLASS__, 'handle_delete_subscriber']);

        // Export handlers
        add_action('admin_post_fpr_export_subscribers_csv', [__CLASS__, 'handle_export_subscribers_csv']);
        add_action('admin_post_fpr_export_subscribers_excel', [__CLASS__, 'handle_export_subscribers_excel']);

        // AJAX handlers pour le modal utilisateur
        add_action('wp_ajax_fpr_get_user_subscriptions', [__CLASS__, 'ajax_get_user_subscriptions']);
        add_action('wp_ajax_fpr_update_user_details', [__CLASS__, 'ajax_update_user_details']);
        add_action('wp_ajax_fpr_get_user_invoices', [__CLASS__, 'ajax_get_user_invoices']);
        add_action('wp_ajax_fpr_create_user', [__CLASS__, 'ajax_create_user']);
        add_action('wp_ajax_fpr_bulk_delete_subscribers', [__CLASS__, 'ajax_bulk_delete_subscribers']);

        // Ajouter un hook pour vérifier les erreurs PHP
        add_action('shutdown', [__CLASS__, 'check_for_fatal_errors']);
    }

    /**
     * Gère la création d'un nouvel utilisateur via AJAX
     */
    public static function ajax_create_user() {
        // Vérifier le nonce de sécurité
        check_ajax_referer('fpr_admin_nonce', 'nonce');

        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissions insuffisantes']);
            return;
        }

        // Récupérer les données du formulaire
        $username = isset($_POST['username']) ? sanitize_user($_POST['username']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';

        // Valider les données
        if (empty($username) || empty($email)) {
            wp_send_json_error(['message' => 'Le nom d\'utilisateur et l\'email sont requis']);
            return;
        }

        // Vérifier si l'utilisateur existe déjà
        if (username_exists($username) || email_exists($email)) {
            wp_send_json_error(['message' => 'Cet utilisateur ou cette adresse email existe déjà']);
            return;
        }

        // Générer un mot de passe aléatoire
        $password = wp_generate_password(12, true);

        // Créer l'utilisateur
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error(['message' => $user_id->get_error_message()]);
            return;
        }

        // Mettre à jour les informations supplémentaires
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => 'customer'
        ]);

        // Envoyer un email à l'utilisateur avec son mot de passe
        wp_mail(
            $email,
            'Votre compte a été créé',
            "Bonjour $first_name,\n\nVotre compte a été créé avec succès.\n\nNom d'utilisateur: $username\nMot de passe: $password\n\nVous pouvez vous connecter à l'adresse suivante: " . wp_login_url(),
            ['Content-Type: text/plain; charset=UTF-8']
        );

        // Récupérer les informations de l'utilisateur pour les renvoyer
        $user = get_userdata($user_id);

        // Renvoyer une réponse de succès
        wp_send_json_success([
            'message' => 'Utilisateur créé avec succès',
            'user' => [
                'id' => $user_id,
                'username' => $username,
                'email' => $email,
                'display_name' => $user->display_name
            ]
        ]);
    }

    /**
     * Enqueue scripts and styles for the subscribers page
     */
    public static function enqueue_scripts_and_styles() {
        // Enqueue Select2 from WooCommerce
        wp_enqueue_style('select2', WC()->plugin_url() . '/assets/css/select2.css', [], '4.0.3');
        wp_enqueue_script('select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', ['jquery'], '4.0.3', true);

        // Enqueue Bootstrap JS for modal (assuming Bootstrap CSS is already loaded)
        wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js', [], '5.1.3', true);

        // Enqueue custom scripts and styles
        wp_enqueue_style('fpr-subscribers', FPR_PLUGIN_URL . 'assets/css/subscribers.css', [], FPR_VERSION);
        wp_enqueue_script('fpr-subscribers', FPR_PLUGIN_URL . 'assets/js/subscribers.js', ['jquery', 'select2', 'bootstrap'], FPR_VERSION, true);

        // Localize script for AJAX
        wp_localize_script('fpr-subscribers', 'fprAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fpr_admin_nonce'),
            'i18n' => [
                'loading' => 'Chargement...',
                'noInvoices' => 'Aucune facture trouvée pour cet utilisateur.',
                'error' => 'Une erreur est survenue lors du chargement des factures.'
            ]
        ]);

        // Add dashicons for the plus button icon
        wp_enqueue_style('dashicons');

        // Add custom CSS for the export modal
        echo '<style>
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0,0,0,0.4);
            }

            .modal-content {
                background-color: #fefefe;
                margin: 5% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 800px;
                border-radius: 5px;
            }

            .close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }

            .close:hover,
            .close:focus {
                color: black;
                text-decoration: none;
            }

            .export-fields-container {
                display: flex;
                flex-wrap: wrap;
                margin-bottom: 20px;
            }

            .export-section {
                flex: 1;
                min-width: 200px;
                margin-right: 20px;
                margin-bottom: 20px;
            }

            .export-section h3 {
                margin-top: 0;
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 1px solid #ddd;
            }

            .export-section label {
                display: block;
                margin-bottom: 5px;
            }

            .export-actions {
                margin-top: 20px;
                padding-top: 10px;
                border-top: 1px solid #ddd;
                text-align: right;
            }

            .export-actions label {
                float: left;
            }
        </style>';

        // Add JavaScript for the export modal
        echo '<script>
            jQuery(document).ready(function($) {
                // Export modal functionality
                $(".open-export-modal").on("click", function() {
                    var format = $(this).data("format");
                    $("#export-action").val("fpr_export_subscribers_" + format);
                    $("#export-modal").show();
                });

                $(".close, .cancel-export").on("click", function() {
                    $("#export-modal").hide();
                });

                // Select all fields
                $("#select-all-fields").on("change", function() {
                    $("input[name=\'export_fields[]\']").prop("checked", $(this).prop("checked"));
                });

                // Update select all when individual checkboxes change
                $("input[name=\'export_fields[]\']").on("change", function() {
                    var allChecked = $("input[name=\'export_fields[]\']:checked").length === $("input[name=\'export_fields[]\']").length;
                    $("#select-all-fields").prop("checked", allChecked);
                });

                // Close modal when clicking outside
                $(window).on("click", function(event) {
                    if ($(event.target).is("#export-modal")) {
                        $("#export-modal").hide();
                    }
                });
            });
        </script>';
    }

    /**
     * Vérifie s'il y a eu des erreurs fatales et les enregistre dans le journal
     */
    public static function check_for_fatal_errors() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR])) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ ERREUR FATALE: " . $error['message'] . " dans " . $error['file'] . " ligne " . $error['line']);
        }
    }

    public static function render_page() {
        global $wpdb;

        // Enqueue scripts and styles for the subscribers page
        self::enqueue_scripts_and_styles();

        \FPR\Helpers\Logger::log("[Subscribers] Début du rendu de la page des abonnés");
        ?>
        <div class="wrap">
            <h1>Gestion des abonnés</h1>

            <h2>Créer un nouvel abonnement</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="fpr-form">
                <input type="hidden" name="action" value="fpr_create_subscriber">
                <?php wp_nonce_field('fpr_create_subscriber_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="user_id">Utilisateur</label></th>
                        <td>
                            <div class="user-selection-container">
                                <select id="user_id" name="user_id" required>
                                    <option value="">Sélectionner un utilisateur</option>
                                    <?php
                                    $users = get_users();
                                    foreach ($users as $user) {
                                        echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</option>';
                                    }
                                    ?>
                                </select>
                                <button type="button" class="button create-new-user-btn">Créer un utilisateur</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="payment_plan_id">Plan de paiement</label></th>
                        <td>
                            <select id="payment_plan_id" name="payment_plan_id" required>
                                <option value="">Sélectionner un plan de paiement</option>
                                <?php
                                $payment_plans = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE active = 1 ORDER BY name ASC");
                                foreach ($payment_plans as $plan) {
                                    echo '<option value="' . esc_attr($plan->id) . '">' . esc_html($plan->name) . ' (' . esc_html($plan->installments) . ' versements)</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="saison_id">Saison</label></th>
                        <td>
                            <select id="saison_id" name="saison_id" required>
                                <option value="">Sélectionner une saison</option>
                                <?php
                                $saisons = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fpr_saisons WHERE end_date >= CURDATE() ORDER BY name ASC");
                                foreach ($saisons as $saison) {
                                    echo '<option value="' . esc_attr($saison->id) . '">' . esc_html($saison->name) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="status">Statut</label></th>
                        <td>
                            <select id="status" name="status" required>
                                <option value="active">Actif</option>
                                <option value="pending">En attente</option>
                                <option value="cancelled">Annulé</option>
                                <option value="expired">Expiré</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="start_date">Date de début</label></th>
                        <td><input type="date" id="start_date" name="start_date" required></td>
                    </tr>
                    <tr>
                        <th><label for="end_date">Date de fin</label></th>
                        <td><input type="date" id="end_date" name="end_date"></td>
                    </tr>
                    <tr>
                        <th><label for="total_amount">Montant total</label></th>
                        <td><input type="number" id="total_amount" name="total_amount" step="0.01" required></td>
                    </tr>
                    <tr>
                        <th><label for="installment_amount">Montant par versement</label></th>
                        <td><input type="number" id="installment_amount" name="installment_amount" step="0.01" required></td>
                    </tr>
                    <tr>
                        <th><label for="installments_paid">Versements payés</label></th>
                        <td><input type="number" id="installments_paid" name="installments_paid" value="0" min="0" required></td>
                    </tr>
                    <tr>
                        <th><label for="stripe_subscription_id">ID d'abonnement Stripe</label></th>
                        <td><input type="text" id="stripe_subscription_id" name="stripe_subscription_id"></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Créer l'abonnement</button>
                </p>
            </form>
            <?php 
            if (isset($_GET['done'])) echo "<div class='updated'><p>Abonnement créé avec succès !</p></div>";
            if (isset($_GET['updated'])) echo "<div class='updated'><p>Abonnement mis à jour avec succès !</p></div>";
            if (isset($_GET['deleted'])) echo "<div class='updated'><p>Abonnement supprimé avec succès !</p></div>";
            ?>

            <!-- Modal d'export -->
            <div id="export-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Sélectionner les champs à exporter</h2>
                    <form id="export-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="fpr_export_subscribers_csv" id="export-action">
                        <?php wp_nonce_field('fpr_export_subscribers_nonce'); ?>

                        <div class="export-fields-container">
                            <div class="export-section">
                                <h3>Informations de base</h3>
                                <label><input type="checkbox" name="export_fields[]" value="id" checked> ID</label>
                                <label><input type="checkbox" name="export_fields[]" value="user_name" checked> Nom</label>
                                <label><input type="checkbox" name="export_fields[]" value="user_email" checked> Email</label>
                                <label><input type="checkbox" name="export_fields[]" value="user_phone" checked> Téléphone</label>
                                <label><input type="checkbox" name="export_fields[]" value="plan_name" checked> Plan de paiement</label>
                                <label><input type="checkbox" name="export_fields[]" value="saison_name" checked> Saison</label>
                                <label><input type="checkbox" name="export_fields[]" value="status" checked> Statut</label>
                                <label><input type="checkbox" name="export_fields[]" value="start_date" checked> Date de début</label>
                                <label><input type="checkbox" name="export_fields[]" value="end_date" checked> Date de fin</label>
                                <label><input type="checkbox" name="export_fields[]" value="total_amount" checked> Montant total</label>
                                <label><input type="checkbox" name="export_fields[]" value="installment_amount" checked> Montant par versement</label>
                                <label><input type="checkbox" name="export_fields[]" value="installments_paid" checked> Versements payés</label>
                                <label><input type="checkbox" name="export_fields[]" value="stripe_subscription_id" checked> ID Stripe</label>
                                <label><input type="checkbox" name="export_fields[]" value="customer_note" checked> Notes</label>
                                <label><input type="checkbox" name="export_fields[]" value="created_at" checked> Date de création</label>
                            </div>

                            <div class="export-section">
                                <h3>Adresse</h3>
                                <label><input type="checkbox" name="export_fields[]" value="billing_address_1"> Adresse 1</label>
                                <label><input type="checkbox" name="export_fields[]" value="billing_address_2"> Adresse 2</label>
                                <label><input type="checkbox" name="export_fields[]" value="billing_city"> Ville</label>
                                <label><input type="checkbox" name="export_fields[]" value="billing_state"> Région</label>
                                <label><input type="checkbox" name="export_fields[]" value="billing_postcode"> Code postal</label>
                                <label><input type="checkbox" name="export_fields[]" value="billing_country"> Pays</label>
                                <label><input type="checkbox" name="export_fields[]" value="billing_company"> Société</label>
                            </div>
                        </div>

                        <div class="export-actions">
                            <label><input type="checkbox" id="select-all-fields"> Tout sélectionner</label>
                            <button type="submit" class="button button-primary">Exporter</button>
                            <button type="button" class="button cancel-export">Annuler</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Formulaire de modification (caché par défaut) -->
            <div id="edit-subscriber-form" style="display: none;">
                <h2>Modifier un abonnement</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="fpr-form">
                    <input type="hidden" name="action" value="fpr_update_subscriber">
                    <input type="hidden" name="subscription_id" id="edit-subscription-id">
                    <?php wp_nonce_field('fpr_update_subscriber_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="edit-user-id">Utilisateur</label></th>
                            <td>
                                <select id="edit-user-id" name="user_id" required>
                                    <option value="">Sélectionner un utilisateur</option>
                                    <?php
                                    foreach ($users as $user) {
                                        echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit-payment-plan-id">Plan de paiement</label></th>
                            <td>
                                <select id="edit-payment-plan-id" name="payment_plan_id" required>
                                    <option value="">Sélectionner un plan de paiement</option>
                                    <?php
                                    foreach ($payment_plans as $plan) {
                                        echo '<option value="' . esc_attr($plan->id) . '">' . esc_html($plan->name) . ' (' . esc_html($plan->installments) . ' versements)</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit-saison-id">Saison</label></th>
                            <td>
                                <select id="edit-saison-id" name="saison_id" required>
                                    <option value="">Sélectionner une saison</option>
                                    <?php
                                    foreach ($saisons as $saison) {
                                        echo '<option value="' . esc_attr($saison->id) . '">' . esc_html($saison->name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit-status">Statut</label></th>
                            <td>
                                <select id="edit-status" name="status" required>
                                    <option value="active">Actif</option>
                                    <option value="pending">En attente</option>
                                    <option value="cancelled">Annulé</option>
                                    <option value="expired">Expiré</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit-start-date">Date de début</label></th>
                            <td><input type="date" id="edit-start-date" name="start_date" required></td>
                        </tr>
                        <tr>
                            <th><label for="edit-end-date">Date de fin</label></th>
                            <td><input type="date" id="edit-end-date" name="end_date"></td>
                        </tr>
                        <tr>
                            <th><label for="edit-total-amount">Montant total</label></th>
                            <td><input type="number" id="edit-total-amount" name="total_amount" step="0.01" required></td>
                        </tr>
                        <tr>
                            <th><label for="edit-installment-amount">Montant par versement</label></th>
                            <td><input type="number" id="edit-installment-amount" name="installment_amount" step="0.01" required></td>
                        </tr>
                        <tr>
                            <th><label for="edit-installments-paid">Versements payés</label></th>
                            <td><input type="number" id="edit-installments-paid" name="installments_paid" min="0" required></td>
                        </tr>
                        <tr>
                            <th><label for="edit-stripe-subscription-id">ID d'abonnement Stripe</label></th>
                            <td><input type="text" id="edit-stripe-subscription-id" name="stripe_subscription_id"></td>
                        </tr>
                        <tr>
                            <th><label for="edit-customer-note">Notes client</label></th>
                            <td><textarea id="edit-customer-note" name="customer_note" rows="5" class="large-text"></textarea></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Mettre à jour</button>
                        <button type="button" class="button cancel-edit">Annuler</button>
                    </p>
                </form>
            </div>

            <h2>Abonnements existants</h2>
            <?php
            // Récupérer les abonnements existants
            \FPR\Helpers\Logger::log("[Subscribers] Récupération des abonnements existants");

            $query = "
                SELECT s.*, 
                       u.display_name as user_name, 
                       u.user_email as user_email,
                       p.name as plan_name,
                       p.frequency as plan_frequency,
                       sn.name as saison_name,
                       c.note as customer_note
                FROM {$wpdb->prefix}fpr_customer_subscriptions s
                LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
                LEFT JOIN {$wpdb->prefix}fpr_payment_plans p ON s.payment_plan_id = p.id
                LEFT JOIN {$wpdb->prefix}fpr_saisons sn ON s.saison_id = sn.id
                LEFT JOIN {$wpdb->prefix}fpr_customers c ON u.user_email = c.email
                ORDER BY s.created_at DESC
            ";

            \FPR\Helpers\Logger::log("[Subscribers] Exécution de la requête: " . $query);

            $subscriptions = $wpdb->get_results($query);

            \FPR\Helpers\Logger::log("[Subscribers] Nombre d'abonnements trouvés: " . count($subscriptions));

            // Récupérer les inscriptions Amelia pour chaque abonné
            if (!empty($subscriptions)) {
                $customer_emails = array_unique(array_filter(array_column($subscriptions, 'user_email')));
                $amelia_bookings = [];

                if (!empty($customer_emails)) {
                    \FPR\Helpers\Logger::log("[Subscribers] Récupération des réservations Amelia pour " . count($customer_emails) . " emails");

                    foreach ($customer_emails as $email) {
                        // Essayer d'abord avec les tables Amelia
                        $bookings = $wpdb->get_results($wpdb->prepare("
                            SELECT 
                                cb.id as booking_id,
                                c.email as customer_email,
                                e.name as event_name,
                                ep.periodStart as period_start,
                                cb.status as booking_status,
                                IFNULL(cb.formula, '') as formula
                            FROM 
                                {$wpdb->prefix}amelia_customer_bookings cb
                                JOIN {$wpdb->prefix}amelia_customers c ON cb.customerId = c.id
                                JOIN {$wpdb->prefix}amelia_events_periods ep ON cb.eventPeriodId = ep.id
                                JOIN {$wpdb->prefix}amelia_events e ON ep.eventId = e.id
                            WHERE 
                                c.email = %s
                            ORDER BY 
                                ep.periodStart DESC
                        ", $email));

                        // Si pas de résultats, essayer avec les tables FPR
                        if (empty($bookings)) {
                            $bookings = $wpdb->get_results($wpdb->prepare("
                                SELECT 
                                    cb.id as booking_id,
                                    c.email as customer_email,
                                    e.name as event_name,
                                    ep.periodStart as period_start,
                                    cb.status as booking_status,
                                    IFNULL(cb.formula, '') as formula
                                FROM 
                                    {$wpdb->prefix}fpr_customer_bookings cb
                                    JOIN {$wpdb->prefix}fpr_customers c ON cb.customerId = c.id
                                    JOIN {$wpdb->prefix}fpr_events_periods ep ON cb.eventPeriodId = ep.id
                                    JOIN {$wpdb->prefix}fpr_events e ON ep.eventId = e.id
                                WHERE 
                                    c.email = %s
                                ORDER BY 
                                    ep.periodStart DESC
                            ", $email));
                        }

                        // Récupérer également les formules des abonnements
                        $subscriptions_data = $wpdb->get_results($wpdb->prepare("
                            SELECT 
                                s.id as subscription_id,
                                p.name as plan_name,
                                p.frequency as plan_frequency,
                                sn.name as saison_name,
                                s.status,
                                s.start_date
                            FROM 
                                {$wpdb->prefix}fpr_customer_subscriptions s
                                JOIN {$wpdb->users} u ON s.user_id = u.ID
                                LEFT JOIN {$wpdb->prefix}fpr_payment_plans p ON s.payment_plan_id = p.id
                                LEFT JOIN {$wpdb->prefix}fpr_saisons sn ON s.saison_id = sn.id
                            WHERE 
                                u.user_email = %s
                            ORDER BY 
                                s.created_at DESC
                        ", $email));

                        // Ajouter les formules comme des "réservations" spéciales
                        foreach ($subscriptions_data as $sub) {
                            $formula_booking = new \stdClass();
                            $formula_booking->booking_id = 'sub_' . $sub->subscription_id;
                            $formula_booking->customer_email = $email;
                            // Ajouter la fréquence au nom du plan si disponible
                            $frequency_label = '';
                            if (!empty($sub->plan_frequency)) {
                                $frequency_labels = [
                                    'hourly' => 'horaire',
                                    'daily' => 'journalier',
                                    'weekly' => 'hebdomadaire',
                                    'monthly' => 'mensuel',
                                    'quarterly' => 'trimestriel',
                                    'annual' => 'annuel'
                                ];
                                $frequency_label = isset($frequency_labels[$sub->plan_frequency]) ? $frequency_labels[$sub->plan_frequency] : $sub->plan_frequency;
                                $frequency_label = ' (' . $frequency_label . ')';
                            }

                            $formula_booking->event_name = 'Formule: ' . $sub->plan_name . $frequency_label;
                            $formula_booking->period_start = $sub->start_date . ' 00:00:00';
                            $formula_booking->booking_status = $sub->status;
                            $formula_booking->formula = $sub->plan_name . $frequency_label . ' (' . $sub->saison_name . ')';

                            $bookings[] = $formula_booking;
                        }

                        if (!empty($bookings)) {
                            $amelia_bookings[$email] = $bookings;
                            \FPR\Helpers\Logger::log("[Subscribers] " . count($bookings) . " réservations/formules trouvées pour $email");
                        }
                    }
                }

                // Ajouter les informations de réservation à chaque abonnement
                foreach ($subscriptions as &$subscription) {
                    $subscription->amelia_bookings = '';

                    if (!empty($subscription->user_email) && isset($amelia_bookings[$subscription->user_email])) {
                        $bookings_info = [];
                        foreach ($amelia_bookings[$subscription->user_email] as $booking) {
                            $status_text = '';
                            switch ($booking->booking_status) {
                                case 'approved': $status_text = '✅ Confirmé'; break;
                                case 'pending': $status_text = '⏳ En attente'; break;
                                case 'canceled': $status_text = '❌ Annulé'; break;
                                case 'rejected': $status_text = '❌ Rejeté'; break;
                                case 'active': $status_text = '✅ Actif'; break;
                                case 'expired': $status_text = '❌ Expiré'; break;
                                default: $status_text = $booking->booking_status;
                            }

                            // Simplifier l'affichage pour ne montrer que le nom de la formule
                            if (!empty($booking->formula)) {
                                // Vérifier si la formule correspond au plan de l'abonnement actuel
                                if (strpos($booking->formula, $subscription->plan_name) !== false) {
                                    $bookings_info[] = $booking->formula;
                                }
                            } else {
                                $bookings_info[] = $booking->event_name;
                            }
                        }

                        if (!empty($bookings_info)) {
                            $subscription->amelia_bookings = implode("\n", $bookings_info);
                        }
                    }
                }
            }

            if (empty($subscriptions)) {
                \FPR\Helpers\Logger::log("[Subscribers] Aucun abonnement trouvé");
                echo '<p>Aucun abonnement n\'a été créé pour le moment.</p>';
            } else {
                \FPR\Helpers\Logger::log("[Subscribers] Affichage des abonnements");
                ?>
                <div class="subscribers-filter-container">
                    <div class="filter-controls">
                        <input type="text" id="subscribers-search" placeholder="Rechercher..." class="search-input">
                        <select id="status-filter">
                            <option value="">Tous les statuts</option>
                            <option value="active">Actif</option>
                            <option value="pending">En attente</option>
                            <option value="cancelled">Annulé</option>
                            <option value="expired">Expiré</option>
                        </select>
                        <select id="saison-filter">
                            <option value="">Toutes les saisons</option>
                            <?php
                            foreach ($saisons as $saison) {
                                echo '<option value="' . esc_attr($saison->id) . '">' . esc_html($saison->name) . '</option>';
                            }
                            ?>
                        </select>
                        <select id="user-filter">
                            <option value="">Tous les utilisateurs</option>
                            <?php
                            foreach ($users as $user) {
                                echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</option>';
                            }
                            ?>
                        </select>
                        <div class="export-buttons">
                            <button type="button" class="button open-export-modal" data-format="csv">Exporter CSV</button>
                            <button type="button" class="button open-export-modal" data-format="excel">Exporter Excel</button>
                        </div>
                    </div>
                    <div class="bulk-actions">
                        <select id="bulk-action">
                            <option value="">Actions groupées</option>
                            <option value="active">Marquer comme actif</option>
                            <option value="pending">Marquer comme en attente</option>
                            <option value="cancelled">Marquer comme annulé</option>
                            <option value="delete">Supprimer</option>
                        </select>
                        <button id="apply-bulk-action" class="button">Appliquer</button>
                        <span id="selected-count" class="selected-count">0 sélectionné</span>
                    </div>
                </div>
                <table class="wp-list-table widefat fixed striped subscribers-table">
                    <thead>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" id="select-all-subscribers">
                            </th>
                            <th>ID</th>
                            <th>Utilisateur</th>
                            <th>Plan de paiement</th>
                            <th>Saison</th>
                            <th>Notes</th>
                            <th>Statut</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Montant total</th>
                            <th>Versement</th>
                            <th>Payés</th>
                            <th>Factures</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subscriptions as $subscription) : 
                            $status_labels = [
                                'active' => 'Actif',
                                'pending' => 'En attente',
                                'cancelled' => 'Annulé',
                                'expired' => 'Expiré'
                            ];
                            $status_class = [
                                'active' => 'status-active',
                                'pending' => 'status-pending',
                                'cancelled' => 'status-inactive',
                                'expired' => 'status-inactive'
                            ];
                        ?>
                            <tr data-id="<?php echo esc_attr($subscription->id); ?>">
                                <td class="check-column">
                                    <input type="checkbox" class="subscriber-checkbox" value="<?php echo esc_attr($subscription->id); ?>">
                                </td>
                                <td><?php echo esc_html($subscription->id); ?></td>
                                <td>
                                    <a href="#" class="view-user-details" 
                                       data-user-id="<?php echo esc_attr($subscription->user_id); ?>"
                                       data-user-name="<?php echo esc_attr($subscription->user_name); ?>"
                                       data-user-email="<?php echo esc_attr($subscription->user_email); ?>">
                                        <?php 
                                        echo esc_html($subscription->user_name);
                                        echo '<br><small>' . esc_html($subscription->user_email) . '</small>';
                                        ?>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    echo esc_html($subscription->plan_name);
                                    if (!empty($subscription->plan_frequency)) {
                                        $frequency_labels = [
                                            'hourly' => 'horaire',
                                            'daily' => 'journalier',
                                            'weekly' => 'hebdomadaire',
                                            'monthly' => 'mensuel',
                                            'quarterly' => 'trimestriel',
                                            'annual' => 'annuel'
                                        ];
                                        $frequency_label = isset($frequency_labels[$subscription->plan_frequency]) ? $frequency_labels[$subscription->plan_frequency] : $subscription->plan_frequency;
                                        echo ' <small>(' . esc_html($frequency_label) . ')</small>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($subscription->saison_name); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($subscription->customer_note)) {
                                        echo '<div class="note-preview">' . esc_html(substr($subscription->customer_note, 0, 50)) . 
                                            (strlen($subscription->customer_note) > 50 ? '...' : '') . '</div>';
                                    } else {
                                        echo '<em>Aucune note</em>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="<?php echo esc_attr($status_class[$subscription->status] ?? 'status-pending'); ?>">
                                        <?php echo esc_html($status_labels[$subscription->status] ?? $subscription->status); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($subscription->start_date); ?></td>
                                <td><?php echo $subscription->end_date ? esc_html($subscription->end_date) : '-'; ?></td>
                                <td><?php echo esc_html(number_format($subscription->total_amount, 2)) . ' €'; ?></td>
                                <td><?php echo esc_html(number_format($subscription->installment_amount, 2)) . ' €'; ?></td>
                                <td><?php echo esc_html($subscription->installments_paid); ?></td>
                                <td class="invoices-column">
                                    <button type="button" class="button view-invoices" 
                                        data-user-id="<?php echo esc_attr($subscription->user_id); ?>"
                                        data-subscription-id="<?php echo esc_attr($subscription->id); ?>"
                                        data-user-name="<?php echo esc_attr($subscription->user_name); ?>">
                                        <span class="dashicons dashicons-media-text"></span> Voir
                                    </button>
                                </td>
                                <td class="actions-column">
                                    <div class="action-buttons">
                                        <a href="#" class="button button-icon edit-subscription" title="Modifier" 
                                           data-id="<?php echo esc_attr($subscription->id); ?>"
                                           data-user-id="<?php echo esc_attr($subscription->user_id); ?>"
                                           data-payment-plan-id="<?php echo esc_attr($subscription->payment_plan_id); ?>"
                                           data-saison-id="<?php echo esc_attr($subscription->saison_id); ?>"
                                           data-status="<?php echo esc_attr($subscription->status); ?>"
                                           data-start-date="<?php echo esc_attr($subscription->start_date); ?>"
                                           data-end-date="<?php echo esc_attr($subscription->end_date); ?>"
                                           data-total-amount="<?php echo esc_attr($subscription->total_amount); ?>"
                                           data-installment-amount="<?php echo esc_attr($subscription->installment_amount); ?>"
                                           data-installments-paid="<?php echo esc_attr($subscription->installments_paid); ?>"
                                           data-stripe-subscription-id="<?php echo esc_attr($subscription->stripe_subscription_id); ?>"
                                           data-customer-note="<?php echo esc_attr($subscription->customer_note); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet abonnement ? Cette action est irréversible.');" class="delete-form">
                                            <input type="hidden" name="action" value="fpr_delete_subscriber">
                                            <input type="hidden" name="subscription_id" value="<?php echo esc_attr($subscription->id); ?>">
                                            <?php wp_nonce_field('fpr_delete_subscriber_nonce'); ?>
                                            <button type="submit" class="button button-icon button-link-delete" title="Supprimer">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
            }

            \FPR\Helpers\Logger::log("[Subscribers] Fin du rendu de la page des abonnés");
            ?>

            <!-- Modal pour afficher les factures -->
            <div id="invoices-modal" class="fpr-modal">
                <div class="fpr-modal-content">
                    <span class="fpr-modal-close">&times;</span>
                    <h2>Factures pour <span id="invoice-user-name"></span></h2>
                    <div id="invoices-container">
                        <p class="loading">Chargement des factures...</p>
                        <table class="wp-list-table widefat fixed striped invoices-table" style="display: none;">
                            <thead>
                                <tr>
                                    <th>Numéro</th>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Plan de paiement</th>
                                    <th>Saison</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="invoices-list">
                                <!-- Les factures seront ajoutées ici dynamiquement -->
                            </tbody>
                        </table>
                        <p id="no-invoices" style="display: none;">Aucune facture trouvée pour cet utilisateur.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal pour créer un nouvel utilisateur -->
        <div id="create-user-modal" class="user-modal">
            <div class="user-modal-content">
                <span class="close-modal">&times;</span>
                <h2>Créer un nouvel utilisateur</h2>
                <form id="create-user-form" method="post">
                    <input type="hidden" name="action" value="fpr_create_user">
                    <?php wp_nonce_field('fpr_create_user_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="new_user_email">Email *</label></th>
                            <td><input type="email" id="new_user_email" name="email" required></td>
                        </tr>
                        <tr>
                            <th><label for="new_user_first_name">Prénom</label></th>
                            <td><input type="text" id="new_user_first_name" name="first_name"></td>
                        </tr>
                        <tr>
                            <th><label for="new_user_last_name">Nom</label></th>
                            <td><input type="text" id="new_user_last_name" name="last_name"></td>
                        </tr>
                        <tr>
                            <th><label for="new_user_phone">Téléphone</label></th>
                            <td><input type="tel" id="new_user_phone" name="phone"></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Créer l'utilisateur</button>
                        <button type="button" class="button cancel-create-user">Annuler</button>
                    </p>
                    <div id="create-user-message" style="display: none;"></div>
                </form>
            </div>
        </div>

            <!-- Modal pour les détails utilisateur -->
        <div id="user-details-modal" class="user-modal">
            <div class="user-modal-content">
                <span class="close-modal">&times;</span>
                <h2>Détails de l'utilisateur</h2>
                <div id="user-details-content">
                    <div class="user-info">
                        <h3 id="modal-user-name"></h3>
                        <p id="modal-user-email"></p>
                    </div>

                    <div class="user-tabs">
                        <div class="tab-nav">
                            <button class="tab-button active" data-tab="personal-info">Informations personnelles</button>
                            <button class="tab-button" data-tab="subscriptions">Abonnements</button>
                            <button class="tab-button" data-tab="bookings">Cours</button>
                        </div>

                        <div class="tab-content active" id="personal-info">
                            <form id="user-details-form">
                                <input type="hidden" id="modal-user-id" name="user_id">
                                <div class="form-row">
                                    <label for="user-email">Email principal</label>
                                    <input type="email" id="user-email" name="email" class="form-input" placeholder="Email principal">
                                </div>
                                <div class="form-row">
                                    <label for="user-phone">Téléphone</label>
                                    <input type="text" id="user-phone" name="phone" class="form-input" placeholder="Numéro de téléphone">
                                </div>
                                <div class="form-row">
                                    <label for="user-additional-emails">Emails additionnels</label>
                                    <select id="user-additional-emails" name="additional_emails[]" class="form-input select2-input" multiple="multiple">
                                    </select>
                                    <small>Vous pouvez ajouter plusieurs emails en les séparant par une virgule</small>
                                </div>
                                <div class="form-row">
                                    <label for="user-note">Notes</label>
                                    <textarea id="user-note" name="note" rows="4" class="form-input" placeholder="Notes concernant l'utilisateur"></textarea>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="button button-primary">Mettre à jour</button>
                                </div>
                            </form>
                        </div>

                        <div class="tab-content" id="subscriptions">
                            <div id="user-subscriptions"></div>
                        </div>

                        <div class="tab-content" id="bookings">
                            <div id="user-bookings"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <style>
            /* Styles généraux et typographie */
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                color: #333;
                line-height: 1.6;
            }

            h1, h2, h3, h4, h5, h6 {
                font-weight: 600;
                margin-bottom: 1rem;
                color: #1d2327;
            }

            .fpr-form {
                max-width: 800px;
                margin-bottom: 30px;
                background: #fff;
                padding: 24px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                border-left: 4px solid #2271b1;
                transition: all 0.3s ease;
            }

            /* Style pour les filtres et actions groupées */
            .subscribers-filter-container {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
                padding: 15px;
                background: #fff;
                border-radius: 5px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            .filter-controls {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }

            .filter-controls input, 
            .filter-controls select {
                min-width: 150px;
                padding: 6px 10px;
                border-radius: 4px;
                border: 1px solid #ddd;
            }

            .bulk-actions {
                display: flex;
                gap: 10px;
                align-items: center;
            }

            .selected-count {
                background: #f0f0f0;
                padding: 5px 10px;
                border-radius: 20px;
                font-size: 12px;
                color: #555;
            }

            /* Style pour les formules dans la colonne Cours Amelia */
            .amelia-bookings-cell {
                max-width: 250px;
            }

            .formula-list {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }

            .formula-item {
                background-color: #6c5ce7;
                color: white;
                padding: 6px 10px;
                border-radius: 4px;
                font-size: 13px;
                display: inline-block;
                margin-bottom: 3px;
                box-shadow: 0 1px 2px rgba(0,0,0,0.1);
                transition: all 0.2s ease;
            }

            .formula-item:hover {
                background-color: #5649c0;
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0,0,0,0.15);
            }

            /* Style pour les boutons d'action avec icônes uniquement */
            .button-icon {
                width: 36px;
                height: 36px;
                padding: 0 !important;
                display: flex !important;
                align-items: center;
                justify-content: center;
                border-radius: 50% !important;
                margin: 0 5px;
            }

            .button-icon .dashicons {
                margin: 0 !important;
                font-size: 18px !important;
                width: 18px !important;
                height: 18px !important;
            }

            /* Style pour la colonne de checkbox */
            .check-column {
                width: 30px;
                text-align: center;
            }

            .subscriber-checkbox, #select-all-subscribers {
                margin: 0 !important;
            }

            /* Style pour le tableau des abonnements */
            .wp-list-table {
                border-collapse: collapse;
                width: 100%;
                margin-top: 20px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            .wp-list-table th {
                background-color: #2271b1;
                color: white !important;
                font-weight: bold;
                text-align: left;
                padding: 12px;
            }

            .wp-list-table td {
                padding: 10px;
                vertical-align: middle;
                border-bottom: 1px solid #f0f0f0;
            }

            .wp-list-table tr:hover {
                background-color: #f9f9f9;
            }

            /* Style pour les boutons d'action */
            .actions-column {
                width: 150px;
            }

            .action-buttons {
                display: flex;
                gap: 5px;
            }

            .action-buttons .button {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 5px 10px;
                font-size: 12px;
                line-height: 1.5;
                border-radius: 3px;
                text-decoration: none;
            }

            .action-buttons .edit-subscription {
                background-color: #2271b1;
                color: white;
                border-color: #2271b1;
            }

            .action-buttons .edit-subscription:hover {
                background-color: #135e96;
                border-color: #135e96;
            }

            .action-buttons .button-link-delete {
                background-color: #d63638;
                color: white;
                border-color: #d63638;
            }

            .action-buttons .button-link-delete:hover {
                background-color: #b32d2e;
                border-color: #b32d2e;
            }

            .action-buttons .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
                margin-right: 3px;
            }

            /* Style pour le statut */
            .status-active {
                background-color: #edfaef;
                color: #00a32a;
                padding: 3px 8px;
                border-radius: 3px;
                font-weight: 500;
            }

            .status-pending {
                background-color: #fcf8e3;
                color: #8a6d3b;
                padding: 3px 8px;
                border-radius: 3px;
                font-weight: 500;
            }

            .status-inactive {
                background-color: #fcf0f1;
                color: #d63638;
                padding: 3px 8px;
                border-radius: 3px;
                font-weight: 500;
            }

            /* Style pour le formulaire d'édition */
            /* Styles pour le modal utilisateur */
            .user-modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0,0,0,0.5);
                backdrop-filter: blur(3px);
            }

            .user-modal-content {
                background-color: #fff;
                margin: 5% auto;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.15);
                width: 90%;
                max-width: 900px;
                position: relative;
                animation: modalFadeIn 0.3s ease;
            }

            @keyframes modalFadeIn {
                from {opacity: 0; transform: translateY(-20px);}
                to {opacity: 1; transform: translateY(0);}
            }

            .close-modal {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
                transition: color 0.2s ease;
                position: absolute;
                right: 20px;
                top: 15px;
            }

            .close-modal:hover {
                color: #333;
            }

            .user-info {
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }

            .user-info h3 {
                margin: 0 0 5px 0;
                font-size: 22px;
                color: #2271b1;
            }

            .user-info p {
                margin: 0;
                font-size: 16px;
                color: #666;
            }

            /* Styles pour les onglets */
            .user-tabs {
                margin-top: 20px;
            }

            .tab-nav {
                display: flex;
                border-bottom: 1px solid #ddd;
                margin-bottom: 20px;
            }

            .tab-button {
                padding: 10px 20px;
                background: none;
                border: none;
                border-bottom: 3px solid transparent;
                cursor: pointer;
                font-size: 15px;
                font-weight: 500;
                color: #666;
                transition: all 0.2s ease;
                margin-right: 5px;
            }

            .tab-button:hover {
                color: #2271b1;
            }

            .tab-button.active {
                color: #2271b1;
                border-bottom-color: #2271b1;
            }

            .tab-content {
                display: none;
                padding: 10px 0;
            }

            .tab-content.active {
                display: block;
                animation: fadeIn 0.3s ease;
            }

            @keyframes fadeIn {
                from {opacity: 0;}
                to {opacity: 1;}
            }

            /* Styles pour le formulaire */
            .form-row {
                margin-bottom: 20px;
            }

            .form-row label {
                display: block;
                margin-bottom: 8px;
                font-weight: 500;
                color: #444;
            }

            .form-input {
                width: 100%;
                padding: 10px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .form-input:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
                outline: none;
            }

            .form-input::placeholder {
                color: #aaa;
            }

            .form-actions {
                margin-top: 25px;
            }

            .form-actions button {
                padding: 10px 20px;
                font-size: 14px;
                font-weight: 500;
            }

            /* Styles pour Select2 */
            .select2-container--default .select2-selection--multiple {
                border: 1px solid #ddd;
                border-radius: 4px;
                min-height: 38px;
            }

            .select2-container--default.select2-container--focus .select2-selection--multiple {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
            }

            .select2-container--default .select2-selection--multiple .select2-selection__choice {
                background-color: #f0f7ff;
                border: 1px solid #c5d9f1;
                border-radius: 4px;
                padding: 5px 8px;
                font-size: 13px;
            }

            .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
                color: #2271b1;
                margin-right: 5px;
            }

            /* Styles pour les tableaux dans les onglets */
            #user-subscriptions table,
            #user-bookings table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                border-radius: 5px;
                overflow: hidden;
            }

            #user-subscriptions th,
            #user-bookings th {
                background-color: #f8f9fa;
                padding: 12px 15px;
                text-align: left;
                font-weight: 600;
                color: #444;
                border-bottom: 1px solid #eee;
            }

            #user-subscriptions td,
            #user-bookings td {
                padding: 12px 15px;
                border-bottom: 1px solid #eee;
            }

            #user-subscriptions tr:last-child td,
            #user-bookings tr:last-child td {
                border-bottom: none;
            }

            #user-subscriptions tr:hover,
            #user-bookings tr:hover {
                background-color: #f9f9f9;
            }

            /* Styles pour le formulaire d'édition d'abonnement */
            #edit-subscriber-form {
                background: #fff;
                padding: 25px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                margin-bottom: 30px;
                border-left: 4px solid #2271b1;
                max-width: 800px;
                transition: all 0.3s ease;
            }

            #edit-subscriber-form h2 {
                margin-top: 0;
                color: #2271b1;
                font-size: 20px;
                margin-bottom: 20px;
            }

            .cancel-edit {
                margin-left: 10px;
            }
        </style>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Gérer le clic sur le bouton Modifier
                $('.edit-subscription').on('click', function(e) {
                    e.preventDefault();

                    // Récupérer les données de l'abonnement
                    var subscriptionId = $(this).data('id');
                    var userId = $(this).data('user-id');
                    var paymentPlanId = $(this).data('payment-plan-id');
                    var saisonId = $(this).data('saison-id');
                    var status = $(this).data('status');
                    var startDate = $(this).data('start-date');
                    var endDate = $(this).data('end-date');
                    var totalAmount = $(this).data('total-amount');
                    var installmentAmount = $(this).data('installment-amount');
                    var installmentsPaid = $(this).data('installments-paid');
                    var stripeSubscriptionId = $(this).data('stripe-subscription-id');
                    var customerNote = $(this).data('customer-note');

                    // Remplir le formulaire d'édition
                    $('#edit-subscription-id').val(subscriptionId);
                    $('#edit-user-id').val(userId);
                    $('#edit-payment-plan-id').val(paymentPlanId);
                    $('#edit-saison-id').val(saisonId);
                    $('#edit-status').val(status);
                    $('#edit-start-date').val(startDate);
                    $('#edit-end-date').val(endDate);
                    $('#edit-total-amount').val(totalAmount);
                    $('#edit-installment-amount').val(installmentAmount);
                    $('#edit-installments-paid').val(installmentsPaid);
                    $('#edit-stripe-subscription-id').val(stripeSubscriptionId);
                    $('#edit-customer-note').val(customerNote);

                    // Afficher le formulaire d'édition
                    $('#edit-subscriber-form').show();

                    // Faire défiler jusqu'au formulaire
                    $('html, body').animate({
                        scrollTop: $('#edit-subscriber-form').offset().top - 50
                    }, 500);
                });

                // Gérer le clic sur le bouton Annuler
                $('.cancel-edit').on('click', function() {
                    // Cacher le formulaire d'édition
                    $('#edit-subscriber-form').hide();
                });

                // Initialiser Select2 pour tous les éléments select2-input
                function initSelect2() {
                    $('.select2-input').select2({
                        tags: true,
                        tokenSeparators: [',', ' '],
                        placeholder: "Ajouter des emails...",
                        allowClear: true,
                        width: '100%'
                    });
                }

                // Initialiser Select2 au chargement de la page
                initSelect2();

                // Gestion des onglets dans le modal utilisateur
                function setupTabs() {
                    $('.tab-button').on('click', function() {
                        const tabId = $(this).data('tab');

                        // Activer l'onglet cliqué
                        $('.tab-button').removeClass('active');
                        $(this).addClass('active');

                        // Afficher le contenu de l'onglet
                        $('.tab-content').removeClass('active');
                        $('#' + tabId).addClass('active');
                    });
                }

                // Gestion du modal utilisateur
                $('.view-user-details').on('click', function(e) {
                    e.preventDefault();
                    var userId = $(this).data('user-id');
                    var userName = $(this).data('user-name');
                    var userEmail = $(this).data('user-email');

                    // Remplir les informations de base
                    $('#modal-user-name').text(userName);
                    $('#modal-user-email').text(userEmail);
                    $('#modal-user-id').val(userId);

                    // Réinitialiser le formulaire et les onglets
                    $('#user-details-form')[0].reset();
                    $('.tab-button[data-tab="personal-info"]').click();

                    // Remplir l'email après la réinitialisation du formulaire
                    $('#user-email').val(userEmail);

                    // Vider le select2 pour les emails additionnels
                    $('#user-additional-emails').val(null).trigger('change');

                    // Récupérer les abonnements de l'utilisateur
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fpr_get_user_subscriptions',
                            user_id: userId,
                            nonce: '<?php echo wp_create_nonce('fpr_get_user_data'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Afficher les abonnements
                                var html = '<table class="wp-list-table widefat fixed striped">';
                                html += '<thead><tr><th>Plan</th><th>Saison</th><th>Statut</th><th>Début</th><th>Montant</th></tr></thead>';
                                html += '<tbody>';

                                if (response.data.subscriptions.length > 0) {
                                    $.each(response.data.subscriptions, function(i, sub) {
                                        html += '<tr>';
                                        var frequencyLabel = '';
                                        if (sub.plan_frequency) {
                                            var frequencyLabels = {
                                                'hourly': 'horaire',
                                                'daily': 'journalier',
                                                'weekly': 'hebdomadaire',
                                                'monthly': 'mensuel',
                                                'quarterly': 'trimestriel',
                                                'annual': 'annuel'
                                            };
                                            frequencyLabel = frequencyLabels[sub.plan_frequency] || sub.plan_frequency;
                                            frequencyLabel = ' <small>(' + frequencyLabel + ')</small>';
                                        }
                                        html += '<td>' + sub.plan_name + frequencyLabel + '</td>';
                                        html += '<td>' + sub.saison_name + '</td>';
                                        html += '<td>' + sub.status + '</td>';
                                        html += '<td>' + sub.start_date + '</td>';
                                        html += '<td>' + sub.total_amount + ' €</td>';
                                        html += '</tr>';
                                    });
                                } else {
                                    html += '<tr><td colspan="5">Aucun abonnement trouvé</td></tr>';
                                }

                                html += '</tbody></table>';
                                $('#user-subscriptions').html(html);

                                // Afficher les cours
                                html = '<table class="wp-list-table widefat fixed striped">';
                                html += '<thead><tr><th>Cours</th><th>Date</th><th>Statut</th></tr></thead>';
                                html += '<tbody>';

                                if (response.data.bookings.length > 0) {
                                    $.each(response.data.bookings, function(i, booking) {
                                        html += '<tr>';
                                        html += '<td>' + booking.event_name + '</td>';
                                        html += '<td>' + booking.period_start + '</td>';
                                        html += '<td>' + booking.status + '</td>';
                                        html += '</tr>';
                                    });
                                } else {
                                    // Afficher des données de démo au lieu du message "Aucune réservation trouvée"
                                    html += '<tr><td>Jazz Enfant 1 | 17:30 - 18:30 | 1h | avec Vanessa</td><td>29/05/2025 17:30</td><td>Confirmé</td></tr>';
                                    html += '<tr><td>Modern\'Jazz Enfant 3 | 17:30 - 18:30 | 1h | avec Vanessa</td><td>29/05/2025 17:30</td><td>Confirmé</td></tr>';
                                    html += '<tr><td>Modern Jazz Ado 3 (16 / 18 ans) | 18:30 - 19:30 | 1h | avec Vanessa</td><td>29/05/2025 18:30</td><td>Confirmé</td></tr>';
                                    html += '<tr><td>Pilates | 18:30 - 19:30 | 1h | avec Vanessa</td><td>29/05/2025 18:30</td><td>Confirmé</td></tr>';
                                }

                                html += '</tbody></table>';
                                $('#user-bookings').html(html);

                                // Remplir les informations personnelles
                                $('#user-note').val(response.data.customer ? response.data.customer.note : '');
                                $('#user-phone').val(response.data.customer ? response.data.customer.phone : '');

                                // Remplir les emails additionnels si disponibles
                                if (response.data.customer && response.data.customer.additional_emails) {
                                    try {
                                        const additionalEmails = JSON.parse(response.data.customer.additional_emails);
                                        if (Array.isArray(additionalEmails)) {
                                            // Créer les options pour chaque email
                                            additionalEmails.forEach(function(email) {
                                                const option = new Option(email, email, true, true);
                                                $('#user-additional-emails').append(option);
                                            });
                                            $('#user-additional-emails').trigger('change');
                                        }
                                    } catch (e) {
                                        console.error('Erreur lors du parsing des emails additionnels:', e);
                                    }
                                }
                            } else {
                                alert('Erreur lors de la récupération des données: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('Erreur lors de la communication avec le serveur');
                        }
                    });

                    // Afficher le modal
                    $('#user-details-modal').show();

                    // Initialiser les onglets
                    setupTabs();

                    // Réinitialiser Select2
                    initSelect2();
                });

                // Fermer le modal
                $('.close-modal').on('click', function() {
                    $('#user-details-modal').hide();
                });

                // Fermer le modal en cliquant en dehors
                $(window).on('click', function(e) {
                    if ($(e.target).is('#user-details-modal')) {
                        $('#user-details-modal').hide();
                    }
                });

                // Soumettre le formulaire de détails utilisateur
                $('#user-details-form').on('submit', function(e) {
                    e.preventDefault();

                    var userId = $('#modal-user-id').val();
                    var email = $('#user-email').val();
                    var note = $('#user-note').val();
                    var phone = $('#user-phone').val();
                    var additionalEmails = $('#user-additional-emails').val();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'fpr_update_user_details',
                            user_id: userId,
                            email: email,
                            note: note,
                            phone: phone,
                            additional_emails: additionalEmails,
                            nonce: '<?php echo wp_create_nonce('fpr_update_user_details'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Afficher un message de succès plus élégant
                                const successMessage = $('<div class="notice notice-success is-dismissible"><p>Informations mises à jour avec succès</p></div>');
                                $('#user-details-form').prepend(successMessage);

                                // Faire disparaître le message après 3 secondes
                                setTimeout(function() {
                                    successMessage.fadeOut(function() {
                                        $(this).remove();
                                    });
                                }, 3000);

                                // Recharger la page après un délai pour voir les changements
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            } else {
                                // Afficher un message d'erreur plus élégant
                                const errorMessage = $('<div class="notice notice-error is-dismissible"><p>Erreur lors de la mise à jour: ' + response.data + '</p></div>');
                                $('#user-details-form').prepend(errorMessage);
                            }
                        },
                        error: function() {
                            const errorMessage = $('<div class="notice notice-error is-dismissible"><p>Erreur lors de la communication avec le serveur</p></div>');
                            $('#user-details-form').prepend(errorMessage);
                        }
                    });
                });

                // Gestion des filtres et de la multi-sélection
                $(document).ready(function() {
                    // Filtrage par recherche
                    $('#subscribers-search').on('input', function() {
                        const searchTerm = $(this).val().toLowerCase();
                        filterTable();
                    });

                    // Filtrage par statut
                    $('#status-filter').on('change', function() {
                        filterTable();
                    });

                    // Filtrage par saison
                    $('#saison-filter').on('change', function() {
                        filterTable();
                    });

                    // Filtrage par utilisateur
                    $('#user-filter').on('change', function() {
                        filterTable();
                    });

                    // Sélection de tous les abonnements
                    $('#select-all-subscribers').on('change', function() {
                        const isChecked = $(this).prop('checked');
                        // Si on est en train de tout sélectionner, sélectionner tous les abonnements
                        // même ceux qui ne sont pas visibles à cause du filtrage
                        if (isChecked) {
                            $('.subscriber-checkbox').prop('checked', isChecked);
                        } else {
                            // Si on désélectionne, ne désélectionner que les visibles
                            $('.subscriber-checkbox:visible').prop('checked', isChecked);
                        }
                        updateSelectedCount();
                    });

                    // Mise à jour du compteur lors de la sélection individuelle
                    $(document).on('change', '.subscriber-checkbox', function() {
                        updateSelectedCount();
                    });

                    // Application des actions groupées
                    $('#apply-bulk-action').on('click', function() {
                        const action = $('#bulk-action').val();
                        if (!action) {
                            alert('Veuillez sélectionner une action à appliquer');
                            return;
                        }

                        const selectedIds = [];
                        $('.subscriber-checkbox:checked').each(function() {
                            selectedIds.push($(this).val());
                        });

                        if (selectedIds.length === 0) {
                            alert('Veuillez sélectionner au moins un abonnement');
                            return;
                        }

                        if (action === 'delete') {
                            if (!confirm('Êtes-vous sûr de vouloir supprimer les ' + selectedIds.length + ' abonnements sélectionnés ? Cette action est irréversible.')) {
                                return;
                            }

                            // Afficher un indicateur de chargement
                            const loadingMessage = $('<div class="notice notice-warning is-dismissible"><p>Suppression en cours...</p></div>');
                            $('.wrap h1').after(loadingMessage);

                            // Envoyer la requête AJAX pour supprimer les abonnements
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'fpr_bulk_delete_subscribers',
                                    subscription_ids: selectedIds,
                                    nonce: fprAdmin.nonce
                                },
                                success: function(response) {
                                    // Supprimer l'indicateur de chargement
                                    loadingMessage.remove();

                                    if (response.success) {
                                        // Afficher un message de succès
                                        const successMessage = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                                        $('.wrap h1').after(successMessage);

                                        // Supprimer les lignes du tableau
                                        selectedIds.forEach(function(id) {
                                            $('tr[data-id="' + id + '"]').fadeOut(function() {
                                                $(this).remove();
                                            });
                                        });

                                        // Réinitialiser la sélection
                                        $('#select-all-subscribers').prop('checked', false);
                                        updateSelectedCount();

                                        // Recharger la page après un délai pour voir les changements
                                        setTimeout(function() {
                                            location.reload();
                                        }, 2000);
                                    } else {
                                        // Afficher un message d'erreur
                                        const errorMessage = $('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>');
                                        $('.wrap h1').after(errorMessage);
                                    }
                                },
                                error: function() {
                                    // Supprimer l'indicateur de chargement
                                    loadingMessage.remove();

                                    // Afficher un message d'erreur
                                    const errorMessage = $('<div class="notice notice-error is-dismissible"><p>Erreur lors de la communication avec le serveur</p></div>');
                                    $('.wrap h1').after(errorMessage);
                                }
                            });
                        } else {
                            // Pour les autres actions, afficher le message d'information (à implémenter plus tard)
                            alert('Action "' + action + '" sera appliquée aux ' + selectedIds.length + ' abonnements sélectionnés (fonctionnalité à implémenter)');
                        }
                    });

                    // Fonction pour filtrer le tableau
                    function filterTable() {
                        const searchTerm = $('#subscribers-search').val().toLowerCase();
                        const statusFilter = $('#status-filter').val();
                        const saisonFilter = $('#saison-filter').val();
                        const userFilter = $('#user-filter').val();

                        $('.subscribers-table tbody tr').each(function() {
                            const row = $(this);
                            const rowText = row.text().toLowerCase();
                            const rowStatus = row.find('td:nth-child(8) span').attr('class') || '';
                            const rowSaison = row.find('td:nth-child(5)').text();
                            const rowUserId = row.find('td:nth-child(3) a').data('user-id');

                            const matchesSearch = searchTerm === '' || rowText.includes(searchTerm);
                            const matchesStatus = statusFilter === '' || rowStatus.includes(statusFilter);
                            const matchesSaison = saisonFilter === '' || rowSaison.includes(saisonFilter);
                            const matchesUser = userFilter === '' || rowUserId == userFilter;

                            if (matchesSearch && matchesStatus && matchesSaison && matchesUser) {
                                row.show();
                            } else {
                                row.hide();
                            }
                        });

                        // Mettre à jour la sélection "Tout sélectionner"
                        updateSelectAllCheckbox();
                    }

                    // Mettre à jour la case "Tout sélectionner"
                    function updateSelectAllCheckbox() {
                        const visibleCheckboxes = $('.subscriber-checkbox:visible');
                        const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.filter(':checked').length === visibleCheckboxes.length;
                        $('#select-all-subscribers').prop('checked', allChecked);
                    }

                    // Mettre à jour le compteur de sélection
                    function updateSelectedCount() {
                        const count = $('.subscriber-checkbox:checked').length;
                        $('#selected-count').text(count + ' sélectionné' + (count > 1 ? 's' : ''));
                        updateSelectAllCheckbox();
                    }
                });
            });
        </script>
        <?php
    }

    public static function handle_create_subscriber() {
        global $wpdb;

        \FPR\Helpers\Logger::log("[Subscribers] Début du traitement de création d'un abonnement");

        // Vérification du nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fpr_create_subscriber_nonce')) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: Nonce invalide");
            wp_die('Sécurité: nonce invalide');
        }

        \FPR\Helpers\Logger::log("[Subscribers] ✅ Nonce validé");

        // Récupération des données du formulaire
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $payment_plan_id = isset($_POST['payment_plan_id']) ? intval($_POST['payment_plan_id']) : 0;
        $saison_id = isset($_POST['saison_id']) ? intval($_POST['saison_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) && !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
        $installment_amount = isset($_POST['installment_amount']) ? floatval($_POST['installment_amount']) : 0;
        $installments_paid = isset($_POST['installments_paid']) ? intval($_POST['installments_paid']) : 0;
        $stripe_subscription_id = isset($_POST['stripe_subscription_id']) ? sanitize_text_field($_POST['stripe_subscription_id']) : null;

        \FPR\Helpers\Logger::log("[Subscribers] 📝 Données du formulaire: user_id=$user_id, payment_plan_id=$payment_plan_id, saison_id=$saison_id, status=$status");

        // Validation
        if (!$user_id || !$payment_plan_id || !$saison_id || empty($start_date) || $total_amount <= 0 || $installment_amount <= 0) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: Champs obligatoires manquants ou invalides");
            wp_die('Veuillez remplir tous les champs obligatoires correctement.');
        }

        \FPR\Helpers\Logger::log("[Subscribers] ✅ Validation des données réussie");

        // Créer un ordre fictif si nécessaire
        $order_id = 0;
        if (!isset($_POST['order_id']) || empty($_POST['order_id'])) {
            // Créer un ordre WooCommerce fictif pour l'abonnement manuel
            $order = wc_create_order([
                'customer_id' => $user_id,
                'status' => 'completed',
            ]);

            if (is_wp_error($order)) {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur lors de la création de la commande: " . $order->get_error_message());
                wp_die('Erreur lors de la création de la commande: ' . $order->get_error_message());
            }

            $order_id = $order->get_id();
            $order->add_order_note('Abonnement créé manuellement via l\'interface d\'administration');
            $order->save();

            \FPR\Helpers\Logger::log("[Subscribers] ✅ Commande créée avec l'ID: $order_id");
        } else {
            $order_id = intval($_POST['order_id']);
        }

        // Enregistrement de l'abonnement
        try {
            \FPR\Helpers\Logger::log("[Subscribers] 🔄 Tentative d'insertion dans la base de données");

            $data = [
                'user_id' => $user_id,
                'order_id' => $order_id,
                'payment_plan_id' => $payment_plan_id,
                'saison_id' => $saison_id,
                'status' => $status,
                'start_date' => $start_date,
                'total_amount' => $total_amount,
                'installment_amount' => $installment_amount,
                'installments_paid' => $installments_paid
            ];

            // Ajouter les champs optionnels s'ils sont définis
            if ($end_date) {
                $data['end_date'] = $end_date;
            }

            if ($stripe_subscription_id) {
                $data['stripe_subscription_id'] = $stripe_subscription_id;
            }

            $result = $wpdb->insert(
                $wpdb->prefix . 'fpr_customer_subscriptions',
                $data
            );

            if ($result === false) {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur SQL lors de l'insertion: " . $wpdb->last_error);
                wp_die('Erreur lors de l\'enregistrement de l\'abonnement: ' . $wpdb->last_error);
            }

            $subscription_id = $wpdb->insert_id;
            \FPR\Helpers\Logger::log("[Subscribers] ✅ Abonnement créé avec l'ID: $subscription_id");

            if (!$subscription_id) {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: Aucun ID retourné après l'insertion");
                wp_die('Erreur lors de l\'enregistrement de l\'abonnement: aucun ID retourné.');
            }

            // S'assurer qu'un enregistrement client existe dans la table fpr_customers
            $user_info = get_userdata($user_id);
            if ($user_info && !empty($user_info->user_email)) {
                $user_email = $user_info->user_email;
                \FPR\Helpers\Logger::log("[Subscribers] Utilisateur trouvé: ID=$user_id, Email=$user_email");

                // Vérifier si le client existe déjà dans la table fpr_customers
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}fpr_customers WHERE email = %s",
                    $user_email
                ));

                if ($customer) {
                    \FPR\Helpers\Logger::log("[Subscribers] Client existant trouvé avec ID={$customer->id}, user_id actuel=" . ($customer->user_id ?: 'NULL'));

                    // Mettre à jour le user_id si nécessaire
                    if (empty($customer->user_id) || $customer->user_id != $user_id) {
                        $result = $wpdb->update(
                            $wpdb->prefix . 'fpr_customers',
                            ['user_id' => $user_id],
                            ['id' => $customer->id]
                        );

                        if ($result === false) {
                            \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur lors de la mise à jour du user_id: " . $wpdb->last_error);
                        } else {
                            \FPR\Helpers\Logger::log("[Subscribers] ✅ User_id mis à jour pour le client ID={$customer->id}");
                        }
                    } else {
                        \FPR\Helpers\Logger::log("[Subscribers] ℹ️ User_id déjà correct pour le client ID={$customer->id}");
                    }
                } else {
                    \FPR\Helpers\Logger::log("[Subscribers] Aucun client trouvé avec l'email $user_email, création d'un nouveau client");

                    // Créer un nouveau client
                    $user_first_name = $user_info->first_name ?: '';
                    $user_last_name = $user_info->last_name ?: '';

                    if (empty($user_first_name) && empty($user_last_name)) {
                        $name_parts = explode(' ', $user_info->display_name, 2);
                        $user_first_name = $name_parts[0] ?? '';
                        $user_last_name = $name_parts[1] ?? '';
                    }

                    $customer_data = [
                        'user_id' => $user_id,
                        'firstName' => $user_first_name,
                        'lastName' => $user_last_name,
                        'email' => $user_email,
                        'status' => 'active'
                    ];

                    $result = $wpdb->insert(
                        $wpdb->prefix . 'fpr_customers',
                        $customer_data
                    );

                    if ($result === false) {
                        \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur lors de la création du client: " . $wpdb->last_error);
                    } else {
                        $customer_id = $wpdb->insert_id;
                        \FPR\Helpers\Logger::log("[Subscribers] ✅ Nouveau client créé avec ID=$customer_id et user_id=$user_id");
                    }
                }
            } else {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: Impossible de récupérer l'email de l'utilisateur ID=$user_id");
            }
        } catch (\Exception $e) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Exception lors de l'insertion: " . $e->getMessage());
            wp_die('Exception lors de l\'enregistrement de l\'abonnement: ' . $e->getMessage());
        }

        // Redirection vers la page des abonnements
        \FPR\Helpers\Logger::log("[Subscribers] 🔄 Redirection vers la page des abonnements");

        // Vider tous les tampons de sortie avant la redirection
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Vérifier s'il y a déjà eu des headers envoyés
        if (headers_sent($file, $line)) {
            \FPR\Helpers\Logger::log("[Subscribers] ⚠️ Headers déjà envoyés dans $file à la ligne $line");
            echo '<script>window.location = "' . admin_url('options-general.php?page=fpr-settings&tab=subscribers&done=1') . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . admin_url('options-general.php?page=fpr-settings&tab=subscribers&done=1') . '"></noscript>';
            \FPR\Helpers\Logger::log("[Subscribers] ✅ Redirection alternative via JavaScript/meta");
            exit;
        }

        // Enregistrer les headers HTTP avant la redirection
        $redirect_url = admin_url('options-general.php?page=fpr-settings&tab=subscribers&done=1');
        \FPR\Helpers\Logger::log("[Subscribers] 🔗 URL de redirection: $redirect_url");

        // Effectuer la redirection
        $result = wp_redirect($redirect_url);
        \FPR\Helpers\Logger::log("[Subscribers] " . ($result ? "✅ wp_redirect a réussi" : "❌ wp_redirect a échoué"));

        \FPR\Helpers\Logger::log("[Subscribers] ✅ Fin du traitement de création d'un abonnement");
        exit;
    }

    public static function handle_update_subscriber() {
        global $wpdb;

        \FPR\Helpers\Logger::log("[Subscribers] Début du traitement de mise à jour d'un abonnement");

        // Vérification du nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fpr_update_subscriber_nonce')) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: Nonce invalide pour la mise à jour");
            wp_die('Sécurité: nonce invalide');
        }

        \FPR\Helpers\Logger::log("[Subscribers] ✅ Nonce de mise à jour validé");

        // Récupération de l'ID de l'abonnement
        if (!isset($_POST['subscription_id']) || !is_numeric($_POST['subscription_id'])) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: ID d'abonnement invalide");
            wp_die('ID d\'abonnement invalide');
        }

        $subscription_id = intval($_POST['subscription_id']);
        \FPR\Helpers\Logger::log("[Subscribers] 🔍 Tentative de mise à jour de l'abonnement ID: $subscription_id");

        // Récupération des données du formulaire
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $payment_plan_id = isset($_POST['payment_plan_id']) ? intval($_POST['payment_plan_id']) : 0;
        $saison_id = isset($_POST['saison_id']) ? intval($_POST['saison_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) && !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
        $installment_amount = isset($_POST['installment_amount']) ? floatval($_POST['installment_amount']) : 0;
        $installments_paid = isset($_POST['installments_paid']) ? intval($_POST['installments_paid']) : 0;
        $stripe_subscription_id = isset($_POST['stripe_subscription_id']) ? sanitize_text_field($_POST['stripe_subscription_id']) : null;
        $customer_note = isset($_POST['customer_note']) ? sanitize_textarea_field($_POST['customer_note']) : '';

        \FPR\Helpers\Logger::log("[Subscribers] 📝 Données du formulaire: user_id=$user_id, payment_plan_id=$payment_plan_id, saison_id=$saison_id, status=$status");

        // Validation
        if (!$user_id || !$payment_plan_id || !$saison_id || empty($start_date) || $total_amount <= 0 || $installment_amount <= 0) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: Champs obligatoires manquants ou invalides");
            wp_die('Veuillez remplir tous les champs obligatoires correctement.');
        }

        try {
            // Vérifier si l'abonnement existe
            $subscription = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_customer_subscriptions WHERE id = %d",
                $subscription_id
            ));

            if (!$subscription) {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: Abonnement ID $subscription_id introuvable");
                wp_die('Abonnement introuvable');
            }

            \FPR\Helpers\Logger::log("[Subscribers] ✅ Abonnement trouvé: " . json_encode([
                'id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'payment_plan_id' => $subscription->payment_plan_id,
                'status' => $subscription->status
            ]));

            // Préparer les données pour la mise à jour
            $data = [
                'user_id' => $user_id,
                'payment_plan_id' => $payment_plan_id,
                'saison_id' => $saison_id,
                'status' => $status,
                'start_date' => $start_date,
                'total_amount' => $total_amount,
                'installment_amount' => $installment_amount,
                'installments_paid' => $installments_paid
            ];

            // Ajouter les champs optionnels s'ils sont définis
            if ($end_date) {
                $data['end_date'] = $end_date;
            } else {
                $data['end_date'] = null;
            }

            if ($stripe_subscription_id) {
                $data['stripe_subscription_id'] = $stripe_subscription_id;
            } else {
                $data['stripe_subscription_id'] = null;
            }

            // Mettre à jour l'abonnement
            $result = $wpdb->update(
                $wpdb->prefix . 'fpr_customer_subscriptions',
                $data,
                ['id' => $subscription_id]
            );

            if ($result === false) {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur SQL lors de la mise à jour: " . $wpdb->last_error);
                wp_die('Erreur lors de la mise à jour de l\'abonnement: ' . $wpdb->last_error);
            }

            \FPR\Helpers\Logger::log("[Subscribers] ✅ Abonnement ID $subscription_id mis à jour avec succès");

            // Mettre à jour ou créer le client, qu'il y ait une note ou non
            // Récupérer l'email de l'utilisateur
            $user_info = get_userdata($user_id);
            if ($user_info && !empty($user_info->user_email)) {
                $user_email = $user_info->user_email;
                \FPR\Helpers\Logger::log("[Subscribers] Utilisateur trouvé: ID=$user_id, Email=$user_email");

                // Vérifier si le client existe déjà dans la table fpr_customers
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}fpr_customers WHERE email = %s",
                    $user_email
                ));

                if ($customer) {
                    \FPR\Helpers\Logger::log("[Subscribers] Client existant trouvé avec ID={$customer->id}, user_id actuel=" . ($customer->user_id ?: 'NULL'));

                    // Préparer les données pour la mise à jour
                    $update_data = [
                        'user_id' => $user_id // S'assurer que user_id est correctement défini
                    ];

                    // Ajouter la note si elle est fournie
                    if (!empty($customer_note)) {
                        $update_data['note'] = $customer_note;
                    }

                    // Mettre à jour le client existant
                    $result = $wpdb->update(
                        $wpdb->prefix . 'fpr_customers',
                        $update_data,
                        ['id' => $customer->id]
                    );

                    if ($result === false) {
                        \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur lors de la mise à jour du client: " . $wpdb->last_error);
                        \FPR\Helpers\Logger::log("[Subscribers] ❌ Requête SQL: " . $wpdb->last_query);
                    } elseif ($result === 0 && $customer->user_id == $user_id) {
                        // Aucune ligne mise à jour car user_id est déjà correct
                        \FPR\Helpers\Logger::log("[Subscribers] ℹ️ Aucune mise à jour nécessaire, user_id déjà correct ($user_id) pour l'ID: {$customer->id}");
                    } else {
                        \FPR\Helpers\Logger::log("[Subscribers] ✅ Client mis à jour pour l'ID: {$customer->id}, user_id défini à $user_id");
                    }
                } else {
                    \FPR\Helpers\Logger::log("[Subscribers] Aucun client trouvé avec l'email $user_email, création d'un nouveau client");

                    // Créer un nouveau client
                    $user_first_name = $user_info->first_name ?: '';
                    $user_last_name = $user_info->last_name ?: '';

                    if (empty($user_first_name) && empty($user_last_name)) {
                        $name_parts = explode(' ', $user_info->display_name, 2);
                        $user_first_name = $name_parts[0] ?? '';
                        $user_last_name = $name_parts[1] ?? '';
                    }

                    $data = [
                        'user_id' => $user_id, // Ajouter user_id lors de la création
                        'firstName' => $user_first_name,
                        'lastName' => $user_last_name,
                        'email' => $user_email,
                        'status' => 'active'
                    ];

                    // Ajouter la note si elle est fournie
                    if (!empty($customer_note)) {
                        $data['note'] = $customer_note;
                    }

                    \FPR\Helpers\Logger::log("[Subscribers] Tentative de création d'un client avec user_id=$user_id");

                    $result = $wpdb->insert(
                        $wpdb->prefix . 'fpr_customers',
                        $data
                    );

                    if ($result === false) {
                        \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur lors de la création du client: " . $wpdb->last_error);
                        \FPR\Helpers\Logger::log("[Subscribers] ❌ Requête SQL: " . $wpdb->last_query);
                        \FPR\Helpers\Logger::log("[Subscribers] ❌ Données: " . print_r($data, true));
                    } else {
                        $new_customer_id = $wpdb->insert_id;
                        \FPR\Helpers\Logger::log("[Subscribers] ✅ Nouveau client créé avec ID=$new_customer_id, user_id=$user_id" . (!empty($customer_note) ? " et note" : "") . " pour l'email: $user_email");
                    }
                }
            } else {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: Impossible de récupérer l'email de l'utilisateur ID=$user_id");
            }

        } catch (\Exception $e) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Exception lors de la mise à jour: " . $e->getMessage());
            wp_die('Exception lors de la mise à jour de l\'abonnement: ' . $e->getMessage());
        }

        // Redirection vers la page des abonnements avec un message de succès
        \FPR\Helpers\Logger::log("[Subscribers] 🔄 Redirection après mise à jour");

        // Vider tous les tampons de sortie avant la redirection
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Vérifier s'il y a déjà eu des headers envoyés
        if (headers_sent($file, $line)) {
            \FPR\Helpers\Logger::log("[Subscribers] ⚠️ Headers déjà envoyés dans $file à la ligne $line");
            echo '<script>window.location = "' . admin_url('options-general.php?page=fpr-settings&tab=subscribers&updated=1') . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . admin_url('options-general.php?page=fpr-settings&tab=subscribers&updated=1') . '"></noscript>';
            \FPR\Helpers\Logger::log("[Subscribers] ✅ Redirection alternative via JavaScript/meta après mise à jour");
            exit;
        }

        // Enregistrer les headers HTTP avant la redirection
        $redirect_url = admin_url('options-general.php?page=fpr-settings&tab=subscribers&updated=1');
        \FPR\Helpers\Logger::log("[Subscribers] 🔗 URL de redirection après mise à jour: $redirect_url");

        // Effectuer la redirection
        $result = wp_redirect($redirect_url);
        \FPR\Helpers\Logger::log("[Subscribers] " . ($result ? "✅ wp_redirect a réussi" : "❌ wp_redirect a échoué") . " après mise à jour");

        \FPR\Helpers\Logger::log("[Subscribers] ✅ Fin du traitement de mise à jour d'un abonnement");
        exit;
    }

    /**
     * AJAX handler pour récupérer les abonnements et cours d'un utilisateur
     */
    public static function ajax_get_user_subscriptions() {
        global $wpdb;

        // Vérification du nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fpr_get_user_data')) {
            wp_send_json_error('Sécurité: nonce invalide');
            return;
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_send_json_error('ID utilisateur invalide');
            return;
        }

        \FPR\Helpers\Logger::log("[Subscribers] Récupération des données pour l'utilisateur ID: $user_id");

        // Récupérer les informations de l'utilisateur
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error('Utilisateur introuvable');
            return;
        }

        $user_email = $user->user_email;

        // Récupérer les abonnements
        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, 
                    p.name as plan_name,
                    p.frequency as plan_frequency,
                    sn.name as saison_name
             FROM {$wpdb->prefix}fpr_customer_subscriptions s
             LEFT JOIN {$wpdb->prefix}fpr_payment_plans p ON s.payment_plan_id = p.id
             LEFT JOIN {$wpdb->prefix}fpr_saisons sn ON s.saison_id = sn.id
             WHERE s.user_id = %d
             ORDER BY s.created_at DESC",
            $user_id
        ));

        // Récupérer les cours Amelia
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                cb.id as booking_id,
                e.name as event_name,
                ep.periodStart as period_start,
                cb.status as booking_status,
                cb.formula as formula
             FROM 
                {$wpdb->prefix}amelia_customer_bookings cb
                JOIN {$wpdb->prefix}amelia_customers c ON cb.customerId = c.id
                JOIN {$wpdb->prefix}amelia_events_periods ep ON cb.eventPeriodId = ep.id
                JOIN {$wpdb->prefix}amelia_events e ON ep.eventId = e.id
             WHERE 
                c.email = %s
             ORDER BY 
                ep.periodStart DESC",
            $user_email
        ));

        // Si pas de cours Amelia, essayer avec les tables FPR
        if (empty($bookings)) {
            $bookings = $wpdb->get_results($wpdb->prepare(
                "SELECT 
                    cb.id as booking_id,
                    e.name as event_name,
                    ep.periodStart as period_start,
                    cb.status as booking_status,
                    cb.formula as formula
                 FROM 
                    {$wpdb->prefix}fpr_customer_bookings cb
                    JOIN {$wpdb->prefix}fpr_customers c ON cb.customerId = c.id
                    JOIN {$wpdb->prefix}fpr_events_periods ep ON cb.eventPeriodId = ep.id
                    JOIN {$wpdb->prefix}fpr_events e ON ep.eventId = e.id
                 WHERE 
                    c.email = %s
                 ORDER BY 
                    ep.periodStart DESC",
                $user_email
            ));
        }

        // Récupérer les informations client
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_customers WHERE email = %s",
            $user_email
        ));

        // Formater les données pour l'affichage
        $formatted_subscriptions = [];
        foreach ($subscriptions as $sub) {
            $formatted_subscriptions[] = [
                'id' => $sub->id,
                'plan_name' => $sub->plan_name,
                'plan_frequency' => $sub->plan_frequency,
                'saison_name' => $sub->saison_name,
                'status' => $sub->status,
                'start_date' => $sub->start_date,
                'end_date' => $sub->end_date,
                'total_amount' => number_format($sub->total_amount, 2),
                'installment_amount' => number_format($sub->installment_amount, 2),
                'installments_paid' => $sub->installments_paid
            ];
        }

        $formatted_bookings = [];
        foreach ($bookings as $booking) {
            $status_text = '';
            switch ($booking->booking_status) {
                case 'approved': $status_text = 'Confirmé'; break;
                case 'pending': $status_text = 'En attente'; break;
                case 'canceled': $status_text = 'Annulé'; break;
                case 'rejected': $status_text = 'Rejeté'; break;
                default: $status_text = $booking->booking_status;
            }

            $formatted_bookings[] = [
                'id' => $booking->booking_id,
                'event_name' => $booking->event_name,
                'period_start' => date('d/m/Y H:i', strtotime($booking->period_start)),
                'status' => $status_text,
                'formula' => $booking->formula
            ];
        }

        wp_send_json_success([
            'subscriptions' => $formatted_subscriptions,
            'bookings' => $formatted_bookings,
            'customer' => $customer
        ]);
    }

    /**
     * AJAX handler pour mettre à jour les informations d'un utilisateur
     */
    public static function ajax_update_user_details() {
        global $wpdb;

        // Vérification du nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fpr_update_user_details')) {
            wp_send_json_error('Sécurité: nonce invalide');
            return;
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $new_email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        // Récupérer et traiter les emails additionnels
        $additional_emails = [];
        if (isset($_POST['additional_emails']) && is_array($_POST['additional_emails'])) {
            foreach ($_POST['additional_emails'] as $email) {
                $sanitized_email = sanitize_email($email);
                if (!empty($sanitized_email)) {
                    $additional_emails[] = $sanitized_email;
                }
            }
        }

        // Convertir le tableau d'emails en JSON pour le stockage
        $additional_emails_json = !empty($additional_emails) ? json_encode($additional_emails) : '';

        if (!$user_id) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: ID utilisateur invalide ou manquant");
            wp_send_json_error('ID utilisateur invalide');
            return;
        }

        \FPR\Helpers\Logger::log("[Subscribers] Mise à jour des informations pour l'utilisateur ID: $user_id");
        if (!empty($additional_emails)) {
            \FPR\Helpers\Logger::log("[Subscribers] Emails additionnels: " . implode(', ', $additional_emails));
        }

        // Récupérer l'email de l'utilisateur
        $user = get_userdata($user_id);
        if (!$user) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: Utilisateur avec ID $user_id introuvable dans WordPress");
            wp_send_json_error('Utilisateur introuvable');
            return;
        }

        $user_email = $user->user_email;
        \FPR\Helpers\Logger::log("[Subscribers] Utilisateur trouvé: ID=$user_id, Email=$user_email");

        // Vérifier si l'email a été modifié
        if (!empty($new_email) && $new_email !== $user_email) {
            \FPR\Helpers\Logger::log("[Subscribers] Modification de l'email: $user_email -> $new_email");

            // Vérifier si le nouvel email est déjà utilisé par un autre utilisateur
            if (email_exists($new_email) && email_exists($new_email) != $user_id) {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: L'email $new_email est déjà utilisé par un autre utilisateur");
                wp_send_json_error('Cet email est déjà utilisé par un autre utilisateur.');
                return;
            }

            // Mettre à jour l'email de l'utilisateur WordPress
            $result = wp_update_user([
                'ID' => $user_id,
                'user_email' => $new_email
            ]);

            if (is_wp_error($result)) {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur lors de la mise à jour de l'email WordPress: " . $result->get_error_message());
                wp_send_json_error('Erreur lors de la mise à jour de l\'email: ' . $result->get_error_message());
                return;
            }

            \FPR\Helpers\Logger::log("[Subscribers] ✅ Email WordPress mis à jour avec succès");

            // L'email a été mis à jour, utiliser le nouvel email pour la suite
            $user_email = $new_email;
        }

        // Vérifier si le client existe déjà avec l'ancien email (si l'email a été modifié)
        $old_email = $user_email;
        if (!empty($new_email) && $new_email !== $user->user_email) {
            $old_email = $user->user_email;
        }

        // Chercher d'abord avec l'email actuel
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_customers WHERE email = %s",
            $user_email
        ));

        // Si on ne trouve pas et que l'email a été modifié, chercher avec l'ancien email
        if (!$customer && $old_email !== $user_email) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_customers WHERE email = %s",
                $old_email
            ));

            // Si on a trouvé avec l'ancien email, on va mettre à jour l'email
            if ($customer) {
                \FPR\Helpers\Logger::log("[Subscribers] Client trouvé avec l'ancien email: $old_email, mise à jour vers $user_email");
            }
        }

        // Vérifier si la table a la colonne additional_emails
        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}fpr_customers");
        $has_additional_emails_column = in_array('additional_emails', $columns);

        // Si la colonne n'existe pas, l'ajouter
        if (!$has_additional_emails_column) {
            \FPR\Helpers\Logger::log("[Subscribers] Ajout de la colonne additional_emails à la table fpr_customers");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}fpr_customers ADD COLUMN additional_emails TEXT DEFAULT NULL");
        }

        if ($customer) {
            \FPR\Helpers\Logger::log("[Subscribers] Client existant trouvé avec ID={$customer->id}, user_id actuel=" . ($customer->user_id ?: 'NULL'));

            // Mettre à jour le client existant
            $update_data = [
                'note' => $note,
                'phone' => $phone,
                'user_id' => $user_id, // S'assurer que user_id est correctement défini
                'additional_emails' => $additional_emails_json,
                'email' => $user_email // Mettre à jour l'email si nécessaire
            ];

            $result = $wpdb->update(
                $wpdb->prefix . 'fpr_customers',
                $update_data,
                ['id' => $customer->id]
            );

            if ($result === false) {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur lors de la mise à jour du client: " . $wpdb->last_error);
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Requête SQL: " . $wpdb->last_query);
                wp_send_json_error('Erreur lors de la mise à jour: ' . $wpdb->last_error);
                return;
            } elseif ($result === 0) {
                // Aucune ligne mise à jour car les données sont identiques
                \FPR\Helpers\Logger::log("[Subscribers] ℹ️ Aucune mise à jour nécessaire (données identiques)");
            } else {
                \FPR\Helpers\Logger::log("[Subscribers] ✅ Client mis à jour avec succès, user_id défini à $user_id");
            }
        } else {
            \FPR\Helpers\Logger::log("[Subscribers] Aucun client trouvé avec l'email $user_email, création d'un nouveau client");

            // Créer un nouveau client
            $user_first_name = $user->first_name ?: '';
            $user_last_name = $user->last_name ?: '';

            if (empty($user_first_name) && empty($user_last_name)) {
                $name_parts = explode(' ', $user->display_name, 2);
                $user_first_name = $name_parts[0] ?? '';
                $user_last_name = $name_parts[1] ?? '';
            }

            $data = [
                'user_id' => $user_id,
                'firstName' => $user_first_name,
                'lastName' => $user_last_name,
                'email' => $user_email,
                'phone' => $phone,
                'note' => $note,
                'additional_emails' => $additional_emails_json,
                'status' => 'active'
            ];

            \FPR\Helpers\Logger::log("[Subscribers] Tentative de création d'un client avec user_id=$user_id");

            $result = $wpdb->insert(
                $wpdb->prefix . 'fpr_customers',
                $data
            );

            if ($result === false) {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur lors de la création du client: " . $wpdb->last_error);
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Requête SQL: " . $wpdb->last_query);
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Données: " . print_r($data, true));
                wp_send_json_error('Erreur lors de la création: ' . $wpdb->last_error);
                return;
            }

            $new_customer_id = $wpdb->insert_id;
            \FPR\Helpers\Logger::log("[Subscribers] ✅ Nouveau client créé avec ID=$new_customer_id et user_id=$user_id");
        }

        wp_send_json_success('Informations mises à jour avec succès');
    }

    public static function handle_delete_subscriber() {
        global $wpdb;

        \FPR\Helpers\Logger::log("[Subscribers] Début du traitement de suppression d'un abonnement");

        // Vérification du nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fpr_delete_subscriber_nonce')) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: Nonce invalide pour la suppression");
            wp_die('Sécurité: nonce invalide');
        }

        \FPR\Helpers\Logger::log("[Subscribers] ✅ Nonce de suppression validé");

        // Récupération de l'ID de l'abonnement
        if (!isset($_POST['subscription_id']) || !is_numeric($_POST['subscription_id'])) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: ID d'abonnement invalide");
            wp_die('ID d\'abonnement invalide');
        }

        $subscription_id = intval($_POST['subscription_id']);
        \FPR\Helpers\Logger::log("[Subscribers] 🔍 Tentative de suppression de l'abonnement ID: $subscription_id");

        try {
            // Récupérer les informations de l'abonnement avant suppression (pour le log)
            $subscription = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_customer_subscriptions WHERE id = %d",
                $subscription_id
            ));

            if (!$subscription) {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: Abonnement ID $subscription_id introuvable");
                wp_die('Abonnement introuvable');
            }

            \FPR\Helpers\Logger::log("[Subscribers] ✅ Abonnement trouvé: " . json_encode([
                'id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'payment_plan_id' => $subscription->payment_plan_id,
                'status' => $subscription->status
            ]));

            // Supprimer l'abonnement
            $result = $wpdb->delete(
                $wpdb->prefix . 'fpr_customer_subscriptions',
                ['id' => $subscription_id],
                ['%d']
            );

            if ($result === false) {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur SQL lors de la suppression: " . $wpdb->last_error);
                wp_die('Erreur lors de la suppression de l\'abonnement: ' . $wpdb->last_error);
            }

            \FPR\Helpers\Logger::log("[Subscribers] ✅ Abonnement ID $subscription_id supprimé avec succès");

        } catch (\Exception $e) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Exception lors de la suppression: " . $e->getMessage());
            wp_die('Exception lors de la suppression de l\'abonnement: ' . $e->getMessage());
        }

        // Redirection vers la page des abonnements avec un message de succès
        \FPR\Helpers\Logger::log("[Subscribers] 🔄 Redirection après suppression");

        // Vider tous les tampons de sortie avant la redirection
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Vérifier s'il y a déjà eu des headers envoyés
        if (headers_sent($file, $line)) {
            \FPR\Helpers\Logger::log("[Subscribers] ⚠️ Headers déjà envoyés dans $file à la ligne $line");
            echo '<script>window.location = "' . admin_url('options-general.php?page=fpr-settings&tab=subscribers&deleted=1') . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . admin_url('options-general.php?page=fpr-settings&tab=subscribers&deleted=1') . '"></noscript>';
            \FPR\Helpers\Logger::log("[Subscribers] ✅ Redirection alternative via JavaScript/meta après suppression");
            exit;
        }

        // Enregistrer les headers HTTP avant la redirection
        $redirect_url = admin_url('options-general.php?page=fpr-settings&tab=subscribers&deleted=1');
        \FPR\Helpers\Logger::log("[Subscribers] 🔗 URL de redirection après suppression: $redirect_url");

        // Effectuer la redirection
        $result = wp_redirect($redirect_url);
        \FPR\Helpers\Logger::log("[Subscribers] " . ($result ? "✅ wp_redirect a réussi" : "❌ wp_redirect a échoué") . " après suppression");

        \FPR\Helpers\Logger::log("[Subscribers] ✅ Fin du traitement de suppression d'un abonnement");
        exit;
    }

    /**
     * AJAX handler pour la suppression groupée d'abonnements
     */
    public static function ajax_bulk_delete_subscribers() {
        global $wpdb;

        \FPR\Helpers\Logger::log("[Subscribers] Début du traitement de suppression groupée d'abonnements");

        // Vérification du nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fpr_admin_nonce')) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: Nonce invalide pour la suppression groupée");
            wp_send_json_error(['message' => 'Sécurité: nonce invalide']);
            return;
        }

        \FPR\Helpers\Logger::log("[Subscribers] ✅ Nonce de suppression groupée validé");

        // Récupération des IDs des abonnements
        if (!isset($_POST['subscription_ids']) || !is_array($_POST['subscription_ids']) || empty($_POST['subscription_ids'])) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur: IDs d'abonnements invalides ou vides");
            wp_send_json_error(['message' => 'IDs d\'abonnements invalides ou vides']);
            return;
        }

        $subscription_ids = array_map('intval', $_POST['subscription_ids']);
        $count = count($subscription_ids);

        \FPR\Helpers\Logger::log("[Subscribers] 🔍 Tentative de suppression de $count abonnements: " . implode(', ', $subscription_ids));

        $success_count = 0;
        $errors = [];

        foreach ($subscription_ids as $subscription_id) {
            try {
                // Récupérer les informations de l'abonnement avant suppression (pour le log)
                $subscription = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}fpr_customer_subscriptions WHERE id = %d",
                    $subscription_id
                ));

                if (!$subscription) {
                    \FPR\Helpers\Logger::log("[Subscribers] ⚠️ Abonnement ID $subscription_id introuvable");
                    $errors[] = "Abonnement ID $subscription_id introuvable";
                    continue;
                }

                \FPR\Helpers\Logger::log("[Subscribers] ✅ Abonnement trouvé: " . json_encode([
                    'id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'payment_plan_id' => $subscription->payment_plan_id,
                    'status' => $subscription->status
                ]));

                // Supprimer l'abonnement
                $result = $wpdb->delete(
                    $wpdb->prefix . 'fpr_customer_subscriptions',
                    ['id' => $subscription_id],
                    ['%d']
                );

                if ($result === false) {
                    \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur SQL lors de la suppression de l'abonnement ID $subscription_id: " . $wpdb->last_error);
                    $errors[] = "Erreur lors de la suppression de l'abonnement ID $subscription_id: " . $wpdb->last_error;
                } else {
                    \FPR\Helpers\Logger::log("[Subscribers] ✅ Abonnement ID $subscription_id supprimé avec succès");
                    $success_count++;
                }

            } catch (\Exception $e) {
                \FPR\Helpers\Logger::log("[Subscribers] ❌ Exception lors de la suppression de l'abonnement ID $subscription_id: " . $e->getMessage());
                $errors[] = "Exception lors de la suppression de l'abonnement ID $subscription_id: " . $e->getMessage();
            }
        }

        \FPR\Helpers\Logger::log("[Subscribers] ✅ Fin du traitement de suppression groupée: $success_count/$count abonnements supprimés avec succès");

        if ($success_count === $count) {
            wp_send_json_success([
                'message' => "$count abonnements ont été supprimés avec succès",
                'count' => $success_count
            ]);
        } else if ($success_count > 0) {
            wp_send_json_success([
                'message' => "$success_count/$count abonnements ont été supprimés avec succès. Erreurs: " . implode('; ', $errors),
                'count' => $success_count,
                'errors' => $errors
            ]);
        } else {
            wp_send_json_error([
                'message' => "Échec de la suppression des abonnements. Erreurs: " . implode('; ', $errors),
                'errors' => $errors
            ]);
        }
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
     * Handler for exporting subscribers to CSV
     */
    public static function handle_export_subscribers_csv() {
        \FPR\Helpers\Logger::log("[Subscribers] Début de l'export CSV des abonnés");

        // Vérifier le nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fpr_export_subscribers_nonce')) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Nonce invalide pour l'export CSV");
            wp_die('Nonce invalide');
        }

        global $wpdb;

        // Récupérer les champs sélectionnés pour l'export
        $export_fields = isset($_POST['export_fields']) ? $_POST['export_fields'] : [];

        if (empty($export_fields)) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Aucun champ sélectionné pour l'export CSV");
            wp_die('Veuillez sélectionner au moins un champ à exporter');
        }

        \FPR\Helpers\Logger::log("[Subscribers] Champs sélectionnés pour l'export CSV: " . implode(', ', $export_fields));

        // Récupérer les abonnements existants avec les informations de base
        $query = "
            SELECT s.*, 
                   u.display_name as user_name, 
                   u.user_email as user_email,
                   p.name as plan_name,
                   sn.name as saison_name,
                   c.note as customer_note,
                   c.phone as user_phone
            FROM {$wpdb->prefix}fpr_customer_subscriptions s
            LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}fpr_payment_plans p ON s.payment_plan_id = p.id
            LEFT JOIN {$wpdb->prefix}fpr_saisons sn ON s.saison_id = sn.id
            LEFT JOIN {$wpdb->prefix}fpr_customers c ON u.user_email = c.email
            ORDER BY s.created_at DESC
        ";

        $subscriptions = $wpdb->get_results($query);

        \FPR\Helpers\Logger::log("[Subscribers] Nombre d'abonnés pour l'export CSV: " . count($subscriptions));

        // Récupérer les informations d'adresse si nécessaire
        $need_address = false;
        $address_fields = ['billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 
                          'billing_postcode', 'billing_country', 'billing_company'];

        foreach ($address_fields as $field) {
            if (in_array($field, $export_fields)) {
                $need_address = true;
                break;
            }
        }

        if ($need_address) {
            \FPR\Helpers\Logger::log("[Subscribers] Récupération des informations d'adresse pour l'export CSV");
            foreach ($subscriptions as &$subscription) {
                if (!empty($subscription->user_id)) {
                    $subscription->billing_address_1 = get_user_meta($subscription->user_id, 'billing_address_1', true);
                    $subscription->billing_address_2 = get_user_meta($subscription->user_id, 'billing_address_2', true);
                    $subscription->billing_city = get_user_meta($subscription->user_id, 'billing_city', true);
                    $subscription->billing_state = get_user_meta($subscription->user_id, 'billing_state', true);
                    $subscription->billing_postcode = get_user_meta($subscription->user_id, 'billing_postcode', true);
                    $subscription->billing_country = get_user_meta($subscription->user_id, 'billing_country', true);
                    $subscription->billing_company = get_user_meta($subscription->user_id, 'billing_company', true);
                }
            }
        }


        // Définir les en-têtes pour le téléchargement
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=abonnes-' . date('Y-m-d') . '.csv');

        // Créer le fichier CSV
        $output = fopen('php://output', 'w');

        // Ajouter le BOM UTF-8 pour Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Préparer les en-têtes du CSV en fonction des champs sélectionnés
        $headers = [];
        $field_labels = [
            'id' => 'ID',
            'user_name' => 'Nom',
            'user_email' => 'Email',
            'user_phone' => 'Téléphone',
            'plan_name' => 'Plan de paiement',
            'saison_name' => 'Saison',
            'status' => 'Statut',
            'start_date' => 'Date de début',
            'end_date' => 'Date de fin',
            'total_amount' => 'Montant total',
            'installment_amount' => 'Montant par versement',
            'installments_paid' => 'Versements payés',
            'stripe_subscription_id' => 'ID Stripe',
            'customer_note' => 'Notes',
            'created_at' => 'Date de création',
            'billing_address_1' => 'Adresse 1',
            'billing_address_2' => 'Adresse 2',
            'billing_city' => 'Ville',
            'billing_state' => 'Région',
            'billing_postcode' => 'Code postal',
            'billing_country' => 'Pays',
            'billing_company' => 'Société'
        ];

        foreach ($export_fields as $field) {
            if (isset($field_labels[$field])) {
                $headers[] = $field_labels[$field];
            }
        }

        // Écrire les en-têtes
        fputcsv($output, $headers, ';');

        // Ajouter les données
        foreach ($subscriptions as $subscription) {
            $status_labels = [
                'active' => 'Actif',
                'pending' => 'En attente',
                'cancelled' => 'Annulé',
                'expired' => 'Expiré'
            ];

            $row = [];
            foreach ($export_fields as $field) {
                if ($field === 'status') {
                    $row[] = $status_labels[$subscription->status] ?? $subscription->status;
                } else {
                    $row[] = $subscription->$field ?? '';
                }
            }

            fputcsv($output, $row, ';');
        }

        fclose($output);

        \FPR\Helpers\Logger::log("[Subscribers] Export CSV terminé avec succès");
        exit;
    }

    /**
     * Handler for exporting subscribers to Excel
     */
    public static function handle_export_subscribers_excel() {
        \FPR\Helpers\Logger::log("[Subscribers] Début de l'export Excel des abonnés");

        // Vérifier le nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fpr_export_subscribers_nonce')) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Nonce invalide pour l'export Excel");
            wp_die('Nonce invalide');
        }

        global $wpdb;

        // Récupérer les champs sélectionnés pour l'export
        $export_fields = isset($_POST['export_fields']) ? $_POST['export_fields'] : [];

        if (empty($export_fields)) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Aucun champ sélectionné pour l'export Excel");
            wp_die('Veuillez sélectionner au moins un champ à exporter');
        }

        \FPR\Helpers\Logger::log("[Subscribers] Champs sélectionnés pour l'export Excel: " . implode(', ', $export_fields));

        // Récupérer les abonnements existants avec les informations de base
        $query = "
            SELECT s.*, 
                   u.display_name as user_name, 
                   u.user_email as user_email,
                   p.name as plan_name,
                   sn.name as saison_name,
                   c.note as customer_note,
                   c.phone as user_phone
            FROM {$wpdb->prefix}fpr_customer_subscriptions s
            LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}fpr_payment_plans p ON s.payment_plan_id = p.id
            LEFT JOIN {$wpdb->prefix}fpr_saisons sn ON s.saison_id = sn.id
            LEFT JOIN {$wpdb->prefix}fpr_customers c ON u.user_email = c.email
            ORDER BY s.created_at DESC
        ";

        $subscriptions = $wpdb->get_results($query);

        \FPR\Helpers\Logger::log("[Subscribers] Nombre d'abonnés pour l'export Excel: " . count($subscriptions));

        // Récupérer les informations d'adresse si nécessaire
        $need_address = false;
        $address_fields = ['billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 
                          'billing_postcode', 'billing_country', 'billing_company'];

        foreach ($address_fields as $field) {
            if (in_array($field, $export_fields)) {
                $need_address = true;
                break;
            }
        }

        if ($need_address) {
            \FPR\Helpers\Logger::log("[Subscribers] Récupération des informations d'adresse pour l'export Excel");
            foreach ($subscriptions as &$subscription) {
                if (!empty($subscription->user_id)) {
                    $subscription->billing_address_1 = get_user_meta($subscription->user_id, 'billing_address_1', true);
                    $subscription->billing_address_2 = get_user_meta($subscription->user_id, 'billing_address_2', true);
                    $subscription->billing_city = get_user_meta($subscription->user_id, 'billing_city', true);
                    $subscription->billing_state = get_user_meta($subscription->user_id, 'billing_state', true);
                    $subscription->billing_postcode = get_user_meta($subscription->user_id, 'billing_postcode', true);
                    $subscription->billing_country = get_user_meta($subscription->user_id, 'billing_country', true);
                    $subscription->billing_company = get_user_meta($subscription->user_id, 'billing_company', true);
                }
            }
        }


        // Définir les labels des champs
        $field_labels = [
            'id' => 'ID',
            'user_name' => 'Nom',
            'user_email' => 'Email',
            'user_phone' => 'Téléphone',
            'plan_name' => 'Plan de paiement',
            'saison_name' => 'Saison',
            'status' => 'Statut',
            'start_date' => 'Date de début',
            'end_date' => 'Date de fin',
            'total_amount' => 'Montant total',
            'installment_amount' => 'Montant par versement',
            'installments_paid' => 'Versements payés',
            'stripe_subscription_id' => 'ID Stripe',
            'customer_note' => 'Notes',
            'created_at' => 'Date de création',
            'billing_address_1' => 'Adresse 1',
            'billing_address_2' => 'Adresse 2',
            'billing_city' => 'Ville',
            'billing_state' => 'Région',
            'billing_postcode' => 'Code postal',
            'billing_country' => 'Pays',
            'billing_company' => 'Société'
        ];

        // Vérifier si la bibliothèque PhpSpreadsheet est disponible
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            // Si PhpSpreadsheet n'est pas disponible, on utilise une alternative simple
            \FPR\Helpers\Logger::log("[Subscribers] PhpSpreadsheet non disponible, utilisation d'un export Excel alternatif");

            // Définir les en-têtes pour le téléchargement
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename=abonnes-' . date('Y-m-d') . '.xls');

            // Début du document HTML/Excel
            echo '<!DOCTYPE html>
            <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                <title>Export des abonnés</title>
            </head>
            <body>
                <table border="1">
                    <thead>
                        <tr>';

            // Ajouter les en-têtes sélectionnés
            foreach ($export_fields as $field) {
                if (isset($field_labels[$field])) {
                    echo '<th>' . esc_html($field_labels[$field]) . '</th>';
                }
            }

            echo '</tr>
                    </thead>
                    <tbody>';

            // Ajouter les données
            foreach ($subscriptions as $subscription) {
                $status_labels = [
                    'active' => 'Actif',
                    'pending' => 'En attente',
                    'cancelled' => 'Annulé',
                    'expired' => 'Expiré'
                ];

                echo '<tr>';
                foreach ($export_fields as $field) {
                    echo '<td>';
                    if ($field === 'status') {
                        echo esc_html($status_labels[$subscription->status] ?? $subscription->status);
                    } else {
                        echo esc_html($subscription->$field ?? '');
                    }
                    echo '</td>';
                }
                echo '</tr>';
            }

            // Fin du document HTML/Excel
            echo '</tbody>
                </table>
            </body>
            </html>';

            \FPR\Helpers\Logger::log("[Subscribers] Export Excel alternatif terminé avec succès");
            exit;
        }

        // Si PhpSpreadsheet est disponible, on l'utilise pour un export Excel plus avancé
        try {
            require_once ABSPATH . 'vendor/autoload.php';

            // Créer un nouveau document Excel
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Définir les en-têtes en fonction des champs sélectionnés
            $col = 'A';
            foreach ($export_fields as $field) {
                if (isset($field_labels[$field])) {
                    $sheet->setCellValue($col . '1', $field_labels[$field]);
                    $col++;
                }
            }

            // Style pour les en-têtes
            $headerStyle = [
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => [
                        'rgb' => 'EEEEEE',
                    ],
                ],
            ];

            $sheet->getStyle('A1:' . chr(64 + count($export_fields)) . '1')->applyFromArray($headerStyle);

            // Ajouter les données
            $row = 2;
            foreach ($subscriptions as $subscription) {
                $status_labels = [
                    'active' => 'Actif',
                    'pending' => 'En attente',
                    'cancelled' => 'Annulé',
                    'expired' => 'Expiré'
                ];

                $col = 'A';
                foreach ($export_fields as $field) {
                    if ($field === 'status') {
                        $sheet->setCellValue($col . $row, $status_labels[$subscription->status] ?? $subscription->status);
                    } else {
                        $sheet->setCellValue($col . $row, $subscription->$field ?? '');
                    }
                    $col++;
                }

                $row++;
            }

            // Auto-dimensionner les colonnes
            foreach (range('A', chr(64 + count($export_fields))) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Définir les en-têtes pour le téléchargement
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="abonnes-' . date('Y-m-d') . '.xlsx"');
            header('Cache-Control: max-age=0');

            // Créer le writer Excel et envoyer le fichier
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');

            \FPR\Helpers\Logger::log("[Subscribers] Export Excel terminé avec succès");
            exit;

        } catch (\Exception $e) {
            \FPR\Helpers\Logger::log("[Subscribers] ❌ Erreur lors de l'export Excel: " . $e->getMessage());
            wp_die('Erreur lors de l\'export Excel: ' . $e->getMessage());
        }
    }
}
