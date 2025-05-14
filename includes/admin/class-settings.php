<?php
namespace FPR\Admin;

require_once FPR_PLUGIN_DIR . 'includes/admin/class-testsimulator.php';

class Settings {
	public static function init() {
		add_action('admin_menu', [__CLASS__, 'add_menu']);
		add_action('admin_init', [__CLASS__, 'register_settings']);
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
		register_setting('fpr_settings_group', 'fpr_aw_enabled');
		register_setting('fpr_settings_group', 'fpr_aw_threshold');
		register_setting('fpr_settings_group', 'fpr_aw_keyword');
		register_setting('fpr_settings_group', 'fpr_excluded_classes');
		register_setting('fpr_settings_group', 'fpr_excluded_courses');
		register_setting('fpr_settings_group', 'fpr_return_button_text');
		register_setting('fpr_settings_group', 'fpr_return_button_url');
		register_setting('fpr_settings_group', 'fpr_enable_return_button');
	}

	public static function render_settings_page() {
		$active_tab = $_GET['tab'] ?? 'general';
		?>
        <div class="wrap">
            <h1>Réglages du plugin Formule Planning Reservation</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=fpr-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">Paramètres généraux</a>
                <a href="?page=fpr-settings&tab=debug" class="nav-tab <?php echo $active_tab === 'debug' ? 'nav-tab-active' : ''; ?>">Tests & Debug</a>
            </h2>

			<?php if ($active_tab === 'general') : ?>
                <form method="post" action="options.php">
					<?php settings_fields('fpr_settings_group'); ?>
                    <table class="form-table">
                        <tr><th>Terme dans le nom du produit</th><td><input type="text" name="fpr_product_keyword" value="<?= esc_attr(get_option('fpr_product_keyword', 'cours/sem')) ?>" class="regular-text" /></td></tr>
                        <tr><th>Stratégie de correspondance</th><td><select name="fpr_match_strategy">
									<?php $strategies = ['contains'=>'Contient','starts_with'=>'Commence par','ends_with'=>'Finit par','equals'=>'Égal'];
									foreach ($strategies as $key=>$label) echo "<option value='$key'" . selected(get_option('fpr_match_strategy'), $key, false) . ">$label</option>"; ?>
                                </select></td></tr>
                        <tr><th>Nombre de cours/formules possibles</th><td><input type="text" name="fpr_course_counts" value="<?= esc_attr(get_option('fpr_course_counts', '1,2,3,4')) ?>" class="regular-text" /></td></tr>
                        <tr><th>Activer formule "à volonté"</th><td><input type="checkbox" name="fpr_aw_enabled" value="1" <?= checked(get_option('fpr_aw_enabled'), 1, false) ?> /></td></tr>
                        <tr><th>Seuil pour "à volonté"</th><td><input type="number" name="fpr_aw_threshold" value="<?= esc_attr(get_option('fpr_aw_threshold', 5)) ?>" class="small-text" /></td></tr>
                        <tr><th>Mot-clé "à volonté"</th><td><input type="text" name="fpr_aw_keyword" value="<?= esc_attr(get_option('fpr_aw_keyword', 'à volonté')) ?>" class="regular-text" /></td></tr>
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
            endif; ?>
        </div>
        <style>
            .fpr-debug-table {
                border-collapse: collapse;
                width: 100%;
            }
            .fpr-debug-table th, .fpr-debug-table td {
                border: 1px solid #ccc;
                padding: 8px;
                text-align: left;
            }
            .fpr-debug-table th {
                background: #f9f9f9;
                font-weight: bold;
            }
        </style>

		<?php
	}
}
