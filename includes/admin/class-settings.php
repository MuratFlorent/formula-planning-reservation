<?php
namespace FPR\Admin;

require_once FPR_PLUGIN_DIR . 'includes/admin/class-testsimulator.php';
require_once FPR_PLUGIN_DIR . 'includes/admin/class-saisons.php';
require_once FPR_PLUGIN_DIR . 'includes/admin/class-orders.php';
require_once FPR_PLUGIN_DIR . 'includes/admin/class-subscribers.php';


class Settings {
	public static function init() {
		add_action('admin_menu', [__CLASS__, 'add_menu']);
		add_action('admin_init', [__CLASS__, 'register_settings']);
	}

	/**
     * Enqueue scripts and styles for the settings page
     */
    public static function enqueue_scripts_and_styles() {
        // Enqueue common admin styles
        wp_enqueue_style('fpr-admin', FPR_PLUGIN_URL . 'assets/css/admin.css', [], FPR_VERSION);

        // Add dashicons for the icons
        wp_enqueue_style('dashicons');
    }

	public static function add_menu() {
		add_options_page(
			'Formules Planning – Réglages',
			'Formules Planning',
			'manage_options',
			'fpr-settings',
			[__CLASS__, 'render_settings_page']
		);
	}

	public static function register_settings() {
		register_setting('fpr_settings_group', 'fpr_product_keyword');
		register_setting('fpr_settings_group', 'fpr_match_strategy');
		register_setting('fpr_settings_group', 'fpr_course_counts');
		register_setting('fpr_settings_group', 'fpr_default_course_duration');
		register_setting('fpr_settings_group', 'fpr_aw_enabled');
		register_setting('fpr_settings_group', 'fpr_aw_threshold');
		register_setting('fpr_settings_group', 'fpr_aw_keyword');
		register_setting('fpr_settings_group', 'fpr_excluded_courses');
		register_setting('fpr_settings_group', 'fpr_return_button_text');
		register_setting('fpr_settings_group', 'fpr_return_button_url');
		register_setting('fpr_settings_group', 'fpr_enable_return_button');
		register_setting('fpr_settings_group', 'fpr_custom_formulas');
	}

	public static function render_settings_page() {
		$active_tab = $_GET['tab'] ?? 'general';

		// Enqueue scripts and styles for the settings page
        self::enqueue_scripts_and_styles();
		?>
        <div class="wrap">
            <h1>Réglages du plugin Formule Planning Reservation</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=fpr-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">Paramètres généraux</a>
                <a href="?page=fpr-settings&tab=debug" class="nav-tab <?php echo $active_tab === 'debug' ? 'nav-tab-active' : ''; ?>">Tests & Debug</a>
                <a href="?page=fpr-settings&tab=seasons" class="nav-tab <?php echo $active_tab === 'seasons' ? 'nav-tab-active' : ''; ?>">Saisons & Événements</a>
                <a href="?page=fpr-settings&tab=payment_plans" class="nav-tab <?php echo $active_tab === 'payment_plans' ? 'nav-tab-active' : ''; ?>">Plans de paiement</a>
                <a href="?page=fpr-settings&tab=orders" class="nav-tab <?php echo $active_tab === 'orders' ? 'nav-tab-active' : ''; ?>">Abonnés</a>
            </h2>


            <?php if ($active_tab === 'general') : ?>
                <form method="post" action="options.php" class="fpr-form">
					<?php settings_fields('fpr_settings_group'); ?>
                    <table class="form-table">
                        <tr><th>Terme dans le nom du produit</th><td><input type="text" name="fpr_product_keyword" value="<?= esc_attr(get_option('fpr_product_keyword', 'cours/sem')) ?>" class="regular-text" /></td></tr>
                        <tr><th>Stratégie de correspondance</th><td><select name="fpr_match_strategy">
									<?php $strategies = ['contains'=>'Contient','starts_with'=>'Commence par','ends_with'=>'Finit par','equals'=>'Égal'];
									foreach ($strategies as $key=>$label) echo "<option value='$key'" . selected(get_option('fpr_match_strategy'), $key, false) . ">$label</option>"; ?>
                                </select></td></tr>
                        <tr><th>Nombre de cours/formules possibles</th><td><input type="text" name="fpr_course_counts" value="<?= esc_attr(get_option('fpr_course_counts', '1,2,3,4')) ?>" class="regular-text" /></td></tr>
                        <tr><th>Durée par défaut des cours</th><td><input type="text" name="fpr_default_course_duration" value="<?= esc_attr(get_option('fpr_default_course_duration', '1h')) ?>" class="small-text" /> <span class="description">Durée par défaut pour les formules basiques (ex: 1h)</span></td></tr>
                        <tr><th>Activer formule "à volonté"</th><td><input type="checkbox" name="fpr_aw_enabled" value="1" <?= checked(get_option('fpr_aw_enabled'), 1, false) ?> /></td></tr>
                        <tr><th>Seuil pour "à volonté"</th><td><input type="number" name="fpr_aw_threshold" value="<?= esc_attr(get_option('fpr_aw_threshold', 5)) ?>" class="small-text" /></td></tr>
                        <tr><th>Mot-clé "à volonté"</th><td><input type="text" name="fpr_aw_keyword" value="<?= esc_attr(get_option('fpr_aw_keyword', 'à volonté')) ?>" class="regular-text" /></td></tr>
                        <tr>
                            <th>Formules personnalisées</th>
                            <td>
                                <div id="fpr-custom-formulas">
                                    <p class="description">Définissez des formules personnalisées pour des combinaisons spécifiques de cours.</p>
                                    <table class="widefat" id="fpr-custom-formulas-table">
                                        <thead>
                                            <tr>
                                                <th>Terme</th>
                                                <th>Stratégie</th>
                                                <th>Durée du cours</th>
                                                <th>Nombre de cours</th>
                                                <th>Durée secondaire</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $custom_formulas = get_option('fpr_custom_formulas', []);
                                            if (!empty($custom_formulas) && is_array($custom_formulas)) {
                                                foreach ($custom_formulas as $index => $formula) {
                                                    ?>
                                                    <tr class="fpr-custom-formula-row">
                                                        <td>
                                                            <input type="text" name="fpr_custom_formulas[<?php echo $index; ?>][term]" value="<?php echo esc_attr($formula['term']); ?>" class="regular-text" placeholder="ex: cours 1h15" />
                                                        </td>
                                                        <td>
                                                            <select name="fpr_custom_formulas[<?php echo $index; ?>][strategy]">
                                                                <?php
                                                                foreach ($strategies as $key => $label) {
                                                                    echo "<option value='$key'" . selected($formula['strategy'], $key, false) . ">$label</option>";
                                                                }
                                                                ?>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="text" name="fpr_custom_formulas[<?php echo $index; ?>][duration]" value="<?php echo esc_attr($formula['duration']); ?>" class="small-text" placeholder="ex: 1h15" />
                                                        </td>
                                                        <td>
                                                            <input type="number" name="fpr_custom_formulas[<?php echo $index; ?>][course_count]" value="<?php echo esc_attr(isset($formula['course_count']) ? $formula['course_count'] : ''); ?>" class="small-text" placeholder="ex: 2" min="1" />
                                                        </td>
                                                        <td>
                                                            <input type="text" name="fpr_custom_formulas[<?php echo $index; ?>][secondary_duration]" value="<?php echo esc_attr(isset($formula['secondary_duration']) ? $formula['secondary_duration'] : ''); ?>" class="small-text" placeholder="ex: 1h30" />
                                                        </td>
                                                        <td>
                                                            <button type="button" class="button button-secondary fpr-remove-formula">Supprimer</button>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                    <p>
                                        <button type="button" class="button button-secondary" id="fpr-add-formula">+ Ajouter une formule</button>
                                    </p>
                                </div>
                                <script>
                                    jQuery(document).ready(function($) {
                                        // Compteur pour les nouveaux champs
                                        let formulaCount = <?php echo !empty($custom_formulas) ? count($custom_formulas) : 0; ?>;

                                        // Ajouter une nouvelle formule
                                        $('#fpr-add-formula').on('click', function() {
                                            const newRow = `
                                                <tr class="fpr-custom-formula-row">
                                                    <td>
                                                        <input type="text" name="fpr_custom_formulas[${formulaCount}][term]" value="" class="regular-text" placeholder="ex: cours 1h15" />
                                                    </td>
                                                    <td>
                                                        <select name="fpr_custom_formulas[${formulaCount}][strategy]">
                                                            <?php
                                                            foreach ($strategies as $key => $label) {
                                                                echo "<option value='$key'>$label</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="fpr_custom_formulas[${formulaCount}][duration]" value="" class="small-text" placeholder="ex: 1h15" />
                                                    </td>
                                                    <td>
                                                        <input type="number" name="fpr_custom_formulas[${formulaCount}][course_count]" value="" class="small-text" placeholder="ex: 2" min="1" />
                                                    </td>
                                                    <td>
                                                        <input type="text" name="fpr_custom_formulas[${formulaCount}][secondary_duration]" value="" class="small-text" placeholder="ex: 1h30" />
                                                    </td>
                                                    <td>
                                                        <button type="button" class="button button-secondary fpr-remove-formula">Supprimer</button>
                                                    </td>
                                                </tr>
                                            `;
                                            $('#fpr-custom-formulas-table tbody').append(newRow);
                                            formulaCount++;
                                        });

                                        // Supprimer une formule
                                        $(document).on('click', '.fpr-remove-formula', function() {
                                            $(this).closest('tr').remove();
                                        });
                                    });
                                </script>
                                <style>
                                    #fpr-custom-formulas-table {
                                        margin-top: 10px;
                                        margin-bottom: 10px;
                                    }
                                    #fpr-custom-formulas-table th {
                                        padding: 8px;
                                        text-align: left;
                                    }
                                    #fpr-custom-formulas-table td {
                                        padding: 8px;
                                    }
                                    .fpr-custom-formula-row {
                                        background-color: #f9f9f9;
                                    }
                                    #fpr-add-formula {
                                        margin-top: 10px;
                                    }
                                </style>
                            </td>
                        </tr>
                        <tr><th>Exclure certains cours</th><td><textarea name="fpr_excluded_courses" rows="5" class="large-text"><?php echo esc_textarea(get_option('fpr_excluded_courses', '')); ?></textarea><p class="description">Un par ligne</p></td></tr>
                        <tr><th>Activer bouton retour panier</th><td><input type="checkbox" name="fpr_enable_return_button" value="1" <?= checked(get_option('fpr_enable_return_button'), 1, false) ?> /></td></tr>
                        <tr><th>Texte bouton retour</th><td><input type="text" name="fpr_return_button_text" value="<?= esc_attr(get_option('fpr_return_button_text', '← Retour au planning')) ?>" class="regular-text" /></td></tr>
                        <tr><th>URL page planning</th><td><input type="text" name="fpr_return_button_url" value="<?= esc_attr(get_option('fpr_return_button_url', '/planning')) ?>" class="regular-text" /></td></tr>
                    </table>
					<?php submit_button('Enregistrer les réglages'); ?>
                </form>
			<?php elseif ($active_tab === 'debug') : ?>
				<?php \FPR\Admin\TestSimulator::render();
                echo '<hr><h3>État actuel du système</h3>';
                 if (class_exists('\FPR\Admin\DebugCheckup')) {
                     \FPR\Admin\DebugCheckup::render();
                 } else {
                     echo '<p class="notice notice-error">Erreur : classe DebugCheckup introuvable.</p>';
                }
                ?>
              <?php elseif ($active_tab === 'seasons') : ?>
	        <?php \FPR\Admin\Saisons::render_page(); ?>
            <?php elseif ($active_tab === 'payment_plans') : ?>
	        <?php \FPR\Admin\PaymentPlans::render_page(); ?>
            <?php elseif ($active_tab === 'orders') : ?>
	        <?php \FPR\Admin\Subscribers::render_page(); ?>
            <?php endif; ?>
        </div>

		<?php
	}
}
