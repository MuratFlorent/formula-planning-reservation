<?php

namespace FPR\Modules;

use FPR\Helpers\ProductMapper;

class WooCommerce {
 public static function init() {
 	add_filter('woocommerce_add_cart_item_data', [__CLASS__, 'inject_selected_courses'], 10, 2);
 	// Removed display_courses_in_cart hook to avoid conflict with WooCommerceHandler
 	// add_filter('woocommerce_get_item_data', [__CLASS__, 'display_courses_in_cart'], 10, 2);
 	add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'add_courses_to_order'], 10, 4);
 	add_action('template_redirect', [__CLASS__, 'redirect_default_cart_page']);
 }

	public static function inject_selected_courses($cart_item_data, $product_id) {
		if (!WC()->session) return $cart_item_data;

		$courses = json_decode(WC()->session->get('fpr_selected_courses', '[]'), true);

		if (is_array($courses)) {
			foreach ($courses as $index => $course) {
				$cart_item_data['event_details_' . ($index + 1)] = self::format_course($course);
			}
		}

		$cart_item_data['unique_key'] = md5(microtime() . rand());
		return $cart_item_data;
	}

	public static function display_courses_in_cart($item_data, $cart_item) {
		for ($i = 1; $i <= 10; $i++) {
			$key = 'event_details_' . $i;
			if (!empty($cart_item[$key])) {
				$item_data[] = [
					'name'  => 'Cours sélectionné ' . $i,
					'value' => $cart_item[$key],
				];
			}
		}
		return $item_data;
	}

	public static function add_courses_to_order($item, $cart_item_key, $values, $order) {
		for ($i = 1; $i <= 10; $i++) {
			$key = 'event_details_' . $i;
			if (!empty($values[$key])) {
				$item->add_meta_data('Cours sélectionné ' . $i, $values[$key], true);
			}
		}
	}

	private static function format_course($course) {
		// Affichage propre dans le panier
		$parts = [];
		if (!empty($course['title'])) $parts[] = $course['title'];
		if (!empty($course['time'])) $parts[] = $course['time'];
		if (!empty($course['duration'])) $parts[] = $course['duration'];
		if (!empty($course['instructor'])) $parts[] = 'avec ' . $course['instructor'];
		return implode(' | ', $parts);
	}

	public static function redirect_default_cart_page() {
		if (is_cart() && !is_page('mon-panier')) {
			wp_redirect(home_url('/mon-panier/'));
			exit;
		}
	}

}
