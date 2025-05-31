<?php
namespace FPR\Admin;

if (!defined('ABSPATH')) exit;

class PaymentPlans {
    public static function init() {
        add_action('admin_post_fpr_create_payment_plan', [__CLASS__, 'handle_create_payment_plan']);
        add_action('admin_post_fpr_update_payment_plan', [__CLASS__, 'handle_update_payment_plan']);
        add_action('admin_post_fpr_delete_payment_plan', [__CLASS__, 'handle_delete_payment_plan']);

        // Ajouter un hook pour vérifier les erreurs PHP
        add_action('shutdown', [__CLASS__, 'check_for_fatal_errors']);
    }

    /**
     * Enqueue scripts and styles for the payment plans page
     */
    public static function enqueue_scripts_and_styles() {
        // Enqueue common admin styles
        wp_enqueue_style('fpr-admin', FPR_PLUGIN_URL . 'assets/css/admin.css', [], FPR_VERSION);

        // Add dashicons for the icons
        wp_enqueue_style('dashicons');
    }

    /**
     * Vérifie s'il y a eu des erreurs fatales et les enregistre dans le journal
     */
    public static function check_for_fatal_errors() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR])) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ❌ ERREUR FATALE: " . $error['message'] . " dans " . $error['file'] . " ligne " . $error['line']);
        }
    }

    public static function render_page() {
        global $wpdb;

        // Enqueue scripts and styles for the payment plans page
        self::enqueue_scripts_and_styles();
        ?>
        <div class="wrap">
            <h1>Gestion des plans de paiement</h1>

            <h2>Créer un nouveau plan de paiement</h2>
            <button type="button" id="fpr-add-payment-plan" class="fpr-add-button">
                <span class="dashicons dashicons-plus"></span>
            </button>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="fpr-form" id="fpr-create-form" style="display: none;">
                <input type="hidden" name="action" value="fpr_create_payment_plan">
                <?php wp_nonce_field('fpr_create_payment_plan_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="name">Nom du plan</label></th>
                        <td><input type="text" id="name" name="name" placeholder="Ex: Paiement mensuel" required></td>
                    </tr>
                    <tr>
                        <th><label for="frequency">Fréquence</label></th>
                        <td>
                            <select id="frequency" name="frequency" required>
                                <option value="hourly">Horaire</option>
                                <option value="daily">Journalier</option>
                                <option value="weekly">Hebdomadaire</option>
                                <option value="monthly">Mensuel</option>
                                <option value="quarterly">Trimestriel</option>
                                <option value="annual">Annuel</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="term">Terme</label></th>
                        <td>
                            <input type="text" id="term" name="term" placeholder="Ex: mois, trim, an">
                            <p class="description">Terme qui sera affiché après le nombre de versements (ex: /mois, /trim, /an).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="installments">Nombre de versements</label></th>
                        <td>
                            <input type="number" id="installments" name="installments" min="1" value="10" required>
                            <p class="description">Nombre total de paiements que le client effectuera sur la durée de son abonnement.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="description">Description</label></th>
                        <td><textarea id="description" name="description" rows="3" placeholder="Description du plan de paiement"></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="is_default">Plan par défaut</label></th>
                        <td>
                            <input type="checkbox" id="is_default" name="is_default" value="1">
                            <p class="description">Si coché, ce plan sera proposé par défaut aux clients. Un seul plan peut être défini par défaut.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Créer le plan de paiement</button>
                </p>
            </form>
            <?php 
            if (isset($_GET['done'])) echo "<div class='updated'><p>Plan de paiement créé avec succès !</p></div>";
            if (isset($_GET['updated'])) echo "<div class='updated'><p>Plan de paiement mis à jour avec succès !</p></div>";
            if (isset($_GET['deleted'])) echo "<div class='updated'><p>Plan de paiement supprimé avec succès !</p></div>";
            ?>

            <!-- Formulaire de modification (caché par défaut) -->
            <div id="edit-payment-plan-form" style="display: none;" class="fpr-edit-form">
                <h2>Modifier un plan de paiement</h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="fpr-form">
                    <input type="hidden" name="action" value="fpr_update_payment_plan">
                    <input type="hidden" name="plan_id" id="edit-plan-id">
                    <?php wp_nonce_field('fpr_update_payment_plan_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="edit-name">Nom du plan</label></th>
                            <td><input type="text" id="edit-name" name="name" placeholder="Ex: Paiement mensuel" required></td>
                        </tr>
                        <tr>
                            <th><label for="edit-frequency">Fréquence</label></th>
                            <td>
                                <select id="edit-frequency" name="frequency" required>
                                    <option value="hourly">Horaire</option>
                                    <option value="daily">Journalier</option>
                                    <option value="weekly">Hebdomadaire</option>
                                    <option value="monthly">Mensuel</option>
                                    <option value="quarterly">Trimestriel</option>
                                    <option value="annual">Annuel</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit-term">Terme</label></th>
                            <td>
                                <input type="text" id="edit-term" name="term" placeholder="Ex: mois, trim, an">
                                <p class="description">Terme qui sera affiché après le nombre de versements (ex: /mois, /trim, /an).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit-installments">Nombre de versements</label></th>
                            <td>
                                <input type="number" id="edit-installments" name="installments" min="1" required>
                                <p class="description">Nombre total de paiements que le client effectuera sur la durée de son abonnement.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit-description">Description</label></th>
                            <td><textarea id="edit-description" name="description" rows="3" placeholder="Description du plan de paiement"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="edit-active">Statut</label></th>
                            <td>
                                <select id="edit-active" name="active">
                                    <option value="1">Actif</option>
                                    <option value="0">Inactif</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit-is_default">Plan par défaut</label></th>
                            <td>
                                <input type="checkbox" id="edit-is_default" name="is_default" value="1">
                                <p class="description">Si coché, ce plan sera proposé par défaut aux clients. Un seul plan peut être défini par défaut.</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Mettre à jour</button>
                        <button type="button" class="button button-secondary cancel-edit">Annuler</button>
                    </p>
                </form>
            </div>

            <h2>Plans de paiement existants</h2>
            <?php
            // Création de la table si elle n'existe pas
            self::create_table_if_not_exists();

            // Récupérer les plans de paiement existants
            $payment_plans = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fpr_payment_plans ORDER BY id DESC");

            if (empty($payment_plans)) {
                echo '<p>Aucun plan de paiement n\'a été créé pour le moment.</p>';
            } else {
                ?>
                <table class="fpr-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Fréquence</th>
                            <th>Terme</th>
                            <th>Versements</th>
                            <th>Description</th>
                            <th>Statut</th>
                            <th>Par défaut</th>
                            <th>Date de création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_plans as $plan) : 
                            $frequency_label = [
                                'hourly' => 'Horaire',
                                'daily' => 'Journalier',
                                'weekly' => 'Hebdomadaire',
                                'monthly' => 'Mensuel',
                                'quarterly' => 'Trimestriel',
                                'annual' => 'Annuel'
                            ];
                        ?>
                            <tr>
                                <td><?php echo esc_html($plan->id); ?></td>
                                <td><?php echo esc_html($plan->name); ?></td>
                                <td><?php echo isset($frequency_label[$plan->frequency]) ? esc_html($frequency_label[$plan->frequency]) : esc_html($plan->frequency); ?></td>
                                <td><?php echo !empty($plan->term) ? esc_html($plan->term) : '-'; ?></td>
                                <td><?php echo esc_html($plan->installments); ?></td>
                                <td><?php echo esc_html($plan->description); ?></td>
                                <td>
                                    <?php if ($plan->active): ?>
                                        <span class="fpr-status fpr-status-active">Actif</span>
                                    <?php else: ?>
                                        <span class="fpr-status fpr-status-inactive">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($plan->is_default): ?>
                                        <span class="fpr-status fpr-status-default">Par défaut</span>
                                    <?php else: ?>
                                        <span>-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($plan->created_at); ?></td>
                                <td class="actions-column">
                                    <div class="action-buttons">
                                        <a href="#" class="button edit-button edit-plan" data-id="<?php echo esc_attr($plan->id); ?>" data-name="<?php echo esc_attr($plan->name); ?>" data-frequency="<?php echo esc_attr($plan->frequency); ?>" data-term="<?php echo esc_attr($plan->term); ?>" data-installments="<?php echo esc_attr($plan->installments); ?>" data-description="<?php echo esc_attr($plan->description); ?>" data-active="<?php echo esc_attr($plan->active); ?>" data-is-default="<?php echo esc_attr($plan->is_default); ?>">
                                            <span class="dashicons dashicons-edit"></span> Modifier
                                        </a>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce plan de paiement ? Cette action est irréversible.');" class="delete-form">
                                            <input type="hidden" name="action" value="fpr_delete_payment_plan">
                                            <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan->id); ?>">
                                            <?php wp_nonce_field('fpr_delete_payment_plan_nonce'); ?>
                                            <button type="submit" class="button delete-button">
                                                <span class="dashicons dashicons-trash"></span> Supprimer
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
            ?>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Gérer le clic sur le bouton d'ajout
                $('#fpr-add-payment-plan').on('click', function() {
                    $('#fpr-create-form').slideToggle(300);
                });

                // Gérer le clic sur le bouton Modifier
                $('.edit-plan').on('click', function(e) {
                    e.preventDefault();

                    // Récupérer les données du plan
                    var planId = $(this).data('id');
                    var planName = $(this).data('name');
                    var planFrequency = $(this).data('frequency');
                    var planTerm = $(this).data('term');
                    var planInstallments = $(this).data('installments');
                    var planDescription = $(this).data('description');
                    var planActive = $(this).data('active');
                    var planIsDefault = $(this).data('is-default');

                    // Remplir le formulaire d'édition
                    $('#edit-plan-id').val(planId);
                    $('#edit-name').val(planName);
                    $('#edit-frequency').val(planFrequency);
                    $('#edit-term').val(planTerm);
                    $('#edit-installments').val(planInstallments);
                    $('#edit-description').val(planDescription);
                    $('#edit-active').val(planActive);
                    $('#edit-is_default').prop('checked', planIsDefault == 1);

                    // Afficher le formulaire d'édition
                    $('#edit-payment-plan-form').show();

                    // Faire défiler jusqu'au formulaire
                    $('html, body').animate({
                        scrollTop: $('#edit-payment-plan-form').offset().top - 50
                    }, 500);
                });

                // Gérer le clic sur le bouton Annuler
                $('.cancel-edit').on('click', function() {
                    // Cacher le formulaire d'édition
                    $('#edit-payment-plan-form').hide();
                });
            });
        </script>
        <?php
    }

    public static function handle_create_payment_plan() {
        global $wpdb;

        \FPR\Helpers\Logger::log("[PaymentPlans] Début du traitement de création d'un plan de paiement");

        // Vérification du nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fpr_create_payment_plan_nonce')) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur: Nonce invalide");
            wp_die('Sécurité: nonce invalide');
        }

        \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Nonce validé");

        // Récupération des données du formulaire
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : '';
        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
        $installments = isset($_POST['installments']) ? intval($_POST['installments']) : 0;
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        \FPR\Helpers\Logger::log("[PaymentPlans] 📝 Données du formulaire: name='$name', frequency='$frequency', term='$term', installments=$installments");

        // Validation
        if (empty($name) || empty($frequency) || $installments < 1) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur: Champs obligatoires manquants");
            wp_die('Veuillez remplir tous les champs obligatoires.');
        }

        \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Validation des données réussie");

        // Création de la table si elle n'existe pas
        try {
            self::create_table_if_not_exists();
            \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Table vérifiée/créée avec succès");
        } catch (\Exception $e) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur lors de la création de la table: " . $e->getMessage());
            wp_die('Erreur lors de la création de la table: ' . $e->getMessage());
        }

        // Enregistrement du plan de paiement
        try {
            \FPR\Helpers\Logger::log("[PaymentPlans] 🔄 Tentative d'insertion dans la base de données");

            // Si ce plan est défini comme par défaut, désactiver tous les autres plans par défaut
            if ($is_default) {
                $wpdb->update(
                    $wpdb->prefix . 'fpr_payment_plans',
                    array('is_default' => 0),
                    array('is_default' => 1)
                );
                \FPR\Helpers\Logger::log("[PaymentPlans] 🔄 Réinitialisation des plans par défaut");
            }

            $result = $wpdb->insert(
                $wpdb->prefix . 'fpr_payment_plans',
                array(
                    'name' => $name,
                    'frequency' => $frequency,
                    'term' => $term,
                    'installments' => $installments,
                    'description' => $description,
                    'active' => 1,
                    'is_default' => $is_default
                )
            );

            if ($result === false) {
                \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur SQL lors de l'insertion: " . $wpdb->last_error);
                wp_die('Erreur lors de l\'enregistrement du plan de paiement: ' . $wpdb->last_error);
            }

            $plan_id = $wpdb->insert_id;
            \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Plan de paiement créé avec l'ID: $plan_id");

            if (!$plan_id) {
                \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur: Aucun ID retourné après l'insertion");
                wp_die('Erreur lors de l\'enregistrement du plan de paiement: aucun ID retourné.');
            }
        } catch (\Exception $e) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Exception lors de l'insertion: " . $e->getMessage());
            wp_die('Exception lors de l\'enregistrement du plan de paiement: ' . $e->getMessage());
        }

        // Redirection vers la page des plans de paiement
        \FPR\Helpers\Logger::log("[PaymentPlans] 🔄 Redirection vers la page des plans de paiement");

        // Vider tous les tampons de sortie avant la redirection
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Vérifier s'il y a déjà eu des headers envoyés
        if (headers_sent($file, $line)) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ⚠️ Headers déjà envoyés dans $file à la ligne $line");
            echo '<script>window.location = "' . admin_url('options-general.php?page=fpr-settings&tab=payment_plans&done=1') . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . admin_url('options-general.php?page=fpr-settings&tab=payment_plans&done=1') . '"></noscript>';
            \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Redirection alternative via JavaScript/meta");
            exit;
        }

        // Enregistrer les headers HTTP avant la redirection
        $redirect_url = admin_url('options-general.php?page=fpr-settings&tab=payment_plans&done=1');
        \FPR\Helpers\Logger::log("[PaymentPlans] 🔗 URL de redirection: $redirect_url");

        // Effectuer la redirection
        $result = wp_redirect($redirect_url);
        \FPR\Helpers\Logger::log("[PaymentPlans] " . ($result ? "✅ wp_redirect a réussi" : "❌ wp_redirect a échoué"));

        \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Fin du traitement de création d'un plan de paiement");
        exit;
    }

    public static function handle_update_payment_plan() {
        global $wpdb;

        \FPR\Helpers\Logger::log("[PaymentPlans] Début du traitement de mise à jour d'un plan de paiement");

        // Vérification du nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fpr_update_payment_plan_nonce')) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur: Nonce invalide pour la mise à jour");
            wp_die('Sécurité: nonce invalide');
        }

        \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Nonce de mise à jour validé");

        // Récupération de l'ID du plan
        if (!isset($_POST['plan_id']) || !is_numeric($_POST['plan_id'])) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur: ID de plan invalide");
            wp_die('ID de plan invalide');
        }

        $plan_id = intval($_POST['plan_id']);
        \FPR\Helpers\Logger::log("[PaymentPlans] 🔍 Tentative de mise à jour du plan ID: $plan_id");

        // Récupération des données du formulaire
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : '';
        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
        $installments = isset($_POST['installments']) ? intval($_POST['installments']) : 0;
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $active = isset($_POST['active']) ? intval($_POST['active']) : 1;
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        \FPR\Helpers\Logger::log("[PaymentPlans] 📝 Données du formulaire: name='$name', frequency='$frequency', term='$term', installments=$installments, active=$active");

        // Validation
        if (empty($name) || empty($frequency) || $installments < 1) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur: Champs obligatoires manquants");
            wp_die('Veuillez remplir tous les champs obligatoires.');
        }

        try {
            // Vérifier si le plan existe
            $plan = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
                $plan_id
            ));

            if (!$plan) {
                \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur: Plan ID $plan_id introuvable");
                wp_die('Plan de paiement introuvable');
            }

            \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Plan trouvé: " . json_encode([
                'id' => $plan->id,
                'name' => $plan->name,
                'frequency' => $plan->frequency,
                'installments' => $plan->installments,
                'active' => $plan->active
            ]));

            // Si ce plan est défini comme par défaut, désactiver tous les autres plans par défaut
            if ($is_default) {
                $wpdb->update(
                    $wpdb->prefix . 'fpr_payment_plans',
                    array('is_default' => 0),
                    array('is_default' => 1)
                );
                \FPR\Helpers\Logger::log("[PaymentPlans] 🔄 Réinitialisation des plans par défaut");
            }

            // Mettre à jour le plan
            $result = $wpdb->update(
                $wpdb->prefix . 'fpr_payment_plans',
                array(
                    'name' => $name,
                    'frequency' => $frequency,
                    'term' => $term,
                    'installments' => $installments,
                    'description' => $description,
                    'active' => $active,
                    'is_default' => $is_default
                ),
                array('id' => $plan_id),
                array('%s', '%s', '%s', '%d', '%s', '%d', '%d'),
                array('%d')
            );

            if ($result === false) {
                \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur SQL lors de la mise à jour: " . $wpdb->last_error);
                wp_die('Erreur lors de la mise à jour du plan de paiement: ' . $wpdb->last_error);
            }

            \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Plan ID $plan_id mis à jour avec succès");

        } catch (\Exception $e) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Exception lors de la mise à jour: " . $e->getMessage());
            wp_die('Exception lors de la mise à jour du plan de paiement: ' . $e->getMessage());
        }

        // Redirection vers la page des plans de paiement avec un message de succès
        \FPR\Helpers\Logger::log("[PaymentPlans] 🔄 Redirection après mise à jour");

        // Vider tous les tampons de sortie avant la redirection
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Vérifier s'il y a déjà eu des headers envoyés
        if (headers_sent($file, $line)) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ⚠️ Headers déjà envoyés dans $file à la ligne $line");
            echo '<script>window.location = "' . admin_url('options-general.php?page=fpr-settings&tab=payment_plans&updated=1') . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . admin_url('options-general.php?page=fpr-settings&tab=payment_plans&updated=1') . '"></noscript>';
            \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Redirection alternative via JavaScript/meta après mise à jour");
            exit;
        }

        // Enregistrer les headers HTTP avant la redirection
        $redirect_url = admin_url('options-general.php?page=fpr-settings&tab=payment_plans&updated=1');
        \FPR\Helpers\Logger::log("[PaymentPlans] 🔗 URL de redirection après mise à jour: $redirect_url");

        // Effectuer la redirection
        $result = wp_redirect($redirect_url);
        \FPR\Helpers\Logger::log("[PaymentPlans] " . ($result ? "✅ wp_redirect a réussi" : "❌ wp_redirect a échoué") . " après mise à jour");

        \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Fin du traitement de mise à jour d'un plan de paiement");
        exit;
    }

    public static function handle_delete_payment_plan() {
        global $wpdb;

        \FPR\Helpers\Logger::log("[PaymentPlans] Début du traitement de suppression d'un plan de paiement");

        // Vérification du nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fpr_delete_payment_plan_nonce')) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur: Nonce invalide pour la suppression");
            wp_die('Sécurité: nonce invalide');
        }

        \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Nonce de suppression validé");

        // Récupération de l'ID du plan
        if (!isset($_POST['plan_id']) || !is_numeric($_POST['plan_id'])) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur: ID de plan invalide");
            wp_die('ID de plan invalide');
        }

        $plan_id = intval($_POST['plan_id']);
        \FPR\Helpers\Logger::log("[PaymentPlans] 🔍 Tentative de suppression du plan ID: $plan_id");

        try {
            // Récupérer les informations du plan avant suppression (pour le log)
            $plan = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
                $plan_id
            ));

            if (!$plan) {
                \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur: Plan ID $plan_id introuvable");
                wp_die('Plan de paiement introuvable');
            }

            \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Plan trouvé: " . json_encode([
                'id' => $plan->id,
                'name' => $plan->name,
                'frequency' => $plan->frequency,
                'installments' => $plan->installments
            ]));

            // Supprimer le plan
            $result = $wpdb->delete(
                $wpdb->prefix . 'fpr_payment_plans',
                array('id' => $plan_id),
                array('%d')
            );

            if ($result === false) {
                \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Erreur SQL lors de la suppression: " . $wpdb->last_error);
                wp_die('Erreur lors de la suppression du plan de paiement: ' . $wpdb->last_error);
            }

            \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Plan ID $plan_id supprimé avec succès");

        } catch (\Exception $e) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Exception lors de la suppression: " . $e->getMessage());
            wp_die('Exception lors de la suppression du plan de paiement: ' . $e->getMessage());
        }

        // Redirection vers la page des plans de paiement avec un message de succès
        \FPR\Helpers\Logger::log("[PaymentPlans] 🔄 Redirection après suppression");

        // Vider tous les tampons de sortie avant la redirection
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Vérifier s'il y a déjà eu des headers envoyés
        if (headers_sent($file, $line)) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ⚠️ Headers déjà envoyés dans $file à la ligne $line");
            echo '<script>window.location = "' . admin_url('options-general.php?page=fpr-settings&tab=payment_plans&deleted=1') . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . admin_url('options-general.php?page=fpr-settings&tab=payment_plans&deleted=1') . '"></noscript>';
            \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Redirection alternative via JavaScript/meta après suppression");
            exit;
        }

        // Enregistrer les headers HTTP avant la redirection
        $redirect_url = admin_url('options-general.php?page=fpr-settings&tab=payment_plans&deleted=1');
        \FPR\Helpers\Logger::log("[PaymentPlans] 🔗 URL de redirection après suppression: $redirect_url");

        // Effectuer la redirection
        $result = wp_redirect($redirect_url);
        \FPR\Helpers\Logger::log("[PaymentPlans] " . ($result ? "✅ wp_redirect a réussi" : "❌ wp_redirect a échoué") . " après suppression");

        \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Fin du traitement de suppression d'un plan de paiement");
        exit;
    }

    private static function create_table_if_not_exists() {
        global $wpdb;

        \FPR\Helpers\Logger::log("[PaymentPlans] 🔄 Vérification/création de la table des plans de paiement");

        $table_name = $wpdb->prefix . 'fpr_payment_plans';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            frequency varchar(50) NOT NULL,
            term varchar(50) DEFAULT NULL,
            installments int(11) NOT NULL DEFAULT 1,
            description text,
            active tinyint(1) NOT NULL DEFAULT 1,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        \FPR\Helpers\Logger::log("[PaymentPlans] 📝 Requête SQL pour la table: " . str_replace("\n", " ", $sql));

        try {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql);

            if (!empty($result)) {
                \FPR\Helpers\Logger::log("[PaymentPlans] ✅ Résultat de dbDelta: " . json_encode($result));
            } else {
                \FPR\Helpers\Logger::log("[PaymentPlans] ℹ️ Table déjà à jour, aucune modification nécessaire");
            }

            // Vérifier si la table existe maintenant
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            \FPR\Helpers\Logger::log("[PaymentPlans] " . ($table_exists ? "✅ Table existe" : "❌ Table n'existe pas après création"));

            if (!$table_exists) {
                throw new \Exception("La table n'a pas pu être créée");
            }
        } catch (\Exception $e) {
            \FPR\Helpers\Logger::log("[PaymentPlans] ❌ Exception lors de la création de la table: " . $e->getMessage());
            throw $e; // Relancer l'exception pour qu'elle soit gérée par l'appelant
        }
    }
}
