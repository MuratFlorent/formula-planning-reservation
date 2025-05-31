<?php
namespace FPR\Modules;

if (!defined('ABSPATH')) exit;

use FPR\Helpers\Logger;

class WooToAmelia {
	public static function init() {
		// Traitement après paiement WooCommerce
		add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_order'], 10, 4);

		// Ajouter un script pour nettoyer le localStorage après la commande
		add_action('wp_footer', [__CLASS__, 'add_clear_localstorage_script']);
	}

	/**
	 * Supprime les accents d'une chaîne
	 * 
	 * @param string $string La chaîne à traiter
	 * @return string La chaîne sans accents
	 */
	private static function remove_accents($string) {
		if (!preg_match('/[\x80-\xff]/', $string)) {
			return $string;
		}

		if (function_exists('transliterator_transliterate')) {
			$string = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);
		} else {
			$chars = [
				// Decompositions for Latin-1 Supplement
				'ª' => 'a', 'º' => 'o', 'À' => 'A', 'Á' => 'A',
				'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
				'Æ' => 'AE', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
				'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I',
				'Î' => 'I', 'Ï' => 'I', 'Ð' => 'D', 'Ñ' => 'N',
				'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
				'Ö' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U',
				'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'TH', 'ß' => 's',
				'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a',
				'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
				'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
				'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
				'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o',
				'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
				'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
				'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y', 'Ø' => 'O',
			];

			$string = strtr($string, $chars);
		}

		return $string;
	}

	/**
	 * Ajoute un script pour nettoyer le localStorage après la commande
	 * et ajouter un lien de retour au planning avec un paramètre
	 */
	public static function add_clear_localstorage_script() {
		// Ne charger le script que sur la page de confirmation de commande
		if (!is_wc_endpoint_url('order-received')) {
			return;
		}

		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			console.log('Nettoyage des données de sélection de cours...');
			// Supprimer les cours sélectionnés du localStorage
			localStorage.removeItem('fpr_selected_courses');
			console.log('Données de sélection de cours nettoyées avec succès.');

			// Ajouter un bouton de retour au planning si non existant
			if (document.querySelectorAll('a[href*="/planning"]').length === 0) {
				const orderDetails = document.querySelector('.woocommerce-order-details');
				if (orderDetails) {
					const returnButton = document.createElement('a');
					returnButton.href = '/planning';
					returnButton.className = 'button';
					returnButton.style.marginTop = '20px';
					returnButton.style.display = 'inline-block';
					returnButton.textContent = 'Retour au planning';
					orderDetails.appendChild(returnButton);
				}
			}

			// Définir un cookie pour indiquer que l'utilisateur vient de la page de commande
			// Ce cookie sera lu par calendar.js pour déclencher les actions nécessaires
			document.cookie = "fpr_from_checkout=1; path=/; max-age=300"; // expire après 5 minutes
		});
		</script>
		<?php
	}

	public static function handle_order($order_id, $old_status, $new_status, $order) {
		// On ne traite que les commandes validées
		if (!in_array($new_status, ['processing', 'completed'])) return;

		// Nettoyer la session WooCommerce
		if (WC()->session) {
			WC()->session->set('fpr_selected_courses', null);
		}

		// Saison sélectionnée par le client (via select au checkout)
		$saison_tag = get_post_meta($order_id, '_fpr_selected_saison', true);
		if (empty($saison_tag)) {
			// Utiliser une saison par défaut basée sur l'année en cours
			$current_year = date('Y');
			$saison_tag = "saison-{$current_year}";
		}

		// Vérifier si la saison existe dans la table des saisons
		global $wpdb;
		$saison = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}fpr_saisons WHERE tag = %s",
			$saison_tag
		));

		// Infos client
		$first = $order->get_billing_first_name();
		$last = $order->get_billing_last_name();
		$email = $order->get_billing_email();
		$phone = $order->get_billing_phone();
		$amount = $order->get_total();
		$user_id = $order->get_user_id();

		// Récupérer les informations d'abonnement Stripe si disponibles
		$stripe_subscription_id = '';
		$payment_plan_id = get_post_meta($order_id, '_fpr_selected_payment_plan', true);

		if ($user_id) {
			$subscription = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fpr_customer_subscriptions 
				WHERE user_id = %d AND order_id = %d
				ORDER BY id DESC LIMIT 1",
				$user_id, $order_id
			));

			if ($subscription) {
				// Si un abonnement existe déjà, mettre à jour le payment_plan_id si nécessaire
				if (!empty($payment_plan_id) && $subscription->payment_plan_id != $payment_plan_id) {
					\FPR\Helpers\Logger::log("[WooToAmelia] Mise à jour du plan de paiement pour l'abonnement #{$subscription->id}: {$subscription->payment_plan_id} -> {$payment_plan_id}");

					// Récupérer les détails du plan de paiement pour le log
					$payment_plan = $wpdb->get_row($wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
						$payment_plan_id
					));

					if ($payment_plan) {
						\FPR\Helpers\Logger::log("[WooToAmelia] Détails du nouveau plan: Nom={$payment_plan->name}, Fréquence={$payment_plan->frequency}, Terme={$payment_plan->term}, Versements={$payment_plan->installments}");
					}

					$result = $wpdb->update(
						$wpdb->prefix . 'fpr_customer_subscriptions',
						['payment_plan_id' => $payment_plan_id],
						['id' => $subscription->id]
					);

					if ($result !== false) {
						\FPR\Helpers\Logger::log("[WooToAmelia] ✅ Plan de paiement mis à jour avec succès pour l'abonnement #{$subscription->id}");

						// Vérifier que la mise à jour a bien été effectuée
						$updated_subscription = $wpdb->get_row($wpdb->prepare(
							"SELECT payment_plan_id FROM {$wpdb->prefix}fpr_customer_subscriptions WHERE id = %d",
							$subscription->id
						));

						if ($updated_subscription && $updated_subscription->payment_plan_id == $payment_plan_id) {
							\FPR\Helpers\Logger::log("[WooToAmelia] ✅ Vérification réussie: Plan de paiement correctement mis à jour à {$payment_plan_id}");
						} else {
							\FPR\Helpers\Logger::log("[WooToAmelia] ⚠️ Vérification échouée: Le plan de paiement n'a pas été correctement mis à jour. Valeur actuelle: " . ($updated_subscription ? $updated_subscription->payment_plan_id : "inconnu"));
						}
					} else {
						\FPR\Helpers\Logger::log("[WooToAmelia] ❌ Erreur lors de la mise à jour du plan de paiement: " . $wpdb->last_error);
					}
				} else {
					if (empty($payment_plan_id)) {
						\FPR\Helpers\Logger::log("[WooToAmelia] ℹ️ Aucun plan de paiement sélectionné, conservation du plan actuel: {$subscription->payment_plan_id}");
					} else {
						\FPR\Helpers\Logger::log("[WooToAmelia] ℹ️ Le plan de paiement sélectionné ({$payment_plan_id}) est identique à celui de l'abonnement, aucune mise à jour nécessaire");
					}
				}

				if (!empty($subscription->stripe_subscription_id)) {
					$stripe_subscription_id = $subscription->stripe_subscription_id;
				}
			}
		}

		// Pour chaque produit/cours choisi dans le panier
		foreach ($order->get_items() as $item) {
			// On va chercher les meta contenant la liste des cours choisis
			foreach ($item->get_meta_data() as $meta) {
				if (strpos($meta->key, 'Cours') !== false) {
					// Pour chaque nom de cours choisi par l'utilisateur
					self::register_in_amelia_by_tag(
						$first, $last, $email, $phone,
						$meta->value, // ex : "Pilates | 12:30 - 13:30 | 1h | avec Vanessa"
						$item->get_name(),
						$amount,
						$saison_tag, // Tag exact sélectionné par l'utilisateur
						$stripe_subscription_id, // ID d'abonnement Stripe (peut être vide)
						$payment_plan_id, // ID du plan de paiement (peut être vide)
						$order_id // ID de la commande
					);
				}
			}
		}
	}

	/**
	 * Enregistre le client à l'event/période Amelia correspondant au nom + tag de saison.
	 * @param $first string
	 * @param $last string
	 * @param $email string
	 * @param $phone string
	 * @param $event_name string (ex: "Pilates | 12:30 - 13:30 | 1h | avec Vanessa")
	 * @param $formula string
	 * @param $amount float
	 * @param $tag string (ex: "cours formule basique 2025-2026")
	 * @param $stripe_subscription_id string ID d'abonnement Stripe (optionnel)
	 * @param $payment_plan_id int ID du plan de paiement (optionnel)
	 * @param $order_id int ID de la commande WooCommerce
	 */
	public static function register_in_amelia_by_tag($first, $last, $email, $phone, $event_name, $formula, $amount, $tag, $stripe_subscription_id = '', $payment_plan_id = null, $order_id = 0) {
		global $wpdb;

		try {
			// Extraire le nom court du cours en utilisant plusieurs méthodes
			// Méthode 1: Extraire la partie avant le premier pipe
			$parts = explode('|', $event_name);
			$short_name = trim($parts[0]);

			// Méthode 2: Extraire la partie avant le premier tiret (si présent)
			$dash_parts = explode(' - ', $event_name);
			$dash_short_name = trim($dash_parts[0]);

			// Utiliser la version la plus courte comme nom principal
			if (strlen($dash_short_name) < strlen($short_name) && !empty($dash_short_name)) {
				$short_name = $dash_short_name;
			}

			// Créer des variantes du nom pour une recherche plus flexible
			$name_variants = [
				$short_name,                                // Nom exact
				preg_replace('/\s+/', ' ', $short_name),    // Normaliser les espaces
				strtolower($short_name),                    // Minuscules
				str_replace('-', ' ', $short_name),         // Remplacer les tirets par des espaces
				str_replace(' ', '-', $short_name),         // Remplacer les espaces par des tirets
			];

			// Ajouter des variantes sans accents
			$name_variants[] = self::remove_accents($short_name);

			// Éliminer les doublons
			$name_variants = array_unique($name_variants);

			// 1. On récupère TOUS les events liés à ce tag (saison)
			$events = $wpdb->get_results($wpdb->prepare("
				SELECT e.id, e.name FROM {$wpdb->prefix}amelia_events e
				INNER JOIN {$wpdb->prefix}amelia_events_tags et ON e.id = et.eventId
				WHERE et.name = %s
			", $tag));

			if (empty($events)) {
				throw new \Exception("Aucun événement trouvé avec le tag: '$tag'");
			}

			// 2. On cherche celui dont le nom match avec une des variantes
			$found = null;
			$match_type = '';

			foreach ($events as $ev) {
				$event_name_clean = trim($ev->name);

				// Test 1: Match exact (insensible à la casse)
				foreach ($name_variants as $variant) {
					if (strcasecmp($event_name_clean, $variant) === 0) {
						$found = $ev->id;
						$match_type = 'exact';
						break 2; // Sortir des deux boucles
					}
				}

				// Test 2: Le nom de l'événement commence par une des variantes (pour les cas avec saison en suffixe)
				if (!$found) {
					foreach ($name_variants as $variant) {
						if (preg_match('/^' . preg_quote($variant, '/') . '\s+\([^)]+\)$/i', $event_name_clean) ||
							stripos($event_name_clean, $variant . ' ') === 0) {
							$found = $ev->id;
							$match_type = 'prefix';
							break 2; // Sortir des deux boucles
						}
					}
				}

				// Test 3: Le nom de l'événement contient une des variantes (moins précis, à utiliser en dernier recours)
				if (!$found) {
					foreach ($name_variants as $variant) {
						if (stripos($event_name_clean, $variant) !== false) {
							$found = $ev->id;
							$match_type = 'contains';
							break 2; // Sortir des deux boucles
						}
					}
				}
			}

			if (!$found) {
				throw new \Exception("Event not found pour tag: '$tag' / cours: '$short_name'");
			}

			// 3. On prend la prochaine période future dispo pour cet event
			$periods = $wpdb->get_results($wpdb->prepare("
				SELECT id, periodStart, periodEnd FROM {$wpdb->prefix}amelia_events_periods
				WHERE eventId = %d AND periodStart > NOW()
				ORDER BY periodStart ASC
			", $found));

			if (empty($periods)) {
				// Si aucune période future n'est trouvée, essayer de trouver des périodes actuelles
				$periods = $wpdb->get_results($wpdb->prepare("
					SELECT id, periodStart, periodEnd FROM {$wpdb->prefix}amelia_events_periods
					WHERE eventId = %d
					ORDER BY periodStart ASC
				", $found));

				if (empty($periods)) {
					throw new \Exception("Aucune période trouvée pour l'événement #$found");
				}
			}

			// On prend la première période (la plus proche dans le futur ou la première disponible)
			$period = $periods[0];
			$period_id = $period->id;

			// Find or create FPR user
			$fpr_user_id = FPRUser::find_or_create_from_email($email, $first, $last, $phone);
			if (!$fpr_user_id) {
				throw new \Exception("Impossible de créer ou trouver un utilisateur FPR pour l'email: $email");
			}

			// Get FPR user
			$fpr_user = FPRUser::get_by_id($fpr_user_id);
			if (!$fpr_user) {
				throw new \Exception("Impossible de récupérer l'utilisateur FPR avec l'ID: $fpr_user_id");
			}

			// Get Amelia customer ID from FPR user
			$customer_id = $fpr_user->amelia_customer_id;
			if (!$customer_id) {
				// Create Amelia customer
				$amelia_data = [
					'firstName' => $first,
					'lastName' => $last,
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
					throw new \Exception("Impossible de créer un client Amelia: " . $wpdb->last_error);
				}

				$customer_id = $wpdb->insert_id;

				// Update FPR user with Amelia customer ID
				$wpdb->update(
					$wpdb->prefix . 'fpr_users',
					['amelia_customer_id' => $customer_id],
					['id' => $fpr_user_id]
				);
			}

			// Vérifier si l'utilisateur est déjà inscrit à ce cours
			$existing_booking = $wpdb->get_row($wpdb->prepare(
				"SELECT cb.id 
				FROM {$wpdb->prefix}amelia_customer_bookings cb
				INNER JOIN {$wpdb->prefix}amelia_events_periods ep ON cb.eventPeriodId = ep.id
				WHERE cb.customerId = %d AND ep.eventId = %d AND cb.status IN ('approved', 'pending')",
				$customer_id, $found
			));

			// 4. Préparer les données et appeler l'API Amelia pour enregistrer le client
			$data = [
				"type" => "event",
				"eventId" => $found,
				"bookings" => [[
					"customer" => [
						"email" => $email,
						"firstName" => $first,
						"lastName" => $last,
						"phone" => $phone,
						"countryPhoneIso" => "fr"
					],
					"customerId" => $customer_id, // Add customerId field
					"customFields" => [
						"1" => ["label" => "Formules(cours choisis)", "value" => $formula, "type" => "text"]
					],
					"persons" => 1
				]],
				"payment" => ["amount" => $amount, "gateway" => "onSite", "currency" => "EUR"],
				"recaptcha" => false,
				"locale" => "fr_FR",
				"timeZone" => "Europe/Paris",
				"eventPeriodId" => $period_id // Add eventPeriodId field
			];

			// Ajouter des métadonnées supplémentaires si disponibles
			$metadata = [
				"order_id" => $order_id
			];

			// Ajouter l'ID d'abonnement Stripe si disponible
			if (!empty($stripe_subscription_id)) {
				$metadata["stripe_subscription_id"] = $stripe_subscription_id;

				// Ajouter également dans les champs personnalisés pour être visible dans l'interface Amelia
				$data["bookings"][0]["customFields"]["2"] = [
					"label" => "ID Abonnement Stripe", 
					"value" => $stripe_subscription_id, 
					"type" => "text"
				];
			}

			// Ajouter l'ID du plan de paiement si disponible
			if (!empty($payment_plan_id)) {
				$metadata["payment_plan_id"] = $payment_plan_id;

				// Récupérer les détails du plan de paiement
				$payment_plan = $wpdb->get_row($wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
					$payment_plan_id
				));

				if ($payment_plan) {
					$data["bookings"][0]["customFields"]["3"] = [
						"label" => "Plan de paiement", 
						"value" => $payment_plan->name, 
						"type" => "text"
					];
				}
			}

			// Ajouter les métadonnées à la requête
			$data["metadata"] = $metadata;

			// Récupérer le token Amelia
			$amelia_token = defined('AMELIA_API_TOKEN') ? AMELIA_API_TOKEN : '';

			// Si le token n'est pas défini, essayer de le récupérer depuis les options
			if (empty($amelia_token)) {
				$amelia_token = get_option('fpr_amelia_api_token', '');
			}

			// Unique log pour l'ajout de l'utilisateur à l'événement
			Logger::log("[Amelia] Ajout de l'utilisateur $first $last ($email) à l'événement '$short_name' (ID: $found) pour la saison '$tag'" . 
				(!empty($stripe_subscription_id) ? " avec abonnement Stripe: $stripe_subscription_id" : ""));

			$response = wp_remote_post(admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/bookings'), [
				'body'    => json_encode($data),
				'headers' => [
					'Content-Type' => 'application/json',
					'Amelia' => $amelia_token,
				],
				'timeout' => 30, // Augmenter le timeout pour éviter les erreurs de timeout
			]);

			if (is_wp_error($response)) {
				throw new \Exception($response->get_error_message());
			}

			$status = wp_remote_retrieve_response_code($response);
			$body = wp_remote_retrieve_body($response);

			if ($status < 200 || $status >= 300) {
				throw new \Exception("Réponse API: ($status) $body");
			}
		} catch (\Exception $e) {
			Logger::log("Erreur lors de l'ajout à l'événement '$short_name'. " . $e->getMessage());
		}
	}
}
