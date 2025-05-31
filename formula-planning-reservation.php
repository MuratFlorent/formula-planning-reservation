<?php
/**
 * Plugin Name: Formula Planning Reservation
 * Description: Gestion modulaire de réservations de cours via formulaire ou calendrier (Amelia + WooCommerce).
 * Version: 0.1
 * Author: Ton copilote du matin
 */

if (!defined('ABSPATH')) exit;

define('FPR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FPR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FPR_VERSION', '0.1');

require_once FPR_PLUGIN_DIR . 'includes/class-init.php';

// Fonctions d'activation du plugin
function fpr_activate_plugin() {
    // Créer la page panier si nécessaire
    \FPR\Init::create_cart_page_if_needed();

    // Exécuter les scripts SQL d'installation
    \FPR\Init::run_installation_sql();
}

register_activation_hook(__FILE__, 'fpr_activate_plugin');

\FPR\Init::register();
