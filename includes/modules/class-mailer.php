<?php

namespace FPR\Modules;

class Mailer {
	public static function init() {
		add_action('woocommerce_order_status_completed', [__CLASS__, 'trigger_after_payment']);
	}

	public static function trigger_after_payment($order_id) {
		$order = wc_get_order($order_id);
		if (!$order) return;

		$prenom = $order->get_billing_first_name();
		$nom = $order->get_billing_last_name();
		$email = $order->get_billing_email();
		$price = $order->get_total();
		$formula = $order->get_items() ? array_values($order->get_items())[0]->get_name() : '';
		$order_number = $order->get_order_number();

		$events = [];
		foreach ($order->get_items() as $item) {
			foreach ($item->get_meta_data() as $meta) {
				if (strpos($meta->key, 'Cours') !== false) {
					$events[] = $meta->value;
				}
			}
		}

		self::notify_client($email, $prenom, $nom, $events, $formula, $price, $order_number);
		self::notify_admin($prenom, $nom, $events, $formula, $price, $order_number);
	}

	public static function send_email_with_template(string $to, string $subject, string $template, array $data = []) {
		$template_path = FPR_PLUGIN_DIR . 'templates/emails/' . $template;
		if (!file_exists($template_path)) return;

		extract($data);
		ob_start();
		include $template_path;
		$message = ob_get_clean();

		$headers = ['Content-Type: text/html; charset=UTF-8'];

		wp_mail($to, $subject, $message, $headers);

		\FPR\Helpers\Logger::log("Email envoyé à $to avec sujet : $subject");
	}

	public static function notify_client($email, $prenom, $nom, $events, $formula, $price, $order_number) {
		self::send_email_with_template($email, 'Confirmation de votre inscription', 'customer-booking-confirmation.php', [
			'firstName' => $prenom,
			'lastName' => $nom,
			'events' => $events,
			'formula' => $formula,
			'price' => $price,
			'order_number' => $order_number
		]);
	}

 public static function notify_admin($prenom, $nom, $events, $formula, $price, $order_number) {
		// Récupérer l'email de l'administrateur avec une adresse de secours
		$admin_email = get_option('admin_email');

		// Si l'email admin n'est pas défini, utiliser une adresse par défaut
		if (empty($admin_email) || !is_email($admin_email)) {
			$admin_email = 'contact@choreame.fr';
			\FPR\Helpers\Logger::log("[Mailer] ⚠️ Email admin non défini ou invalide, utilisation de l'adresse par défaut: $admin_email");
		} else {
			\FPR\Helpers\Logger::log("[Mailer] ✅ Utilisation de l'email admin: $admin_email");
		}

		// Récupérer les informations du plan de paiement si disponible
		$payment_plan_info = '';
		$payment_plan_id = get_post_meta($order_number, '_fpr_selected_payment_plan', true);
		if (!empty($payment_plan_id)) {
			global $wpdb;
			$payment_plan = $wpdb->get_row($wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
				$payment_plan_id
			));

			if ($payment_plan) {
				$term_text = !empty($payment_plan->term) ? '/' . $payment_plan->term : '';
				if (empty($term_text)) {
					$frequency_label = [
						'hourly' => '/heure',
						'daily' => '/jour',
						'weekly' => '/sem',
						'monthly' => '/mois',
						'quarterly' => '/trim',
						'annual' => '/an'
					];
					$term_text = isset($frequency_label[$payment_plan->frequency]) ? $frequency_label[$payment_plan->frequency] : '';
				}
				$payment_plan_info = $payment_plan->name . ' (' . $payment_plan->installments . ' versements' . ($term_text ? ' ' . $term_text : '') . ')';
			}
		}

		// Récupérer les informations de contact du client
		$order = wc_get_order($order_number);
		$email = '';
		$phone = '';
		if ($order) {
			$email = $order->get_billing_email();
			$phone = $order->get_billing_phone();
		}

		// Envoyer la notification de réservation standard
		self::send_email_with_template($admin_email, 'Nouvelle réservation', 'admin-booking-notification.php', [
			'firstName' => $prenom,
			'lastName' => $nom,
			'email' => $email,
			'phone' => $phone,
			'events' => $events,
			'formula' => $formula,
			'price' => $price,
			'order_number' => $order_number,
			'payment_plan_info' => $payment_plan_info,
			'order_date' => date_i18n('d/m/Y à H:i')
		]);

		// Envoyer la notification détaillée avec les informations de formule et cours
		self::send_email_with_template($admin_email, 'Détails de formule et cours - ' . $prenom . ' ' . $nom, 'admin-formula-notification.php', [
			'firstName' => $prenom,
			'lastName' => $nom,
			'email' => $email,
			'phone' => $phone,
			'events' => $events,
			'formula' => $formula,
			'price' => $price,
			'order_number' => $order_number,
			'payment_plan_info' => $payment_plan_info,
			'order_date' => date_i18n('d/m/Y à H:i')
		]);
	}
}
