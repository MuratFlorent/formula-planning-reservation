<?php
/**
 * Test script for recurring payments
 * 
 * This script simulates a subscription with a payment due today to test the recurring payment processing.
 */

// Load WordPress
require_once dirname(__FILE__, 5) . '/wp-load.php';

// Ensure only admins can run this script
if (!current_user_can('manage_options')) {
    die('Unauthorized access');
}

echo "<h1>Test des paiements récurrents</h1>";

// Create a test subscription with payment due today
function create_test_subscription() {
    global $wpdb;
    
    echo "<h2>Création d'un abonnement de test</h2>";
    
    // Get current user
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        echo "<p>Erreur: Aucun utilisateur connecté</p>";
        return false;
    }
    
    // Get a payment plan
    $payment_plan = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE active = 1 LIMIT 1");
    if (!$payment_plan) {
        echo "<p>Erreur: Aucun plan de paiement actif trouvé</p>";
        return false;
    }
    
    // Get a saison
    $saison = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}fpr_saisons WHERE end_date >= CURDATE() LIMIT 1");
    if (!$saison) {
        echo "<p>Erreur: Aucune saison active trouvée</p>";
        return false;
    }
    
    // Create a test order
    $order = wc_create_order([
        'customer_id' => $current_user_id,
        'status' => 'completed',
    ]);
    
    if (is_wp_error($order)) {
        echo "<p>Erreur lors de la création de la commande: " . $order->get_error_message() . "</p>";
        return false;
    }
    
    // Add a product
    $item = new WC_Order_Item_Product();
    $item->set_props([
        'name' => "Test Subscription Product",
        'quantity' => 1,
        'total' => 100,
    ]);
    $order->add_item($item);
    $order->calculate_totals();
    $order->save();
    
    $order_id = $order->get_id();
    
    echo "<p>Commande de test créée: #$order_id</p>";
    
    // Create subscription with payment due today
    $today = date('Y-m-d');
    $data = [
        'user_id' => $current_user_id,
        'order_id' => $order_id,
        'payment_plan_id' => $payment_plan->id,
        'saison_id' => $saison->id,
        'stripe_subscription_id' => null,
        'status' => 'active',
        'start_date' => date('Y-m-d', strtotime('-1 month')),
        'next_payment_date' => $today, // Payment due today
        'total_amount' => 300,
        'installment_amount' => 100,
        'installments_paid' => 1,
    ];
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'fpr_customer_subscriptions',
        $data
    );
    
    if ($result === false) {
        echo "<p>Erreur lors de la création de l'abonnement: " . $wpdb->last_error . "</p>";
        return false;
    }
    
    $subscription_id = $wpdb->insert_id;
    echo "<p>Abonnement de test créé: #$subscription_id</p>";
    echo "<p>Plan de paiement: {$payment_plan->name}</p>";
    echo "<p>Saison: {$saison->name}</p>";
    echo "<p>Date de début: {$data['start_date']}</p>";
    echo "<p>Prochain paiement: {$data['next_payment_date']} (aujourd'hui)</p>";
    echo "<p>Montant: {$data['installment_amount']} €</p>";
    
    return $subscription_id;
}

// Run the recurring payments process
function test_recurring_payments() {
    echo "<h2>Exécution du traitement des paiements récurrents</h2>";
    
    // Call the process_recurring_payments method
    FPR\Modules\StripeHandler::process_recurring_payments();
    
    echo "<p>Traitement terminé. Vérifiez les logs pour plus de détails.</p>";
}

// Main execution
$subscription_id = create_test_subscription();

if ($subscription_id) {
    echo "<p>Abonnement créé avec succès. Exécution du traitement des paiements récurrents...</p>";
    test_recurring_payments();
    
    // Check the updated subscription
    global $wpdb;
    $updated_subscription = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}fpr_customer_subscriptions WHERE id = %d",
        $subscription_id
    ));
    
    if ($updated_subscription) {
        echo "<h2>Résultat</h2>";
        echo "<p>Statut de l'abonnement: {$updated_subscription->status}</p>";
        echo "<p>Versements payés: {$updated_subscription->installments_paid}</p>";
        echo "<p>Prochaine date de paiement: {$updated_subscription->next_payment_date}</p>";
        
        // Check if a new order was created
        $orders = wc_get_orders([
            'meta_key' => '_fpr_subscription_id',
            'meta_value' => $subscription_id,
            'limit' => 1,
        ]);
        
        if (!empty($orders)) {
            $order = reset($orders);
            echo "<p>Nouvelle commande créée: #{$order->get_id()}</p>";
        } else {
            echo "<p>Aucune nouvelle commande n'a été créée.</p>";
        }
    } else {
        echo "<p>Erreur: Impossible de récupérer l'abonnement mis à jour.</p>";
    }
}

echo "<p><a href='javascript:history.back()'>Retour</a></p>";