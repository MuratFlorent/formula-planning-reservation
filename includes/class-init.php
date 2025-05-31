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

		require_once FPR_PLUGIN_DIR . 'includes/admin/class-saisons.php';
		\FPR\Admin\Saisons::init();

		require_once FPR_PLUGIN_DIR . 'includes/admin/class-payment-plans.php';
		\FPR\Admin\PaymentPlans::init();

		require_once FPR_PLUGIN_DIR . 'includes/admin/class-product-payment-plans.php';
		\FPR\Admin\ProductPaymentPlans::init();

		require_once FPR_PLUGIN_DIR . 'includes/admin/class-orders.php';
		\FPR\Admin\Orders::init();

		require_once FPR_PLUGIN_DIR . 'includes/admin/class-subscribers.php';
		\FPR\Admin\Subscribers::init();

		require_once FPR_PLUGIN_DIR . 'includes/admin/class-migration.php';
		\FPR\Admin\Migration::init();


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
		\FPR\Admin\DebugCheckup::init();

	}

	public static function load_modules() {
		$modules = [
			'form-handler',
			'calendar-handler',
			'woocommerce',
			'amelia',
			'mailer',
			'woocommerce-handler',
			'wootoamelia',
			'emailhandler',
			'stripe-handler',
			'user-registration',
			'fpr-amelia',
			'fpr-user',
			'invoice-handler',
			'course-handler',
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

			// Préparation des exclusions - utiliser uniquement fpr_excluded_courses
			$excluded_courses_raw = get_option('fpr_excluded_courses', '');
			if (is_array($excluded_courses_raw)) {
				$excluded_courses_raw = implode("\n", $excluded_courses_raw); // sécurité si l’option est accidentellement un array
			}

			$excluded_courses = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $excluded_courses_raw)));

			Logger::log('🧪 fpr_excluded_courses_raw: ' . $excluded_courses_raw);
			Logger::log('📋 fpr_excluded_courses final: ' . print_r($excluded_courses, true));

			wp_localize_script('fpr-calendar-js', 'fprAjax', [
				'ajaxurl' => admin_url('admin-ajax.php'),
				'excluded_courses' => array_map('strtolower', $excluded_courses),
			]);

			wp_enqueue_style(
				'fpr-calendar-css',
				FPR_PLUGIN_URL . 'assets/css/calendar.css'
			);
		}

		if ($current_page === 'mon-panier') {
			// We're using cart-loader.js instead of cart.js to avoid duplication
			// of course information display

			wp_enqueue_style(
				'fpr-cart-css',
				FPR_PLUGIN_URL . 'assets/css/cart.css'
			);
		}

		// Charger les assets de transition et d'amélioration UI sur toutes les pages du site
		wp_enqueue_script(
			'fpr-cart-loader-js',
			FPR_PLUGIN_URL . 'assets/js/cart-loader.js',
			['jquery'],
			null,
			true
		);

		// Localiser le script pour rendre ajaxurl et nonce disponibles
		$cart_loader_data = [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('fpr-save-payment-plan')
		];

		// Si on a des cours exclus, les ajouter aussi au cart loader
		if (isset($excluded_courses) && !empty($excluded_courses)) {
			$cart_loader_data['excluded_courses'] = array_map('strtolower', $excluded_courses);
		}

		wp_localize_script('fpr-cart-loader-js', 'fprAjax', $cart_loader_data);

		wp_enqueue_style(
			'fpr-cart-loader-css',
			FPR_PLUGIN_URL . 'assets/css/cart-loader.css'
		);

		// Charger des styles spécifiques pour la page de checkout
		if (is_checkout()) {
			// Charger le fichier CSS dédié pour la page de commande
			wp_enqueue_style(
				'fpr-checkout-css',
				FPR_PLUGIN_URL . 'assets/css/checkout.css'
			);
		}

		// Charger des styles spécifiques pour la page panier (plans de paiement)
		if (is_cart()) {
			wp_enqueue_style(
				'fpr-payment-plans-css',
				FPR_PLUGIN_URL . 'assets/css/payment-plans.css'
			);
		}

		// Charger des styles spécifiques pour la page de confirmation de commande
		if (is_wc_endpoint_url('order-received')) {
			wp_add_inline_style('fpr-cart-loader-css', '
				.woocommerce-order {
					animation: fpr-fade-in 0.8s ease-in-out;
				}
				.woocommerce-order-overview {
					background-color: #f9f9f9;
					padding: 20px;
					border-radius: 5px;
					box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
				}
				.woocommerce-order-details {
					margin-top: 30px;
				}
				.woocommerce-thankyou-order-received {
					font-size: 24px;
					color: #3498db;
					margin-bottom: 20px;
				}
			');
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
				'post_content' => '[woocommerce_cart]',
			]);

			if ($page_id) {
				update_post_meta($page_id, '_wp_page_template', 'default');
			}
		}
	}

	/**
	 * Exécute les scripts SQL d'installation du plugin
	 */
	public static function run_installation_sql() {
		global $wpdb;

		// Charger les fichiers SQL
		$sql_files = [
			'create_payment_plans_table.sql',
			'create_saisons_table.sql',
			'create_customer_subscriptions_table.sql',
			'create_product_payment_plans.sql',
			'create_fpr_events_table.sql',
			'create_fpr_events_tags_table.sql',
			'create_fpr_events_periods_table.sql',
			'create_fpr_customers_table.sql',
			'create_fpr_customer_bookings_table.sql',
			'create_fpr_users_table.sql',
			'create_fpr_invoices_table.sql',
			'create_fpr_courses_table.sql',
			'create_fpr_subscription_courses_table.sql'
		];

		foreach ($sql_files as $sql_file) {
			$sql_path = FPR_PLUGIN_DIR . 'sql/' . $sql_file;

			if (file_exists($sql_path)) {
				// Lire le contenu du fichier SQL
				$sql_content = file_get_contents($sql_path);

				// Remplacer les variables
				$sql_content = str_replace('{prefix}', $wpdb->prefix, $sql_content);
				$sql_content = str_replace('{charset_collate}', $wpdb->get_charset_collate(), $sql_content);

				// Exécuter les requêtes SQL
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql_content);

				\FPR\Helpers\Logger::log("[Installation] ✅ Script SQL exécuté: $sql_file");
			} else {
				\FPR\Helpers\Logger::log("[Installation] ❌ Fichier SQL introuvable: $sql_file");
			}
		}

		\FPR\Helpers\Logger::log("[Installation] ✅ Installation SQL terminée");
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
