<?php
/**
 * Test script for formula selection
 * 
 * This script simulates different combinations of course selections
 * to verify that the correct formulas are selected.
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';

// Load the ProductMapper class
require_once dirname(dirname(__FILE__)) . '/helpers/product-mapper.php';

use FPR\Helpers\ProductMapper;
use FPR\Helpers\Logger;

// Enable logging
Logger::$enabled = true;

echo "<h1>Test de sélection de formules</h1>";

// Function to test formula selection
function test_formula_selection($count1h, $countOver) {
    $total = $count1h + $countOver;
    echo "<h2>Test avec $count1h cours d'1h et $countOver cours de plus d'1h (total: $total)</h2>";
    
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
echo "<h3>Tests pour formules de cours d'1h</h3>";
test_formula_selection(1, 0); // 1 cours d'1h
test_formula_selection(2, 0); // 2 cours d'1h
test_formula_selection(3, 0); // 3 cours d'1h
test_formula_selection(4, 0); // 4 cours d'1h
test_formula_selection(5, 0); // 5 cours d'1h (devrait être "à volonté" si activé)
test_formula_selection(6, 0); // 6 cours d'1h (devrait être "à volonté" si activé)

echo "<h3>Tests pour formules de cours de plus d'1h</h3>";
test_formula_selection(0, 1); // 1 cours de plus d'1h
test_formula_selection(0, 2); // 2 cours de plus d'1h
test_formula_selection(1, 1); // 1 cours d'1h + 1 cours de plus d'1h
test_formula_selection(2, 1); // 2 cours d'1h + 1 cours de plus d'1h
test_formula_selection(1, 2); // 1 cours d'1h + 2 cours de plus d'1h
test_formula_selection(3, 2); // 3 cours d'1h + 2 cours de plus d'1h (devrait être "à volonté" si activé)

echo "<h3>Tests pour formules mixtes avec seuil \"à volonté\"</h3>";
test_formula_selection(3, 3); // 3 cours d'1h + 3 cours de plus d'1h (total: 6, devrait être "à volonté" si activé)
test_formula_selection(2, 3); // 2 cours d'1h + 3 cours de plus d'1h (total: 5, devrait être "à volonté" si activé)
test_formula_selection(0, 5); // 0 cours d'1h + 5 cours de plus d'1h (total: 5, devrait être "à volonté" si activé)

echo "<p>Tests terminés.</p>";