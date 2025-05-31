<?php
/**
 * Test script for session issue
 * 
 * This script simulates the scenario where a user selects 5 courses of 1h,
 * then returns to select more courses, to verify if there's an issue with
 * session management or formula selection.
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';

// Load the required classes
require_once dirname(dirname(__FILE__)) . '/helpers/product-mapper.php';
require_once dirname(dirname(__FILE__)) . '/includes/modules/class-form-handler.php';

use FPR\Helpers\ProductMapper;
use FPR\Helpers\Logger;
use FPR\Modules\FormHandler;

// Enable logging
Logger::$enabled = true;

echo "<h1>Test de l'issue de session et de sélection de formule</h1>";

// Display current settings
echo "<h3>Paramètres actuels</h3>";
echo "<p>Durée par défaut des cours: <strong>" . get_option('fpr_default_course_duration', '1h') . "</strong></p>";
echo "<p>Seuil pour formule \"à volonté\": <strong>" . get_option('fpr_aw_threshold', 5) . "</strong></p>";
echo "<p>Mot-clé \"à volonté\": <strong>" . get_option('fpr_aw_keyword', 'à volonté') . "</strong></p>";
echo "<p>Formule \"à volonté\" activée: <strong>" . (get_option('fpr_aw_enabled', 1) ? 'Oui' : 'Non') . "</strong></p>";

// Function to simulate course selection
function simulate_course_selection($durations, $clear_session = false) {
    global $wpdb;
    
    // Clear session if requested
    if ($clear_session && WC()->session) {
        WC()->session->set('fpr_selected_courses', null);
        echo "<p>Session cleared</p>";
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
    
    // Get current session data
    $existing_data = WC()->session ? WC()->session->get('fpr_selected_courses') : null;
    $existing_courses = $existing_data ? json_decode($existing_data, true) : [];
    
    echo "<p>Existing courses in session: " . count($existing_courses) . "</p>";
    if (!empty($existing_courses)) {
        echo "<ul>";
        foreach ($existing_courses as $course) {
            echo "<li>" . $course['title'] . " - " . $course['duration'] . "</li>";
        }
        echo "</ul>";
    }
    
    // Store courses in session
    $json_data = json_encode($courses);
    WC()->session->set('fpr_selected_courses', $json_data);
    
    echo "<p>New courses added to session: " . count($courses) . "</p>";
    echo "<ul>";
    foreach ($courses as $course) {
        echo "<li>" . $course['title'] . " - " . $course['duration'] . "</li>";
    }
    echo "</ul>";
    
    // Simulate the form handler processing
    $_GET['fpr_store_cart_selection'] = true;
    $_GET['fpr_confirmed'] = true;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Inject the data
    $GLOBALS['fpr_raw_post'] = json_encode([
        'count' => count($courses),
        'courses' => $courses
    ]);
    
    // Call the form handler
    FormHandler::maybe_store_selection();
    
    // Get the updated session data
    $updated_data = WC()->session ? WC()->session->get('fpr_selected_courses') : null;
    $updated_courses = $updated_data ? json_decode($updated_data, true) : [];
    
    echo "<p>Updated courses in session after processing: " . count($updated_courses) . "</p>";
    if (!empty($updated_courses)) {
        echo "<ul>";
        foreach ($updated_courses as $course) {
            echo "<li>" . $course['title'] . " - " . $course['duration'] . "</li>";
        }
        echo "</ul>";
    }
    
    // Count courses by duration
    $default_duration = get_option('fpr_default_course_duration', '1h');
    $count1h = 0;
    $countOver = 0;
    
    foreach ($updated_courses as $course) {
        if (isset($course['duration'])) {
            if ($course['duration'] === $default_duration) {
                $count1h++;
            } else {
                $countOver++;
            }
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
        'countOver' => $countOver,
        'total_courses' => count($updated_courses)
    ];
}

// Test case 1: Select 5 courses of 1h
echo "<h2>Test 1: Sélection de 5 cours d'1h</h2>";
$durations = ['1h', '1h', '1h', '1h', '1h'];
$result1 = simulate_course_selection($durations, true); // Clear session first

echo "<p>Nombre de cours de durée par défaut: <strong>{$result1['count1h']}</strong></p>";
echo "<p>Nombre de cours de durée non standard: <strong>{$result1['countOver']}</strong></p>";
echo "<p>Nombre total de cours: <strong>{$result1['total_courses']}</strong></p>";

if ($result1['product_id']) {
    echo "<p>Formule sélectionnée: <strong>{$result1['product_name']}</strong> (ID: {$result1['product_id']})</p>";
} else {
    echo "<p>Aucune formule trouvée</p>";
}

// Test case 2: Return and select 2 more courses of 1h
echo "<h2>Test 2: Retour et sélection de 2 cours d'1h supplémentaires</h2>";
$durations = ['1h', '1h'];
$result2 = simulate_course_selection($durations, false); // Don't clear session

echo "<p>Nombre de cours de durée par défaut: <strong>{$result2['count1h']}</strong></p>";
echo "<p>Nombre de cours de durée non standard: <strong>{$result2['countOver']}</strong></p>";
echo "<p>Nombre total de cours: <strong>{$result2['total_courses']}</strong></p>";

if ($result2['product_id']) {
    echo "<p>Formule sélectionnée: <strong>{$result2['product_name']}</strong> (ID: {$result2['product_id']})</p>";
} else {
    echo "<p>Aucune formule trouvée</p>";
}

echo "<h2>Conclusion</h2>";
echo "<p>Test 1: 5 cours d'1h => Formule: {$result1['product_name']}</p>";
echo "<p>Test 2: 5 cours d'1h + 2 cours d'1h => Formule: {$result2['product_name']}</p>";

// Clear session at the end
WC()->session->set('fpr_selected_courses', null);
echo "<p>Session cleared at the end of the test</p>";

echo "<p>Tests terminés.</p>";