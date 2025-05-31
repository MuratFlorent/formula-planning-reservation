<?php

// class-woocommerce-handler.php

namespace FPR\Modules;

if (!defined('ABSPATH')) exit;

class WooCommerceHandler {
 public static function init() {
 	add_filter('woocommerce_add_cart_item_data', [self::class, 'inject_course_data'], 10, 3);
 	add_filter('woocommerce_get_item_data', [self::class, 'display_course_data'], 10, 2);

 	// Les hooks WooCommerce pour le selecteur de saison
 	add_action('woocommerce_after_order_notes', [self::class, 'add_saison_selector_checkout']);
 	add_action('woocommerce_checkout_update_order_meta', [self::class, 'save_saison_checkout']);

 	// Les hooks WooCommerce pour le selecteur de plan de paiement
 	add_action('woocommerce_after_order_notes', [self::class, 'add_payment_plan_selector_checkout'], 20);
 	add_action('woocommerce_checkout_before_customer_details', [self::class, 'add_payment_plan_selector_checkout'], 10);
 	add_action('woocommerce_checkout_update_order_meta', [self::class, 'save_payment_plan_checkout']);

 	// Ajouter le sélecteur de plan de paiement sur la page panier
 	add_action('woocommerce_after_cart_table', [self::class, 'add_payment_plan_selector_cart']);

 	// AJAX handler pour sauvegarder le plan de paiement sélectionné
 	add_action('wp_ajax_fpr_save_payment_plan_session', [self::class, 'ajax_save_payment_plan_session']);
 	add_action('wp_ajax_nopriv_fpr_save_payment_plan_session', [self::class, 'ajax_save_payment_plan_session']);

 	// AJAX handler pour récupérer les plans de paiement
 	add_action('wp_ajax_fpr_get_payment_plans', [self::class, 'ajax_get_payment_plans']);
 	add_action('wp_ajax_nopriv_fpr_get_payment_plans', [self::class, 'ajax_get_payment_plans']);

 	// Afficher le plan de paiement dans le résumé de la commande sur la page de checkout
 	add_action('woocommerce_review_order_before_order_total', [self::class, 'display_payment_plan_in_checkout']);

 	// Afficher la saison dans les détails de la commande
 	add_action('woocommerce_order_details_after_order_table', [self::class, 'display_saison_in_order_details']);
 	add_action('woocommerce_email_order_meta', [self::class, 'display_saison_in_emails'], 10, 3);

 	// Afficher le plan de paiement dans les détails de la commande
 	add_action('woocommerce_order_details_after_order_table', [self::class, 'display_payment_plan_in_order_details']);
 	add_action('woocommerce_email_order_meta', [self::class, 'display_payment_plan_in_emails'], 10, 3);

 	// Personnaliser l'en-tête des emails WooCommerce
 	add_action('woocommerce_email_header', [self::class, 'customize_email_header'], 10, 2);
 }

	public static function inject_course_data($cart_item_data, $product_id, $variation_id) {
		\FPR\Helpers\Logger::log("[WooCommerce] 📥 Injection des données de cours pour le produit #$product_id");

		if (WC()->session) {
			$stored = WC()->session->get('fpr_selected_courses');
			\FPR\Helpers\Logger::log("[WooCommerce] 🔍 Données en session: " . ($stored ? "présentes" : "absentes"));

			if ($stored) {
				\FPR\Helpers\Logger::log("[WooCommerce] 📋 Données brutes en session: $stored");
				\FPR\Helpers\Logger::log("[WooCommerce] 📊 Taille des données: " . strlen($stored) . " caractères");

				$courses = json_decode($stored, true);
				$jsonError = json_last_error();

				if ($jsonError !== JSON_ERROR_NONE) {
					\FPR\Helpers\Logger::log("[WooCommerce] ❌ Erreur de décodage JSON: " . json_last_error_msg());
				}

				if (is_array($courses)) {
					\FPR\Helpers\Logger::log("[WooCommerce] ✅ Décodage JSON réussi, " . count($courses) . " cours trouvés");
					$cart_item_data['fpr_selected_courses'] = $courses;

					// Log détaillé des cours
					foreach ($courses as $index => $course) {
						\FPR\Helpers\Logger::log("[WooCommerce] 📌 Cours #" . ($index + 1) . " injecté: " . json_encode($course));
					}
				} else {
					\FPR\Helpers\Logger::log("[WooCommerce] ⚠️ Les données décodées ne sont pas un tableau");
				}
			}
		} else {
			\FPR\Helpers\Logger::log("[WooCommerce] ⚠️ Session WooCommerce non disponible");
		}

		\FPR\Helpers\Logger::log("[WooCommerce] 📤 Données injectées: " . (isset($cart_item_data['fpr_selected_courses']) ? "oui" : "non"));
		return $cart_item_data;
	}

	public static function display_course_data($item_data, $cart_item) {
		\FPR\Helpers\Logger::log("[WooCommerce] 🔍 Affichage des données de cours pour un article du panier");

		if (!empty($cart_item['fpr_selected_courses'])) {
			\FPR\Helpers\Logger::log("[WooCommerce] ✅ Données de cours trouvées dans l'article");

			// Log des données brutes
			$coursesData = $cart_item['fpr_selected_courses'];
			$coursesCount = is_array($coursesData) ? count($coursesData) : "N/A";
			\FPR\Helpers\Logger::log("[WooCommerce] 📊 Nombre de cours dans l'article: " . $coursesCount);

			if (is_array($coursesData)) {
				\FPR\Helpers\Logger::log("[WooCommerce] 📋 Données brutes des cours:");
				foreach ($coursesData as $index => $course) {
					\FPR\Helpers\Logger::log("[WooCommerce]   📌 Cours #" . ($index + 1) . ": " . json_encode($course));
				}
			} else {
				\FPR\Helpers\Logger::log("[WooCommerce] ⚠️ Les données de cours ne sont pas un tableau");
				\FPR\Helpers\Logger::log("[WooCommerce] 📋 Type de données: " . gettype($coursesData));
				\FPR\Helpers\Logger::log("[WooCommerce] 📋 Contenu: " . (is_string($coursesData) ? $coursesData : "non affichable"));
			}

			// Ne pas éliminer les doublons - garder tous les cours sélectionnés
			\FPR\Helpers\Logger::log("[WooCommerce] 🔍 Processus de déduplication désactivé - conservation de tous les cours sélectionnés");

			// Log des cours conservés
			foreach ($cart_item['fpr_selected_courses'] as $index => $course) {
				// Créer une clé unique pour chaque cours (uniquement pour le log)
				$course_key = '';
				foreach ($course as $key => $value) {
					if (is_string($value) || is_numeric($value)) {
						$course_key .= $key . '=' . $value . '|';
					}
				}

				\FPR\Helpers\Logger::log("[WooCommerce]   🔑 Cours #" . ($index + 1) . " - Clé générée: " . $course_key);
				\FPR\Helpers\Logger::log("[WooCommerce]   ✅ Cours #" . ($index + 1) . " - Conservé");
			}

			\FPR\Helpers\Logger::log("[WooCommerce] 🧮 Résultat: " . count($cart_item['fpr_selected_courses']) . " cours conservés (y compris les doublons)");

			// Utiliser directement les cours sans déduplication
			$unique_courses = $cart_item['fpr_selected_courses'];
			\FPR\Helpers\Logger::log("[WooCommerce] 📊 Nombre de cours (sans déduplication): " . count($unique_courses));

			// Afficher les cours uniques
			\FPR\Helpers\Logger::log("[WooCommerce] 📝 Préparation de l'affichage des cours");
			$course_counter = 1; // Counter for sequential course numbering
			foreach ($unique_courses as $i => $course) {
				// Format the time to ensure consistent display
				$time = $course['time'];
				// Standardize time format (remove any existing formatting)
				$time = preg_replace('/[^0-9:-]/', '', $time);
				\FPR\Helpers\Logger::log("[WooCommerce]   ⏰ Cours #" . $course_counter . " - Horaire brut: " . $course['time'] . " -> Standardisé: " . $time);

				// Extract start and end times
				$times = explode('-', $time);
				$start_time = trim($times[0]);
				$end_time = isset($times[1]) ? trim($times[1]) : '';
				\FPR\Helpers\Logger::log("[WooCommerce]   ⏰ Cours #" . $course_counter . " - Début: " . $start_time . ", Fin: " . $end_time);

				// Format times with colons if needed
				if (strlen($start_time) == 4 && strpos($start_time, ':') === false) {
					$start_time = substr($start_time, 0, 2) . ':' . substr($start_time, 2);
					\FPR\Helpers\Logger::log("[WooCommerce]   ⏰ Cours #" . $course_counter . " - Formatage heure début: " . $start_time);
				}
				if (strlen($end_time) == 4 && strpos($end_time, ':') === false) {
					$end_time = substr($end_time, 0, 2) . ':' . substr($end_time, 2);
					\FPR\Helpers\Logger::log("[WooCommerce]   ⏰ Cours #" . $course_counter . " - Formatage heure fin: " . $end_time);
				}

				// Reconstruct the formatted time
				$formatted_time = $start_time . ' - ' . $end_time;
				\FPR\Helpers\Logger::log("[WooCommerce]   ⏰ Cours #" . $course_counter . " - Horaire formaté: " . $formatted_time);

				// Get the day of the week (if available)
				$day_of_week = '';
				if (isset($course['day_of_week']) && !empty($course['day_of_week'])) {
					$day_of_week = $course['day_of_week'] . ' | ';
					\FPR\Helpers\Logger::log("[WooCommerce]   📅 Cours #" . $course_counter . " - Jour trouvé: " . $course['day_of_week']);
				} else {
					// Don't add day information if it's not available
					$day_of_week = '';
					\FPR\Helpers\Logger::log("[WooCommerce]   📅 Cours #" . $course_counter . " - Jour non spécifié");
				}

				$display_value = $course['title'] . ' | ' . $day_of_week . $formatted_time . ' | ' . $course['duration'] . ' | avec ' . $course['instructor'];
				\FPR\Helpers\Logger::log("[WooCommerce]   📝 Cours #" . $course_counter . " - Valeur affichée: " . $display_value);

				$item_data[] = [
					'name'  => 'Cours sélectionné ' . $course_counter,
					'value' => $display_value,
				];
				$course_counter++; // Increment the counter for the next course
			}

			// Mettre à jour le nombre de cours dans la session pour refléter les cours uniques
			if (WC()->session && count($unique_courses) != count($cart_item['fpr_selected_courses'])) {
				$removed = count($cart_item['fpr_selected_courses']) - count($unique_courses);
				\FPR\Helpers\Logger::log("[WooCommerce] ⚠️ Élimination de " . $removed . " cours en double");

				// Convertir en JSON pour stockage
				$jsonData = json_encode($unique_courses);
				\FPR\Helpers\Logger::log("[WooCommerce] 📤 Données JSON à enregistrer en session: " . $jsonData);
				\FPR\Helpers\Logger::log("[WooCommerce] 📊 Taille des données JSON: " . strlen($jsonData) . " caractères");

				// Vérifier si les données sont valides
				$validJson = json_decode($jsonData) !== null;
				\FPR\Helpers\Logger::log("[WooCommerce] " . ($validJson ? "✅ JSON valide" : "❌ JSON invalide"));

				// Enregistrer en session
				WC()->session->set('fpr_selected_courses', $jsonData);
				\FPR\Helpers\Logger::log("[WooCommerce] 🧠 Données mises à jour en session");
			} else {
				\FPR\Helpers\Logger::log("[WooCommerce] ✅ Aucun doublon détecté, pas de mise à jour de session nécessaire");
			}
		}
		return $item_data;
	}

	// Récupère la liste des saisons ouvertes depuis la table des saisons
	public static function get_open_seasons() {
		global $wpdb;
		$now = date('Y-m-d');

		// D'abord, essayons de récupérer les saisons actuellement ouvertes
		$results = $wpdb->get_results(
			"SELECT id, name, tag 
			 FROM {$wpdb->prefix}fpr_saisons
			 WHERE start_date <= '$now' AND end_date >= '$now'
			 ORDER BY name DESC"
		);

		// Si aucune saison ouverte n'est trouvée, récupérons toutes les saisons
		if (empty($results)) {
			// Récupérer les saisons futures (qui commencent après aujourd'hui)
			$results = $wpdb->get_results(
				"SELECT id, name, tag 
				 FROM {$wpdb->prefix}fpr_saisons
				 WHERE start_date > '$now'
				 ORDER BY start_date ASC"
			);

			// Si toujours aucune saison, récupérer les saisons passées les plus récentes
			if (empty($results)) {
				$results = $wpdb->get_results(
					"SELECT id, name, tag 
					 FROM {$wpdb->prefix}fpr_saisons
					 ORDER BY end_date DESC
					 LIMIT 5"
				);
			}
		}

		// Log le résultat pour le débogage
		\FPR\Helpers\Logger::log("[WooCommerce] Saisons disponibles: " . count($results));

		return $results;
	}

	// Affiche le selecteur de saison dans le checkout WooCommerce
	public static function add_saison_selector_checkout($checkout) {
		$saisons = self::get_open_seasons();
		echo '<h3>Saison d\'inscription</h3>';

		if (empty($saisons)) {
			echo '<p>Aucune saison disponible actuellement.</p>';
			return;
		}

		echo '<select name="fpr_selected_saison" class="input-select" required>';
		foreach ($saisons as $saison) {
			echo '<option value="' . esc_attr($saison->tag) . '">' . esc_html($saison->name) . '</option>';
		}
		echo '</select>';
		echo '<p class="form-row form-row-wide"><small>Sélectionnez la saison pour laquelle vous souhaitez vous inscrire aux cours.</small></p>';
	}

	/**
	 * Enregistre la saison choisie dans la meta de la commande
	 * 
	 * @param int $order_id L'ID de la commande
	 */
	public static function save_saison_checkout($order_id) {
		\FPR\Helpers\Logger::log("[WooCommerce] 🔍 Début de save_saison_checkout pour la commande #$order_id");

		// Dump des données POST pour le débogage
		\FPR\Helpers\Logger::log("[WooCommerce] 📋 Données POST reçues pour saison: " . print_r($_POST, true));

		$saison_tag = null;

		// Vérifier d'abord si la saison est dans les données POST
		if (isset($_POST['fpr_selected_saison']) && !empty($_POST['fpr_selected_saison'])) {
			$saison_tag = sanitize_text_field($_POST['fpr_selected_saison']);
			\FPR\Helpers\Logger::log("[WooCommerce] ✅ Saison sélectionnée depuis POST: $saison_tag");
		} 
		// Si toujours pas de saison, utiliser la première saison disponible
		else {
			\FPR\Helpers\Logger::log("[WooCommerce] ⚠️ Aucune saison sélectionnée dans le formulaire");

			// Récupérer la première saison disponible comme fallback
			$saisons = self::get_open_seasons();
			if (!empty($saisons)) {
				$first_saison = $saisons[0];
				$saison_tag = $first_saison->tag;
				\FPR\Helpers\Logger::log("[WooCommerce] 🔄 Utilisation de la première saison disponible comme fallback: $saison_tag");
			} else {
				\FPR\Helpers\Logger::log("[WooCommerce] ❌ Aucune saison disponible pour fallback");
				return; // Sortir si aucune saison n'est disponible
			}
		}

		// Vérifier que la saison existe
		if ($saison_tag) {
			global $wpdb;
			$saison_exists = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}fpr_saisons WHERE tag = %s",
				$saison_tag
			));

			if ($saison_exists) {
				// Enregistrer la saison dans la meta de la commande
				update_post_meta($order_id, '_fpr_selected_saison', $saison_tag);
				\FPR\Helpers\Logger::log("[WooCommerce] ✅ Saison enregistrée dans la meta de la commande: $saison_tag");
			} else {
				\FPR\Helpers\Logger::log("[WooCommerce] ❌ Saison avec tag '$saison_tag' non trouvée dans la base de données");

				// Essayer avec la première saison disponible comme dernier recours
				$saisons = self::get_open_seasons();
				if (!empty($saisons)) {
					$first_saison = $saisons[0];
					$saison_tag = $first_saison->tag;
					update_post_meta($order_id, '_fpr_selected_saison', $saison_tag);
					\FPR\Helpers\Logger::log("[WooCommerce] ✅ Saison de dernier recours enregistrée dans la meta de la commande: $saison_tag");
				}
			}
		}
	}

	/**
	 * Affiche la saison sélectionnée dans les détails de la commande
	 * 
	 * @param WC_Order $order L'objet commande
	 */
	public static function display_saison_in_order_details($order) {
		$saison_tag = get_post_meta($order->get_id(), '_fpr_selected_saison', true);

		if (!empty($saison_tag)) {
			// Récupérer le nom de la saison à partir du tag
			global $wpdb;
			$saison_name = $wpdb->get_var($wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}fpr_saisons WHERE tag = %s",
				$saison_tag
			));

			// Si on ne trouve pas le nom, on utilise le tag
			if (empty($saison_name)) {
				$saison_name = $saison_tag;
			}

			echo '<h2 class="woocommerce-order-details__title">Saison d\'inscription</h2>';
			echo '<p>' . esc_html($saison_name) . '</p>';
		}
	}

	/**
	 * Affiche la saison sélectionnée dans les emails de commande
	 * 
	 * @param WC_Order $order L'objet commande
	 * @param bool $sent_to_admin Si l'email est envoyé à l'admin
	 * @param bool $plain_text Si l'email est en texte brut
	 */
	public static function display_saison_in_emails($order, $sent_to_admin, $plain_text) {
		$saison_tag = get_post_meta($order->get_id(), '_fpr_selected_saison', true);

		if (!empty($saison_tag)) {
			// Récupérer le nom de la saison à partir du tag
			global $wpdb;
			$saison_name = $wpdb->get_var($wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}fpr_saisons WHERE tag = %s",
				$saison_tag
			));

			// Si on ne trouve pas le nom, on utilise le tag
			if (empty($saison_name)) {
				$saison_name = $saison_tag;
			}

			if ($plain_text) {
				echo "\n\nSaison d'inscription: " . $saison_name . "\n\n";
			} else {
				echo '<div style="margin-top: 25px; background-color: #f5f5f5; padding: 15px; border-radius: 5px; border-left: 4px solid #3498db;">';
				echo '<h2 style="margin-top: 0; color: #333; font-size: 16px;">Saison d\'inscription</h2>';
				echo '<p style="font-size: 15px; margin: 5px 0;">' . esc_html($saison_name) . '</p>';
				echo '</div>';
			}
		}
	}

	/**
	 * Ajoute le sélecteur de plan de paiement dans le checkout WooCommerce
	 * 
	 * @param WC_Checkout $checkout L'objet checkout
	 */
	public static function add_payment_plan_selector_checkout($checkout) {
		\FPR\Helpers\Logger::log("[WooCommerce] 🔍 Début de add_payment_plan_selector_checkout");
		$payment_plans = self::get_active_payment_plans();
		$default_plan_id = self::get_default_payment_plan();

		// Log des plans de paiement récupérés
		\FPR\Helpers\Logger::log("[WooCommerce] 📋 Nombre de plans de paiement actifs: " . count($payment_plans));
		foreach ($payment_plans as $index => $plan) {
			\FPR\Helpers\Logger::log("[WooCommerce] 📌 Plan #" . ($index + 1) . ": ID=" . $plan->id . ", Nom=" . $plan->name . ", Fréquence=" . $plan->frequency . ", Terme=" . $plan->term . ", Versements=" . $plan->installments . ", Par défaut=" . $plan->is_default);
		}

		// Récupérer le plan sélectionné depuis la session ou utiliser le plan par défaut
		$selected_plan_id = WC()->session->get('fpr_selected_payment_plan', $default_plan_id);

		echo '<h3>Plan de paiement</h3>';

		if (empty($payment_plans)) {
			echo '<p>Aucun plan de paiement disponible actuellement.</p>';
			return;
		}

		echo '<select name="fpr_selected_payment_plan" class="input-select" required>';
		foreach ($payment_plans as $plan) {
			// Utiliser le terme personnalisé s'il existe, sinon utiliser les valeurs par défaut
			$term_text = !empty($plan->term) ? '/' . $plan->term : '';
			\FPR\Helpers\Logger::log("[WooCommerce] 📌 Checkout - Plan ID " . $plan->id . " - Terme personnalisé: " . ($term_text ? $term_text : "non défini"));

			if (empty($term_text)) {
				$frequency_label = [
					'hourly' => '/heure',
					'daily' => '/jour',
					'weekly' => '/sem',
					'monthly' => '/mois',
					'quarterly' => '/trim',
					'annual' => '/an'
				];
				$term_text = isset($frequency_label[$plan->frequency]) ? $frequency_label[$plan->frequency] : '';
				\FPR\Helpers\Logger::log("[WooCommerce] 📌 Checkout - Plan ID " . $plan->id . " - Terme basé sur la fréquence: " . ($term_text ? $term_text : "non défini"));
			}

			$label = sprintf('%s (%d versements%s)', $plan->name, $plan->installments, $term_text ? ' ' . $term_text : '');
			\FPR\Helpers\Logger::log("[WooCommerce] 📌 Checkout - Plan ID " . $plan->id . " - Label final: " . $label);

			$selected = ($selected_plan_id && $plan->id == $selected_plan_id) ? ' selected="selected"' : '';
			echo '<option value="' . esc_attr($plan->id) . '"' . $selected . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '<p class="form-row form-row-wide"><small>Sélectionnez votre plan de paiement préféré.</small></p>';
	}

	/**
	 * Enregistre le plan de paiement choisi dans la meta de la commande
	 * 
	 * @param int $order_id L'ID de la commande
	 */
	public static function save_payment_plan_checkout($order_id) {
		\FPR\Helpers\Logger::log("[WooCommerce] 🔍 Début de save_payment_plan_checkout pour la commande #$order_id");

		// Dump des données POST pour le débogage
		\FPR\Helpers\Logger::log("[WooCommerce] 📋 Données POST reçues: " . print_r($_POST, true));

		$plan_id = null;

		// Vérifier d'abord si le plan est dans les données POST
		if (isset($_POST['fpr_selected_payment_plan'])) {
			$plan_id = sanitize_text_field($_POST['fpr_selected_payment_plan']);
			\FPR\Helpers\Logger::log("[WooCommerce] ✅ Plan de paiement sélectionné depuis POST: $plan_id");
		} 
		// Sinon, vérifier si le plan est dans la session
		else if (WC()->session && WC()->session->get('fpr_selected_payment_plan')) {
			$plan_id = WC()->session->get('fpr_selected_payment_plan');
			\FPR\Helpers\Logger::log("[WooCommerce] ✅ Plan de paiement récupéré depuis la session: $plan_id");
		}
		// Si toujours pas de plan, utiliser le plan par défaut
		else {
			\FPR\Helpers\Logger::log("[WooCommerce] ⚠️ Aucun plan de paiement trouvé dans POST ou session");

			// Récupérer le plan par défaut comme fallback
			$plan_id = self::get_default_payment_plan();
			if ($plan_id) {
				\FPR\Helpers\Logger::log("[WooCommerce] 🔄 Utilisation du plan de paiement par défaut comme fallback: $plan_id");
			} else {
				\FPR\Helpers\Logger::log("[WooCommerce] ❌ Aucun plan de paiement par défaut disponible pour fallback");
				return; // Sortir si aucun plan n'est disponible
			}
		}

		// Vérifier que le plan existe et récupérer ses détails
		if ($plan_id) {
			global $wpdb;
			$payment_plan = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
				$plan_id
			));

			if ($payment_plan) {
				\FPR\Helpers\Logger::log("[WooCommerce] 📌 Détails du plan: Nom=" . $payment_plan->name . ", Fréquence=" . $payment_plan->frequency . ", Terme=" . $payment_plan->term . ", Versements=" . $payment_plan->installments);

				// Enregistrer le plan dans la meta de la commande
				update_post_meta($order_id, '_fpr_selected_payment_plan', $plan_id);
				\FPR\Helpers\Logger::log("[WooCommerce] ✅ Plan de paiement enregistré dans la meta de la commande");
			} else {
				\FPR\Helpers\Logger::log("[WooCommerce] ❌ Plan de paiement #$plan_id non trouvé dans la base de données");
			}
		}
	}

	/**
	 * Affiche le plan de paiement sélectionné dans les détails de la commande
	 * 
	 * @param WC_Order $order L'objet commande
	 */
	public static function display_payment_plan_in_order_details($order) {
		\FPR\Helpers\Logger::log("[WooCommerce] 🔍 Début de display_payment_plan_in_order_details pour la commande #" . $order->get_id());

		$payment_plan_id = get_post_meta($order->get_id(), '_fpr_selected_payment_plan', true);
		\FPR\Helpers\Logger::log("[WooCommerce] 📌 ID du plan de paiement récupéré: " . ($payment_plan_id ? $payment_plan_id : "aucun"));

		if (!empty($payment_plan_id)) {
			// Récupérer les détails du plan de paiement
			global $wpdb;
			$payment_plan = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
				$payment_plan_id
			));

			\FPR\Helpers\Logger::log("[WooCommerce] 📌 Plan de paiement trouvé: " . ($payment_plan ? "oui" : "non"));

			if ($payment_plan) {
				\FPR\Helpers\Logger::log("[WooCommerce] 📌 Détails du plan: Nom=" . $payment_plan->name . ", Fréquence=" . $payment_plan->frequency . ", Terme=" . $payment_plan->term . ", Versements=" . $payment_plan->installments);

				// Utiliser le terme personnalisé s'il existe, sinon utiliser les valeurs par défaut
				$term_text = !empty($payment_plan->term) ? '/' . $payment_plan->term : '';
				\FPR\Helpers\Logger::log("[WooCommerce] 📌 Terme personnalisé: " . ($term_text ? $term_text : "non défini"));

				if (empty($term_text)) {
					$frequency_label = [
						'hourly' => '/heure',
						'daily' => '/jour',
						'weekly' => '/sem',
						'monthly' => '/mois',
						'quarterly' => '/trim',
						'annual' => '/an'
					];
					$term_text = isset($frequency_label[$payment_plan->frequency]) ? $frequency_label[$payment_plan->frequency] : '';
					\FPR\Helpers\Logger::log("[WooCommerce] 📌 Terme basé sur la fréquence: " . ($term_text ? $term_text : "non défini"));
				}

				echo '<h2 class="woocommerce-order-details__title">Plan de paiement</h2>';
				echo '<p>' . esc_html($payment_plan->name) . ' (' . esc_html($payment_plan->installments) . ' versements' . ($term_text ? ' ' . esc_html($term_text) : '') . ')</p>';

				if (!empty($payment_plan->description)) {
					echo '<p><small>' . esc_html($payment_plan->description) . '</small></p>';
				}
			}
		}
	}

	/**
	 * Affiche le plan de paiement sélectionné dans les emails de commande
	 * 
	 * @param WC_Order $order L'objet commande
	 * @param bool $sent_to_admin Si l'email est envoyé à l'admin
	 * @param bool $plain_text Si l'email est en texte brut
	 */
	public static function display_payment_plan_in_emails($order, $sent_to_admin, $plain_text) {
		\FPR\Helpers\Logger::log("[WooCommerce] 🔍 Début de display_payment_plan_in_emails pour la commande #" . $order->get_id());

		$payment_plan_id = get_post_meta($order->get_id(), '_fpr_selected_payment_plan', true);
		\FPR\Helpers\Logger::log("[WooCommerce] 📌 ID du plan de paiement récupéré: " . ($payment_plan_id ? $payment_plan_id : "aucun"));

		if (!empty($payment_plan_id)) {
			// Récupérer les détails du plan de paiement
			global $wpdb;
			$payment_plan = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
				$payment_plan_id
			));

			\FPR\Helpers\Logger::log("[WooCommerce] 📌 Plan de paiement trouvé: " . ($payment_plan ? "oui" : "non"));

			if ($payment_plan) {
				\FPR\Helpers\Logger::log("[WooCommerce] 📌 Détails du plan: Nom=" . $payment_plan->name . ", Fréquence=" . $payment_plan->frequency . ", Terme=" . $payment_plan->term . ", Versements=" . $payment_plan->installments);

				// Utiliser le terme personnalisé s'il existe, sinon utiliser les valeurs par défaut
				$term_text = !empty($payment_plan->term) ? '/' . $payment_plan->term : '';
				\FPR\Helpers\Logger::log("[WooCommerce] 📌 Terme personnalisé: " . ($term_text ? $term_text : "non défini"));

				if (empty($term_text)) {
					$frequency_label = [
						'hourly' => '/heure',
						'daily' => '/jour',
						'weekly' => '/sem',
						'monthly' => '/mois',
						'quarterly' => '/trim',
						'annual' => '/an'
					];
					$term_text = isset($frequency_label[$payment_plan->frequency]) ? $frequency_label[$payment_plan->frequency] : '';
					\FPR\Helpers\Logger::log("[WooCommerce] 📌 Terme basé sur la fréquence: " . ($term_text ? $term_text : "non défini"));
				}
				$plan_text = $payment_plan->name . ' (' . $payment_plan->installments . ' versements' . ($term_text ? ' ' . $term_text : '') . ')';

				if ($plain_text) {
					echo "\n\nPlan de paiement: " . $plan_text . "\n\n";
					if (!empty($payment_plan->description)) {
						echo $payment_plan->description . "\n\n";
					}
				} else {
					echo '<div style="margin-top: 25px; background-color: #f5f5f5; padding: 15px; border-radius: 5px; border-left: 4px solid #5cb85c;">';
					echo '<h2 style="margin-top: 0; color: #333; font-size: 16px;">Plan de paiement</h2>';
					echo '<p style="font-size: 15px; margin: 5px 0;">' . esc_html($plan_text) . '</p>';
					if (!empty($payment_plan->description)) {
						echo '<p style="font-size: 14px; margin: 5px 0; color: #666;"><small>' . esc_html($payment_plan->description) . '</small></p>';
					}
					echo '</div>';
				}
			}
		}
	}

	/**
	 * Récupère la liste des plans de paiement actifs
	 * 
	 * @return array Liste des plans de paiement actifs
	 */
	public static function get_active_payment_plans() {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}fpr_payment_plans 
			 WHERE active = 1
			 ORDER BY name ASC"
		);

		return $results;
	}

	/**
	 * Ajoute un sélecteur de plan de paiement sur la page panier
	 */
	public static function add_payment_plan_selector_cart() {
		\FPR\Helpers\Logger::log("[WooCommerce] 🔍 Début de add_payment_plan_selector_cart");

		$payment_plans = self::get_active_payment_plans();
		$default_plan_id = self::get_default_payment_plan();

		\FPR\Helpers\Logger::log("[WooCommerce] 📌 Plan par défaut ID: " . ($default_plan_id ? $default_plan_id : "aucun"));
		\FPR\Helpers\Logger::log("[WooCommerce] 📋 Nombre de plans de paiement actifs: " . count($payment_plans));

		foreach ($payment_plans as $index => $plan) {
			\FPR\Helpers\Logger::log("[WooCommerce] 📌 Plan #" . ($index + 1) . ": ID=" . $plan->id . ", Nom=" . $plan->name . ", Fréquence=" . $plan->frequency . ", Terme=" . $plan->term . ", Versements=" . $plan->installments . ", Par défaut=" . $plan->is_default);
		}

		// Récupérer le plan sélectionné depuis la session ou utiliser le plan par défaut
		$selected_plan_id = WC()->session->get('fpr_selected_payment_plan', $default_plan_id);
		\FPR\Helpers\Logger::log("[WooCommerce] 📌 Plan sélectionné ID: " . ($selected_plan_id ? $selected_plan_id : "aucun") . " pour la page panier");

		echo '<div class="fpr-payment-plan-selector">';
		echo '<h3>Plan de paiement</h3>';

		if (empty($payment_plans)) {
			echo '<p>Aucun plan de paiement disponible actuellement.</p>';
			echo '</div>';
			return;
		}

		echo '<select id="fpr-payment-plan-select" name="fpr_selected_payment_plan" class="input-select">';
		foreach ($payment_plans as $plan) {
			// Utiliser le terme personnalisé s'il existe, sinon utiliser les valeurs par défaut
			$term_text = !empty($plan->term) ? '/' . $plan->term : '';
			\FPR\Helpers\Logger::log("[WooCommerce] 📌 Cart - Plan ID " . $plan->id . " - Terme personnalisé: " . ($term_text ? $term_text : "non défini"));

			if (empty($term_text)) {
				$frequency_label = [
					'hourly' => '/heure',
					'daily' => '/jour',
					'weekly' => '/sem',
					'monthly' => '/mois',
					'quarterly' => '/trim',
					'annual' => '/an'
				];
				$term_text = isset($frequency_label[$plan->frequency]) ? $frequency_label[$plan->frequency] : '';
				\FPR\Helpers\Logger::log("[WooCommerce] 📌 Cart - Plan ID " . $plan->id . " - Terme basé sur la fréquence: " . ($term_text ? $term_text : "non défini"));
			}

			$label = sprintf('%s (%d versements%s)', $plan->name, $plan->installments, $term_text ? ' ' . $term_text : '');
			\FPR\Helpers\Logger::log("[WooCommerce] 📌 Cart - Plan ID " . $plan->id . " - Label final: " . $label);

			$selected = ($selected_plan_id && $plan->id == $selected_plan_id) ? ' selected="selected"' : '';
			echo '<option value="' . esc_attr($plan->id) . '"' . $selected . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '<p class="form-row form-row-wide"><small>Sélectionnez votre plan de paiement préféré.</small></p>';

		// Ajouter le JavaScript pour rafraîchir la page lors du changement de sélection
		echo '<script type="text/javascript">
			jQuery(document).ready(function($) {
				$("#fpr-payment-plan-select").on("change", function() {
					// Afficher le loader
					if (typeof showLoader === "function") {
						showLoader();
					}

					// Stocker la valeur sélectionnée dans une session via AJAX
					$.ajax({
						url: "' . admin_url('admin-ajax.php') . '",
						type: "POST",
						data: {
							action: "fpr_save_payment_plan_session",
							plan_id: $(this).val(),
							security: "' . wp_create_nonce('fpr-save-payment-plan') . '"
						},
						success: function(response) {
							// Rafraîchir la page
							window.location.reload();
						}
					});
				});
			});
		</script>';
		echo '</div>';

		// Le CSS est maintenant chargé via un fichier externe
	}

	/**
	 * Gestionnaire AJAX pour récupérer les plans de paiement
	 */
	public static function ajax_get_payment_plans() {
		\FPR\Helpers\Logger::log("[WooCommerce] 🔍 Début de ajax_get_payment_plans");

		// Récupérer les plans de paiement actifs
		$payment_plans = self::get_active_payment_plans();

		// Récupérer le plan sélectionné depuis la session ou utiliser le plan par défaut
		$default_plan_id = self::get_default_payment_plan();
		$selected_plan_id = WC()->session ? WC()->session->get('fpr_selected_payment_plan', $default_plan_id) : $default_plan_id;

		\FPR\Helpers\Logger::log("[WooCommerce] 📋 Nombre de plans de paiement actifs: " . count($payment_plans));
		\FPR\Helpers\Logger::log("[WooCommerce] 📌 Plan sélectionné ID: " . ($selected_plan_id ? $selected_plan_id : "aucun"));

		// Préparer les données pour le JSON
		$plans_data = [];
		foreach ($payment_plans as $plan) {
			$plans_data[] = [
				'id' => $plan->id,
				'name' => $plan->name,
				'frequency' => $plan->frequency,
				'term' => $plan->term,
				'installments' => $plan->installments,
				'is_default' => $plan->is_default
			];
		}

		// Renvoyer les données en JSON
		wp_send_json_success([
			'plans' => $plans_data,
			'selected_plan_id' => $selected_plan_id
		]);
	}

	/**
	 * Gestionnaire AJAX pour sauvegarder le plan de paiement sélectionné dans la session
	 */
	public static function ajax_save_payment_plan_session() {
		// Vérifier le nonce de sécurité
		check_ajax_referer('fpr-save-payment-plan', 'security');

		// Récupérer l'ID du plan
		$plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;

		if ($plan_id > 0) {
			// Vérifier que le plan existe et est actif
			global $wpdb;
			$payment_plan = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d AND active = 1",
				$plan_id
			));

			if ($payment_plan) {
				// Sauvegarder dans la session WooCommerce
				WC()->session->set('fpr_selected_payment_plan', $plan_id);

				// Log détaillé pour le débogage
				\FPR\Helpers\Logger::log("[WooCommerce] ✅ Plan de paiement #$plan_id sauvegardé en session");
				\FPR\Helpers\Logger::log("[WooCommerce] 📌 Détails du plan: Nom=" . $payment_plan->name . ", Fréquence=" . $payment_plan->frequency . ", Terme=" . $payment_plan->term . ", Versements=" . $payment_plan->installments);

				// Vérifier que le plan a bien été sauvegardé en session
				$saved_plan_id = WC()->session->get('fpr_selected_payment_plan');
				if ($saved_plan_id == $plan_id) {
					\FPR\Helpers\Logger::log("[WooCommerce] ✅ Vérification réussie: Plan #$plan_id correctement sauvegardé en session");
				} else {
					\FPR\Helpers\Logger::log("[WooCommerce] ⚠️ Problème de session: Plan #$plan_id non sauvegardé correctement. Valeur en session: " . ($saved_plan_id ? $saved_plan_id : "aucune"));
				}

				wp_send_json_success([
					'message' => 'Plan de paiement sauvegardé',
					'plan_id' => $plan_id,
					'plan_name' => $payment_plan->name,
					'plan_frequency' => $payment_plan->frequency
				]);
			} else {
				\FPR\Helpers\Logger::log("[WooCommerce] ❌ Plan de paiement #$plan_id non trouvé ou inactif");
				wp_send_json_error(['message' => 'Plan de paiement invalide']);
			}
		} else {
			\FPR\Helpers\Logger::log("[WooCommerce] ❌ ID de plan de paiement invalide: $plan_id");
			wp_send_json_error(['message' => 'ID de plan invalide']);
		}

		wp_die();
	}


	/**
	 * Affiche le plan de paiement sélectionné dans le résumé de la commande sur la page de checkout
	 */
	public static function display_payment_plan_in_checkout() {
		\FPR\Helpers\Logger::log("[WooCommerce] 🔍 Début de display_payment_plan_in_checkout");

		// Récupérer le plan sélectionné depuis la session
		$payment_plan_id = WC()->session ? WC()->session->get('fpr_selected_payment_plan') : null;
		\FPR\Helpers\Logger::log("[WooCommerce] 📌 ID du plan de paiement récupéré de la session: " . ($payment_plan_id ? $payment_plan_id : "aucun"));

		if (!empty($payment_plan_id)) {
			// Récupérer les détails du plan de paiement
			global $wpdb;
			$payment_plan = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
				$payment_plan_id
			));

			\FPR\Helpers\Logger::log("[WooCommerce] 📌 Plan de paiement trouvé: " . ($payment_plan ? "oui" : "non"));

			if ($payment_plan) {
				\FPR\Helpers\Logger::log("[WooCommerce] 📌 Détails du plan: Nom=" . $payment_plan->name . ", Fréquence=" . $payment_plan->frequency . ", Terme=" . $payment_plan->term . ", Versements=" . $payment_plan->installments);

				// Utiliser le terme personnalisé s'il existe, sinon utiliser les valeurs par défaut
				$term_text = !empty($payment_plan->term) ? '/' . $payment_plan->term : '';
				\FPR\Helpers\Logger::log("[WooCommerce] 📌 Terme personnalisé: " . ($term_text ? $term_text : "non défini"));

				if (empty($term_text)) {
					$frequency_label = [
						'hourly' => '/heure',
						'daily' => '/jour',
						'weekly' => '/sem',
						'monthly' => '/mois',
						'quarterly' => '/trim',
						'annual' => '/an'
					];
					$term_text = isset($frequency_label[$payment_plan->frequency]) ? $frequency_label[$payment_plan->frequency] : '';
					\FPR\Helpers\Logger::log("[WooCommerce] 📌 Terme basé sur la fréquence: " . ($term_text ? $term_text : "non défini"));
				}

				// Ajouter un style pour la ligne du plan de paiement
				echo '<style>
					tr.payment-plan {
						background-color: #f8f8f8;
					}
					tr.payment-plan th, tr.payment-plan td {
						padding: 10px;
						border-top: 1px solid #e1e1e1;
						border-bottom: 1px solid #e1e1e1;
					}
					tr.payment-plan td {
						font-weight: 600;
						color: #2271b1;
					}
				</style>';

				echo '<tr class="payment-plan">';
				echo '<th>' . esc_html__('Plan de paiement', 'formula-planning-reservation') . '</th>';
				echo '<td>' . esc_html($payment_plan->name) . ' (' . esc_html($payment_plan->installments) . ' versements' . ($term_text ? ' ' . esc_html($term_text) : '') . ')</td>';
				echo '</tr>';
			}
		}
	}

	/**
	 * Récupère l'ID du plan de paiement par défaut
	 * 
	 * @return int|null ID du plan de paiement par défaut ou null si aucun plan n'est disponible
	 */
	public static function get_default_payment_plan() {
		\FPR\Helpers\Logger::log("[WooCommerce] 🔍 Début de get_default_payment_plan");
		global $wpdb;

		// Essayer d'abord de trouver un plan marqué comme défaut
		$default_plan = $wpdb->get_var(
			"SELECT id FROM {$wpdb->prefix}fpr_payment_plans 
			 WHERE active = 1 AND is_default = 1
			 LIMIT 1"
		);

		\FPR\Helpers\Logger::log("[WooCommerce] 📌 Plan par défaut (is_default=1): " . ($default_plan ? $default_plan : "aucun"));

		if ($default_plan) {
			return $default_plan;
		}

		// Si aucun plan par défaut n'est défini, essayer de trouver un plan horaire (hourly)
		$hourly_plan = $wpdb->get_var(
			"SELECT id FROM {$wpdb->prefix}fpr_payment_plans 
			 WHERE active = 1 AND frequency = 'hourly'
			 ORDER BY name ASC
			 LIMIT 1"
		);

		\FPR\Helpers\Logger::log("[WooCommerce] 📌 Plan horaire (fallback 1): " . ($hourly_plan ? $hourly_plan : "aucun"));

		if ($hourly_plan) {
			return $hourly_plan;
		}

		// Si aucun plan horaire n'est disponible, essayer de trouver un plan quotidien (daily)
		$daily_plan = $wpdb->get_var(
			"SELECT id FROM {$wpdb->prefix}fpr_payment_plans 
			 WHERE active = 1 AND frequency = 'daily'
			 ORDER BY name ASC
			 LIMIT 1"
		);

		\FPR\Helpers\Logger::log("[WooCommerce] 📌 Plan quotidien (fallback 2): " . ($daily_plan ? $daily_plan : "aucun"));

		if ($daily_plan) {
			return $daily_plan;
		}

		// Si aucun plan quotidien n'est disponible, essayer de trouver un plan mensuel
		$monthly_plan = $wpdb->get_var(
			"SELECT id FROM {$wpdb->prefix}fpr_payment_plans 
			 WHERE active = 1 AND frequency = 'monthly'
			 ORDER BY name ASC
			 LIMIT 1"
		);

		\FPR\Helpers\Logger::log("[WooCommerce] 📌 Plan mensuel (fallback 3): " . ($monthly_plan ? $monthly_plan : "aucun"));

		if ($monthly_plan) {
			return $monthly_plan;
		}

		// Si aucun plan mensuel n'est disponible, prendre le premier plan actif
		$any_plan = $wpdb->get_var(
			"SELECT id FROM {$wpdb->prefix}fpr_payment_plans 
			 WHERE active = 1
			 ORDER BY name ASC
			 LIMIT 1"
		);

		\FPR\Helpers\Logger::log("[WooCommerce] 📌 Premier plan actif (fallback 3): " . ($any_plan ? $any_plan : "aucun"));

		return $any_plan;
	}

	/**
	 * Personnalise l'en-tête des emails WooCommerce pour ajouter le logo
	 * 
	 * @param string $email_heading L'en-tête de l'email
	 * @param WC_Email $email L'objet email
	 */
	public static function customize_email_header($email_heading, $email) {
		// Utiliser le logo spécifique du site
		$logo_url = site_url('/wp-content/uploads/2024/05/accueil.jpg');

		echo '<div style="text-align: center; margin-bottom: 20px;">';
		echo '<img src="' . esc_url($logo_url) . '" alt="Logo" style="max-width: 180px; height: auto;" />';
		echo '</div>';
	}
}
