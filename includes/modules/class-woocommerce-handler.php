<?php

namespace FPR\Modules;

if (!defined('ABSPATH')) exit;

use FPR\Helpers\Logger;

class WooCommerceHandler {
	public static function init() {
		// Injecte les données de session dans le panier
		add_filter('woocommerce_add_cart_item_data', [self::class, 'inject_course_data'], 10, 3);

		// Affiche les cours dans le récap du panier
		add_filter('woocommerce_get_item_data', [self::class, 'display_course_data'], 10, 2);

		// Hook à la validation d'une commande
		add_action('woocommerce_order_status_changed', [self::class, 'handle_completed_order'], 10, 4);
	}

	public static function inject_course_data($cart_item_data, $product_id, $variation_id) {
		if (WC()->session) {
			$stored = WC()->session->get('fpr_selected_courses');
			if ($stored) {
				$courses = json_decode($stored, true);
				if (is_array($courses)) {
					$cart_item_data['fpr_selected_courses'] = $courses;
				}
			}
		}
		return $cart_item_data;
	}

	public static function display_course_data($item_data, $cart_item) {
		if (!empty($cart_item['fpr_selected_courses'])) {
			foreach ($cart_item['fpr_selected_courses'] as $i => $course) {
				$item_data[] = [
					'name'  => 'Cours ' . ($i + 1),
					'value' => $course['title'] . ' – ' . $course['time'] . ' (' . $course['duration'] . ') avec ' . $course['instructor'],
				];
			}
		}
		return $item_data;
	}

	public static function handle_completed_order($order_id, $old_status, $new_status, $order) {
		if (!in_array($new_status, ['processing', 'completed'])) return;

		Logger::log("[WooToAmelia] Order #$order_id status changed to $new_status");

		foreach ($order->get_items() as $item) {
			foreach ($item->get_meta_data() as $meta) {
				if (strpos($meta->key, 'Cours') !== false) {
					self::register_in_amelia(
						$order->get_billing_first_name(),
						$order->get_billing_last_name(),
						$order->get_billing_email(),
						$order->get_billing_phone(),
						$meta->value,
						$item->get_name(),
						$order->get_total()
					);
				}
			}
		}
	}

	private static function register_in_amelia($first, $last, $email, $phone, $event_name, $formula, $amount) {
		Logger::log("[Amelia] Registering: $first $last / $email / $event_name");

		$event_id = self::get_event_id_by_name($event_name);
		if (!$event_id) {
			Logger::log("[Amelia ERROR] Event not found for '$event_name'");
			return;
		}

		$api_url = admin_url('admin-ajax.php?action=wpamelia_api&call=/api/v1/bookings');
		$data = [
			"type" => "event",
			"bookings" => [[
				"customer" => [
					"email" => $email,
					"firstName" => $first,
					"lastName" => $last,
					"phone" => $phone,
					"countryPhoneIso" => "fr"
				],
				"customFields" => ["1" => ["label" => "Formule", "value" => $formula]],
				"persons" => 1
			]],
			"payment" => ["amount" => $amount, "gateway" => "onSite", "currency" => "EUR"],
			"eventId" => $event_id
		];

		$response = wp_remote_post($api_url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'Amelia' => defined('AMELIA_API_TOKEN') ? AMELIA_API_TOKEN : ''
			],
			'body' => json_encode($data)
		]);

		$body = wp_remote_retrieve_body($response);
		Logger::log("[Amelia API] Response (" . wp_remote_retrieve_response_code($response) . "): $body");
	}

	private static function get_event_id_by_name($event_name) {
		global $wpdb;
		$split = explode(' - ', $event_name);
		$name = trim($split[0]);

		return $wpdb->get_var($wpdb->prepare(
			"SELECT e.id FROM {$wpdb->prefix}amelia_events e
			 JOIN {$wpdb->prefix}amelia_events_tags et ON e.id = et.eventId
			 WHERE e.name = %s AND et.name = %s LIMIT 1",
			$name, 'cours formule basique'
		));
	}
}
