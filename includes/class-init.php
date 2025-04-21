<?php

namespace FPR;

class Init {
    public static function register() {
        // Chargement des modules principaux
        add_action('plugins_loaded', [__CLASS__, 'load_modules']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function load_modules() {
        $modules = [
            'form-handler',
            'calendar-handler',
            'woocommerce',
            'amelia',
            'mailer'
        ];

        foreach ($modules as $module) {
            $file = FPR_PLUGIN_DIR . 'includes/modules/class-' . $module . '.php';
            if (file_exists($file)) {
                require_once $file;
                $class = '\\FPR\\Modules\\' . str_replace('-', '', ucwords($module, '-'));
                if (class_exists($class) && method_exists($class, 'init')) {
                    $class::init();
                }
            }
        }
    }

    public static function enqueue_assets() {
        wp_enqueue_script(
            'fpr-calendar-js',
            FPR_PLUGIN_URL . 'assets/js/calendar.js',
            ['jquery'],
            null,
            true
        );

        wp_enqueue_style(
            'fpr-calendar-css',
            FPR_PLUGIN_URL . 'assets/css/calendar.css'
        );
    }
}
