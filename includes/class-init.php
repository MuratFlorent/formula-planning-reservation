<?php

namespace FPR;

use FPR\Helpers\Logger;

class Init {
	public static function register() {
		// Chargement des helpers avant tout
		self::load_helpers();

		// Chargement des modules
		add_action('plugins_loaded', [__CLASS__, 'load_modules']);

		// Chargement des assets selon la page
		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

		// Admin settings
		require_once FPR_PLUGIN_DIR . 'includes/admin/class-settings.php';
		\FPR\Admin\Settings::init();

		// Ajout de classes personnalisées au <body>
		add_filter('body_class', [__CLASS__, 'add_fpr_body_classes']);

		add_filter('woocommerce_return_to_shop_redirect', function() {
			return home_url('/planning');
		});

		add_filter('woocommerce_return_to_shop_text', function() {
			return 'Retour au planning';
		});

		require_once FPR_PLUGIN_DIR . 'includes/modules/class-woocommerce-handler.php';
		\FPR\Modules\WooCommerceHandler::init();

		add_action('woocommerce_before_cart', function () {
			if (!get_option('fpr_enable_return_button')) return;

			$text = get_option('fpr_return_button_text', '← Retour au planning');
			$url  = esc_url(get_option('fpr_return_button_url', '/planning'));

			echo '<a href="' . $url . '" class="button button-secondary" style="margin-bottom: 20px; display: inline-block;">' . esc_html($text) . '</a>';
		});

		require_once FPR_PLUGIN_DIR . 'includes/admin/class-debugcheckup.php';

	}

	public static function load_modules() {
		$modules = [
			'form-handler',
			'calendar-handler',
			'woocommerce',
			'amelia',
			'mailer',
			'wootoamelia' // ✅ Ajout ici
		];

		foreach ($modules as $module) {
			$file = FPR_PLUGIN_DIR . 'includes/modules/class-' . $module . '.php';
			if (file_exists($file)) {
				require_once $file;
				$class = '\\FPR\\Modules\\' . str_replace('-', '', ucwords($module, '-'));
				if (class_exists($class) && method_exists($class, 'init')) {
					$class::init();
				}
			}
		}

		// Shortcodes
		require_once FPR_PLUGIN_DIR . 'shortcodes/fpr-planning.php';
	}

	public static function enqueue_assets() {
		$current_page = trim($_SERVER['REQUEST_URI'], '/');

		if ($current_page === 'planning') {
			wp_enqueue_script(
				'fpr-calendar-js',
				FPR_PLUGIN_URL . 'assets/js/calendar.js',
				['jquery'],
				null,
				true
			);

			// Préparation des exclusions
			$excluded_courses_raw = get_option('fpr_excluded_classes', '');
			if (is_array($excluded_courses_raw)) {
				$excluded_courses_raw = implode("\n", $excluded_courses_raw); // sécurité si l’option est accidentellement un array
			}

			$excluded_courses = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $excluded_courses_raw)));

			Logger::log('🧪 fpr_excluded_courses_raw: ' . $excluded_courses_raw);
			Logger::log('📋 fpr_excluded_courses final: ' . print_r($excluded_courses, true));


			wp_localize_script('fpr-calendar-js', 'fprAjax', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'excluded_courses' => array_map('strtolower', $excluded_courses),
			]);

			wp_enqueue_style(
				'fpr-calendar-css',
				FPR_PLUGIN_URL . 'assets/css/calendar.css'
			);
		}

		if ($current_page === 'mon-panier') {
			wp_enqueue_script(
				'fpr-cart-js',
				FPR_PLUGIN_URL . 'assets/js/cart.js',
				['jquery'],
				null,
				true
			);

			wp_enqueue_style(
				'fpr-cart-css',
				FPR_PLUGIN_URL . 'assets/css/cart.css'
			);
		}
	}

	// Helpers (chargés tôt)
	private static function load_helpers() {
		require_once FPR_PLUGIN_DIR . 'helpers/product-mapper.php';
		require_once FPR_PLUGIN_DIR . 'helpers/class-body-helper.php';
		require_once FPR_PLUGIN_DIR . 'helpers/function-log.php'; // <== ajoute cette ligne
	}


	// Création auto de la page panier
	public static function create_cart_page_if_needed() {
		$page = get_page_by_path('mon-panier');
		if (!$page) {
			$page_id = wp_insert_post([
				'post_title'   => 'Mon panier',
				'post_name'    => 'mon-panier',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[woocommerce_cart]<div id="fpr-selection-preview"></div>',
			]);

			if ($page_id) {
				update_post_meta($page_id, '_wp_page_template', 'default');
			}
		}
	}

	// Ajout de classes body (planning, mon-panier...)
	public static function add_fpr_body_classes($classes) {
		if (is_page('planning')) {
			$classes[] = 'fpr-page-planning';
		}
		if (is_page('mon-panier')) {
			$classes[] = 'fpr-page-cart';
		}
		return $classes;
	}
}
