<?php
/**
 * Test script for special formula options
 * 
 * This script simulates different combinations of course selections
 * to verify that the correct formulas are selected based on the
 * number of courses and multiple hour combinations.
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';

// Load the ProductMapper class
require_once dirname(dirname(__FILE__)) . '/helpers/product-mapper.php';

use FPR\Helpers\ProductMapper;
use FPR\Helpers\Logger;

// Enable logging
Logger::$enabled = true;

echo "<h1>Test des formules spéciales</h1>";

// Display current settings
echo "<h3>Paramètres actuels</h3>";
echo "<p>Durée par défaut des cours: <strong>" . get_option('fpr_default_course_duration', '1h') . "</strong></p>";

// Display custom formulas
echo "<p>Formules personnalisées: ";
$custom_formulas = get_option('fpr_custom_formulas', []);
if (!empty($custom_formulas) && is_array($custom_formulas)) {
    echo "<ul>";
    foreach ($custom_formulas as $formula) {
        if (empty($formula['term']) || empty($formula['strategy']) || empty($formula['duration'])) {
            continue;
        }
        echo "<li><strong>Terme:</strong> " . $formula['term'] . 
             ", <strong>Stratégie:</strong> " . $formula['strategy'] . 
             ", <strong>Durée:</strong> " . $formula['duration'];
        
        if (isset($formula['course_count']) && !empty($formula['course_count'])) {
            echo ", <strong>Nombre de cours:</strong> " . $formula['course_count'];
        }
        
        if (isset($formula['secondary_duration']) && !empty($formula['secondary_duration'])) {
            echo ", <strong>Durée secondaire:</strong> " . $formula['secondary_duration'];
        }
        
        echo "</li>";
    }
    echo "</ul>";
} else {
    echo "Aucune formule personnalisée définie.</p>";
}
echo "<hr>";

// Function to test formula selection
function test_formula_selection($count1h, $countOver) {
    $total = $count1h + $countOver;
    $default_duration = get_option('fpr_default_course_duration', '1h');
    
    echo "<h2>Test avec $count1h cours de durée par défaut ($default_duration) et $countOver cours d'autres durées (total: $total)</h2>";
    
    // Get the product ID
    $product_id = ProductMapper::get_product_id_smart($count1h, $countOver);
    
    // Get the product name
    if ($product_id) {
        $product = wc_get_product($product_id);
        $product_name = $product ? $product->get_name() : "Produit introuvable";
        echo "<p>Formule sélectionnée: <strong>$product_name</strong> (ID: $product_id)</p>";
    } else {
        echo "<p>Aucune formule trouvée</p>";
    }
    
    echo "<hr>";
}

// Test different combinations
echo "<h3>Tests pour formules avec nombre de cours spécifique</h3>";
test_formula_selection(1, 0); // 1 cours de durée par défaut
test_formula_selection(2, 0); // 2 cours de durée par défaut
test_formula_selection(3, 0); // 3 cours de durée par défaut
test_formula_selection(4, 0); // 4 cours de durée par défaut

echo "<h3>Tests pour formules avec durées spécifiques</h3>";
test_formula_selection(0, 1); // 1 cours d'autre durée
test_formula_selection(0, 2); // 2 cours d'autre durée
test_formula_selection(1, 1); // 1 cours de durée par défaut + 1 cours d'autre durée
test_formula_selection(2, 1); // 2 cours de durée par défaut + 1 cours d'autre durée
test_formula_selection(1, 2); // 1 cours de durée par défaut + 2 cours d'autre durée

echo "<h3>Tests pour formules avec combinaisons spéciales</h3>";
test_formula_selection(0, 2); // 2 cours d'autre durée (pour tester "2 cours 1h30" ou "1 cours 1h15 + 1 cours 1h30")
test_formula_selection(0, 3); // 3 cours d'autre durée
test_formula_selection(1, 2); // 1 cours de durée par défaut + 2 cours d'autre durée
test_formula_selection(2, 2); // 2 cours de durée par défaut + 2 cours d'autre durée

echo "<p>Tests terminés.</p>";