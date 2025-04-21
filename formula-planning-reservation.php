<?php
/**
 * Plugin Name: Formula Planning Reservation
 * Description: Gestion modulaire de réservations de cours via formulaire ou calendrier (Amelia + WooCommerce).
 * Version: 0.1
 * Author: Ton copilote du matin
 */

// Sécurité
if (!defined('ABSPATH')) exit;

// Définir les chemins utiles
define('FPR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FPR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Inclure la classe d'initialisation
require_once FPR_PLUGIN_DIR . 'includes/class-init.php';

// Démarrer le plugin
\FPR\Init::register();
