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

		// Simuler l’appel classique
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

		// ✅ Réinitialisation explicite
		if (isset($_GET['fpr_confirmed'])) {
			WC()->session->set('fpr_selected_courses', []);
			Logger::log("🧹 Réinitialisation des cours (confirmation explicite)");
		}

		$existing = WC()->session->get('fpr_selected_courses');
		$existingCourses = $existing ? json_decode($existing, true) : [];

		$mergedCourses = array_merge($existingCourses, $newCourses);
		Logger::log("🔁 Fusion totale : " . count($mergedCourses) . " cours");

		// Analyse des durées
		$durations = array_map(fn($c) => trim($c['duration']), $mergedCourses);
		$count1h = 0;
		$countOver = 0;

		foreach ($durations as $d) {
			if (str_starts_with($d, '1h') && !str_contains($d, '1h15') && !str_contains($d, '1h30')) {
				$count1h++;
			} else {
				$countOver++;
			}
		}

		Logger::log("🧮 Répartition des cours : 1h=$count1h | >1h=$countOver");

		$rules = get_option('fpr_rules', [
			'volonte_threshold' => 5,
			'duration_limit' => '1h15'
		]);
		$seuilAVolonte = intval($rules['volonte_threshold'] ?? 5);

		$product_id = ProductMapper::get_product_id_smart($count1h, $countOver, $seuilAVolonte);

		if (!$product_id) {
			Logger::log("❌ Aucun produit WooCommerce ne correspond.");
			wp_send_json_error(['message' => 'Produit introuvable.']);
			exit;
		}

		WC()->session->set('fpr_selected_courses', json_encode($mergedCourses));
		Logger::log("🧠 Données enregistrées en session.");

		if (!WC()->cart) wc_load_cart();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart($product_id);
		WC()->cart->calculate_totals();

		Logger::log("🛒 Produit $product_id ajouté au panier.");
		wp_send_json_success(['added_product_id' => $product_id]);
		exit;
	}
}
