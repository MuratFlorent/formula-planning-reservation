<?php
namespace FPR\Modules;

use FPR\Helpers\ProductMapper;
use FPR\Helpers\Logger;

class FormHandler {
	public static function init() {
		add_action('template_redirect', [__CLASS__, 'maybe_store_selection']);

		// Enregistrement AJAX standard
		add_action('wp_ajax_fpr_get_product_id', [__CLASS__, 'handle_ajax_selection']);
		add_action('wp_ajax_nopriv_fpr_get_product_id', [__CLASS__, 'handle_ajax_selection']);
	}

	// ✅ AJAX handler (admin-ajax.php)
	public static function handle_ajax_selection() {
		if (!isset($_POST['count']) || !isset($_POST['courses'])) {
			wp_send_json_error(['message' => 'Paramètres manquants']);
			exit;
		}

		// Simuler l'appel classique
		$_GET['fpr_store_cart_selection'] = true;
		$_GET['fpr_confirmed'] = true;
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// Injecter les données JSON directement
		$GLOBALS['fpr_raw_post'] = json_encode([
			'count'   => intval($_POST['count']),
			'courses' => $_POST['courses']
		]);

		// Appel central
		self::maybe_store_selection();
	}

	/**
	 * Standardise le format de l'heure pour un cours
	 * 
	 * @param string $time Le temps à standardiser
	 * @return string Le temps standardisé
	 */
	private static function standardize_time_format($time) {
		// Standardize time format (remove any existing formatting)
		$time = preg_replace('/[^0-9:-]/', '', $time);

		// Extract start and end times
		$times = explode('-', $time);
		$start_time = trim($times[0]);
		$end_time = isset($times[1]) ? trim($times[1]) : '';

		// Format times with colons if needed
		if (strlen($start_time) == 4 && strpos($start_time, ':') === false) {
			$start_time = substr($start_time, 0, 2) . ':' . substr($start_time, 2);
		}
		if (strlen($end_time) == 4 && strpos($end_time, ':') === false) {
			$end_time = substr($end_time, 0, 2) . ':' . substr($end_time, 2);
		}

		// Reconstruct the formatted time
		return $start_time . ' - ' . $end_time;
	}

	// 🔁 Handler central pour POST personnalisé ou AJAX simulé
	public static function maybe_store_selection() {
		if (!isset($_GET['fpr_store_cart_selection']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
			Logger::log("🔸 Requête ignorée (pas de paramètre ou mauvaise méthode)");
			return;
		}

		$raw = $GLOBALS['fpr_raw_post'] ?? file_get_contents('php://input');
		Logger::log("📩 Données brutes reçues : $raw");

		$data = json_decode($raw, true);
		if (!isset($data['count']) || !is_array($data['courses'])) {
			Logger::log("❌ Paramètres manquants ou invalides.");
			wp_send_json_error(['message' => 'Paramètres manquants.']);
			exit;
		}

		$newCourses = $data['courses'];
		Logger::log("✅ {$data['count']} cours reçus. Exemple : " . json_encode($newCourses[0]));

		if (!WC()->session) WC()->session = new \WC_Session_Handler();

		// Récupérer les cours existants avant toute modification
		$existing = WC()->session->get('fpr_selected_courses');
		$existingCourses = $existing ? json_decode($existing, true) : [];

		// ✅ Réinitialisation explicite - seulement si on n'a pas de cours existants ou si on veut vraiment réinitialiser
		if (isset($_GET['fpr_confirmed']) && empty($existingCourses)) {
			Logger::log("🧹 Réinitialisation des cours (confirmation explicite, aucun cours existant)");
			// Pas besoin de réinitialiser car $existingCourses est déjà vide
		} else if (isset($_GET['fpr_confirmed']) && isset($_GET['fpr_reset']) && $_GET['fpr_reset'] === '1') {
			// Réinitialisation forcée si le paramètre fpr_reset est présent et égal à 1
			$existingCourses = [];
			Logger::log("🧹 Réinitialisation forcée des cours (paramètre fpr_reset=1)");
		} else if (isset($_GET['fpr_confirmed'])) {
			// Si on a des cours existants et qu'on ne veut pas réinitialiser, on les garde
			Logger::log("🔄 Conservation des cours existants malgré fpr_confirmed");
		}

		Logger::log("📋 Cours existants dans la session : " . ($existing ? $existing : "aucun"));
		if (!empty($existingCourses)) {
			Logger::log("🔢 Nombre de cours existants : " . count($existingCourses));
			Logger::log("🔍 Premier cours existant : " . json_encode($existingCourses[0]));

			// Standardiser le format de l'heure pour tous les cours existants
			foreach ($existingCourses as &$course) {
				if (isset($course['time'])) {
					$originalTime = $course['time'];
					$course['time'] = self::standardize_time_format($course['time']);

					if ($originalTime !== $course['time']) {
						Logger::log("  ⏰ Standardisation du temps pour un cours existant: " . $originalTime . " -> " . $course['time']);
					}
				}
			}
			unset($course); // Détruire la référence
		}

		// Standardiser le format de l'heure pour tous les nouveaux cours
		foreach ($newCourses as &$course) {
			if (isset($course['time'])) {
				$originalTime = $course['time'];
				$course['time'] = self::standardize_time_format($course['time']);

				if ($originalTime !== $course['time']) {
					Logger::log("  ⏰ Standardisation du temps pour un nouveau cours: " . $originalTime . " -> " . $course['time']);
				}
			}
		}
		unset($course); // Détruire la référence

		// Fusionner les cours existants et nouveaux
		$mergedCourses = array_merge($existingCourses, $newCourses);
		Logger::log("🔄 Fusion des cours - Existants: " . count($existingCourses) . " + Nouveaux: " . count($newCourses) . " = Total avant déduplication: " . count($mergedCourses));

		// Log détaillé des nouveaux cours
		Logger::log("📥 Détail des nouveaux cours:");
		foreach ($newCourses as $index => $course) {
			Logger::log("  📌 Cours #" . ($index + 1) . ": " . json_encode($course));
		}

		// Ne pas éliminer les doublons - garder tous les cours sélectionnés
		Logger::log("🔍 Processus de déduplication désactivé - conservation de tous les cours sélectionnés");

		// Log des cours conservés
		foreach ($mergedCourses as $index => $course) {
			// Créer une clé unique pour chaque cours (uniquement pour le log)
			$courseKey = '';
			foreach ($course as $key => $value) {
				if (is_string($value) || is_numeric($value)) {
					$courseKey .= $key . '=' . $value . '|';
				}
			}

			// Log de la clé générée
			Logger::log("  🔑 Cours #" . ($index + 1) . " - Clé générée: " . $courseKey);
			Logger::log("  ✅ Cours #" . ($index + 1) . " - Conservé");
		}

		Logger::log("🧮 Résultat: " . count($mergedCourses) . " cours conservés (y compris les doublons)");

		Logger::log("🔁 Fusion totale : " . count($mergedCourses) . " cours (sans déduplication)");

		// Analyse des durées
		$durations = array_map(fn($c) => trim($c['duration']), $mergedCourses);
		$count1h = 0;
		$countOver = 0;

		// Récupérer la durée par défaut des cours
		$default_duration = get_option('fpr_default_course_duration', '1h');
		Logger::log("⏱️ Durée par défaut des cours: $default_duration");

		foreach ($durations as $d) {
			// Vérifier si la durée correspond à la durée par défaut
			if ($d === $default_duration) {
				$count1h++;
				Logger::log("✅ Cours de durée par défaut trouvé: $d");
			} else {
				$countOver++;
				Logger::log("⏱️ Cours de durée non standard trouvé: $d");
			}
		}

		Logger::log("🧮 Répartition des cours : durée par défaut ($default_duration)=$count1h | autres durées=$countOver");

		// Récupérer le seuil "à volonté" depuis les options d'administration
		$seuilAVolonte = intval(get_option('fpr_aw_threshold', 5));

		$product_id = ProductMapper::get_product_id_smart($count1h, $countOver, $seuilAVolonte);

		if (!$product_id) {
			Logger::log("❌ Aucun produit WooCommerce ne correspond.");
			wp_send_json_error(['message' => 'Produit introuvable.']);
			exit;
		}

		// Convertir en JSON pour stockage
		$jsonData = json_encode($mergedCourses);
		Logger::log("📤 Données JSON à enregistrer en session: " . $jsonData);
		Logger::log("📊 Taille des données JSON: " . strlen($jsonData) . " caractères");

		// Vérifier si les données sont valides
		$validJson = json_decode($jsonData) !== null;
		Logger::log($validJson ? "✅ JSON valide" : "❌ JSON invalide");

		// Enregistrer en session
		WC()->session->set('fpr_selected_courses', $jsonData);
		Logger::log("🧠 Données enregistrées en session.");

		// Vérification immédiate
		$storedData = WC()->session->get('fpr_selected_courses');
		Logger::log("🔄 Vérification des données en session: " . ($storedData === $jsonData ? "✅ Identiques" : "❌ Différentes"));
		if ($storedData !== $jsonData) {
			Logger::log("⚠️ ALERTE: Les données stockées ne correspondent pas aux données envoyées!");
			Logger::log("📥 Données stockées: " . $storedData);
		}

		// Opérations sur le panier
		Logger::log("🛒 Début des opérations sur le panier");

		if (!WC()->cart) {
			Logger::log("⚠️ Panier non initialisé, chargement du panier");
			wc_load_cart();
		}

		// Vider le panier
		Logger::log("🗑️ Vidage du panier");
		$itemsBeforeEmpty = WC()->cart->get_cart_contents_count();
		Logger::log("📊 Nombre d'articles avant vidage: " . $itemsBeforeEmpty);
		WC()->cart->empty_cart();

		// Ajouter le produit au panier
		Logger::log("➕ Ajout du produit $product_id au panier");
		$result = WC()->cart->add_to_cart($product_id);

		if ($result === false) {
			Logger::log("❌ Échec de l'ajout du produit");
			// Vérifier si le produit existe
			$product = wc_get_product($product_id);
			if (!$product) {
				Logger::log("❌ Le produit $product_id n'existe pas");
				wp_send_json_error(['message' => 'Le produit sélectionné n\'existe pas.']);
				exit;
			}

			// Vérifier si le produit est en stock
			if (!$product->is_in_stock()) {
				Logger::log("❌ Le produit $product_id n'est pas en stock");
				wp_send_json_error(['message' => 'Le produit sélectionné n\'est pas en stock.']);
				exit;
			}

			// Erreur générique si aucune cause spécifique n'est identifiée
			Logger::log("❌ Erreur inconnue lors de l'ajout du produit $product_id au panier");
			wp_send_json_error(['message' => 'Une erreur est survenue lors de l\'ajout du produit au panier.']);
			exit;
		}

		Logger::log("✅ Produit ajouté avec succès");

		// Calculer les totaux
		Logger::log("🧮 Calcul des totaux du panier");
		WC()->cart->calculate_totals();

		// Vérifier le contenu du panier
		$itemsAfterAdd = WC()->cart->get_cart_contents_count();
		Logger::log("📊 Nombre d'articles après ajout: " . $itemsAfterAdd);

		// Vérifier que le panier n'est pas vide
		if ($itemsAfterAdd == 0) {
			Logger::log("❌ Le panier est vide après tentative d'ajout du produit");
			wp_send_json_error(['message' => 'Une erreur est survenue lors de l\'ajout des cours au panier. Le panier est vide.']);
			exit;
		}

		// Vérifier que les données de cours sont bien attachées au produit dans le panier
		$cartItems = WC()->cart->get_cart();
		Logger::log("🔍 Vérification des données de cours dans le panier");
		$coursesAttached = false;

		foreach ($cartItems as $cartItemKey => $cartItem) {
			if (isset($cartItem['fpr_selected_courses'])) {
				Logger::log("✅ Données de cours trouvées pour l'article $cartItemKey");
				$coursesInCart = is_string($cartItem['fpr_selected_courses']) 
					? json_decode($cartItem['fpr_selected_courses'], true) 
					: $cartItem['fpr_selected_courses'];
				Logger::log("📊 Nombre de cours dans l'article: " . (is_array($coursesInCart) ? count($coursesInCart) : "N/A"));
				$coursesAttached = true;
			} else {
				Logger::log("❌ Aucune donnée de cours trouvée pour l'article $cartItemKey");
			}
		}

		// Vérifier que les données de cours sont bien attachées à au moins un article
		if (!$coursesAttached) {
			Logger::log("❌ Aucune donnée de cours n'a été attachée aux articles du panier");
			wp_send_json_error(['message' => 'Une erreur est survenue lors de l\'ajout des cours au panier. Les données de cours n\'ont pas été attachées.']);
			exit;
		}

		Logger::log("🛒 Produit $product_id ajouté au panier avec succès.");
		wp_send_json_success(['added_product_id' => $product_id]);
		exit;
	}
}
