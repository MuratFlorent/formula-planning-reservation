<?php
/**
 * Test script for formula selection based on course durations
 * 
 * This script simulates different combinations of course durations
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

echo "<h1>Test de sélection de formules basées sur les durées</h1>";

// Display current settings
echo "<h3>Paramètres actuels</h3>";
echo "<p>Durée par défaut des cours: <strong>" . get_option('fpr_default_course_duration', '1h') . "</strong></p>";
echo "<p>Seuil pour formule \"à volonté\": <strong>" . get_option('fpr_aw_threshold', 5) . "</strong></p>";
echo "<p>Mot-clé \"à volonté\": <strong>" . get_option('fpr_aw_keyword', 'à volonté') . "</strong></p>";

// Function to simulate course selection
function simulate_course_selection($durations) {
    global $wpdb;
    
    // Clear any existing course selection
    if (WC()->session) {
        WC()->session->set('fpr_selected_courses', null);
    }
    
    // Create simulated courses
    $courses = [];
    foreach ($durations as $index => $duration) {
        $courses[] = [
            'title' => "Cours Test " . ($index + 1),
            'time' => "18:00 - 19:00",
            'duration' => $duration,
            'instructor' => "Instructeur Test",
            'day_of_week' => "Lundi"
        ];
    }
    
    // Store courses in session
    WC()->session->set('fpr_selected_courses', json_encode($courses));
    
    // Count courses by duration
    $default_duration = get_option('fpr_default_course_duration', '1h');
    $count1h = 0;
    $countOver = 0;
    
    foreach ($durations as $duration) {
        if ($duration === $default_duration) {
            $count1h++;
        } else {
            $countOver++;
        }
    }
    
    // Get the product ID
    $product_id = ProductMapper::get_product_id_smart($count1h, $countOver);
    
    // Get the product name
    $product_name = "Produit introuvable";
    if ($product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $product_name = $product->get_name();
        }
    }
    
    return [
        'product_id' => $product_id,
        'product_name' => $product_name,
        'count1h' => $count1h,
        'countOver' => $countOver
    ];
}

// Function to display test results
function display_test_results($test_name, $durations, $result) {
    echo "<h3>$test_name</h3>";
    echo "<p>Durées des cours: <strong>" . implode(", ", $durations) . "</strong></p>";
    echo "<p>Nombre de cours de durée par défaut: <strong>{$result['count1h']}</strong></p>";
    echo "<p>Nombre de cours de durée non standard: <strong>{$result['countOver']}</strong></p>";
    
    if ($result['product_id']) {
        echo "<p>Formule sélectionnée: <strong>{$result['product_name']}</strong> (ID: {$result['product_id']})</p>";
    } else {
        echo "<p>Aucune formule trouvée</p>";
    }
    
    echo "<hr>";
}

// Test cases
echo "<h2>Tests pour les formules spécifiques</h2>";

// Test 1: 2 cours de 1h15
$durations = ['1h15', '1h15'];
$result = simulate_course_selection($durations);
display_test_results("Test 1: 2 cours de 1h15", $durations, $result);

// Test 2: 2 cours de 1h30
$durations = ['1h30', '1h30'];
$result = simulate_course_selection($durations);
display_test_results("Test 2: 2 cours de 1h30", $durations, $result);

// Test 3: 1 cours de 1h15 + 1 cours de 1h30
$durations = ['1h15', '1h30'];
$result = simulate_course_selection($durations);
display_test_results("Test 3: 1 cours de 1h15 + 1 cours de 1h30", $durations, $result);

// Test 4: 1 cours de 1h + 1 cours de 1h30
$durations = ['1h', '1h30'];
$result = simulate_course_selection($durations);
display_test_results("Test 4: 1 cours de 1h + 1 cours de 1h30", $durations, $result);

// Test 5: 1 cours de 1h + 1 cours de 1h15
$durations = ['1h', '1h15'];
$result = simulate_course_selection($durations);
display_test_results("Test 5: 1 cours de 1h + 1 cours de 1h15", $durations, $result);

// Test 6: 3 cours de 1h + 2 cours de 1h30 (devrait être "à volonté" si le seuil est 5 ou moins)
$durations = ['1h', '1h', '1h', '1h30', '1h30'];
$result = simulate_course_selection($durations);
display_test_results("Test 6: 3 cours de 1h + 2 cours de 1h30", $durations, $result);

// Test 7: 5 cours de 1h (devrait être "à volonté" si le seuil est 5 ou moins)
$durations = ['1h', '1h', '1h', '1h', '1h'];
$result = simulate_course_selection($durations);
display_test_results("Test 7: 5 cours de 1h", $durations, $result);

echo "<p>Tests terminés.</p>";