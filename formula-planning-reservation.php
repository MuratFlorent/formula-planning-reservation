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

require_once FPR_PLUGIN_DIR . 'includes/class-init.php';

register_activation_hook(__FILE__, ['FPR\Init', 'create_cart_page_if_needed']);

\FPR\Init::register();
