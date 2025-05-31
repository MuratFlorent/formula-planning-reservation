<?php
namespace FPR\Admin;

use FPR\Helpers\Logger;

class TestSimulator {
 public static function render() {
		global $wpdb;

		// Enqueue Select2 from WooCommerce
		wp_enqueue_style('select2', WC()->plugin_url() . '/assets/css/select2.css', [], '4.0.3');
		wp_enqueue_script('select2', WC()->plugin_url() . '/assets/js/select2/select2.full.min.js', ['jquery'], '4.0.3', true);

		// Enqueue Bootstrap CSS and JS for modal
		wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css', [], '5.1.3');
		wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js', [], '5.1.3', true);

		// Enqueue subscribers.js for the "Create user" button functionality
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

		?>
        <div class="wrap">
            <h2>Tests et Simulation</h2>

            <div class="nav-tab-wrapper">
                <a href="#tab-order" class="nav-tab nav-tab-active">Test de commande</a>
                <a href="#tab-payment" class="nav-tab">Test de paiement récurrent</a>
            </div>

            <div id="tab-order" class="tab-content" style="display:block;">
                <h3>Test de simulation d'une commande WooCommerce validée</h3>
                <p>Cliquez sur le bouton ci-dessous pour simuler le processus complet de validation d'une commande WooCommerce, ajout des cours sélectionnés dans Amelia, etc.</p>

                <form method="post">
                    <?php submit_button('Lancer le test de validation'); ?>
                    <input type="hidden" name="fpr_run_test" value="1">
                </form>

                <?php if (!empty($_POST['fpr_run_test'])): ?>
                    <div style="background:#fff; border:1px solid #ccd0d4; padding:15px; margin-top:20px;">
                        <p><strong>🔄 Lancement du test...</strong></p>
                        <?php
                        Logger::log('--- 🧪 Lancement du test WooCommerce ---');
                        flush(); ob_flush();

                        echo "<p>✅ Création d'une commande test...</p>";
                        Logger::log('✅ Création d\'une commande test...');

                        $order_id = self::create_test_order();
                        if (!$order_id) {
                            echo "<p style='color:red;'>❌ Erreur lors de la création de la commande test.</p>";
                            Logger::log('❌ Erreur : impossible de créer une commande test.');
                            return;
                        }

                        echo "<p>📦 Commande test créée avec ID : <code>$order_id</code></p>";
                        Logger::log("📦 Commande test créée avec ID : $order_id");
                        flush(); ob_flush();

                        $order = wc_get_order($order_id);
                        if ($order) {
                            $order->update_status('completed', 'Test manuel');
                            echo "<p>🚀 Statut de la commande mis à jour à <code>completed</code>.</p>";
                            Logger::log("🚀 Statut de la commande #$order_id mis à jour : completed");
                        } else {
                            echo "<p style='color:red;'>❌ Impossible de charger la commande.</p>";
                            Logger::log("❌ Erreur : impossible de charger la commande #$order_id");
                        }

                        echo "<p><strong>🎉 Test terminé. Consulte le fichier <code>/wp-content/logs/fpr-debug.log</code> pour les détails.</strong></p>";
                        Logger::log('🎉 Test terminé.');
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <div id="tab-payment" class="tab-content" style="display:none;">
                <h3>Test de paiement récurrent avec Stripe</h3>
                <p>Cette section vous permet de tester le système de paiement récurrent avec Stripe. Vous pouvez créer un abonnement test et simuler des paiements récurrents.</p>

                <div class="payment-test-container">
                    <div class="payment-test-section">
                        <h4>1. Créer un abonnement test</h4>
                        <form method="post" class="payment-test-form">
                            <input type="hidden" name="fpr_create_test_subscription" value="1">
                            <?php wp_nonce_field('fpr_create_test_subscription_nonce'); ?>

                            <table class="form-table">
                                <tr>
                                    <th><label for="test_user_id">Utilisateur</label></th>
                                    <td>
                                        <div class="user-selection-container">
                                            <select id="test_user_id" name="test_user_id" required>
                                                <option value="">Sélectionner un utilisateur</option>
                                                <?php
                                                $users = get_users(['role__in' => ['customer', 'subscriber']]);
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
                                    <th><label for="test_payment_plan_id">Plan de paiement</label></th>
                                    <td>
                                        <select id="test_payment_plan_id" name="test_payment_plan_id" required>
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
                                    <th><label for="test_saison_id">Saison</label></th>
                                    <td>
                                        <select id="test_saison_id" name="test_saison_id" required>
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
                                    <th><label for="test_total_amount">Montant total</label></th>
                                    <td><input type="number" id="test_total_amount" name="test_total_amount" step="0.01" value="100" required></td>
                                </tr>
                                <tr>
                                    <th><label for="test_frequency">Fréquence de test</label></th>
                                    <td>
                                        <select id="test_frequency" name="test_frequency" required>
                                            <option value="hourly">Toutes les heures (pour test)</option>
                                            <option value="daily">Quotidien</option>
                                            <option value="weekly">Hebdomadaire</option>
                                            <option value="monthly">Mensuel</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" class="button button-primary">Créer l'abonnement test</button>
                            </p>
                        </form>
                    </div>

                    <div class="payment-test-section">
                        <h4>2. Simuler un paiement récurrent</h4>
                        <form method="post" class="payment-test-form">
                            <input type="hidden" name="fpr_simulate_payment" value="1">
                            <?php wp_nonce_field('fpr_simulate_payment_nonce'); ?>

                            <table class="form-table">
                                <tr>
                                    <th><label for="test_subscription_id">Abonnement</label></th>
                                    <td>
                                        <select id="test_subscription_id" name="test_subscription_id" required>
                                            <option value="">Sélectionner un abonnement</option>
                                            <?php
                                            $subscriptions = $wpdb->get_results("
                                                SELECT s.*, u.display_name as user_name, p.name as plan_name
                                                FROM {$wpdb->prefix}fpr_customer_subscriptions s
                                                LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
                                                LEFT JOIN {$wpdb->prefix}fpr_payment_plans p ON s.payment_plan_id = p.id
                                                WHERE s.status = 'active'
                                                ORDER BY s.created_at DESC
                                            ");

                                            foreach ($subscriptions as $sub) {
                                                echo '<option value="' . esc_attr($sub->id) . '">' . 
                                                    esc_html("ID: {$sub->id} - {$sub->user_name} - {$sub->plan_name}") . 
                                                    '</option>';
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" class="button button-primary">Simuler un paiement</button>
                            </p>
                        </form>
                    </div>
                </div>

                <?php 
                // Traitement de la création d'un abonnement test
                if (!empty($_POST['fpr_create_test_subscription']) && check_admin_referer('fpr_create_test_subscription_nonce')) {
                    self::handle_create_test_subscription();
                }

                // Traitement de la simulation d'un paiement
                if (!empty($_POST['fpr_simulate_payment']) && check_admin_referer('fpr_simulate_payment_nonce')) {
                    self::handle_simulate_payment();
                }
                ?>
            </div>
        </div>

        <style>
            /* Styles améliorés pour le cadre de test */
            .nav-tab-wrapper {
                margin-bottom: 20px;
                border-bottom: 2px solid #e2e8f0;
                padding-bottom: 0;
                display: flex;
                flex-wrap: wrap;
                gap: 2px;
            }

            .nav-tab {
                background-color: #f8fafc;
                border: 1px solid #e2e8f0;
                border-bottom: none;
                border-radius: 8px 8px 0 0;
                color: #64748b;
                font-size: 14px;
                font-weight: 500;
                padding: 12px 20px;
                margin-left: 0;
                margin-right: 4px;
                margin-bottom: -1px;
                transition: all 0.3s ease;
                text-decoration: none;
                position: relative;
                box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.02);
            }

            .nav-tab:hover {
                background-color: #fff;
                color: #3b82f6;
            }

            .nav-tab-active {
                background-color: #fff;
                border-bottom: 2px solid #fff;
                color: #3b82f6;
                font-weight: 600;
            }

            .nav-tab-active:before {
                content: '';
                position: absolute;
                top: -1px;
                left: 0;
                right: 0;
                height: 3px;
                background-color: #3b82f6;
                border-radius: 3px 3px 0 0;
            }

            .tab-content {
                background: #fff;
                padding: 25px;
                border-radius: 0 0 12px 12px;
                box-shadow: 0 2px 15px rgba(0, 0, 0, 0.04);
                margin-bottom: 30px;
                animation: fadeIn 0.3s ease;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .payment-test-container {
                display: flex;
                flex-wrap: wrap;
                gap: 25px;
                margin-top: 20px;
            }

            .payment-test-section {
                flex: 1;
                min-width: 300px;
                background: #f8fafc;
                padding: 25px;
                border-radius: 12px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
                transition: all 0.3s ease;
                border: none;
            }

            .payment-test-section:hover {
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
                transform: translateY(-2px);
            }

            .payment-test-section h4 {
                color: #3b82f6;
                font-size: 18px;
                margin-top: 0;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 1px solid #e2e8f0;
            }

            .payment-test-form .form-table {
                border-collapse: separate;
                border-spacing: 0 12px;
            }

            .payment-test-form .form-table th {
                width: 150px;
                padding: 10px 15px 10px 0;
                font-weight: 500;
                color: #334155;
                vertical-align: top;
            }

            .payment-test-form .form-table td {
                padding: 10px 0;
            }

            .payment-test-form select, 
            .payment-test-form input[type="number"] {
                width: 100%;
                padding: 10px 15px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                background-color: #f8fafc;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
                transition: all 0.3s ease;
                font-size: 14px;
                color: #334155;
            }

            .payment-test-form select:hover, 
            .payment-test-form input[type="number"]:hover {
                background-color: #fff;
                border-color: #cbd5e1;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            }

            .payment-test-form select:focus, 
            .payment-test-form input[type="number"]:focus {
                background-color: #fff;
                border-color: #3b82f6;
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
                outline: none;
            }

            .payment-test-form .button {
                padding: 10px 20px;
                border-radius: 8px;
                font-weight: 500;
                font-size: 14px;
                transition: all 0.3s ease;
                border: none;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                line-height: 1.5;
                text-decoration: none;
                background-color: #3b82f6;
                color: white;
            }

            .payment-test-form .button:hover {
                background-color: #2563eb;
                transform: translateY(-1px);
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }

            .payment-result {
                margin-top: 25px;
                padding: 20px;
                background: #f0f9ff;
                border-radius: 10px;
                border-left: 4px solid #3b82f6;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
                animation: slideIn 0.3s ease;
            }

            @keyframes slideIn {
                from { opacity: 0; transform: translateX(-10px); }
                to { opacity: 1; transform: translateX(0); }
            }

            .payment-result.success {
                background-color: #ecfdf5;
                border-left-color: #10b981;
            }

            .payment-result.error {
                background-color: #fef2f2;
                border-left-color: #ef4444;
            }

            .payment-result p {
                margin-top: 0;
                font-weight: 500;
            }

            .payment-result ul {
                margin-bottom: 0;
            }

            .payment-result li {
                margin-bottom: 8px;
            }

            .user-selection-container {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .create-new-user-btn {
                background-color: #3b82f6 !important;
                color: white !important;
                border: none !important;
                padding: 8px 15px !important;
                border-radius: 6px !important;
                cursor: pointer !important;
                transition: all 0.3s ease !important;
                font-size: 13px !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
            }

            .create-new-user-btn:hover {
                background-color: #2563eb !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
            }

            /* Styles pour les dropdowns Select2 améliorés */
            .select2-dropdown-large {
                max-height: 400px !important;
                overflow-y: auto !important;
                width: auto !important;
                min-width: 300px !important;
            }

            .select2-container--default .select2-results > .select2-results__options {
                max-height: 350px !important;
            }

            .select2-container--open .select2-dropdown {
                z-index: 100000 !important;
            }

            .select2-search--dropdown .select2-search__field {
                padding: 8px 12px !important;
                border-radius: 6px !important;
                border: 1px solid #e2e8f0 !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important;
                transition: all 0.2s ease !important;
            }

            .select2-search--dropdown .select2-search__field:focus {
                border-color: #3b82f6 !important;
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15) !important;
                outline: none !important;
            }

            .select2-container--default .select2-results__option--highlighted[aria-selected] {
                background-color: #3b82f6 !important;
                color: white !important;
            }

            .select2-container--default .select2-results__option[aria-selected=true] {
                background-color: #e5edff !important;
                color: #3b82f6 !important;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                // Gestion des onglets internes au simulateur de test
                $('.nav-tab-wrapper a').on('click', function(e) {
                    e.preventDefault();
                    var target = $(this).attr('href');

                    // Activer l'onglet
                    $('.nav-tab-wrapper a').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');

                    // Afficher le contenu
                    $('.tab-content').hide();
                    $(target).show();
                });
            });
        </script>
        <?php
	}

	private static function create_test_order() {
		$address = [
			'first_name' => 'Test',
			'last_name'  => 'User',
			'email'      => 'test@example.com',
			'phone'      => '0600000000',
			'address_1'  => '123 Rue de Test',
			'city'       => 'Paris',
			'postcode'   => '75000',
			'country'    => 'FR'
		];

		$order = wc_create_order();
		$order->set_address($address, 'billing');

		$products = wc_get_products(['limit' => 1, 'return' => 'ids']);
		if (empty($products)) {
			Logger::log('⚠️ Aucun produit WooCommerce trouvé.');
			return false;
		}

		$product_id = $products[0];
		$item_id = $order->add_product(wc_get_product($product_id), 1);
		$order->calculate_totals();

		$item = $order->get_item($item_id);
		if ($item) {
			$item->add_meta_data('Cours sélectionné 1', 'Pilates - 12:00 - 13:00 (1h) avec Sophie', true);
			$item->save();
			Logger::log('📝 Métadonnée ajoutée au produit : Cours sélectionné 1');
		}

		$order->save();
		return $order->get_id();
	}

	/**
	 * Gère la création d'un abonnement test pour les paiements récurrents
	 */
	private static function handle_create_test_subscription() {
		global $wpdb;

		// Récupérer les données du formulaire
		$user_id = isset($_POST['test_user_id']) ? intval($_POST['test_user_id']) : 0;
		$payment_plan_id = isset($_POST['test_payment_plan_id']) ? intval($_POST['test_payment_plan_id']) : 0;
		$saison_id = isset($_POST['test_saison_id']) ? intval($_POST['test_saison_id']) : 0;
		$total_amount = isset($_POST['test_total_amount']) ? floatval($_POST['test_total_amount']) : 100;
		$frequency = isset($_POST['test_frequency']) ? sanitize_text_field($_POST['test_frequency']) : 'hourly';

		// Vérifier que les données sont valides
		if (!$user_id || !$payment_plan_id || !$saison_id) {
			echo '<div class="payment-result error"><p>❌ Erreur: Veuillez remplir tous les champs requis.</p></div>';
			return;
		}

		// Récupérer les détails du plan de paiement
		$payment_plan = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
			$payment_plan_id
		));

		if (!$payment_plan) {
			echo '<div class="payment-result error"><p>❌ Erreur: Plan de paiement non trouvé.</p></div>';
			return;
		}

		// Récupérer les détails de la saison
		$saison = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}fpr_saisons WHERE id = %d",
			$saison_id
		));

		if (!$saison) {
			echo '<div class="payment-result error"><p>❌ Erreur: Saison non trouvée.</p></div>';
			return;
		}

		// Calculer le montant de chaque versement
		$installments = $payment_plan->installments;
		$installment_amount = round($total_amount / $installments, 2);

		// Créer un plan de paiement test temporaire si nécessaire
		$test_plan_id = $payment_plan_id;

		if ($frequency === 'hourly') {
			// Vérifier si un plan de test horaire existe déjà
			$test_plan = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE name = 'Plan Test Horaire'");

			if ($test_plan) {
				$test_plan_id = $test_plan->id;

				// Mettre à jour le plan existant
				$wpdb->update(
					$wpdb->prefix . 'fpr_payment_plans',
					[
						'frequency' => 'hourly',
						'installments' => $installments,
						'active' => 1
					],
					['id' => $test_plan_id]
				);

				Logger::log("[TestSimulator] Plan de test horaire existant mis à jour (ID: $test_plan_id)");
			} else {
				// Créer un nouveau plan de test horaire
				$wpdb->insert(
					$wpdb->prefix . 'fpr_payment_plans',
					[
						'name' => 'Plan Test Horaire',
						'description' => 'Plan de paiement pour les tests avec fréquence horaire',
						'frequency' => 'hourly',
						'term' => 'heure',
						'installments' => $installments,
						'is_default' => 0,
						'active' => 1
					]
				);

				$test_plan_id = $wpdb->insert_id;
				Logger::log("[TestSimulator] Nouveau plan de test horaire créé (ID: $test_plan_id)");
			}
		}

		// Créer l'abonnement dans la base de données
		$current_date = date('Y-m-d');
		$next_payment_date = date('Y-m-d', strtotime('+1 hour'));

		if ($frequency === 'daily') {
			$next_payment_date = date('Y-m-d', strtotime('+1 day'));
		} elseif ($frequency === 'weekly') {
			$next_payment_date = date('Y-m-d', strtotime('+1 week'));
		} elseif ($frequency === 'monthly') {
			$next_payment_date = date('Y-m-d', strtotime('+1 month'));
		}

		// Insérer l'abonnement
		$result = $wpdb->insert(
			$wpdb->prefix . 'fpr_customer_subscriptions',
			[
				'user_id' => $user_id,
				'order_id' => 0, // Pas de commande associée pour le test
				'payment_plan_id' => $test_plan_id,
				'saison_id' => $saison_id,
				'stripe_subscription_id' => null, // Sera mis à jour plus tard si nécessaire
				'status' => 'active',
				'start_date' => $current_date,
				'next_payment_date' => $next_payment_date,
				'total_amount' => $total_amount,
				'installment_amount' => $installment_amount,
				'installments_paid' => 1, // Premier paiement considéré comme effectué
				'created_at' => current_time('mysql')
			]
		);

		if ($result === false) {
			echo '<div class="payment-result error"><p>❌ Erreur lors de la création de l\'abonnement: ' . $wpdb->last_error . '</p></div>';
			Logger::log("[TestSimulator] Erreur lors de la création de l'abonnement: " . $wpdb->last_error);
			return;
		}

		$subscription_id = $wpdb->insert_id;

		// Créer l'abonnement dans Stripe si possible
		$stripe_subscription_id = self::create_stripe_subscription($user_id, $subscription_id, $installment_amount, $frequency);

		// Mettre à jour l'ID Stripe si disponible
		if ($stripe_subscription_id) {
			$wpdb->update(
				$wpdb->prefix . 'fpr_customer_subscriptions',
				['stripe_subscription_id' => $stripe_subscription_id],
				['id' => $subscription_id]
			);

			Logger::log("[TestSimulator] Abonnement Stripe créé et associé: $stripe_subscription_id");
		}

		// Afficher le résultat
		echo '<div class="payment-result success">';
		echo '<p>✅ Abonnement test créé avec succès!</p>';
		echo '<ul>';
		echo '<li><strong>ID:</strong> ' . $subscription_id . '</li>';
		echo '<li><strong>Utilisateur:</strong> ' . get_user_by('id', $user_id)->display_name . '</li>';
		echo '<li><strong>Plan:</strong> ' . ($frequency === 'hourly' ? 'Plan Test Horaire' : $payment_plan->name) . '</li>';
		echo '<li><strong>Saison:</strong> ' . $saison->name . '</li>';
		echo '<li><strong>Montant total:</strong> ' . number_format($total_amount, 2) . ' €</li>';
		echo '<li><strong>Montant par versement:</strong> ' . number_format($installment_amount, 2) . ' €</li>';
		echo '<li><strong>Fréquence:</strong> ' . $frequency . '</li>';
		echo '<li><strong>Prochain paiement:</strong> ' . $next_payment_date . '</li>';

		if ($stripe_subscription_id) {
			echo '<li><strong>ID Stripe:</strong> ' . $stripe_subscription_id . '</li>';
		} else {
			echo '<li><strong>ID Stripe:</strong> <em>Non créé</em></li>';
		}

		echo '</ul>';
		echo '</div>';

		Logger::log("[TestSimulator] Abonnement test créé avec succès (ID: $subscription_id)");
	}

	/**
	 * Crée un abonnement Stripe pour le test
	 * 
	 * @param int $user_id ID de l'utilisateur
	 * @param int $subscription_id ID de l'abonnement local
	 * @param float $amount Montant du paiement
	 * @param string $frequency Fréquence de paiement
	 * @return string|null ID de l'abonnement Stripe ou null en cas d'erreur
	 */
	private static function create_stripe_subscription($user_id, $subscription_id, $amount, $frequency) {
		// Vérifier que la bibliothèque Stripe est disponible
		if (!class_exists('\Stripe\Stripe')) {
			if (function_exists('WC')) {
				// Utiliser la bibliothèque Stripe de WooCommerce si disponible
				$wc_stripe = WC()->payment_gateways->payment_gateways()['stripe'] ?? null;
				if ($wc_stripe && method_exists($wc_stripe, 'get_stripe_api')) {
					$wc_stripe->get_stripe_api();
				} else {
					Logger::log("[TestSimulator] La bibliothèque Stripe n'est pas disponible");
					return null;
				}
			} else {
				Logger::log("[TestSimulator] WooCommerce n'est pas disponible");
				return null;
			}
		}

		try {
			// Récupérer la clé secrète Stripe
			$stripe_settings = get_option('woocommerce_stripe_settings', []);
			$secret_key = isset($stripe_settings['secret_key']) ? $stripe_settings['secret_key'] : '';

			if (empty($secret_key)) {
				Logger::log("[TestSimulator] Clé secrète Stripe non configurée");
				return null;
			}

			// Configurer Stripe avec la clé secrète
			\Stripe\Stripe::setApiKey($secret_key);

			// Récupérer le client Stripe
			$customer_id = get_user_meta($user_id, '_stripe_customer_id', true);

			if (empty($customer_id)) {
				// Essayer de récupérer l'ID client Stripe via d'autres méthodes
				$user = get_userdata($user_id);
				$user_email = $user->user_email;

				// Vérifier si WooCommerce Stripe est actif et utiliser ses fonctions si disponible
				if (function_exists('wc_stripe_get_customer_id_from_meta')) {
					$customer_id = wc_stripe_get_customer_id_from_meta($user_id);
				}

				if (empty($customer_id)) {
					Logger::log("[TestSimulator] ID client Stripe non trouvé pour l'utilisateur");
					return null;
				}
			}

			// Déterminer l'intervalle de facturation
			$interval = 'hour';
			$interval_count = 1;

			switch ($frequency) {
				case 'daily':
					$interval = 'day';
					$interval_count = 1;
					break;
				case 'weekly':
					$interval = 'week';
					$interval_count = 1;
					break;
				case 'monthly':
					$interval = 'month';
					$interval_count = 1;
					break;
			}

			// Créer un produit pour l'abonnement
			$product = \Stripe\Product::create([
				'name' => 'Abonnement Test - ID ' . $subscription_id,
				'type' => 'service',
			]);

			// Créer un plan de prix
			$price = \Stripe\Price::create([
				'product' => $product->id,
				'unit_amount' => round($amount * 100), // Convertir en centimes
				'currency' => 'eur',
				'recurring' => [
					'interval' => $interval,
					'interval_count' => $interval_count,
				],
			]);

			// Créer l'abonnement Stripe
			$stripe_subscription = \Stripe\Subscription::create([
				'customer' => $customer_id,
				'items' => [
					['price' => $price->id],
				],
				'metadata' => [
					'subscription_id' => $subscription_id,
					'test_mode' => 'true',
				],
			]);

			Logger::log("[TestSimulator] Abonnement Stripe créé: " . $stripe_subscription->id);
			return $stripe_subscription->id;

		} catch (\Exception $e) {
			Logger::log("[TestSimulator] Erreur Stripe: " . $e->getMessage());
			return null;
		}
	}

	/**
	 * Simule un paiement récurrent pour un abonnement existant
	 */
	private static function handle_simulate_payment() {
		global $wpdb;

		// Récupérer l'ID de l'abonnement
		$subscription_id = isset($_POST['test_subscription_id']) ? intval($_POST['test_subscription_id']) : 0;

		if (!$subscription_id) {
			echo '<div class="payment-result error"><p>❌ Erreur: Veuillez sélectionner un abonnement.</p></div>';
			return;
		}

		// Récupérer les détails de l'abonnement
		$subscription = $wpdb->get_row($wpdb->prepare(
			"SELECT s.*, p.name as plan_name, p.frequency, p.installments, u.display_name as user_name
			 FROM {$wpdb->prefix}fpr_customer_subscriptions s
			 LEFT JOIN {$wpdb->prefix}fpr_payment_plans p ON s.payment_plan_id = p.id
			 LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
			 WHERE s.id = %d",
			$subscription_id
		));

		if (!$subscription) {
			echo '<div class="payment-result error"><p>❌ Erreur: Abonnement non trouvé.</p></div>';
			return;
		}

		// Vérifier que l'abonnement est actif
		if ($subscription->status !== 'active') {
			echo '<div class="payment-result error"><p>❌ Erreur: L\'abonnement n\'est pas actif.</p></div>';
			return;
		}

		// Simuler un paiement récurrent
		try {
			// Déterminer l'intervalle pour le prochain paiement en fonction de la fréquence
			$next_payment_interval = '+1 month'; // Par défaut

			switch ($subscription->frequency) {
				case 'hourly':
					$next_payment_interval = '+1 hour';
					break;
				case 'daily':
					$next_payment_interval = '+1 day';
					break;
				case 'weekly':
					$next_payment_interval = '+1 week';
					break;
				case 'quarterly':
					$next_payment_interval = '+3 months';
					break;
				case 'annual':
					$next_payment_interval = '+1 year';
					break;
			}

			// Calculer la prochaine date de paiement
			$next_payment_date = date('Y-m-d H:i:s', strtotime($next_payment_interval, strtotime($subscription->next_payment_date)));

			// Incrémenter le nombre de versements payés
			$installments_paid = $subscription->installments_paid + 1;

			// Préparer les données de mise à jour
			$update_data = [
				'installments_paid' => $installments_paid,
				'next_payment_date' => $next_payment_date,
			];

			// Si tous les versements sont payés, marquer comme terminé
			if ($installments_paid >= $subscription->installments && $subscription->installments > 0) {
				$update_data['status'] = 'completed';
				$update_data['end_date'] = date('Y-m-d');
			}

			// Mettre à jour l'abonnement
			$wpdb->update(
				$wpdb->prefix . 'fpr_customer_subscriptions',
				$update_data,
				['id' => $subscription_id]
			);

			// Si l'abonnement a un ID Stripe, essayer de créer une facture
			$stripe_payment_success = false;

			if (!empty($subscription->stripe_subscription_id)) {
				$stripe_payment_success = self::process_stripe_payment($subscription);
			}

			// Afficher le résultat
			echo '<div class="payment-result success">';
			echo '<p>✅ Paiement récurrent simulé avec succès!</p>';
			echo '<ul>';
			echo '<li><strong>ID:</strong> ' . $subscription_id . '</li>';
			echo '<li><strong>Utilisateur:</strong> ' . $subscription->user_name . '</li>';
			echo '<li><strong>Plan:</strong> ' . $subscription->plan_name . '</li>';
			echo '<li><strong>Montant du versement:</strong> ' . number_format($subscription->installment_amount, 2) . ' €</li>';
			echo '<li><strong>Versements payés:</strong> ' . $installments_paid . ' sur ' . $subscription->installments . '</li>';
			echo '<li><strong>Prochain paiement:</strong> ' . date('Y-m-d', strtotime($next_payment_date)) . '</li>';

			if (!empty($subscription->stripe_subscription_id)) {
				echo '<li><strong>ID Stripe:</strong> ' . $subscription->stripe_subscription_id . '</li>';
				echo '<li><strong>Paiement Stripe:</strong> ' . ($stripe_payment_success ? 'Réussi' : 'Échec') . '</li>';
			}

			echo '</ul>';
			echo '</div>';

			Logger::log("[TestSimulator] Paiement récurrent simulé avec succès pour l'abonnement #$subscription_id");

		} catch (\Exception $e) {
			echo '<div class="payment-result error"><p>❌ Erreur lors de la simulation du paiement: ' . $e->getMessage() . '</p></div>';
			Logger::log("[TestSimulator] Erreur lors de la simulation du paiement: " . $e->getMessage());
		}
	}

	/**
	 * Traite un paiement Stripe pour un abonnement
	 * 
	 * @param object $subscription L'objet abonnement
	 * @return bool True si le paiement a réussi, false sinon
	 */
	private static function process_stripe_payment($subscription) {
		// Vérifier que la bibliothèque Stripe est disponible
		if (!class_exists('\Stripe\Stripe')) {
			if (function_exists('WC')) {
				// Utiliser la bibliothèque Stripe de WooCommerce si disponible
				$wc_stripe = WC()->payment_gateways->payment_gateways()['stripe'] ?? null;
				if ($wc_stripe && method_exists($wc_stripe, 'get_stripe_api')) {
					$wc_stripe->get_stripe_api();
				} else {
					Logger::log("[TestSimulator] La bibliothèque Stripe n'est pas disponible");
					return false;
				}
			} else {
				Logger::log("[TestSimulator] WooCommerce n'est pas disponible");
				return false;
			}
		}

		try {
			// Récupérer la clé secrète Stripe
			$stripe_settings = get_option('woocommerce_stripe_settings', []);
			$secret_key = isset($stripe_settings['secret_key']) ? $stripe_settings['secret_key'] : '';

			if (empty($secret_key)) {
				Logger::log("[TestSimulator] Clé secrète Stripe non configurée");
				return false;
			}

			// Configurer Stripe avec la clé secrète
			\Stripe\Stripe::setApiKey($secret_key);

			// Vérifier si l'abonnement Stripe existe toujours
			$stripe_subscription = \Stripe\Subscription::retrieve($subscription->stripe_subscription_id);

			// Si l'abonnement est actif, créer une facture
			if ($stripe_subscription->status === 'active') {
				Logger::log("[TestSimulator] Création d'une facture pour l'abonnement Stripe");

				$invoice = \Stripe\Invoice::create([
					'customer' => $stripe_subscription->customer,
					'subscription' => $subscription->stripe_subscription_id,
					'auto_advance' => true, // Finaliser et collecter automatiquement
				]);

				Logger::log("[TestSimulator] Facture créée: " . $invoice->id);
				return true;
			} else {
				Logger::log("[TestSimulator] L'abonnement Stripe n'est pas actif: " . $stripe_subscription->status);
				return false;
			}

		} catch (\Exception $e) {
			Logger::log("[TestSimulator] Erreur Stripe: " . $e->getMessage());
			return false;
		}
	}
}
