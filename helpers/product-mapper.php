<?php
namespace FPR\Helpers;

// ✅ Fonctions globales (compatibilité PHP 7.4+)
if (!function_exists('str_starts_with')) {
	function str_starts_with($haystack, $needle) {
		return substr($haystack, 0, strlen($needle)) === $needle;
	}
}

if (!function_exists('str_ends_with')) {
	function str_ends_with($haystack, $needle) {
		return substr($haystack, -strlen($needle)) === $needle;
	}
}


class ProductMapper {
	public static function get_product_id_by_course_count($count) {
		$term     = strtolower(trim(get_option('fpr_product_keyword', '')));
		$strategy = get_option('fpr_match_strategy', 'ends_with');
		$raw_vals = get_option('fpr_course_counts', '');
		$counts   = array_map('trim', explode(',', $raw_vals));
		$counts   = array_filter($counts, 'is_numeric');

		Logger::log("📌 Terme utilisé pour la correspondance : $term");
		Logger::log("🧪 Valeurs brutes options : term=$term | strategy=$strategy | values=$raw_vals");
		Logger::log("🔍 Vérification produit pour $count cours | stratégie: $strategy | terme: $term | valeurs autorisées: " . implode(',', $counts));

		if (!in_array($count, $counts)) {
			Logger::log("⚠️ Nombre de cours non autorisé : $count");
			return null;
		}

		$products = wc_get_products(['limit' => -1, 'status' => 'publish']);
		Logger::log("📦 " . count($products) . " produits WooCommerce récupérés");

		$pattern = strtolower((string)$count);
		$target_string = $pattern . ' ' . $term;

		foreach ($products as $product) {
			$name = strtolower(trim($product->get_name()));
			Logger::log("➡️ Test du produit : \"$name\"");

			$match = false;
			switch ($strategy) {
				case 'equals':
					$match = ($name === $target_string);
					break;
				case 'starts_with':
					$match = str_starts_with($name, $target_string);
					break;
				case 'ends_with':
					$match = str_ends_with($name, $target_string);
					break;
				case 'contains':
					$match = strpos($name, $target_string) !== false;
					break;
				default:
					$match = false;
			}

			Logger::log("🔎 Match avec \"$target_string\" ? " . ($match ? "✅ OUI" : "❌ NON"));

			if ($match) {
				Logger::log("🎯 Produit trouvé : " . $product->get_name());
				return $product->get_id();
			}
		}

		Logger::log("🔁 Fallback : recherche d'un produit contenant \"$target_string\"");
		foreach ($products as $product) {
			$name = strtolower(trim($product->get_name()));
			if (strpos($name, $target_string) !== false) {
				Logger::log("🎯 Fallback réussi : " . $product->get_name());
				return $product->get_id();
			}
		}

		Logger::log("❌ Aucun produit correspondant trouvé pour $count cours");
		return null;
	}

	public static function get_product_id_smart($count1h, $countOver, $seuilAVolonte = 5) {
		$total = $count1h + $countOver;
		$term     = get_option('fpr_product_keyword', 'cours/sem');
		$strategy = get_option('fpr_match_strategy', 'ends_with');
		$counts   = array_map('trim', explode(',', get_option('fpr_course_counts', '')));
		$counts   = array_filter($counts, 'is_numeric');

		Logger::log("🔎 [smart] total=$total | 1h=$count1h | >1h=$countOver | seuil=$seuilAVolonte");

		if ($total >= $seuilAVolonte) {
			$awol_name = strtolower(trim(get_option('fpr_awol_product_name', 'à volonté')));
			$products = wc_get_products(['limit' => -1, 'status' => 'publish']);
			foreach ($products as $product) {
				$name = strtolower($product->get_name());
				if (strpos($name, $awol_name) !== false) {
					Logger::log("🎯 Produit à volonté trouvé : $name");
					return $product->get_id();
				}
			}
			Logger::log("❌ Aucun produit 'à volonté' trouvé.");
			return null;
		}

		return self::get_product_id_by_course_count($count1h);
	}
}
