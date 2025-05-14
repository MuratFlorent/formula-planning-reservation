<?php

namespace FPR\Helpers;

class Logger {
    public static function log($message) {
        $log_dir  = WP_CONTENT_DIR . '/logs';
        $log_file = $log_dir . '/fpr-debug.log';

        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $entry = "[$timestamp] $message\n";

        file_put_contents($log_file, $entry, FILE_APPEND);
    }
}
