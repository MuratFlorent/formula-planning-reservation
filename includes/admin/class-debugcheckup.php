<?php
namespace FPR\Admin;

class DebugCheckup {
	public static function run() {
		$results = [];

		// 1. Vérifier si la constante AMELIA_API_TOKEN est définie
		$results[] = [
			'label' => 'Clé API Amelia définie',
			'check' => defined('AMELIA_API_TOKEN') && AMELIA_API_TOKEN !== '',
			'detail' => defined('AMELIA_API_TOKEN') ? AMELIA_API_TOKEN : 'Non définie'
		];

		// 2. Dernière commande test créée ?
		$last_order = wc_get_orders([
			'limit' => 1,
			'orderby' => 'date',
			'order' => 'DESC'
		]);
		if (!empty($last_order)) {
			$order = $last_order[0];
			$results[] = [
				'label' => 'Dernière commande WooCommerce',
				'check' => true,
				'detail' => 'Commande #' . $order->get_id() . ' - statut : ' . $order->get_status(),
			];
		} else {
			$results[] = [
				'label' => 'Dernière commande WooCommerce',
				'check' => false,
				'detail' => 'Aucune commande trouvée',
			];
		}

		// 3. Dernier log dans custom_dev.log
		$log_path = WP_CONTENT_DIR . '/logs/custom_dev.log';
		if (file_exists($log_path)) {
			$lines = array_slice(file($log_path), -5);
			$results[] = [
				'label' => 'Derniers logs personnalisés',
				'check' => true,
				'detail' => '<pre>' . implode("", $lines) . '</pre>'
			];
		} else {
			$results[] = [
				'label' => 'Fichier de logs',
				'check' => false,
				'detail' => 'Fichier custom_dev.log non trouvé dans wp-content/logs/'
			];
		}

		// 4. Vérification des paramètres clés du plugin
		$required_options = [
			'fpr_course_counts',
			'fpr_product_keyword',
			'fpr_match_strategy'
		];
		foreach ($required_options as $option) {
			$results[] = [
				'label' => 'Option : ' . $option,
				'check' => get_option($option),
				'detail' => get_option($option) ?: 'Non défini'
			];
		}

		return $results;
	}

	public static function render() {
		echo '<table class="widefat"><thead><tr><th>Vérification</th><th>Statut</th><th>Détail</th></tr></thead><tbody>';
		foreach (self::run() as $row) {
			echo '<tr>';
			echo '<td>' . esc_html($row['label']) . '</td>';
			echo '<td>' . ($row['check'] ? '<span style="color:green;">✅ OK</span>' : '<span style="color:red;">❌ KO</span>') . '</td>';
			echo '<td>' . $row['detail'] . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}
