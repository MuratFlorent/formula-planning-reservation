<?php

namespace FPR\Helpers;

class BodyHelper {

	/**
	 * Ajoute dynamiquement des classes au body en fonction de la page.
	 *
	 * @param array $classes
	 * @return array
	 */
	public static function add_classes($classes) {
		if (is_page('planning')) {
			$classes[] = 'fpr-page-planning';
		}

		if (is_page('mon-panier')) {
			$classes[] = 'fpr-page-cart';
		}

		return $classes;
	}
}
