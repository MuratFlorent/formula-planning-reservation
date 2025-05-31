<?php
namespace FPR\Admin;

class DebugCheckup {
	public static function init() {
		add_action('admin_post_fpr_clean_database', [__CLASS__, 'handle_clean_database']);
	}

	/**
     * Enqueue scripts and styles for the debug page
     */
    public static function enqueue_scripts_and_styles() {
        // Enqueue common admin styles
        wp_enqueue_style('fpr-admin', FPR_PLUGIN_URL . 'assets/css/admin.css', [], FPR_VERSION);

        // Add dashicons for the icons
        wp_enqueue_style('dashicons');
    }

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
		// Enqueue scripts and styles for the debug page
        self::enqueue_scripts_and_styles();

		echo '<table class="fpr-table fpr-debug-table"><thead><tr><th>Vérification</th><th>Statut</th><th>Détail</th></tr></thead><tbody>';
		foreach (self::run() as $row) {
			echo '<tr>';
			echo '<td>' . esc_html($row['label']) . '</td>';
			echo '<td>' . ($row['check'] ? '<span class="fpr-status fpr-status-active">✅ OK</span>' : '<span class="fpr-status fpr-status-inactive">❌ KO</span>') . '</td>';
			echo '<td>' . $row['detail'] . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		// Ajouter le formulaire de nettoyage de la base de données
		echo '<hr><h3>Nettoyage de la base de données</h3>';
		echo '<p>Cette fonction permet de nettoyer la base de données en supprimant les cours en double et en réinitialisant les données problématiques.</p>';
		echo '<form method="post" action="' . admin_url('admin-post.php') . '" class="fpr-form" onsubmit="return confirm(\'Êtes-vous sûr de vouloir nettoyer la base de données ? Cette action est irréversible.\');">';
		echo '<input type="hidden" name="action" value="fpr_clean_database">';
		wp_nonce_field('fpr_clean_database_nonce');
		echo '<p><button type="submit" class="button button-primary">Nettoyer la base de données</button></p>';
		echo '</form>';

		// Afficher un message si le nettoyage a été effectué
		if (isset($_GET['cleaned']) && $_GET['cleaned'] == '1') {
			echo '<div class="notice notice-success"><p>La base de données a été nettoyée avec succès.</p></div>';
		}
	}

	/**
	 * Gère le nettoyage de la base de données
	 */
	public static function handle_clean_database() {
		// Vérification du nonce
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fpr_clean_database_nonce')) {
			wp_die('Sécurité: nonce invalide');
		}

		global $wpdb;
		$cleaned = 0;
		$errors = 0;

		// 1. Supprimer les périodes d'événements en double
		\FPR\Helpers\Logger::log("[Nettoyage] Début du nettoyage de la base de données");

		// Identifier les périodes en double (même eventId, même date/heure)
		$duplicate_periods = $wpdb->get_results("
			SELECT ep1.id, ep1.eventId, ep1.periodStart, ep1.periodEnd
			FROM {$wpdb->prefix}amelia_events_periods ep1
			INNER JOIN (
				SELECT eventId, periodStart, periodEnd, COUNT(*) as cnt
				FROM {$wpdb->prefix}amelia_events_periods
				GROUP BY eventId, periodStart, periodEnd
				HAVING cnt > 1
			) ep2 ON ep1.eventId = ep2.eventId AND ep1.periodStart = ep2.periodStart AND ep1.periodEnd = ep2.periodEnd
			ORDER BY ep1.eventId, ep1.periodStart
		");

		if (!empty($duplicate_periods)) {
			\FPR\Helpers\Logger::log("[Nettoyage] " . count($duplicate_periods) . " périodes en double trouvées");

			// Garder une seule période pour chaque combinaison eventId/periodStart/periodEnd
			$processed = [];
			foreach ($duplicate_periods as $period) {
				$key = $period->eventId . '_' . $period->periodStart . '_' . $period->periodEnd;

				if (!isset($processed[$key])) {
					// Garder la première occurrence
					$processed[$key] = $period->id;
				} else {
					// Supprimer les doublons
					$result = $wpdb->delete(
						$wpdb->prefix . 'amelia_events_periods',
						array('id' => $period->id),
						array('%d')
					);

					if ($result) {
						$cleaned++;
						\FPR\Helpers\Logger::log("[Nettoyage] Période supprimée: ID #{$period->id} (Event #{$period->eventId}, {$period->periodStart} - {$period->periodEnd})");
					} else {
						$errors++;
						\FPR\Helpers\Logger::log("[Nettoyage] ERREUR: Échec de suppression de la période #{$period->id}");
					}
				}
			}
		} else {
			\FPR\Helpers\Logger::log("[Nettoyage] Aucune période en double trouvée");
		}

		// 2. Nettoyer les réservations orphelines (sans période associée)
		$orphaned_bookings = $wpdb->get_results("
			SELECT cb.id, cb.eventId, cb.customerId
			FROM {$wpdb->prefix}amelia_customer_bookings cb
			LEFT JOIN {$wpdb->prefix}amelia_events_periods ep ON cb.eventPeriodId = ep.id
			WHERE cb.eventPeriodId IS NOT NULL AND ep.id IS NULL
		");

		if (!empty($orphaned_bookings)) {
			\FPR\Helpers\Logger::log("[Nettoyage] " . count($orphaned_bookings) . " réservations orphelines trouvées");

			foreach ($orphaned_bookings as $booking) {
				$result = $wpdb->delete(
					$wpdb->prefix . 'amelia_customer_bookings',
					array('id' => $booking->id),
					array('%d')
				);

				if ($result) {
					$cleaned++;
					\FPR\Helpers\Logger::log("[Nettoyage] Réservation orpheline supprimée: ID #{$booking->id} (Client #{$booking->customerId}, Event #{$booking->eventId})");
				} else {
					$errors++;
					\FPR\Helpers\Logger::log("[Nettoyage] ERREUR: Échec de suppression de la réservation #{$booking->id}");
				}
			}
		} else {
			\FPR\Helpers\Logger::log("[Nettoyage] Aucune réservation orpheline trouvée");
		}

		\FPR\Helpers\Logger::log("[Nettoyage] Fin du nettoyage: $cleaned éléments nettoyés, $errors erreurs");

		// Redirection vers la page de debug avec un message de succès
		wp_redirect(admin_url('options-general.php?page=fpr-settings&tab=debug&cleaned=1'));
		exit;
	}
}
