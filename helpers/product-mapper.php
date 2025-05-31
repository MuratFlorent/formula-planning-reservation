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

		// Récupérer les paramètres "à volonté" depuis les options
		$aw_enabled = get_option('fpr_aw_enabled', 1); // Forcer l'activation par défaut
		$aw_threshold = intval(get_option('fpr_aw_threshold', 5));
		$aw_keyword = strtolower(trim(get_option('fpr_aw_keyword', 'à volonté')));

		// Utiliser le seuil des options s'il est défini, sinon utiliser celui passé en paramètre
		$seuilAVolonte = $aw_threshold > 0 ? $aw_threshold : $seuilAVolonte;

		Logger::log("🔎 [smart] total=$total | 1h=$count1h | >1h=$countOver | seuil=$seuilAVolonte | à volonté activé=" . ($aw_enabled ? "oui" : "non"));

		// Vérifier si la formule "à volonté" est activée et si le seuil est atteint
		// Pour les cours d'1h, on applique le seuil uniquement sur count1h
		// Pour les cours de plus d'1h, on applique le seuil sur le total
		if ($aw_enabled && (($countOver == 0 && $count1h >= $seuilAVolonte) || $total >= $seuilAVolonte)) {
			Logger::log("🎯 Recherche d'un produit 'à volonté'");
			$products = wc_get_products(['limit' => -1, 'status' => 'publish']);

			// Essayer plusieurs stratégies de recherche pour trouver un produit "à volonté"
			$found_product_id = null;

			// Stratégie 1: Recherche exacte avec le mot-clé "à volonté"
			Logger::log("🔍 Stratégie 1: Recherche exacte avec le mot-clé 'à volonté'");
			if ($countOver == 0) {
				// Pour les cours d'1h uniquement
				Logger::log("🔍 Recherche d'un produit 'à volonté' pour cours d'1h");
				foreach ($products as $product) {
					$name = strtolower($product->get_name());
					if (strpos($name, $aw_keyword) !== false && strpos($name, $term) !== false) {
						Logger::log("🎯 Produit à volonté pour cours d'1h trouvé : $name");
						$found_product_id = $product->get_id();
						break;
					}
				}
			} else {
				// Pour les cours mixtes ou de plus d'1h
				Logger::log("🔍 Recherche d'un produit 'à volonté' général");
				foreach ($products as $product) {
					$name = strtolower($product->get_name());
					if (strpos($name, $aw_keyword) !== false) {
						Logger::log("🎯 Produit à volonté général trouvé : $name");
						$found_product_id = $product->get_id();
						break;
					}
				}
			}

			// Stratégie 2: Recherche avec des termes alternatifs si aucun produit n'a été trouvé
			if (!$found_product_id) {
				Logger::log("🔍 Stratégie 2: Recherche avec des termes alternatifs");
				$alternative_keywords = ['illimité', 'unlimited', 'tous les cours', 'all courses', 'volonte'];

				foreach ($products as $product) {
					$name = strtolower($product->get_name());
					foreach ($alternative_keywords as $keyword) {
						if (strpos($name, $keyword) !== false) {
							Logger::log("🎯 Produit trouvé avec terme alternatif '$keyword': $name");
							$found_product_id = $product->get_id();
							break 2; // Sortir des deux boucles
						}
					}
				}
			}

			// Stratégie 3: Recherche basée sur le nombre de cours si aucun produit n'a été trouvé
			if (!$found_product_id) {
				Logger::log("🔍 Stratégie 3: Recherche basée sur le nombre de cours");
				// Chercher un produit qui contient le nombre total de cours ou plus
				foreach ($products as $product) {
					$name = strtolower($product->get_name());
					// Chercher des motifs comme "5 cours", "5+", "5 ou plus", etc.
					if (strpos($name, "$total cours") !== false || 
						strpos($name, "$total+") !== false || 
						strpos($name, "$total ou plus") !== false) {
						Logger::log("🎯 Produit trouvé avec nombre de cours $total: $name");
						$found_product_id = $product->get_id();
						break;
					}
				}
			}

			if ($found_product_id) {
				return $found_product_id;
			}

			// Stratégie 4: Dernier recours - prendre le premier produit disponible
			Logger::log("🔍 Stratégie 4: Dernier recours - recherche du premier produit disponible");
			if (count($products) > 0) {
				$first_product = $products[0];
				Logger::log("🎯 Produit de dernier recours trouvé: " . $first_product->get_name());
				return $first_product->get_id();
			}

			Logger::log("❌ Aucun produit 'à volonté' trouvé avec toutes les stratégies.");
			// Si aucun produit "à volonté" n'est trouvé, on continue avec la logique standard
		}

		// Vérifier à nouveau si le nombre total de cours atteint le seuil "à volonté"
		// Cette vérification supplémentaire est nécessaire au cas où la recherche initiale d'un produit "à volonté" a échoué
		if ($total >= $seuilAVolonte) {
			Logger::log("🔄 Nouvelle tentative de recherche d'un produit à volonté avec des critères plus larges");
			// Rechercher un produit "à volonté" avec des termes génériques
			$aw_keywords = ['à volonté', 'illimité', 'unlimited', 'tous les cours', 'all courses', 'volonte'];

			foreach ($products as $product) {
				$name = strtolower($product->get_name());
				foreach ($aw_keywords as $keyword) {
					if (strpos($name, $keyword) !== false) {
						Logger::log("🎯 Produit à volonté trouvé avec le terme '$keyword': $name");
						return $product->get_id();
					}
				}
			}

			// Si toujours aucun produit "à volonté" trouvé, chercher un produit avec le nombre total de cours
			Logger::log("🔍 Recherche d'un produit avec le nombre total de cours: $total");
			$total_product_id = self::get_product_id_by_course_count($total);
			if ($total_product_id) {
				Logger::log("🎯 Produit trouvé pour le nombre total de cours: $total");
				return $total_product_id;
			}
		}

		// Si nous avons uniquement des cours d'1h, utiliser la formule standard
		if ($countOver == 0) {
			Logger::log("📊 Uniquement des cours d'1h, recherche de formule standard");
			return self::get_product_id_by_course_count($count1h);
		}

		// Si nous avons des cours de plus d'1h, rechercher une formule spécifique
		Logger::log("📊 Présence de cours de plus d'1h, recherche de formule spécifique");
		return self::get_product_id_for_longer_courses($count1h, $countOver);
	}

	/**
	 * Trouve un produit correspondant à une combinaison de cours de plus d'1h
	 * 
	 * @param int $count1h Nombre de cours d'1h
	 * @param int $countOver Nombre de cours de plus d'1h
	 * @return int|null ID du produit ou null si aucun produit ne correspond
	 */
	public static function get_product_id_for_longer_courses($count1h, $countOver) {
		$products = wc_get_products(['limit' => -1, 'status' => 'publish']);
		Logger::log("📦 " . count($products) . " produits WooCommerce récupérés pour recherche de formule spécifique");

		// Vérifier si le nombre total de cours atteint le seuil "à volonté"
		$total = $count1h + $countOver;
		$seuilAVolonte = intval(get_option('fpr_aw_threshold', 5));

		if ($total >= $seuilAVolonte) {
			Logger::log("🔍 Le nombre total de cours ($total) atteint le seuil à volonté ($seuilAVolonte), priorité à la recherche d'un produit à volonté");

			// Rechercher un produit "à volonté" avec des termes génériques
			$aw_keywords = ['à volonté', 'illimité', 'unlimited', 'tous les cours', 'all courses', 'volonte'];

			foreach ($products as $product) {
				$name = strtolower($product->get_name());
				foreach ($aw_keywords as $keyword) {
					if (strpos($name, $keyword) !== false) {
						Logger::log("🎯 Produit à volonté trouvé avec le terme '$keyword': $name");
						return $product->get_id();
					}
				}
			}

			Logger::log("⚠️ Aucun produit à volonté trouvé malgré le seuil atteint, poursuite avec la recherche standard");
		}

		// Récupérer la stratégie de correspondance depuis les options
		$strategy = get_option('fpr_match_strategy', 'contains');
		Logger::log("🔍 Stratégie de correspondance pour formules spécifiques: $strategy");

		// Récupérer les formules personnalisées
		$custom_formulas = get_option('fpr_custom_formulas', []);
		if (!empty($custom_formulas) && is_array($custom_formulas)) {
			Logger::log("📋 Formules personnalisées trouvées: " . count($custom_formulas));

			// Essayer d'abord les formules personnalisées
			foreach ($custom_formulas as $formula) {
				if (empty($formula['term']) || empty($formula['strategy']) || empty($formula['duration'])) {
					continue;
				}

				Logger::log("🔍 Vérification de la formule personnalisée: " . $formula['term'] . " (stratégie: " . $formula['strategy'] . ", durée: " . $formula['duration'] . ")");

				// Vérifier si la durée correspond à nos cours
				$default_duration = get_option('fpr_default_course_duration', '1h');
				$is_default_duration = ($formula['duration'] === $default_duration);

				// Vérifier si un nombre de cours spécifique est défini pour cette formule
				$course_count = isset($formula['course_count']) ? intval($formula['course_count']) : 0;
				$has_secondary_duration = isset($formula['secondary_duration']) && !empty($formula['secondary_duration']);

				Logger::log("🔍 Formule avec nombre de cours spécifique: " . ($course_count > 0 ? "Oui ($course_count)" : "Non"));
				Logger::log("🔍 Formule avec durée secondaire: " . ($has_secondary_duration ? "Oui (" . $formula['secondary_duration'] . ")" : "Non"));

				// Si la formule a un nombre de cours spécifique, vérifier si ça correspond
				if ($course_count > 0) {
					// Pour les formules avec durée secondaire, vérifier les combinaisons spécifiques
					if ($has_secondary_duration) {
						// Cas spécial: 2 cours de durées différentes (ex: 1 cours 1h15 + 1 cours 1h30)
						if ($course_count == 2 && $countOver == 2) {
							Logger::log("🔍 Vérification pour formule spéciale avec 2 cours de durées différentes");

							// Rechercher les produits qui correspondent à cette formule
							foreach ($products as $product) {
								$name = strtolower($product->get_name());
								$match = false;

								switch ($formula['strategy']) {
									case 'equals':
										$match = ($name === strtolower($formula['term']));
										break;
									case 'starts_with':
										$match = str_starts_with($name, strtolower($formula['term']));
										break;
									case 'ends_with':
										$match = str_ends_with($name, strtolower($formula['term']));
										break;
									case 'contains':
									default:
										$match = strpos($name, strtolower($formula['term'])) !== false;
										break;
								}

								if ($match) {
									Logger::log("🎯 Produit trouvé pour formule spéciale avec 2 cours de durées différentes: " . $product->get_name());
									return $product->get_id();
								}
							}
						}
						// Autres cas avec durée secondaire
						else if ($countOver == $course_count) {
							Logger::log("🔍 Vérification pour formule avec $course_count cours de durées spécifiques");

							// Rechercher les produits qui correspondent à cette formule
							foreach ($products as $product) {
								$name = strtolower($product->get_name());
								$match = false;

								switch ($formula['strategy']) {
									case 'equals':
										$match = ($name === strtolower($formula['term']));
										break;
									case 'starts_with':
										$match = str_starts_with($name, strtolower($formula['term']));
										break;
									case 'ends_with':
										$match = str_ends_with($name, strtolower($formula['term']));
										break;
									case 'contains':
									default:
										$match = strpos($name, strtolower($formula['term'])) !== false;
										break;
								}

								if ($match) {
									Logger::log("🎯 Produit trouvé pour formule avec $course_count cours de durées spécifiques: " . $product->get_name());
									return $product->get_id();
								}
							}
						}
					}
					// Pour les formules sans durée secondaire, vérifier si le nombre de cours correspond
					else if (($is_default_duration && $count1h == $course_count) || 
							(!$is_default_duration && $countOver == $course_count)) {
						Logger::log("🔍 Vérification pour formule avec $course_count cours de durée " . ($is_default_duration ? "par défaut" : "non standard"));

						// Rechercher les produits qui correspondent à cette formule
						foreach ($products as $product) {
							$name = strtolower($product->get_name());
							$match = false;

							switch ($formula['strategy']) {
								case 'equals':
									$match = ($name === strtolower($formula['term']));
									break;
								case 'starts_with':
									$match = str_starts_with($name, strtolower($formula['term']));
									break;
								case 'ends_with':
									$match = str_ends_with($name, strtolower($formula['term']));
									break;
								case 'contains':
								default:
									$match = strpos($name, strtolower($formula['term'])) !== false;
									break;
							}

							if ($match) {
								Logger::log("🎯 Produit trouvé pour formule avec $course_count cours de durée " . ($is_default_duration ? "par défaut" : "non standard") . ": " . $product->get_name());
								return $product->get_id();
							}
						}
					}
				}
				// Si aucun nombre de cours spécifique n'est défini, utiliser la logique existante
				else if ($is_default_duration && $count1h > 0) {
					Logger::log("🔍 Formule pour durée par défaut, vérification avec $count1h cours");

					// Rechercher les produits qui correspondent à cette formule
					foreach ($products as $product) {
						$name = strtolower($product->get_name());
						$match = false;

						switch ($formula['strategy']) {
							case 'equals':
								$match = ($name === strtolower($formula['term']));
								break;
							case 'starts_with':
								$match = str_starts_with($name, strtolower($formula['term']));
								break;
							case 'ends_with':
								$match = str_ends_with($name, strtolower($formula['term']));
								break;
							case 'contains':
							default:
								$match = strpos($name, strtolower($formula['term'])) !== false;
								break;
						}

						if ($match) {
							Logger::log("🎯 Produit trouvé pour formule personnalisée: " . $product->get_name());
							return $product->get_id();
						}
					}
				} 
				// Si la formule est pour une durée différente, vérifier le nombre de cours de plus d'1h
				else if (!$is_default_duration && $countOver > 0) {
					Logger::log("🔍 Formule pour durée non standard, vérification avec $countOver cours");

					// Rechercher les produits qui correspondent à cette formule
					foreach ($products as $product) {
						$name = strtolower($product->get_name());
						$match = false;

						switch ($formula['strategy']) {
							case 'equals':
								$match = ($name === strtolower($formula['term']));
								break;
							case 'starts_with':
								$match = str_starts_with($name, strtolower($formula['term']));
								break;
							case 'ends_with':
								$match = str_ends_with($name, strtolower($formula['term']));
								break;
							case 'contains':
							default:
								$match = strpos($name, strtolower($formula['term'])) !== false;
								break;
						}

						if ($match) {
							Logger::log("🎯 Produit trouvé pour formule personnalisée: " . $product->get_name());
							return $product->get_id();
						}
					}
				}
			}

			Logger::log("⚠️ Aucune formule personnalisée ne correspond, utilisation des patterns par défaut");
		}

		// Récupérer les durées des cours depuis la session
		$stored = WC()->session->get('fpr_selected_courses');
		$courses = $stored ? json_decode($stored, true) : [];

		// Compter les cours par durée spécifique
		$count1h15 = 0;
		$count1h30 = 0;
		$countOther = 0;

		if (is_array($courses)) {
			foreach ($courses as $course) {
				if (isset($course['duration'])) {
					$duration = strtolower(trim($course['duration']));
					if ($duration === '1h15') {
						$count1h15++;
					} else if ($duration === '1h30') {
						$count1h30++;
					} else if ($duration !== get_option('fpr_default_course_duration', '1h')) {
						$countOther++;
					}
				}
			}
		}

		Logger::log("📊 Répartition détaillée des cours: 1h15=$count1h15, 1h30=$count1h30, autres=$countOther");

		// Cas spécifiques mentionnés dans les exigences
		$patterns = [];

		// Cas spécifiques pour les formules avec durées précises
		if ($count1h15 == 2 && $count1h30 == 0 && $countOther == 0) {
			// 2 cours de 1h15
			Logger::log("🔍 Cas spécifique: 2 cours de 1h15");
			$patterns[] = '2 cours 1h15';
		} 
		else if ($count1h15 == 0 && $count1h30 == 2 && $countOther == 0) {
			// 2 cours de 1h30
			Logger::log("🔍 Cas spécifique: 2 cours de 1h30");
			$patterns[] = '2 cours 1h30';
		}
		else if ($count1h15 == 1 && $count1h30 == 1 && $countOther == 0) {
			// 1 cours de 1h15 + 1 cours de 1h30
			Logger::log("🔍 Cas spécifique: 1 cours de 1h15 + 1 cours de 1h30");
			$patterns[] = '1 cours 1h15 + 1 cours 1h30';
		}
		// Formules pour 2 cours de plus d'1h (cas général)
		else if ($countOver == 2 && $count1h == 0) {
			// Formules spécifiques mentionnées dans l'issue description
			$patterns[] = '2 cours 1h15';
			$patterns[] = '1 cours 1h15 + 1 cours 1h30';
			$patterns[] = '2 cours 1h30';

			// Autres formules génériques pour 2 cours
			$patterns[] = '2 cours plus d\'1h';
		}
		// Formules pour 1 cours de plus d'1h + des cours d'1h
		else if ($countOver == 1 && $count1h > 0) {
			if ($count1h15 == 1) {
				$patterns[] = '1 cours 1h15 + ' . $count1h . ' cours 1h';
			}
			if ($count1h30 == 1) {
				$patterns[] = '1 cours 1h30 + ' . $count1h . ' cours 1h';
			}
			$patterns[] = '1 cours plus d\'1h + ' . $count1h . ' cours 1h';
		}
		// Autres combinaisons
		else {
			// Pour les cours de plus d'1h uniquement
			if ($count1h == 0) {
				$patterns[] = $countOver . ' cours plus d\'1h';
				if ($count1h15 > 0) {
					$patterns[] = $countOver . ' cours 1h15';
				}
				if ($count1h30 > 0) {
					$patterns[] = $countOver . ' cours 1h30';
				}
			} 
			// Pour les combinaisons mixtes
			else {
				$patterns[] = $countOver . ' cours plus d\'1h + ' . $count1h . ' cours 1h';
				if ($count1h15 > 0) {
					$patterns[] = $count1h15 . ' cours 1h15 + ' . $count1h . ' cours 1h';
				}
				if ($count1h30 > 0) {
					$patterns[] = $count1h30 . ' cours 1h30 + ' . $count1h . ' cours 1h';
				}
			}
		}

		Logger::log("📋 Patterns à rechercher: " . implode(", ", $patterns));

		// Rechercher les patterns dans les produits
		foreach ($patterns as $pattern) {
			Logger::log("🔍 Recherche d'un produit correspondant à \"$pattern\"");
			foreach ($products as $product) {
				$name = strtolower($product->get_name());
				$match = false;

				switch ($strategy) {
					case 'equals':
						$match = ($name === strtolower($pattern));
						break;
					case 'starts_with':
						$match = str_starts_with($name, strtolower($pattern));
						break;
					case 'ends_with':
						$match = str_ends_with($name, strtolower($pattern));
						break;
					case 'contains':
					default:
						$match = strpos($name, strtolower($pattern)) !== false;
						break;
				}

				if ($match) {
					Logger::log("🎯 Produit trouvé pour pattern \"$pattern\": " . $product->get_name());
					return $product->get_id();
				}
			}
		}

		// Si aucun pattern spécifique ne correspond, essayer une approche plus générique
		Logger::log("⚠️ Aucun pattern spécifique ne correspond, tentative avec approche générique");

		// Essayer de trouver un produit qui contient le nombre total de cours
		$total = $count1h + $countOver;
		foreach ($products as $product) {
			$name = strtolower($product->get_name());
			if (strpos($name, "$total cours") !== false) {
				Logger::log("🎯 Produit générique trouvé : " . $product->get_name());
				return $product->get_id();
			}
		}

		// En dernier recours, utiliser la formule standard basée sur le nombre total de cours
		Logger::log("⚠️ Aucune formule spécifique trouvée, fallback sur formule standard");
		return self::get_product_id_by_course_count($total);
	}
}
