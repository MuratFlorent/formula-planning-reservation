<?php

namespace FPR\Modules;

class Mailer {
    public static function init() {
        // Peut être déclenché dans d'autres modules (ex: après succès Amelia)
    }

    public static function send_email_with_template(string $to, string $subject, string $template, array $data = []) {
        $template_path = FPR_PLUGIN_DIR . 'templates/emails/' . $template;
        if (!file_exists($template_path)) return;

        extract($data);
        ob_start();
        include $template_path;
        $message = ob_get_clean();

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($to, $subject, $message, $headers);

        \FPR\Helpers\Logger::log("Email envoyé à $to avec sujet : $subject");
    }

    public static function notify_client($email, $prenom, $nom, $events, $formula, $price, $order_number) {
        self::send_email_with_template($email, 'Confirmation de votre inscription', 'customer-booking-confirmation.php', [
            'firstName' => $prenom,
            'lastName' => $nom,
            'events' => $events,
            'formula' => $formula,
            'price' => $price,
            'order_number' => $order_number
        ]);
    }

    public static function notify_admin($prenom, $nom, $events, $formula, $price, $order_number) {
        $admin_email = get_option('admin_email');
        self::send_email_with_template($admin_email, 'Nouvelle réservation', 'admin-booking-notification.php', [
            'firstName' => $prenom,
            'lastName' => $nom,
            'events' => $events,
            'formula' => $formula,
            'price' => $price,
            'order_number' => $order_number
        ]);
    }
}
