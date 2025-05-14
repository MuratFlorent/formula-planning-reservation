<?php

namespace FPR\Helpers;

class Events {
    public static function get_french_day($english_day) {
        $days = [
            'Monday'    => 'Lundi',
            'Tuesday'   => 'Mardi',
            'Wednesday' => 'Mercredi',
            'Thursday'  => 'Jeudi',
            'Friday'    => 'Vendredi',
            'Saturday'  => 'Samedi',
            'Sunday'    => 'Dimanche',
        ];
        return $days[$english_day] ?? $english_day;
    }

    public static function get_amelia_events_with_date($tag_name = 'cours formule basique') {
        global $wpdb;

        $query = "
            SELECT e.name, MIN(ea.periodStart) as periodStart
            FROM {$wpdb->prefix}amelia_events e
            JOIN {$wpdb->prefix}amelia_events_tags et ON e.id = et.eventId
            LEFT JOIN {$wpdb->prefix}amelia_events_periods ea ON e.id = ea.eventId
            WHERE et.name = %s
            GROUP BY e.id
            ORDER BY ea.periodStart ASC
        ";

        $results = $wpdb->get_results($wpdb->prepare($query, $tag_name));
        $events = [];

        foreach ($results as $event) {
            $utc = new \DateTimeZone('UTC');
            $paris = new \DateTimeZone('Europe/Paris');
            $event_date = new \DateTime($event->periodStart, $utc);
            $event_date->setTimezone($paris);

            $day = self::get_french_day($event_date->format('l'));
            $hour = $event_date->format('H:i');
            $full = $event->name . ' - ' . $day . ' ' . $hour;
            $events[$full] = $full;
        }

        return $events;
    }
}
