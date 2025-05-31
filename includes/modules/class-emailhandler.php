<?php
namespace FPR\Modules;

if (!defined('ABSPATH')) exit;

use FPR\Helpers\Logger;

class EmailHandler {
    public static function init() {
        // Envoyer un email après la validation d'une commande
        add_action('woocommerce_order_status_completed', [__CLASS__, 'send_confirmation_email'], 10, 1);
        add_action('woocommerce_order_status_processing', [__CLASS__, 'send_confirmation_email'], 10, 1);
    }

    /**
     * Envoie un email de confirmation à l'utilisateur et à l'administrateur
     * 
     * @param int $order_id ID de la commande
     */
    public static function send_confirmation_email($order_id) {
        // Vérifier si l'email a déjà été envoyé pour cette commande
        $email_sent = get_post_meta($order_id, '_fpr_confirmation_email_sent', true);
        if ($email_sent) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            Logger::log("[Email] Erreur: Impossible de trouver la commande #$order_id");
            return;
        }

        // Récupérer les informations de la commande
        $customer_email = $order->get_billing_email();
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $order_total = $order->get_total();
        $order_date = $order->get_date_created()->date_i18n('d/m/Y à H:i');
        $payment_method = $order->get_payment_method_title();

        // Récupérer l'ID de l'utilisateur
        $user_id = $order->get_user_id();

        // Récupérer les informations d'abonnement Stripe si disponibles
        $stripe_subscription_id = '';
        $payment_plan_info = '';

        if ($user_id) {
            global $wpdb;
            $subscription = $wpdb->get_row($wpdb->prepare(
                "SELECT s.*, p.name as plan_name, p.frequency, p.installments 
                FROM {$wpdb->prefix}fpr_customer_subscriptions s
                LEFT JOIN {$wpdb->prefix}fpr_payment_plans p ON s.payment_plan_id = p.id
                WHERE s.user_id = %d AND s.order_id = %d
                ORDER BY s.id DESC LIMIT 1",
                $user_id, $order_id
            ));

            if ($subscription) {
                $stripe_subscription_id = $subscription->stripe_subscription_id;

                // Formater les informations du plan de paiement
                $frequency_label = [
                    'hourly' => 'horaire',
                    'daily' => 'quotidien',
                    'weekly' => 'hebdomadaire',
                    'monthly' => 'mensuel',
                    'quarterly' => 'trimestriel',
                    'annual' => 'annuel'
                ];
                $frequency = isset($frequency_label[$subscription->frequency]) ? $frequency_label[$subscription->frequency] : $subscription->frequency;

                $payment_plan_info = sprintf(
                    "Plan de paiement: %s (%d versements %s)\nMontant par versement: %s",
                    $subscription->plan_name,
                    $subscription->installments,
                    $frequency ? "- $frequency" : "",
                    wc_price($subscription->installment_amount)
                );

                Logger::log("[Email] Informations d'abonnement trouvées pour la commande #$order_id: Plan={$subscription->plan_name}, Stripe ID={$stripe_subscription_id}");
            } else {
                Logger::log("[Email] Aucun abonnement trouvé pour l'utilisateur #$user_id et la commande #$order_id");
            }
        }

        // Récupérer la saison sélectionnée
        $saison_tag = get_post_meta($order_id, '_fpr_selected_saison', true);

        // Récupérer le nom de la saison à partir du tag
        global $wpdb;
        $saison_name = '';
        if (!empty($saison_tag)) {
            $saison = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}fpr_saisons WHERE tag = %s",
                $saison_tag
            ));
            $saison_name = $saison ? $saison->name : $saison_tag;
        }

        // Récupérer les cours sélectionnés
        $courses = [];
        foreach ($order->get_items() as $item) {
            // Récupérer les cours choisis dans les meta
            foreach ($item->get_meta_data() as $meta) {
                if (strpos($meta->key, 'Cours sélectionné') !== false) {
                    $courses[] = $meta->value;
                }
            }
        }

        // Construire le contenu de l'email
        $subject = 'Confirmation de votre inscription aux cours - ' . get_bloginfo('name');

        $body = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';
        $body .= '<h2 style="color: #3498db; border-bottom: 1px solid #eee; padding-bottom: 10px;">Confirmation de votre inscription</h2>';
        $body .= '<p>Bonjour ' . esc_html($customer_name) . ',</p>';
        $body .= '<p>Nous vous confirmons votre inscription aux cours pour la saison <strong>' . esc_html($saison_name) . '</strong>.</p>';

        if (!empty($courses)) {
            $body .= '<h3 style="margin-top: 20px; color: #333;">Cours sélectionnés :</h3>';
            $body .= '<ul style="background-color: #f9f9f9; padding: 15px; border-radius: 5px;">';
            foreach ($courses as $course) {
                $body .= '<li style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;">' . esc_html($course) . '</li>';
            }
            $body .= '</ul>';
        }

        $body .= '<div style="margin-top: 20px; background-color: #f9f9f9; padding: 15px; border-radius: 5px;">';
        $body .= '<h3 style="margin-top: 0; color: #333;">Détails de la commande :</h3>';
        $body .= '<p><strong>Numéro de commande :</strong> #' . $order_id . '</p>';
        $body .= '<p><strong>Date :</strong> ' . $order_date . '</p>';
        $body .= '<p><strong>Total :</strong> ' . wc_price($order_total) . '</p>';
        $body .= '<p><strong>Méthode de paiement :</strong> ' . esc_html($payment_method) . '</p>';

        // Ajouter les informations d'abonnement si disponibles
        if (!empty($payment_plan_info)) {
            $body .= '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">';
            $body .= '<h4 style="margin-top: 0; color: #333;">Informations d\'abonnement :</h4>';
            $body .= '<p>' . nl2br(esc_html($payment_plan_info)) . '</p>';

            // Ajouter l'ID d'abonnement Stripe si disponible (uniquement pour l'administrateur)
            if (!empty($stripe_subscription_id)) {
                $body .= '<p><strong>ID d\'abonnement Stripe :</strong> ' . esc_html($stripe_subscription_id) . '</p>';
            }

            $body .= '</div>';
        }

        $body .= '</div>';

        $body .= '<p style="margin-top: 20px;">Nous vous remercions pour votre confiance et sommes ravis de vous accueillir pour cette nouvelle saison.</p>';
        $body .= '<p>L\'équipe de ' . get_bloginfo('name') . '</p>';
        $body .= '</div>';

        // Envoyer l'email au client
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Log des informations avant l'envoi
        Logger::log("[Email] 📧 Tentative d'envoi d'email à $customer_email pour la commande #$order_id");
        Logger::log("[Email] 📋 Sujet: $subject");
        Logger::log("[Email] 📋 Headers: " . print_r($headers, true));

        // Vérifier que l'adresse email est valide
        if (!is_email($customer_email)) {
            Logger::log("[Email] ❌ ERREUR: Adresse email client invalide: $customer_email");
            $customer_email = get_option('admin_email'); // Fallback à l'email admin
            Logger::log("[Email] 🔄 Utilisation de l'email admin comme fallback: $customer_email");
        }

        $sent_to_customer = wp_mail($customer_email, $subject, $body, $headers);

        // Préparer une version spéciale de l'email pour l'administrateur avec plus d'informations
        $admin_body = $body;

        // Ajouter des informations supplémentaires pour l'administrateur
        if (!empty($stripe_subscription_id)) {
            $admin_info = '<div style="margin-top: 20px; background-color: #f8f8f8; padding: 15px; border-radius: 5px; border-left: 4px solid #e74c3c;">';
            $admin_info .= '<h3 style="margin-top: 0; color: #333;">Informations techniques (admin uniquement) :</h3>';
            $admin_info .= '<p><strong>ID d\'abonnement Stripe :</strong> ' . esc_html($stripe_subscription_id) . '</p>';
            $admin_info .= '<p><strong>ID utilisateur WordPress :</strong> ' . $user_id . '</p>';

            // Ajouter un lien vers le tableau de bord Stripe si disponible
            if (!empty($stripe_subscription_id)) {
                $stripe_dashboard_url = 'https://dashboard.stripe.com/subscriptions/' . $stripe_subscription_id;
                $admin_info .= '<p><a href="' . esc_url($stripe_dashboard_url) . '" target="_blank" style="color: #3498db;">Voir l\'abonnement dans Stripe</a></p>';
            }

            $admin_info .= '</div>';

            // Insérer avant la dernière balise div fermante
            $admin_body = str_replace('</div>', $admin_info . '</div>', $admin_body);
        }

        // Envoyer une copie à l'administrateur
        $admin_email = get_option('admin_email');
        $admin_subject = '[Copie] Nouvelle inscription aux cours - ' . $customer_name;

        // Vérifier que l'adresse email admin est valide
        if (!is_email($admin_email)) {
            Logger::log("[Email] ❌ ERREUR: Adresse email admin invalide: $admin_email");
            $admin_email = 'admin@' . parse_url(home_url(), PHP_URL_HOST); // Fallback
            Logger::log("[Email] 🔄 Utilisation d'une adresse email admin générée: $admin_email");
        }

        Logger::log("[Email] 📧 Tentative d'envoi de copie à $admin_email");
        $sent_to_admin = wp_mail($admin_email, $admin_subject, $admin_body, $headers);

        // Enregistrer que l'email a été envoyé
        update_post_meta($order_id, '_fpr_confirmation_email_sent', 'yes');

        // Journaliser le résultat
        if ($sent_to_customer) {
            Logger::log("[Email] ✅ Email de confirmation envoyé à $customer_email pour la commande #$order_id");
        } else {
            Logger::log("[Email] ❌ ERREUR: Échec de l'envoi de l'email à $customer_email pour la commande #$order_id");
            // Essayer de diagnostiquer le problème
            global $phpmailer;
            if (isset($phpmailer->ErrorInfo) && !empty($phpmailer->ErrorInfo)) {
                Logger::log("[Email] 🔍 Erreur PHPMailer: " . $phpmailer->ErrorInfo);
            }
        }

        if ($sent_to_admin) {
            Logger::log("[Email] ✅ Copie de l'email envoyée à l'administrateur ($admin_email)");
        } else {
            Logger::log("[Email] ❌ ERREUR: Échec de l'envoi de la copie à l'administrateur ($admin_email)");
            // Essayer de diagnostiquer le problème
            global $phpmailer;
            if (isset($phpmailer->ErrorInfo) && !empty($phpmailer->ErrorInfo)) {
                Logger::log("[Email] 🔍 Erreur PHPMailer: " . $phpmailer->ErrorInfo);
            }
        }
    }
}
