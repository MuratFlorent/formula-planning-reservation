<?php
namespace FPR\Modules;

class WooToAmelia {
	public static function init() {
		add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_order'], 10, 4);
	}

	public static function handle_order($order_id, $old_status, $new_status, $order) {
		if (!in_array($new_status, ['processing', 'completed'])) return;

		\FPR\Helpers\Logger::log("[WooToAmelia] Order #$order_id status changed to $new_status");

		$first = $order->get_billing_first_name();
		$last = $order->get_billing_last_name();
		$email = $order->get_billing_email();
		$phone = $order->get_billing_phone();
		$amount = $order->get_total();

		foreach ($order->get_items() as $item) {
			foreach ($item->get_meta_data() as $meta) {
				if (strpos($meta->key, 'Cours sélectionné') !== false) {
					self::register_in_amelia($first, $last, $email, $phone, $meta->value, $item->get_name(), $amount);
				}
			}
		}
	}

	public static function register_in_amelia($first, $last, $email, $phone, $event_name, $formula, $amount) {
		\FPR\Helpers\Logger::log("[Amelia] Registering: $first $last / $email / $event_name");

		$event_id = self::get_event_id_by_name($event_name);
		if (!$event_id) return \FPR\Helpers\Logger::log("[Amelia] Event not found: $event_name");

		$booking_id = self::get_event_booking_id($event_id);
		if (!$booking_id) return \FPR\Helpers\Logger::log("[Amelia] Booking ID not found for event #$event_id");

		$data = [
			"type" => "event",
			"eventId" => $event_id,
			"bookings" => [[
				"customer" => [
					"email" => $email,
					"firstName" => $first,
					"lastName" => $last,
					"phone" => $phone,
					"countryPhoneIso" => "fr"
				],
				"customFields" => [
					"1" => ["label" => "Formules(cours choisis)", "value" => $formula, "type" => "text"]
				],
				"persons" => 1
			]],
			"payment" => ["amount" => $amount, "gateway" => "onSite", "currency" => "EUR"],
			"recaptcha" => false,
			"locale" => "fr_FR",
			"timeZone" => "Europe/Paris"
		];

		$response = wp_remote_post(admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/bookings'), [
			'body'    => json_encode($data),
			'headers' => [
				'Content-Type' => 'application/json',
				'Amelia' => defined('AMELIA_API_TOKEN') ? AMELIA_API_TOKEN : '',
			],
		]);

		if (is_wp_error($response)) {
			\FPR\Helpers\Logger::log('[Amelia API Error] ' . $response->get_error_message());
		} else {
			$status = wp_remote_retrieve_response_code($response);
			$body = wp_remote_retrieve_body($response);
			\FPR\Helpers\Logger::log("[Amelia API] Response ($status): $body");
		}
	}

	private static function get_event_id_by_name($event_name, $tag = 'cours formule basique') {
		global $wpdb;
		$short_name = explode(' - ', $event_name)[0];
		return $wpdb->get_var($wpdb->prepare("SELECT e.id FROM {$wpdb->prefix}amelia_events e
			INNER JOIN {$wpdb->prefix}amelia_events_tags et ON e.id = et.eventId
			WHERE e.name = %s AND et.name = %s", $short_name, $tag));
	}

	private static function get_event_booking_id($event_id) {
		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}amelia_events_periods WHERE eventId = %d LIMIT 1", $event_id));
	}
}
