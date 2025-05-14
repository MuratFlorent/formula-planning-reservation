<?php
namespace FPR\Admin;

class Settings {
	public static function init() {
		add_action('admin_menu', [__CLASS__, 'add_settings_page']);
		add_action('admin_init', [__CLASS__, 'register_settings']);
	}

	public static function add_settings_page() {
		add_options_page(
			'Réglages Formule Planning',
			'Formule Planning',
			'manage_options',
			'fpr-settings',
			[__CLASS__, 'render_settings_page']
		);
	}

	public static function register_settings() {
		register_setting('fpr_settings_group', 'fpr_match_type');
		register_setting('fpr_settings_group', 'fpr_match_keyword');

		add_settings_section('fpr_section_main', '', null, 'fpr-settings');

		add_settings_field('fpr_match_type', 'Type de correspondance', [__CLASS__, 'render_type_field'], 'fpr-settings', 'fpr_section_main');
		add_settings_field('fpr_match_keyword', 'Mot-clé à rechercher', [__CLASS__, 'render_keyword_field'], 'fpr-settings', 'fpr_section_main');
	}

	public static function render_settings_page() {
		?>
		<div class="wrap">
			<h1>Réglages Formule Planning Reservation</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields('fpr_settings_group');
				do_settings_sections('fpr-settings');
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public static function render_type_field() {
		$value = get_option('fpr_match_type', 'ends_with');
		?>
		<select name="fpr_match_type">
			<option value="starts_with" <?php selected($value, 'starts_with'); ?>>Commence par</option>
			<option value="contains" <?php selected($value, 'contains'); ?>>Contient</option>
			<option value="ends_with" <?php selected($value, 'ends_with'); ?>>Finit par</option>
		</select>
		<?php
	}

	public static function render_keyword_field() {
		$value = get_option('fpr_match_keyword', 'cours/sem');
		?>
		<input type="text" name="fpr_match_keyword" value="<?php echo esc_attr($value); ?>" />
		<?php
	}
}
