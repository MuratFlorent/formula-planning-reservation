<?php

namespace FPR\Modules;

if (!defined('ABSPATH')) exit;

class WooCommerceHandler {
	public static function init() {
		// Injecte les données de session dans le panier
		add_filter('woocommerce_add_cart_item_data', [self::class, 'inject_course_data'], 10, 3);

		// Affiche les cours dans le récap du panier
		add_filter('woocommerce_get_item_data', [self::class, 'display_course_data'], 10, 2);
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
}
