<?php
namespace FPR\Admin;

if (!defined('ABSPATH')) exit;

class Saisons {
	// PAS de add_menu ici !
	public static function init() {
		add_action('admin_post_fpr_create_saison', [__CLASS__, 'handle_create_saison']);
		add_action('admin_post_fpr_delete_saison', [__CLASS__, 'handle_delete_saison']);
	}

	/**
     * Enqueue scripts and styles for the saisons page
     */
    public static function enqueue_scripts_and_styles() {
        // Enqueue common admin styles
        wp_enqueue_style('fpr-admin', FPR_PLUGIN_URL . 'assets/css/admin.css', [], FPR_VERSION);

        // Add dashicons for the icons
        wp_enqueue_style('dashicons');
    }

 public static function render_page() {
		global $wpdb;

		// Enqueue scripts and styles for the saisons page
        self::enqueue_scripts_and_styles();
		?>
        <div class="wrap">
            <h1>Gestion des saisons</h1>

            <h2>Créer une nouvelle saison</h2>
            <button type="button" id="fpr-add-saison" class="fpr-add-button">
                <span class="dashicons dashicons-plus"></span>
            </button>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="fpr-form" id="fpr-create-form" style="display: none;">
                <input type="hidden" name="action" value="fpr_create_saison">
				<?php wp_nonce_field('fpr_create_saison_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="saison">Nom de la saison</label></th>
                        <td><input type="text" id="saison" name="saison" placeholder="Ex: 2025-2026" required></td>
                    </tr>
                    <tr>
                        <th><label for="tag">Tag associé</label></th>
                        <td><input type="text" id="tag" name="tag" placeholder="Ex: cours formule basique 2025-2026" required></td>
                    </tr>
                    <tr>
                        <th><label for="start_date">Date de début</label></th>
                        <td><input type="text" id="start_date" name="start_date" placeholder="Ex: 2025-09-01" required></td>
                    </tr>
                    <tr>
                        <th><label for="end_date">Date de fin</label></th>
                        <td><input type="text" id="end_date" name="end_date" placeholder="Ex: 2026-07-01" required></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Créer la saison et les cours associés</button>
                </p>
            </form>
			<?php 
			if (isset($_GET['done'])) echo "<div class='updated'><p>Saison et cours créés !</p></div>";
			if (isset($_GET['deleted'])) echo "<div class='updated'><p>Saison supprimée avec succès !</p></div>";
			?>

            <h2>Saisons existantes</h2>
            <?php
            // Récupérer les saisons existantes depuis la base de données
            $saisons = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fpr_saisons ORDER BY id DESC");

            if (empty($saisons)) {
                echo '<p>Aucune saison n\'a été créée pour le moment.</p>';
            } else {
                ?>
                <table class="fpr-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Tag</th>
                            <th>Date de début</th>
                            <th>Date de fin</th>
                            <th>Date de création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saisons as $saison) : ?>
                            <tr>
                                <td><?php echo esc_html($saison->id); ?></td>
                                <td><?php echo esc_html($saison->name); ?></td>
                                <td><?php echo esc_html($saison->tag); ?></td>
                                <td><?php echo esc_html($saison->start_date); ?></td>
                                <td><?php echo esc_html($saison->end_date); ?></td>
                                <td><?php echo esc_html($saison->created_at); ?></td>
                                <td class="actions-column">
                                    <div class="action-buttons">
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette saison ? Cette action est irréversible.');">
                                            <input type="hidden" name="action" value="fpr_delete_saison">
                                            <input type="hidden" name="saison_id" value="<?php echo esc_attr($saison->id); ?>">
                                            <?php wp_nonce_field('fpr_delete_saison_nonce'); ?>
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
                $('#fpr-add-saison').on('click', function() {
                    $('#fpr-create-form').slideToggle(300);
                });
            });
        </script>
		<?php
	}

 public static function handle_create_saison() {
		global $wpdb;

		// Vérification du nonce
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fpr_create_saison_nonce')) {
			wp_die('Sécurité: nonce invalide');
		}

		// Récupération des données du formulaire
		$saison = sanitize_text_field($_POST['saison']);
		$tag = sanitize_text_field($_POST['tag']);
		$start_date = sanitize_text_field($_POST['start_date']);
		$end_date = sanitize_text_field($_POST['end_date']);

		// Validation des dates
		$start_timestamp = strtotime($start_date);
		$end_timestamp = strtotime($end_date);

		if (!$start_timestamp || !$end_timestamp) {
			wp_die('Format de date invalide. Utilisez le format YYYY-MM-DD.');
		}

		if ($start_timestamp >= $end_timestamp) {
			wp_die('La date de début doit être antérieure à la date de fin.');
		}

		// Création de la table si elle n'existe pas
		$table_name = $wpdb->prefix . 'fpr_saisons';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			tag varchar(255) NOT NULL,
			start_date date NOT NULL,
			end_date date NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		// Enregistrement de la saison
		$wpdb->insert(
			$table_name,
			array(
				'name' => $saison,
				'tag' => $tag,
				'start_date' => date('Y-m-d', $start_timestamp),
				'end_date' => date('Y-m-d', $end_timestamp),
			)
		);

		$saison_id = $wpdb->insert_id;

		if (!$saison_id) {
			wp_die('Erreur lors de l\'enregistrement de la saison.');
		}

		// Création des périodes d'événements dans Amelia
		self::create_event_periods($tag, $start_date, $end_date);

		// Redirection vers la page des saisons
		wp_redirect(admin_url('options-general.php?page=fpr-settings&tab=seasons&done=1'));
		exit;
	}

	/**
	 * Gère la suppression d'une saison
	 */
	public static function handle_delete_saison() {
		global $wpdb;

		// Vérification du nonce
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fpr_delete_saison_nonce')) {
			wp_die('Sécurité: nonce invalide');
		}

		// Récupération de l'ID de la saison
		if (!isset($_POST['saison_id']) || !is_numeric($_POST['saison_id'])) {
			wp_die('ID de saison invalide');
		}

		$saison_id = intval($_POST['saison_id']);

		// Récupérer les informations de la saison avant suppression (pour le log)
		$saison = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}fpr_saisons WHERE id = %d",
			$saison_id
		));

		if (!$saison) {
			wp_die('Saison introuvable');
		}

		// Supprimer la saison
		$result = $wpdb->delete(
			$wpdb->prefix . 'fpr_saisons',
			array('id' => $saison_id),
			array('%d')
		);

		if ($result === false) {
			wp_die('Erreur lors de la suppression de la saison');
		}

		// Redirection vers la page des saisons avec un message de succès
		wp_redirect(admin_url('options-general.php?page=fpr-settings&tab=seasons&deleted=1'));
		exit;
	}

	private static function create_event_periods($tag, $start_date, $end_date) {
		global $wpdb;

		\FPR\Helpers\Logger::log("[Saisons] Début de la création des périodes pour le tag: '$tag' du $start_date au $end_date");

		// Récupérer la saison pour ajouter son nom aux événements
		$saison = $wpdb->get_row($wpdb->prepare("
			SELECT name FROM {$wpdb->prefix}fpr_saisons 
			WHERE tag = %s
		", $tag));

		$saison_name = $saison ? $saison->name : '';
		\FPR\Helpers\Logger::log("[Saisons] Saison trouvée: " . ($saison_name ?: 'Aucune'));

		// Récupérer tous les événements avec ce tag
		$events = $wpdb->get_results($wpdb->prepare("
			SELECT e.id, e.name 
			FROM {$wpdb->prefix}amelia_events e
			INNER JOIN {$wpdb->prefix}amelia_events_tags et ON e.id = et.eventId
			WHERE et.name = %s
		", $tag));

		if (empty($events)) {
			\FPR\Helpers\Logger::log("[Saisons] ERREUR: Aucun événement trouvé avec le tag: '$tag'");
			return;
		}

		\FPR\Helpers\Logger::log("[Saisons] " . count($events) . " événements trouvés avec le tag: '$tag'");

		// Supprimer les anciennes périodes pour éviter les doublons
		$event_ids = array_map(function($event) { return $event->id; }, $events);
		$placeholders = implode(',', array_fill(0, count($event_ids), '%d'));

		// Construire la requête pour supprimer les périodes futures
		$query = $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}amelia_events_periods 
			WHERE eventId IN ($placeholders) 
			AND periodStart >= %s",
			array_merge($event_ids, [$start_date])
		);

		$deleted = $wpdb->query($query);
		\FPR\Helpers\Logger::log("[Saisons] $deleted périodes futures supprimées pour éviter les doublons");

		// Pour chaque événement, créer des périodes hebdomadaires
		foreach ($events as $event) {
			\FPR\Helpers\Logger::log("[Saisons] Traitement de l'événement #" . $event->id . ": " . $event->name);

			// Récupérer les informations de la dernière période de cet événement pour copier les heures et le jour de la semaine
			$last_period = $wpdb->get_row($wpdb->prepare("
				SELECT periodStart, periodEnd 
				FROM {$wpdb->prefix}amelia_events_periods
				WHERE eventId = %d
				ORDER BY id DESC
				LIMIT 1
			", $event->id));

			if (!$last_period) {
				\FPR\Helpers\Logger::log("[Saisons] ERREUR: Aucune période existante trouvée pour l'événement #" . $event->id);
				continue;
			}

			// Extraire les heures et minutes de début et de fin
			$start_time = date('H:i:s', strtotime($last_period->periodStart));
			$end_time = date('H:i:s', strtotime($last_period->periodEnd));

			// Déterminer le jour de la semaine (0 = dimanche, 1 = lundi, etc.)
			$day_of_week = date('w', strtotime($last_period->periodStart));
			$day_names = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
			$day_name = $day_names[$day_of_week];

			\FPR\Helpers\Logger::log("[Saisons] Horaire de l'événement: $day_name de $start_time à $end_time");

			// Mettre à jour le nom de l'événement pour inclure la saison
			if (!empty($saison_name) && strpos($event->name, $saison_name) === false) {
				$new_name = $event->name . ' (' . $saison_name . ')';
				$wpdb->update(
					$wpdb->prefix . 'amelia_events',
					array('name' => $new_name),
					array('id' => $event->id)
				);
				\FPR\Helpers\Logger::log("[Saisons] Nom de l'événement mis à jour: " . $new_name);
			}

			// Créer des périodes hebdomadaires
			$current_date = new \DateTime($start_date);
			$end_datetime = new \DateTime($end_date);

			// Ajuster la date de début au premier jour de la semaine correspondant
			while ($current_date->format('w') != $day_of_week) {
				$current_date->modify('+1 day');
			}

			\FPR\Helpers\Logger::log("[Saisons] Date de début ajustée au premier $day_name: " . $current_date->format('Y-m-d'));

			// Créer une période pour chaque semaine
			$periods_created = 0;
			$periods_to_insert = [];

			while ($current_date < $end_datetime) {
				$period_start = $current_date->format('Y-m-d') . ' ' . $start_time;
				$period_end = $current_date->format('Y-m-d') . ' ' . $end_time;

				// Vérifier si cette période existe déjà
				$exists = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}amelia_events_periods 
					WHERE eventId = %d AND periodStart = %s AND periodEnd = %s",
					$event->id, $period_start, $period_end
				));

				if ($exists) {
					\FPR\Helpers\Logger::log("[Saisons] Période déjà existante pour le " . $current_date->format('Y-m-d') . ", ignorée");
				} else {
					// Ajouter à la liste des périodes à insérer
					$periods_to_insert[] = [
						'eventId' => $event->id,
						'periodStart' => $period_start,
						'periodEnd' => $period_end
					];
				}

				// Passer à la semaine suivante
				$current_date->modify('+7 days');
			}

			// Insérer toutes les périodes en une seule requête pour optimiser
			if (!empty($periods_to_insert)) {
				$values = [];
				$placeholders = [];

				foreach ($periods_to_insert as $period) {
					$placeholders[] = "(%d, %s, %s)";
					$values[] = $period['eventId'];
					$values[] = $period['periodStart'];
					$values[] = $period['periodEnd'];
				}

				$query = $wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}amelia_events_periods (eventId, periodStart, periodEnd) VALUES " . 
					implode(', ', $placeholders),
					$values
				);

				$result = $wpdb->query($query);

				if ($result) {
					$periods_created = count($periods_to_insert);
					\FPR\Helpers\Logger::log("[Saisons] $periods_created périodes créées pour l'événement #" . $event->id);
				} else {
					\FPR\Helpers\Logger::log("[Saisons] ERREUR: Échec de création des périodes pour l'événement #" . $event->id);
				}
			} else {
				\FPR\Helpers\Logger::log("[Saisons] Aucune nouvelle période à créer pour l'événement #" . $event->id);
			}
		}

		\FPR\Helpers\Logger::log("[Saisons] Fin de la création des périodes pour le tag: '$tag'");
	}
}
