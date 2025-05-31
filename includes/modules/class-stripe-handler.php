<?php
namespace FPR\Modules;

if (!defined('ABSPATH')) exit;

/**
 * Classe pour gérer l'intégration avec Stripe pour les paiements récurrents
 */
class StripeHandler {
    /**
     * Initialise les hooks pour l'intégration Stripe
     */
    public static function init() {
        // Hook pour créer un abonnement Stripe après la création d'une commande
        add_action('woocommerce_checkout_order_processed', [self::class, 'process_subscription'], 10, 3);

        // Hook pour créer un abonnement Stripe lorsque le statut de la commande change à "processing" ou "completed"
        add_action('woocommerce_order_status_changed', [self::class, 'handle_order_status_change'], 10, 4);

        // Hook pour gérer les webhooks Stripe
        add_action('woocommerce_api_fpr_stripe_webhook', [self::class, 'handle_webhook']);

        // Hook pour afficher les informations d'abonnement dans le compte client
        add_action('woocommerce_account_dashboard', [self::class, 'display_customer_subscriptions']);

        // Enregistrer le cron job pour les paiements récurrents
        if (!wp_next_scheduled('fpr_process_recurring_payments')) {
            wp_schedule_event(time(), 'daily', 'fpr_process_recurring_payments');
        }

        // Hook pour traiter les paiements récurrents
        add_action('fpr_process_recurring_payments', [self::class, 'process_recurring_payments']);
    }

    /**
     * Traite la création d'un abonnement Stripe après la commande
     * 
     * @param int $order_id ID de la commande
     * @param array $posted_data Données du formulaire de checkout
     * @param WC_Order $order Objet commande
     */
    public static function process_subscription($order_id, $posted_data, $order) {
        \FPR\Helpers\Logger::log("[StripeHandler] Commande #$order_id créée, mais l'abonnement sera créé uniquement après vérification du paiement");

        // Nous ne créons plus l'abonnement ici, mais plutôt lorsque le statut de la commande change à "processing" ou "completed"
        // Voir la méthode handle_order_status_change
    }

    /**
     * Gère le changement de statut d'une commande et crée un abonnement si le paiement est vérifié
     * 
     * @param int $order_id ID de la commande
     * @param string $old_status Ancien statut
     * @param string $new_status Nouveau statut
     * @param WC_Order $order Objet commande
     */
    public static function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        // On ne traite que les commandes dont le statut change à "processing" ou "completed"
        if (!in_array($new_status, ['processing', 'completed'])) {
            return;
        }

        \FPR\Helpers\Logger::log("[StripeHandler] Commande #$order_id: statut changé de $old_status à $new_status");

        // Vérifier si un abonnement existe déjà pour cette commande
        global $wpdb;
        $existing_subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT id, payment_plan_id FROM {$wpdb->prefix}fpr_customer_subscriptions WHERE order_id = %d",
            $order_id
        ));

        // Vérifier si un plan de paiement a été sélectionné
        $payment_plan_id = get_post_meta($order_id, '_fpr_selected_payment_plan', true);
        \FPR\Helpers\Logger::log("[StripeHandler] Tentative de récupération du plan de paiement pour la commande #$order_id: " . ($payment_plan_id ? $payment_plan_id : "non trouvé"));

        if ($existing_subscription) {
            \FPR\Helpers\Logger::log("[StripeHandler] Un abonnement existe déjà pour la commande #$order_id (ID: {$existing_subscription->id})");

            // Si un plan de paiement a été sélectionné et qu'il est différent de celui dans l'abonnement existant
            if (!empty($payment_plan_id) && $existing_subscription->payment_plan_id != $payment_plan_id) {
                \FPR\Helpers\Logger::log("[StripeHandler] Mise à jour du plan de paiement pour l'abonnement #{$existing_subscription->id}: {$existing_subscription->payment_plan_id} -> {$payment_plan_id}");

                // Récupérer les détails du plan de paiement pour le log
                $payment_plan = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
                    $payment_plan_id
                ));

                if ($payment_plan) {
                    \FPR\Helpers\Logger::log("[StripeHandler] Détails du nouveau plan: Nom={$payment_plan->name}, Fréquence={$payment_plan->frequency}, Terme={$payment_plan->term}, Versements={$payment_plan->installments}");
                }

                $result = $wpdb->update(
                    $wpdb->prefix . 'fpr_customer_subscriptions',
                    ['payment_plan_id' => $payment_plan_id],
                    ['id' => $existing_subscription->id]
                );

                if ($result !== false) {
                    \FPR\Helpers\Logger::log("[StripeHandler] ✅ Plan de paiement mis à jour avec succès pour l'abonnement #{$existing_subscription->id}");

                    // Vérifier que la mise à jour a bien été effectuée
                    $updated_subscription = $wpdb->get_row($wpdb->prepare(
                        "SELECT payment_plan_id FROM {$wpdb->prefix}fpr_customer_subscriptions WHERE id = %d",
                        $existing_subscription->id
                    ));

                    if ($updated_subscription && $updated_subscription->payment_plan_id == $payment_plan_id) {
                        \FPR\Helpers\Logger::log("[StripeHandler] ✅ Vérification réussie: Plan de paiement correctement mis à jour à {$payment_plan_id}");
                    } else {
                        \FPR\Helpers\Logger::log("[StripeHandler] ⚠️ Vérification échouée: Le plan de paiement n'a pas été correctement mis à jour. Valeur actuelle: " . ($updated_subscription ? $updated_subscription->payment_plan_id : "inconnu"));
                    }
                } else {
                    \FPR\Helpers\Logger::log("[StripeHandler] ❌ Erreur lors de la mise à jour du plan de paiement: " . $wpdb->last_error);
                }
            } else {
                if (empty($payment_plan_id)) {
                    \FPR\Helpers\Logger::log("[StripeHandler] ℹ️ Aucun plan de paiement sélectionné, conservation du plan actuel: {$existing_subscription->payment_plan_id}");
                } else {
                    \FPR\Helpers\Logger::log("[StripeHandler] ℹ️ Le plan de paiement sélectionné ({$payment_plan_id}) est identique à celui de l'abonnement, aucune mise à jour nécessaire");
                }
            }
            return;
        }


        // Si aucun plan de paiement n'est trouvé, essayer de récupérer le plan par défaut
        if (empty($payment_plan_id)) {
            \FPR\Helpers\Logger::log("[StripeHandler] Aucun plan de paiement trouvé dans les meta données, tentative de récupération du plan par défaut");

            // Récupérer le plan par défaut
            global $wpdb;
            $default_plan_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}fpr_payment_plans WHERE is_default = 1 AND active = 1 LIMIT 1");

            if ($default_plan_id) {
                $payment_plan_id = $default_plan_id;
                \FPR\Helpers\Logger::log("[StripeHandler] Plan de paiement par défaut trouvé et utilisé: $payment_plan_id");

                // Enregistrer le plan par défaut dans les meta données de la commande
                update_post_meta($order_id, '_fpr_selected_payment_plan', $payment_plan_id);
                \FPR\Helpers\Logger::log("[StripeHandler] Plan de paiement par défaut enregistré dans les meta données de la commande");
            } else {
                \FPR\Helpers\Logger::log("[StripeHandler] Aucun plan de paiement par défaut trouvé");
                return;
            }
        }

        \FPR\Helpers\Logger::log("[StripeHandler] Plan de paiement trouvé pour la commande #$order_id: $payment_plan_id");

        // Vérifier si une saison a été sélectionnée
        $saison_tag = get_post_meta($order_id, '_fpr_selected_saison', true);
        if (empty($saison_tag)) {
            \FPR\Helpers\Logger::log("[StripeHandler] Aucune saison sélectionnée dans les meta données, tentative de récupération de la première saison disponible");

            // Récupérer la première saison disponible (dont la date de fin est dans le futur)
            global $wpdb;
            $first_saison = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}fpr_saisons WHERE end_date >= CURDATE() ORDER BY id ASC LIMIT 1");

            if ($first_saison) {
                $saison_tag = $first_saison->tag;
                \FPR\Helpers\Logger::log("[StripeHandler] Première saison disponible trouvée et utilisée: $saison_tag");

                // Enregistrer la saison dans les meta données de la commande
                update_post_meta($order_id, '_fpr_selected_saison', $saison_tag);
                \FPR\Helpers\Logger::log("[StripeHandler] Saison enregistrée dans les meta données de la commande");
            } else {
                \FPR\Helpers\Logger::log("[StripeHandler] Aucune saison disponible");
                return;
            }
        }

        \FPR\Helpers\Logger::log("[StripeHandler] Saison tag trouvé pour la commande #$order_id: $saison_tag");

        // Récupérer les détails du plan de paiement
        $payment_plan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
            $payment_plan_id
        ));

        if (!$payment_plan) {
            \FPR\Helpers\Logger::log("[StripeHandler] Plan de paiement #$payment_plan_id non trouvé pour la commande #$order_id");
            return;
        }

        // Récupérer les détails de la saison
        $saison = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_saisons WHERE tag = %s",
            $saison_tag
        ));

        if (!$saison) {
            \FPR\Helpers\Logger::log("[StripeHandler] Saison avec tag '$saison_tag' non trouvée pour la commande #$order_id");
            return;
        }

        \FPR\Helpers\Logger::log("[StripeHandler] Saison trouvée pour la commande #$order_id: ID={$saison->id}, Nom={$saison->name}");

        // Vérifier si le paiement a été effectué avec Stripe
        $payment_method = $order->get_payment_method();
        \FPR\Helpers\Logger::log("[StripeHandler] Méthode de paiement pour la commande #$order_id: $payment_method");

        // Vérifier si la méthode de paiement est Stripe
        $is_stripe_payment = ($payment_method === 'stripe' || $payment_method === 'stripe_cc');
        \FPR\Helpers\Logger::log("[StripeHandler] Est-ce un paiement Stripe? " . ($is_stripe_payment ? 'Oui' : 'Non'));

        if (!$is_stripe_payment) {
            // Ajouter une note à la commande
            $order->add_order_note('Le paiement récurrent nécessite Stripe comme méthode de paiement.');
            \FPR\Helpers\Logger::log("[StripeHandler] La méthode de paiement n'est pas Stripe pour la commande #$order_id");

            // Créer quand même un abonnement local sans Stripe
            \FPR\Helpers\Logger::log("[StripeHandler] Création d'un abonnement local sans Stripe pour la commande #$order_id");

            try {
                // Calculer le montant de chaque versement
                $total_amount = $order->get_total();
                $installments = $payment_plan->installments;
                $installment_amount = round($total_amount / $installments, 2);

                // Créer l'abonnement dans la base de données locale
                $subscription_id = self::create_local_subscription(
                    $order->get_user_id(),
                    $order_id,
                    $payment_plan_id,
                    $saison->id,
                    $total_amount,
                    $installment_amount,
                    $installments
                );

                if (!$subscription_id) {
                    throw new \Exception('Erreur lors de la création de l\'abonnement local.');
                }

                // Ajouter une note à la commande
                $order->add_order_note(
                    sprintf(
                        'Abonnement local créé avec succès. Plan: %s, Montant: %s, Versements: %d x %s',
                        $payment_plan->name,
                        wc_price($total_amount),
                        $installments,
                        wc_price($installment_amount)
                    )
                );

                \FPR\Helpers\Logger::log("[StripeHandler] Abonnement local créé avec succès pour la commande #$order_id (ID: $subscription_id)");

            } catch (\Exception $e) {
                // Ajouter une note d'erreur à la commande
                $order->add_order_note('Erreur lors de la création de l\'abonnement local: ' . $e->getMessage());
                \FPR\Helpers\Logger::log("[StripeHandler] Erreur lors de la création de l'abonnement local: " . $e->getMessage());
            }

            return;
        }

        \FPR\Helpers\Logger::log("[StripeHandler] Création d'un abonnement Stripe pour la commande #$order_id");

        try {
            // Calculer le montant de chaque versement
            $total_amount = $order->get_total();
            $installments = $payment_plan->installments;
            $installment_amount = round($total_amount / $installments, 2);

            // Déterminer l'intervalle de facturation
            $interval = 'month';
            $interval_count = 1;

            switch ($payment_plan->frequency) {
                case 'hourly':
                    $interval = 'hour';
                    $interval_count = 1;
                    break;
                case 'daily':
                    $interval = 'day';
                    $interval_count = 1;
                    break;
                case 'weekly':
                    $interval = 'week';
                    $interval_count = 1;
                    break;
                case 'monthly':
                    $interval = 'month';
                    $interval_count = 1;
                    break;
                case 'quarterly':
                    $interval = 'month';
                    $interval_count = 3;
                    break;
                case 'annual':
                    $interval = 'year';
                    $interval_count = 1;
                    break;
            }

            // Créer l'abonnement dans la base de données locale
            $subscription_id = self::create_local_subscription(
                $order->get_user_id(),
                $order_id,
                $payment_plan_id,
                $saison->id,
                $total_amount,
                $installment_amount,
                $installments
            );

            if (!$subscription_id) {
                throw new \Exception('Erreur lors de la création de l\'abonnement local.');
            }

            // Ajouter une note à la commande
            $order->add_order_note(
                sprintf(
                    'Abonnement créé avec succès. Plan: %s, Montant: %s, Versements: %d x %s',
                    $payment_plan->name,
                    wc_price($total_amount),
                    $installments,
                    wc_price($installment_amount)
                )
            );

            // Créer l'abonnement dans Stripe
            if (!class_exists('\Stripe\Stripe')) {
                if (function_exists('WC')) {
                    // Utiliser la bibliothèque Stripe de WooCommerce si disponible
                    $wc_stripe = WC()->payment_gateways->payment_gateways()['stripe'];
                    if ($wc_stripe && method_exists($wc_stripe, 'get_stripe_api')) {
                        $wc_stripe->get_stripe_api();
                    } else {
                        throw new \Exception("La bibliothèque Stripe n'est pas disponible");
                    }
                } else {
                    throw new \Exception("La bibliothèque Stripe n'est pas disponible");
                }
            }

            // Récupérer la clé secrète Stripe
            $stripe_settings = get_option('woocommerce_stripe_settings', []);
            $secret_key = isset($stripe_settings['secret_key']) ? $stripe_settings['secret_key'] : '';

            if (empty($secret_key)) {
                throw new \Exception("Clé secrète Stripe non configurée");
            }

            // Configurer Stripe avec la clé secrète
            \Stripe\Stripe::setApiKey($secret_key);

            // Récupérer le client Stripe
            $user_id = $order->get_user_id();
            \FPR\Helpers\Logger::log("[StripeHandler] 🔍 Recherche de l'ID client Stripe pour l'utilisateur ID=$user_id");

            $customer_id = get_user_meta($user_id, '_stripe_customer_id', true);
            \FPR\Helpers\Logger::log("[StripeHandler] " . (!empty($customer_id) ? "✅ ID client Stripe trouvé: $customer_id" : "❌ ID client Stripe non trouvé"));

            if (empty($customer_id)) {
                // Essayer de récupérer l'ID client Stripe via d'autres méthodes
                $user_email = $order->get_billing_email();
                \FPR\Helpers\Logger::log("[StripeHandler] 🔍 Tentative de récupération de l'ID client Stripe via l'email: $user_email");

                // Vérifier si WooCommerce Stripe est actif et utiliser ses fonctions si disponible
                if (function_exists('wc_stripe_get_customer_id_from_meta')) {
                    $customer_id = wc_stripe_get_customer_id_from_meta($user_id);
                    \FPR\Helpers\Logger::log("[StripeHandler] " . (!empty($customer_id) ? "✅ ID client Stripe trouvé via wc_stripe_get_customer_id_from_meta: $customer_id" : "❌ ID client Stripe non trouvé via wc_stripe_get_customer_id_from_meta"));
                }

                if (empty($customer_id)) {
                    throw new \Exception("ID client Stripe non trouvé pour l'utilisateur");
                }
            }

            // Créer un produit pour l'abonnement
            $product = \Stripe\Product::create([
                'name' => 'Abonnement ' . $payment_plan->name . ' - Commande #' . $order_id,
                'type' => 'service',
            ]);

            // Créer un plan de prix
            $price = \Stripe\Price::create([
                'product' => $product->id,
                'unit_amount' => round($installment_amount * 100), // Convertir en centimes
                'currency' => 'eur',
                'recurring' => [
                    'interval' => $interval,
                    'interval_count' => $interval_count,
                ],
            ]);

            // Récupérer l'abonnement local pour obtenir les dates ajustées
            $local_subscription = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}fpr_customer_subscriptions WHERE id = %d",
                $subscription_id
            ));

            // Paramètres pour la création de l'abonnement Stripe
            $stripe_subscription_params = [
                'customer' => $customer_id,
                'items' => [
                    ['price' => $price->id],
                ],
                'metadata' => [
                    'order_id' => $order_id,
                    'payment_plan_id' => $payment_plan_id,
                    'saison_id' => $saison->id,
                    'total_amount' => $total_amount,
                    'installments' => $installments,
                ],
            ];

            // Si l'abonnement local a une date de début dans le futur, configurer la date de début pour Stripe
            if ($local_subscription && strtotime($local_subscription->start_date) > time()) {
                $stripe_subscription_params['billing_cycle_anchor'] = strtotime($local_subscription->start_date);
                $stripe_subscription_params['prorate'] = false;
                \FPR\Helpers\Logger::log("[StripeHandler] Configuration de la date de début Stripe: " . date('Y-m-d', $stripe_subscription_params['billing_cycle_anchor']));
            }

            // Si l'abonnement local a une date de fin, configurer la date de fin pour Stripe
            if ($local_subscription && !empty($local_subscription->end_date)) {
                $stripe_subscription_params['cancel_at'] = strtotime($local_subscription->end_date);
                \FPR\Helpers\Logger::log("[StripeHandler] Configuration de la date de fin Stripe: " . date('Y-m-d', $stripe_subscription_params['cancel_at']));
            }

            // Créer l'abonnement Stripe
            $stripe_subscription = \Stripe\Subscription::create($stripe_subscription_params);

            // S'assurer que l'ID Stripe est valide et bien formaté
            $stripe_id = sanitize_text_field($stripe_subscription->id);
            \FPR\Helpers\Logger::log("[StripeHandler] 🔍 ID Stripe obtenu: " . $stripe_id);

            if (empty($stripe_id)) {
                \FPR\Helpers\Logger::log("[StripeHandler] ⚠️ L'ID Stripe est vide, impossible de mettre à jour l'abonnement local");
            } else {
                // Mettre à jour l'abonnement local avec l'ID Stripe
                \FPR\Helpers\Logger::log("[StripeHandler] 🔄 Tentative de mise à jour de l'abonnement local ID=$subscription_id avec l'ID Stripe: " . $stripe_id);

                // Vérifier d'abord si l'abonnement existe
                $check_query = $wpdb->prepare("SELECT id, stripe_subscription_id FROM {$wpdb->prefix}fpr_customer_subscriptions WHERE id = %d", $subscription_id);
                $check_result = $wpdb->get_row($check_query);

                if (!$check_result) {
                    \FPR\Helpers\Logger::log("[StripeHandler] ❌ L'abonnement ID=$subscription_id n'existe pas");
                } else {
                    \FPR\Helpers\Logger::log("[StripeHandler] ✅ L'abonnement existe. ID Stripe actuel: " . ($check_result->stripe_subscription_id ? $check_result->stripe_subscription_id : "non défini"));

                    // Mettre à jour avec wpdb->update
                    $update_result = $wpdb->update(
                        $wpdb->prefix . 'fpr_customer_subscriptions',
                        ['stripe_subscription_id' => $stripe_id],
                        ['id' => $subscription_id]
                    );

                    if ($update_result === false) {
                        \FPR\Helpers\Logger::log("[StripeHandler] ❌ Erreur lors de la mise à jour de l'abonnement local: " . $wpdb->last_error);
                        \FPR\Helpers\Logger::log("[StripeHandler] ❌ Requête SQL: " . $wpdb->last_query);

                        // Essayer une mise à jour directe avec une requête SQL comme solution de secours
                        $direct_query = $wpdb->prepare(
                            "UPDATE {$wpdb->prefix}fpr_customer_subscriptions SET stripe_subscription_id = %s WHERE id = %d",
                            $stripe_id, $subscription_id
                        );
                        $direct_result = $wpdb->query($direct_query);
                        \FPR\Helpers\Logger::log("[StripeHandler] " . ($direct_result !== false ? "✅ Mise à jour directe réussie" : "❌ Échec de la mise à jour directe: " . $wpdb->last_error));
                    } elseif ($update_result === 0) {
                        \FPR\Helpers\Logger::log("[StripeHandler] ⚠️ Aucune ligne mise à jour. Cela peut être normal si l'ID Stripe est déjà défini avec la même valeur.");

                        // Vérifier si l'ID Stripe a été correctement enregistré malgré le résultat 0
                        $verify_query = $wpdb->prepare("SELECT stripe_subscription_id FROM {$wpdb->prefix}fpr_customer_subscriptions WHERE id = %d", $subscription_id);
                        $current_stripe_id = $wpdb->get_var($verify_query);

                        if ($current_stripe_id === $stripe_id) {
                            \FPR\Helpers\Logger::log("[StripeHandler] ✅ L'ID Stripe est correctement enregistré dans la base de données");
                        } else {
                            \FPR\Helpers\Logger::log("[StripeHandler] ⚠️ L'ID Stripe dans la base de données (" . $current_stripe_id . ") ne correspond pas à l'ID attendu (" . $stripe_id . ")");

                            // Forcer la mise à jour avec une requête SQL directe
                            $force_query = $wpdb->prepare(
                                "UPDATE {$wpdb->prefix}fpr_customer_subscriptions SET stripe_subscription_id = %s WHERE id = %d",
                                $stripe_id, $subscription_id
                            );
                            $force_result = $wpdb->query($force_query);
                            \FPR\Helpers\Logger::log("[StripeHandler] " . ($force_result !== false ? "✅ Mise à jour forcée réussie" : "❌ Échec de la mise à jour forcée: " . $wpdb->last_error));
                        }
                    } else {
                        \FPR\Helpers\Logger::log("[StripeHandler] ✅ Abonnement local mis à jour avec succès");
                    }
                }
            }

            \FPR\Helpers\Logger::log("[Stripe] Abonnement Stripe créé: " . $stripe_subscription->id);

        } catch (\Exception $e) {
            // Ajouter une note d'erreur à la commande
            $order->add_order_note('Erreur lors de la création de l\'abonnement: ' . $e->getMessage());
            \FPR\Helpers\Logger::log("[Stripe] Erreur: " . $e->getMessage());
        }
    }

    /**
     * Crée un abonnement dans la base de données locale
     * 
     * @param int $user_id ID de l'utilisateur
     * @param int $order_id ID de la commande
     * @param int $payment_plan_id ID du plan de paiement
     * @param int $saison_id ID de la saison
     * @param float $total_amount Montant total
     * @param float $installment_amount Montant de chaque versement
     * @param int $installments Nombre de versements
     * @return int|false ID de l'abonnement créé ou false en cas d'erreur
     */
    private static function create_local_subscription($user_id, $order_id, $payment_plan_id, $saison_id, $total_amount, $installment_amount, $installments) {
        global $wpdb;

        \FPR\Helpers\Logger::log("[StripeHandler] Création d'un abonnement local pour user_id: $user_id, order_id: $order_id");

        // Créer la table si elle n'existe pas
        self::create_subscriptions_table_if_not_exists();

        // Récupérer les détails de la saison
        $saison = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_saisons WHERE id = %d",
            $saison_id
        ));

        // Calculer les dates
        $current_date = date('Y-m-d');
        $start_date = $current_date;

        if ($saison) {
            \FPR\Helpers\Logger::log("[StripeHandler] Saison trouvée: ID={$saison->id}, Nom={$saison->name}, Début={$saison->start_date}, Fin={$saison->end_date}");

            // Si la date actuelle est avant le début de la saison, utiliser la date de début de la saison comme date de début
            if (strtotime($current_date) < strtotime($saison->start_date)) {
                $start_date = $saison->start_date;
                \FPR\Helpers\Logger::log("[StripeHandler] La date actuelle est avant le début de la saison, utilisation de la date de début de la saison: $start_date");
            } else {
                \FPR\Helpers\Logger::log("[StripeHandler] La date actuelle est après le début de la saison, utilisation de la date actuelle: $start_date");
            }
        } else {
            \FPR\Helpers\Logger::log("[StripeHandler] Saison ID=$saison_id non trouvée, utilisation de la date actuelle: $start_date");
        }

        // Récupérer les détails du plan de paiement pour déterminer la fréquence
        $payment_plan = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
            $payment_plan_id
        ));

        // Déterminer l'intervalle pour le prochain paiement en fonction de la fréquence
        $next_payment_interval = '+1 month'; // Par défaut
        $interval_days = 30; // Nombre de jours par défaut pour un mois

        if ($payment_plan) {
            switch ($payment_plan->frequency) {
                case 'hourly':
                    $next_payment_interval = '+1 hour';
                    $interval_days = 0.042; // 1/24 of a day
                    break;
                case 'daily':
                    $next_payment_interval = '+1 day';
                    $interval_days = 1;
                    break;
                case 'weekly':
                    $next_payment_interval = '+1 week';
                    $interval_days = 7;
                    break;
                case 'monthly':
                    $next_payment_interval = '+1 month';
                    $interval_days = 30;
                    break;
                case 'quarterly':
                    $next_payment_interval = '+3 months';
                    $interval_days = 90;
                    break;
                case 'annual':
                    $next_payment_interval = '+1 year';
                    $interval_days = 365;
                    break;
                default: // fallback to monthly
                    $next_payment_interval = '+1 month';
                    $interval_days = 30;
                    break;
            }
        }

        // Calculer le prochain paiement à partir de la date de début
        $next_payment_date = date('Y-m-d', strtotime($start_date . ' ' . $next_payment_interval));

        // Ajuster le nombre d'installments si la saison a déjà commencé
        $adjusted_installments = $installments;
        $installments_paid = 1; // Le premier paiement est déjà effectué

        if ($saison && $start_date !== $saison->start_date) {
            // Calculer le nombre de jours entre le début de la saison et aujourd'hui
            $days_since_season_start = (strtotime($current_date) - strtotime($saison->start_date)) / (60 * 60 * 24);

            // Calculer combien de paiements auraient dû être effectués depuis le début de la saison
            $payments_missed = floor($days_since_season_start / $interval_days);

            // Ajuster le nombre d'installments restants
            if ($payments_missed > 0) {
                $installments_paid = $payments_missed + 1; // +1 pour le paiement actuel
                \FPR\Helpers\Logger::log("[StripeHandler] La saison a commencé depuis $days_since_season_start jours, $payments_missed paiements manqués, installments_paid ajusté à $installments_paid");
            }
        }

        // Vérifier que le nombre d'installments payés ne dépasse pas le total
        if ($installments_paid > $installments) {
            $installments_paid = $installments;
            \FPR\Helpers\Logger::log("[StripeHandler] Le nombre d'installments payés a été plafonné au total: $installments_paid");
        }

        // Trouver ou créer un utilisateur FPR
        $fpr_user_id = null;
        $user = get_userdata($user_id);
        if ($user && !empty($user->user_email)) {
            // Utiliser FPRUser pour trouver ou créer un utilisateur FPR
            if (class_exists('\FPR\Modules\FPRUser')) {
                $fpr_user_id = \FPR\Modules\FPRUser::find_or_create_from_email(
                    $user->user_email,
                    $user->first_name,
                    $user->last_name,
                    get_user_meta($user_id, 'billing_phone', true)
                );
                \FPR\Helpers\Logger::log("[StripeHandler] Utilisateur FPR trouvé ou créé: ID=$fpr_user_id");
            }
        }

        // Insérer l'abonnement
        $data = [
            'user_id' => $user_id,
            'order_id' => $order_id,
            'payment_plan_id' => $payment_plan_id,
            'saison_id' => $saison_id,
            'stripe_subscription_id' => null, // Initialiser à null, sera mis à jour plus tard
            'status' => 'active',
            'start_date' => $start_date,
            'next_payment_date' => $next_payment_date,
            'total_amount' => $total_amount,
            'installment_amount' => $installment_amount,
            'installments_paid' => $installments_paid, // Utiliser la valeur ajustée
        ];

        // Ajouter la date de fin si tous les paiements sont effectués
        if ($installments_paid >= $installments) {
            $data['status'] = 'completed';
            $data['end_date'] = date('Y-m-d');
            \FPR\Helpers\Logger::log("[StripeHandler] Tous les paiements sont effectués, l'abonnement est marqué comme terminé");
        } else if ($saison) {
            // Vérifier si la date de fin de la saison est définie et dans le futur
            if (!empty($saison->end_date) && strtotime($saison->end_date) > time()) {
                // Ajouter la date de fin de la saison comme date de fin de l'abonnement
                $data['end_date'] = $saison->end_date;
                \FPR\Helpers\Logger::log("[StripeHandler] Date de fin de l'abonnement définie à la date de fin de la saison: {$saison->end_date}");
            }
        }

        // Ajouter l'ID de l'utilisateur FPR s'il existe
        if ($fpr_user_id) {
            $data['fpr_user_id'] = $fpr_user_id;
            \FPR\Helpers\Logger::log("[StripeHandler] Ajout de l'ID utilisateur FPR à l'abonnement: $fpr_user_id");
        }

        \FPR\Helpers\Logger::log("[StripeHandler] Insertion dans la table {$wpdb->prefix}fpr_customer_subscriptions");
        \FPR\Helpers\Logger::log("[StripeHandler] Données de l'abonnement: " . json_encode([
            'user_id' => $user_id,
            'fpr_user_id' => $fpr_user_id,
            'order_id' => $order_id,
            'payment_plan_id' => $payment_plan_id,
            'saison_id' => $saison_id,
            'start_date' => $start_date,
            'next_payment_date' => $next_payment_date,
            'end_date' => isset($data['end_date']) ? $data['end_date'] : 'non définie',
            'status' => $data['status'],
            'total_amount' => $total_amount,
            'installment_amount' => $installment_amount,
            'installments_paid' => $installments_paid,
            'installments_total' => $installments
        ]));

        // Vérifier que la table existe avant d'insérer
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}fpr_customer_subscriptions'") === $wpdb->prefix . 'fpr_customer_subscriptions';
        if (!$table_exists) {
            \FPR\Helpers\Logger::log("[StripeHandler] ❌ ERREUR: La table {$wpdb->prefix}fpr_customer_subscriptions n'existe pas");
            // Créer la table
            self::create_subscriptions_table_if_not_exists();

            // Vérifier à nouveau
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}fpr_customer_subscriptions'") === $wpdb->prefix . 'fpr_customer_subscriptions';
            if (!$table_exists) {
                \FPR\Helpers\Logger::log("[StripeHandler] ❌ ERREUR: Impossible de créer la table {$wpdb->prefix}fpr_customer_subscriptions");
                return false;
            }
        }

        // Vérifier si la colonne fpr_user_id existe dans la table
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}fpr_customer_subscriptions LIKE 'fpr_user_id'");
        if (empty($column_exists)) {
            \FPR\Helpers\Logger::log("[StripeHandler] ⚠️ La colonne fpr_user_id n'existe pas dans la table, tentative d'ajout");

            // Ajouter la colonne fpr_user_id
            $wpdb->query("ALTER TABLE {$wpdb->prefix}fpr_customer_subscriptions ADD COLUMN fpr_user_id mediumint(9) NULL AFTER user_id");

            // Vérifier si la colonne a été ajoutée
            $column_added = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}fpr_customer_subscriptions LIKE 'fpr_user_id'");
            if (empty($column_added)) {
                \FPR\Helpers\Logger::log("[StripeHandler] ❌ ERREUR: Impossible d'ajouter la colonne fpr_user_id");
                // Supprimer la clé fpr_user_id du tableau data pour éviter une erreur lors de l'insertion
                if (isset($data['fpr_user_id'])) {
                    unset($data['fpr_user_id']);
                }
            } else {
                \FPR\Helpers\Logger::log("[StripeHandler] ✅ Colonne fpr_user_id ajoutée avec succès");
            }
        }

        // Insérer l'abonnement
        $result = $wpdb->insert(
            $wpdb->prefix . 'fpr_customer_subscriptions',
            $data
        );

        if ($result === false) {
            \FPR\Helpers\Logger::log("[StripeHandler] ❌ ERREUR lors de la création de l'abonnement local: " . $wpdb->last_error);
            return false;
        }

        $insert_id = $wpdb->insert_id;
        \FPR\Helpers\Logger::log("[StripeHandler] Abonnement local créé avec succès. ID: $insert_id");

        // Ensure user_id is synced to fpr_customers table
        if (class_exists('\FPR\Modules\FPRAmelia')) {
            // Get user email
            $user = get_userdata($user_id);
            if ($user && !empty($user->user_email)) {
                \FPR\Modules\FPRAmelia::update_customer_user_ids($user->user_email);
                \FPR\Helpers\Logger::log("[StripeHandler] Synced user_id to fpr_customers for email: {$user->user_email}");
            }
        }

        return $insert_id;
    }

    /**
     * Crée la table des abonnements si elle n'existe pas
     * 
     * @return bool True si la table existe ou a été créée avec succès, false sinon
     */
    private static function create_subscriptions_table_if_not_exists() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'fpr_customer_subscriptions';
        $charset_collate = $wpdb->get_charset_collate();

        \FPR\Helpers\Logger::log("[StripeHandler] 🔍 Vérification/création de la table $table_name");

        // Vérifier si la table existe déjà
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if ($table_exists) {
            \FPR\Helpers\Logger::log("[StripeHandler] ✅ La table $table_name existe déjà");
            return true;
        }

        \FPR\Helpers\Logger::log("[StripeHandler] 🔄 La table $table_name n'existe pas, tentative de création");

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            order_id bigint(20) NOT NULL,
            payment_plan_id mediumint(9) NOT NULL,
            saison_id mediumint(9) NOT NULL,
            stripe_subscription_id varchar(255),
            status varchar(50) NOT NULL DEFAULT 'active',
            start_date date NOT NULL,
            next_payment_date date,
            end_date date,
            total_amount decimal(10,2) NOT NULL,
            installment_amount decimal(10,2) NOT NULL,
            installments_paid int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY payment_plan_id (payment_plan_id),
            KEY saison_id (saison_id)
        ) $charset_collate;";


        // Utiliser try/catch pour capturer les erreurs
        try {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql);
        } catch (\Exception $e) {
            \FPR\Helpers\Logger::log("[StripeHandler] ❌ ERREUR lors de la création de la table: " . $e->getMessage());
            return false;
        }

        // Vérifier si la table existe maintenant
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        \FPR\Helpers\Logger::log("[StripeHandler] " . ($table_exists ? "✅ La table $table_name a été créée avec succès" : "❌ ERREUR: La table $table_name n'a pas été créée"));

        return $table_exists;
    }

    /**
     * Gère les webhooks Stripe
     */
    public static function handle_webhook() {
        \FPR\Helpers\Logger::log("[Stripe] Webhook reçu");

        // Récupérer le payload du webhook
        $payload = @file_get_contents('php://input');
        $event = null;

        // Vérifier que le payload est valide
        if (empty($payload)) {
            \FPR\Helpers\Logger::log("[Stripe] Erreur: Payload vide");
            status_header(400);
            exit;
        }

        try {
            // Vérifier que la bibliothèque Stripe est disponible
            if (!class_exists('\Stripe\Stripe')) {
                if (function_exists('WC')) {
                    // Utiliser la bibliothèque Stripe de WooCommerce si disponible
                    $wc_stripe = WC()->payment_gateways->payment_gateways()['stripe'];
                    if ($wc_stripe && method_exists($wc_stripe, 'get_stripe_api')) {
                        $wc_stripe->get_stripe_api();
                    } else {
                        throw new \Exception("La bibliothèque Stripe n'est pas disponible");
                    }
                } else {
                    throw new \Exception("La bibliothèque Stripe n'est pas disponible");
                }
            }

            // Récupérer la clé secrète Stripe
            $stripe_settings = get_option('woocommerce_stripe_settings', []);
            $secret_key = isset($stripe_settings['secret_key']) ? $stripe_settings['secret_key'] : '';
            $webhook_secret = isset($stripe_settings['webhook_secret']) ? $stripe_settings['webhook_secret'] : '';

            if (empty($secret_key)) {
                \FPR\Helpers\Logger::log("[Stripe] Erreur: Clé secrète Stripe non configurée");
                status_header(500);
                exit;
            }

            // Configurer Stripe avec la clé secrète
            \Stripe\Stripe::setApiKey($secret_key);

            // Vérifier la signature du webhook si un secret est configuré
            if (!empty($webhook_secret)) {
                $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
                $event = \Stripe\Webhook::constructEvent(
                    $payload, $sig_header, $webhook_secret
                );
            } else {
                // Si pas de secret configuré, on fait confiance au payload
                $event = json_decode($payload);
            }

            // Traiter l'événement
            if ($event) {
                self::process_webhook_event($event);
            }

            status_header(200);
            exit;

        } catch (\UnexpectedValueException $e) {
            // Signature invalide
            \FPR\Helpers\Logger::log("[Stripe] Erreur de signature: " . $e->getMessage());
            status_header(400);
            exit;
        } catch (\Exception $e) {
            // Autre erreur
            \FPR\Helpers\Logger::log("[Stripe] Erreur: " . $e->getMessage());
            status_header(500);
            exit;
        }
    }

    /**
     * Traite les événements Stripe
     * 
     * @param object $event L'événement Stripe
     */
    private static function process_webhook_event($event) {
        global $wpdb;

        \FPR\Helpers\Logger::log("[Stripe] Traitement de l'événement: " . $event->type);

        switch ($event->type) {
            case 'invoice.payment_succeeded':
                // Paiement réussi
                $subscription_id = $event->data->object->subscription;
                $amount = $event->data->object->amount_paid / 100; // Convertir en euros

                // Mettre à jour l'abonnement local
                $subscription = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}fpr_customer_subscriptions 
                     WHERE stripe_subscription_id = %s",
                    $subscription_id
                ));

                if ($subscription) {
                    // Récupérer les détails du plan de paiement pour déterminer la fréquence
                    $payment_plan = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
                        $subscription->payment_plan_id
                    ));

                    // Déterminer l'intervalle pour le prochain paiement en fonction de la fréquence
                    $next_payment_interval = '+1 month'; // Par défaut

                    if ($payment_plan) {
                        switch ($payment_plan->frequency) {
                            case 'hourly':
                                $next_payment_interval = '+1 hour';
                                break;
                            case 'daily':
                                $next_payment_interval = '+1 day';
                                break;
                            case 'weekly':
                                $next_payment_interval = '+1 week';
                                break;
                            case 'monthly':
                                $next_payment_interval = '+1 month';
                                break;
                            case 'quarterly':
                                $next_payment_interval = '+3 months';
                                break;
                            case 'annual':
                                $next_payment_interval = '+1 year';
                                break;
                            default: // fallback to monthly
                                $next_payment_interval = '+1 month';
                                break;
                        }
                    }

                    // Incrémenter le nombre de versements payés
                    $wpdb->update(
                        $wpdb->prefix . 'fpr_customer_subscriptions',
                        [
                            'installments_paid' => $subscription->installments_paid + 1,
                            'status' => 'active',
                            'next_payment_date' => date('Y-m-d', strtotime($next_payment_interval))
                        ],
                        ['id' => $subscription->id]
                    );

                    \FPR\Helpers\Logger::log("[Stripe] Paiement réussi pour l'abonnement #" . $subscription->id);

                    // Si tous les versements sont payés, marquer comme terminé
                    if ($subscription->installments_paid + 1 >= $subscription->installments) {
                        $wpdb->update(
                            $wpdb->prefix . 'fpr_customer_subscriptions',
                            [
                                'status' => 'completed',
                                'end_date' => date('Y-m-d')
                            ],
                            ['id' => $subscription->id]
                        );
                        \FPR\Helpers\Logger::log("[Stripe] Abonnement #" . $subscription->id . " terminé");
                    }
                }
                break;

            case 'invoice.payment_failed':
                // Paiement échoué
                $subscription_id = $event->data->object->subscription;

                // Mettre à jour l'abonnement local
                $subscription = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}fpr_customer_subscriptions 
                     WHERE stripe_subscription_id = %s",
                    $subscription_id
                ));

                if ($subscription) {
                    $wpdb->update(
                        $wpdb->prefix . 'fpr_customer_subscriptions',
                        ['status' => 'payment_failed'],
                        ['id' => $subscription->id]
                    );

                    \FPR\Helpers\Logger::log("[Stripe] Paiement échoué pour l'abonnement #" . $subscription->id);
                }
                break;

            case 'customer.subscription.deleted':
                // Abonnement annulé
                $subscription_id = $event->data->object->id;

                // Mettre à jour l'abonnement local
                $subscription = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}fpr_customer_subscriptions 
                     WHERE stripe_subscription_id = %s",
                    $subscription_id
                ));

                if ($subscription) {
                    $wpdb->update(
                        $wpdb->prefix . 'fpr_customer_subscriptions',
                        [
                            'status' => 'cancelled',
                            'end_date' => date('Y-m-d')
                        ],
                        ['id' => $subscription->id]
                    );

                    \FPR\Helpers\Logger::log("[Stripe] Abonnement #" . $subscription->id . " annulé");
                }
                break;

            default:
                \FPR\Helpers\Logger::log("[Stripe] Événement non traité: " . $event->type);
                break;
        }
    }

    /**
     * Traite les paiements récurrents pour les abonnements actifs dont la date de paiement est atteinte
     */
    public static function process_recurring_payments() {
        global $wpdb;

        \FPR\Helpers\Logger::log("[StripeHandler] Début du traitement des paiements récurrents");

        // Récupérer tous les abonnements actifs dont la date de paiement est aujourd'hui ou dans le passé
        $today = date('Y-m-d');
        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, p.name as plan_name, p.frequency, p.installments 
             FROM {$wpdb->prefix}fpr_customer_subscriptions s
             LEFT JOIN {$wpdb->prefix}fpr_payment_plans p ON s.payment_plan_id = p.id
             WHERE s.status = 'active' 
             AND s.next_payment_date <= %s
             AND (s.installments_paid < p.installments OR p.installments = 0)
             AND (s.end_date IS NULL OR s.end_date >= %s)",
            $today, $today
        ));

        \FPR\Helpers\Logger::log("[StripeHandler] Nombre d'abonnements à traiter: " . count($subscriptions));

        if (empty($subscriptions)) {
            \FPR\Helpers\Logger::log("[StripeHandler] Aucun paiement récurrent à traiter aujourd'hui");
            return;
        }

        // Vérifier que la bibliothèque Stripe est disponible
        if (!class_exists('\Stripe\Stripe')) {
            if (function_exists('WC')) {
                // Utiliser la bibliothèque Stripe de WooCommerce si disponible
                $wc_stripe = WC()->payment_gateways->payment_gateways()['stripe'];
                if ($wc_stripe && method_exists($wc_stripe, 'get_stripe_api')) {
                    $wc_stripe->get_stripe_api();
                } else {
                    \FPR\Helpers\Logger::log("[StripeHandler] Erreur: La bibliothèque Stripe n'est pas disponible");
                    return;
                }
            } else {
                \FPR\Helpers\Logger::log("[StripeHandler] Erreur: La bibliothèque Stripe n'est pas disponible");
                return;
            }
        }

        // Récupérer la clé secrète Stripe
        $stripe_settings = get_option('woocommerce_stripe_settings', []);
        $secret_key = isset($stripe_settings['secret_key']) ? $stripe_settings['secret_key'] : '';

        if (empty($secret_key)) {
            \FPR\Helpers\Logger::log("[StripeHandler] Erreur: Clé secrète Stripe non configurée");
            return;
        }

        // Configurer Stripe avec la clé secrète
        \Stripe\Stripe::setApiKey($secret_key);

        foreach ($subscriptions as $subscription) {
            \FPR\Helpers\Logger::log("[StripeHandler] Traitement de l'abonnement #{$subscription->id} pour l'utilisateur #{$subscription->user_id}");

            try {
                // Si l'abonnement a un ID Stripe, utiliser l'API Stripe pour créer une facture
                if (!empty($subscription->stripe_subscription_id)) {
                    \FPR\Helpers\Logger::log("[StripeHandler] Abonnement Stripe trouvé: {$subscription->stripe_subscription_id}");

                    // Vérifier si l'abonnement Stripe existe toujours
                    try {
                        $stripe_subscription = \Stripe\Subscription::retrieve($subscription->stripe_subscription_id);

                        // Si l'abonnement est actif, créer une facture
                        if ($stripe_subscription->status === 'active') {
                            \FPR\Helpers\Logger::log("[StripeHandler] Création d'une facture pour l'abonnement Stripe");

                            $invoice = \Stripe\Invoice::create([
                                'customer' => $stripe_subscription->customer,
                                'subscription' => $subscription->stripe_subscription_id,
                                'auto_advance' => true, // Finaliser et collecter automatiquement
                            ]);

                            \FPR\Helpers\Logger::log("[StripeHandler] Facture créée: {$invoice->id}");

                            // Mettre à jour l'abonnement local
                            self::update_subscription_after_payment($subscription);
                        } else {
                            \FPR\Helpers\Logger::log("[StripeHandler] L'abonnement Stripe n'est pas actif: {$stripe_subscription->status}");

                            // Mettre à jour le statut de l'abonnement local
                            $wpdb->update(
                                $wpdb->prefix . 'fpr_customer_subscriptions',
                                ['status' => $stripe_subscription->status],
                                ['id' => $subscription->id]
                            );
                        }
                    } catch (\Exception $e) {
                        \FPR\Helpers\Logger::log("[StripeHandler] Erreur lors de la récupération de l'abonnement Stripe: " . $e->getMessage());

                        // Si l'abonnement n'existe plus, créer un paiement manuel
                        self::create_manual_payment($subscription);
                    }
                } else {
                    \FPR\Helpers\Logger::log("[StripeHandler] Aucun abonnement Stripe trouvé, création d'un paiement manuel");

                    // Créer un paiement manuel
                    self::create_manual_payment($subscription);
                }
            } catch (\Exception $e) {
                \FPR\Helpers\Logger::log("[StripeHandler] Erreur lors du traitement de l'abonnement #{$subscription->id}: " . $e->getMessage());

                // Marquer l'abonnement comme ayant échoué
                $wpdb->update(
                    $wpdb->prefix . 'fpr_customer_subscriptions',
                    ['status' => 'payment_failed'],
                    ['id' => $subscription->id]
                );
            }
        }

        \FPR\Helpers\Logger::log("[StripeHandler] Fin du traitement des paiements récurrents");
    }

    /**
     * Crée un paiement manuel pour un abonnement sans Stripe
     * 
     * @param object $subscription L'objet abonnement
     */
    private static function create_manual_payment($subscription) {
        global $wpdb;

        \FPR\Helpers\Logger::log("[StripeHandler] Création d'un paiement manuel pour l'abonnement #{$subscription->id}");

        // Récupérer les informations de l'utilisateur
        $user = get_userdata($subscription->user_id);
        if (!$user) {
            \FPR\Helpers\Logger::log("[StripeHandler] Utilisateur #{$subscription->user_id} non trouvé");
            return;
        }

        // Créer une nouvelle commande WooCommerce
        $order = wc_create_order([
            'customer_id' => $subscription->user_id,
            'status' => 'pending',
        ]);

        if (is_wp_error($order)) {
            \FPR\Helpers\Logger::log("[StripeHandler] Erreur lors de la création de la commande: " . $order->get_error_message());
            return;
        }

        // Ajouter un produit virtuel représentant le paiement récurrent
        $item = new \WC_Order_Item_Product();
        $item->set_props([
            'name' => "Paiement récurrent - {$subscription->plan_name}",
            'quantity' => 1,
            'total' => $subscription->installment_amount,
        ]);
        $order->add_item($item);

        // Ajouter les informations de facturation
        $order->set_billing_first_name($user->first_name);
        $order->set_billing_last_name($user->last_name);
        $order->set_billing_email($user->user_email);

        // Ajouter une note à la commande
        $order->add_order_note(
            sprintf(
                'Paiement récurrent automatique pour l\'abonnement #%d. Versement %d/%d.',
                $subscription->id,
                $subscription->installments_paid + 1,
                $subscription->installments
            )
        );

        // Enregistrer les métadonnées
        $order->update_meta_data('_fpr_subscription_payment', 'yes');
        $order->update_meta_data('_fpr_subscription_id', $subscription->id);
        $order->update_meta_data('_fpr_selected_payment_plan', $subscription->payment_plan_id);
        $order->update_meta_data('_fpr_selected_saison', $subscription->saison_id);

        // Calculer les totaux et enregistrer
        $order->calculate_totals();
        $order->save();

        \FPR\Helpers\Logger::log("[StripeHandler] Commande #{$order->get_id()} créée pour le paiement récurrent");

        // Mettre à jour l'abonnement
        self::update_subscription_after_payment($subscription);

        // Envoyer un email à l'administrateur et au client
        $admin_email = get_option('admin_email');
        $subject = 'Paiement récurrent à traiter - Abonnement #' . $subscription->id;
        $message = "Un paiement récurrent a été créé pour l'abonnement #{$subscription->id}.\n\n";
        $message .= "Client: {$user->display_name} ({$user->user_email})\n";
        $message .= "Montant: " . wc_price($subscription->installment_amount) . "\n";
        $message .= "Commande: #{$order->get_id()}\n\n";
        $message .= "Veuillez traiter ce paiement manuellement.";

        wp_mail($admin_email, $subject, $message);

        // Envoyer un email au client
        $client_subject = 'Votre paiement récurrent - ' . get_bloginfo('name');
        $client_message = "Cher(e) {$user->display_name},\n\n";
        $client_message .= "Un paiement récurrent de " . wc_price($subscription->installment_amount) . " a été programmé pour votre abonnement.\n";
        $client_message .= "Vous recevrez bientôt une confirmation de paiement.\n\n";
        $client_message .= "Cordialement,\n";
        $client_message .= get_bloginfo('name');

        wp_mail($user->user_email, $client_subject, $client_message);
    }

    /**
     * Met à jour un abonnement après un paiement réussi
     * 
     * @param object $subscription L'objet abonnement
     */
    private static function update_subscription_after_payment($subscription) {
        global $wpdb;

        \FPR\Helpers\Logger::log("[StripeHandler] Mise à jour de l'abonnement #{$subscription->id} après paiement");

        // Déterminer l'intervalle pour le prochain paiement en fonction de la fréquence
        $next_payment_interval = '+1 month'; // Par défaut

        switch ($subscription->frequency) {
            case 'hourly':
                $next_payment_interval = '+1 hour';
                break;
            case 'daily':
                $next_payment_interval = '+1 day';
                break;
            case 'weekly':
                $next_payment_interval = '+1 week';
                break;
            case 'monthly':
                $next_payment_interval = '+1 month';
                break;
            case 'quarterly':
                $next_payment_interval = '+3 months';
                break;
            case 'annual':
                $next_payment_interval = '+1 year';
                break;
            default: // fallback to monthly
                $next_payment_interval = '+1 month';
                break;
        }

        // Calculer la prochaine date de paiement
        $next_payment_date = date('Y-m-d', strtotime($next_payment_interval, strtotime($subscription->next_payment_date)));

        // Incrémenter le nombre de versements payés
        $installments_paid = $subscription->installments_paid + 1;

        // Préparer les données de mise à jour
        $update_data = [
            'installments_paid' => $installments_paid,
            'next_payment_date' => $next_payment_date,
        ];

        // Si tous les versements sont payés, marquer comme terminé
        if ($installments_paid >= $subscription->installments && $subscription->installments > 0) {
            $update_data['status'] = 'completed';
            $update_data['end_date'] = date('Y-m-d');
            \FPR\Helpers\Logger::log("[StripeHandler] Abonnement #{$subscription->id} terminé (tous les versements sont payés)");
        }

        // Mettre à jour l'abonnement
        $wpdb->update(
            $wpdb->prefix . 'fpr_customer_subscriptions',
            $update_data,
            ['id' => $subscription->id]
        );

        \FPR\Helpers\Logger::log("[StripeHandler] Abonnement #{$subscription->id} mis à jour: prochain paiement le {$next_payment_date}, versements payés: {$installments_paid}");
    }

    /**
     * Affiche les abonnements du client dans son compte
     */
    public static function display_customer_subscriptions() {
        // Vérifier si l'utilisateur est connecté
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        // Récupérer les abonnements de l'utilisateur
        global $wpdb;
        $subscriptions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, p.name as plan_name, p.frequency, p.installments, 
                    sn.name as saison_name
             FROM {$wpdb->prefix}fpr_customer_subscriptions s
             LEFT JOIN {$wpdb->prefix}fpr_payment_plans p ON s.payment_plan_id = p.id
             LEFT JOIN {$wpdb->prefix}fpr_saisons sn ON s.saison_id = sn.id
             WHERE s.user_id = %d
             ORDER BY s.created_at DESC",
            $user_id
        ));

        if (empty($subscriptions)) {
            return;
        }

        // Afficher les abonnements
        echo '<h2>Mes abonnements</h2>';
        echo '<table class="woocommerce-orders-table woocommerce-MyAccount-subscriptions shop_table shop_table_responsive">';
        echo '<thead><tr>
                <th>Plan</th>
                <th>Saison</th>
                <th>Statut</th>
                <th>Prochain paiement</th>
                <th>Montant</th>
                <th>Progression</th>
                <th>Actions</th>
              </tr></thead>';
        echo '<tbody>';

        foreach ($subscriptions as $subscription) {
            $frequency_label = [
                'hourly' => 'horaire',
                'daily' => 'quotidien',
                'weekly' => 'hebdomadaire',
                'monthly' => 'mensuel',
                'quarterly' => 'trimestriel',
                'annual' => 'annuel'
            ];
            $frequency = isset($frequency_label[$subscription->frequency]) ? $frequency_label[$subscription->frequency] : $subscription->frequency;

            $status_label = [
                'active' => 'Actif',
                'paused' => 'En pause',
                'cancelled' => 'Annulé',
                'completed' => 'Terminé'
            ];
            $status = isset($status_label[$subscription->status]) ? $status_label[$subscription->status] : $subscription->status;

            $progress = $subscription->installments_paid . ' / ' . $subscription->installments;

            echo '<tr>';
            echo '<td>' . esc_html($subscription->plan_name) . ' (' . esc_html($frequency) . ')</td>';
            echo '<td>' . esc_html($subscription->saison_name) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '<td>' . esc_html($subscription->next_payment_date) . '</td>';
            echo '<td>' . wc_price($subscription->installment_amount) . '</td>';
            echo '<td>' . esc_html($progress) . '</td>';
            echo '<td><button class="button generate-invoice" data-order-id="0">Voir</button></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
