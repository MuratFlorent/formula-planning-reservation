<?php

namespace FPR\Modules;

use WC_Order;

class Amelia {
    public static function init() {
        add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_successful_payment'], 10, 4);
    }

    public static function handle_successful_payment($order_id, $old_status, $new_status, $order) {
        if (!in_array($new_status, ['processing', 'completed'])) return;

        $first_name = $order->get_billing_first_name();
        $last_name  = $order->get_billing_last_name();
        $email      = $order->get_billing_email();
        $phone      = $order->get_billing_phone();
        $price      = $order->get_total();
        $order_num  = $order->get_order_number();

        foreach ($order->get_items() as $item) {
            $formula = $item->get_name();

            foreach ($item->get_meta_data() as $meta) {
                if (strpos($meta->key, 'Cours sélectionné') !== false) {
                    $event_name = $meta->value;
                    self::book_customer($first_name, $last_name, $email, $phone, $event_name, $formula, $price, $order_num);
                }
            }
        }
    }

    public static function book_customer($firstName, $lastName, $email, $phone, $event_name, $formula, $amount, $order_number) {
        $event_id = self::get_event_id_by_name($event_name);
        if (!$event_id) return false;

        $data = [
            "type" => "event",
            "bookings" => [[
                "customer" => [
                    "email" => $email,
                    "firstName" => $firstName,
                    "lastName" => $lastName,
                    "phone" => $phone,
                    "countryPhoneIso" => "fr",
                    "note" => $formula
                ],
                "persons" => 1,
                "customFields" => ["3" => [
                    "label" => "Formule choisie",
                    "value" => $formula
                ]],
                "customerId" => 0,
                "deposit" => false
            ]],
            "payment" => [
                "amount" => $amount,
                "gateway" => "onSite",
                "currency" => "EUR"
            ],
            "locale" => "fr_FR",
            "timeZone" => "Europe/Paris",
            "eventId" => $event_id
        ];

        $response = wp_remote_post(site_url('/wp-admin/admin-ajax.php?action=wpamelia_api&call=/api/v1/bookings'), [
            'method'  => 'POST',
            'body'    => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'Amelia'       => AMELIA_API_TOKEN,
            ]
        ]);

        \FPR\Helpers\Logger::log('Booking response: ' . wp_remote_retrieve_body($response));

        return wp_remote_retrieve_response_code($response) === 200;
    }

    public static function get_event_id_by_name($event_name, $tag = 'cours formule basique') {
        global $wpdb;

        $split = explode(' - ', $event_name);
        $name  = trim($split[0]);

        return $wpdb->get_var($wpdb->prepare(
            "SELECT e.id FROM {$wpdb->prefix}amelia_events e
            JOIN {$wpdb->prefix}amelia_events_tags et ON e.id = et.eventId
            WHERE e.name = %s AND et.name = %s",
            $name,
            $tag
        ));
    }
}
