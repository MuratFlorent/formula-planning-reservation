<?php
// Plugin: Formula Planning Reservation
// Fichier: includes/modules/class-emailhandler.php

namespace FPR\Modules;

use WC_Emails;

if (!defined('ABSPATH')) exit;

class EmailHandler {

	public static function init() {
		add_action('woocommerce_email_order_details', [__CLASS__, 'add_courses_to_email'], 20, 4);
	}

	public static function add_courses_to_email($order, $sent_to_admin, $plain_text, $email) {
		if ($sent_to_admin) return; // pas pour l’admin

		foreach ($order->get_items() as $item) {
			$meta = $item->get_meta_data();
			$courses = [];
			foreach ($meta as $m) {
				if (strpos($m->key, 'Cours sélectionné') !== false) {
					$courses[] = $m->value;
				}
			}

			if (!empty($courses)) {
				echo '<h3 style="margin-top:30px;">🧘 Cours sélectionnés</h3><ul style="margin-bottom:30px;">';
				foreach ($courses as $c) echo '<li>' . esc_html($c) . '</li>';
				echo '</ul>';
			}
		}
	}
}
