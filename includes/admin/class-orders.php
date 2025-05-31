<?php
namespace FPR\Admin;

if (!defined('ABSPATH')) exit;

class Orders {
    public static function init() {
        // No specific initialization needed for now
    }

    /**
     * Enqueue scripts and styles for the orders page
     */
    public static function enqueue_scripts_and_styles() {
        // Enqueue common admin styles
        wp_enqueue_style('fpr-admin', FPR_PLUGIN_URL . 'assets/css/admin.css', [], FPR_VERSION);

        // Add dashicons for the icons
        wp_enqueue_style('dashicons');
    }

    /**
     * Affiche la liste des abonnés avec les informations sur les formules et cours choisis
     */
    public static function render_page() {
        global $wpdb;

        // Enqueue scripts and styles for the orders page
        self::enqueue_scripts_and_styles();

        \FPR\Helpers\Logger::log("[Orders] Début du rendu de la page des commandes");
        ?>
        <div class="wrap">
            <h1>Gestion des abonnés</h1>

            <h2>Abonnés avec formules et cours</h2>

            <!-- Loader pour les transitions de page -->
            <div class="fpr-loader">
                <div class="fpr-loader-spinner"></div>
            </div>

            <!-- Formulaire de filtrage -->
            <div class="fpr-filter-form">
                <form method="get">
                    <input type="hidden" name="page" value="fpr-settings">
                    <input type="hidden" name="tab" value="orders">

                    <label for="filter-saison">Saison:</label>
                    <select name="saison" id="filter-saison">
                        <option value="">Toutes les saisons</option>
                        <?php
                        $saisons = $wpdb->get_results("SELECT name, tag FROM {$wpdb->prefix}fpr_saisons ORDER BY name DESC");
                        foreach ($saisons as $saison) {
                            $selected = isset($_GET['saison']) && $_GET['saison'] === $saison->tag ? 'selected' : '';
                            echo '<option value="' . esc_attr($saison->tag) . '" ' . $selected . '>' . esc_html($saison->name) . '</option>';
                        }
                        ?>
                    </select>

                    <label for="filter-status">Statut:</label>
                    <select name="status" id="filter-status">
                        <option value="">Tous les statuts</option>
                        <option value="wc-processing" <?php echo isset($_GET['status']) && $_GET['status'] === 'wc-processing' ? 'selected' : ''; ?>>En cours</option>
                        <option value="wc-completed" <?php echo isset($_GET['status']) && $_GET['status'] === 'wc-completed' ? 'selected' : ''; ?>>Terminé</option>
                    </select>

                    <label for="filter-search">Recherche:</label>
                    <input type="text" name="search" id="filter-search" value="<?php echo isset($_GET['search']) ? esc_attr($_GET['search']) : ''; ?>" placeholder="Nom, email...">

                    <button type="submit" class="button">Filtrer</button>
                    <a href="?page=fpr-settings&tab=orders" class="button">Réinitialiser</a>
                </form>
            </div>

            <?php
            // Récupérer les abonnements WooCommerce avec les meta données des saisons
            \FPR\Helpers\Logger::log("[Orders] Appel à get_orders_with_courses()");
            $orders = self::get_orders_with_courses();
            \FPR\Helpers\Logger::log("[Orders] Nombre de commandes récupérées: " . count($orders));

            // Appliquer les filtres si nécessaire
            if (!empty($_GET['saison']) || !empty($_GET['status']) || !empty($_GET['search'])) {
                $filtered_orders = [];

                foreach ($orders as $order) {
                    // Filtre par saison
                    if (!empty($_GET['saison']) && $order->saison_tag !== $_GET['saison']) {
                        continue;
                    }

                    // Filtre par statut
                    if (!empty($_GET['status']) && 'wc-' . $order->status !== $_GET['status']) {
                        continue;
                    }

                    // Filtre par recherche
                    if (!empty($_GET['search'])) {
                        $search = strtolower($_GET['search']);
                        $found = false;

                        // Rechercher dans le nom, l'email, les cours, etc.
                        if (
                            strpos(strtolower($order->customer_name), $search) !== false ||
                            strpos(strtolower($order->customer_email), $search) !== false ||
                            strpos(strtolower($order->courses), $search) !== false ||
                            strpos(strtolower($order->formula), $search) !== false
                        ) {
                            $found = true;
                        }

                        if (!$found) {
                            continue;
                        }
                    }

                    $filtered_orders[] = $order;
                }

                $orders = $filtered_orders;
            }

            if (empty($orders)) {
                \FPR\Helpers\Logger::log("[Orders] Aucune commande à afficher");
                echo '<p>Aucun abonné avec formule et cours n\'a été trouvé.</p>';
            } else {
                \FPR\Helpers\Logger::log("[Orders] Affichage de " . count($orders) . " commandes");
                ?>
                <p><strong><?php echo count($orders); ?> abonnés trouvés</strong></p>

                <table class="fpr-orders-table">
                    <thead>
                        <tr>
                            <th class="column-id">ID</th>
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Formule</th>
                            <th>Cours sélectionnés</th>
                            <th>Statut Amelia</th>
                            <th>Saison</th>
                            <th class="column-price">Prix</th>
                            <th>Paiement</th>
                            <th>Abonnement</th>
                            <th class="column-status">Statut</th>
                            <th class="column-date">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order) : ?>
                            <tr>
                                <td class="column-id"><a href="<?php echo admin_url('post.php?post=' . $order->id . '&action=edit'); ?>">#<?php echo $order->id; ?></a></td>
                                <td><?php echo esc_html($order->customer_name); ?></td>
                                <td>
                                    <?php if (!empty($order->customer_email)) : ?>
                                        <strong>Email:</strong> <?php echo esc_html($order->customer_email); ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($order->customer_phone)) : ?>
                                        <strong>Tél:</strong> <?php echo esc_html($order->customer_phone); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($order->formula); ?></td>
                                <td><pre><?php echo esc_html($order->courses); ?></pre></td>
                                <td>
                                    <?php if (!empty($order->amelia_status)) : ?>
                                        <pre><?php echo esc_html($order->amelia_status); ?></pre>
                                    <?php else : ?>
                                        <em>Aucune réservation</em>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($order->saison); ?></td>
                                <td class="column-price"><?php echo wc_price($order->total); ?></td>
                                <td><?php echo esc_html($order->payment_method); ?></td>
                                <td>
                                    <?php if (!empty($order->subscription_id)) : ?>
                                        <div class="fpr-subscription-info">
                                            <strong>Plan:</strong> <?php echo esc_html($order->payment_plan_name); ?><br>
                                            <strong>Statut:</strong> <?php echo esc_html($order->subscription_status); ?><br>
                                            <strong>Paiements:</strong> <?php echo esc_html($order->installments_paid); ?>/<?php echo esc_html($order->total_installments); ?>
                                            <br>
                                            <a href="?page=fpr-settings&tab=orders&subscription_id=<?php echo esc_attr($order->subscription_id); ?>" class="button button-small">Détails</a>
                                        </div>
                                    <?php else : ?>
                                        <em>Pas d'abonnement</em>
                                    <?php endif; ?>
                                </td>
                                <td class="column-status">
                                    <?php 
                                    $status_class = '';
                                    $status_icon = '';

                                    switch ($order->status) {
                                        case 'En cours':
                                            $status_class = 'status-processing';
                                            $status_icon = '⏳';
                                            break;
                                        case 'Terminé':
                                            $status_class = 'status-completed';
                                            $status_icon = '✅';
                                            break;
                                        default:
                                            $status_class = 'status-default';
                                            $status_icon = '❓';
                                    }

                                    echo '<span class="' . $status_class . '">' . $status_icon . ' ' . esc_html($order->status) . '</span>';
                                    ?>
                                </td>
                                <td class="column-date"><?php echo date('d/m/Y H:i', strtotime($order->date)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <script>
                // Ajouter un loader lors des transitions de page
                document.addEventListener('DOMContentLoaded', function() {
                    const filterForm = document.querySelector('.fpr-filter-form form');
                    const loader = document.querySelector('.fpr-loader');

                    if (filterForm) {
                        filterForm.addEventListener('submit', function() {
                            loader.style.display = 'flex';
                        });
                    }

                    // Cacher le loader quand la page est chargée
                    window.addEventListener('load', function() {
                        loader.style.display = 'none';
                    });
                });
                </script>
                <?php
            }

            \FPR\Helpers\Logger::log("[Orders] Fin du rendu de la page des commandes");
            ?>
        </div>
        <?php
    }

    /**
     * Récupère les abonnements WooCommerce avec les informations sur les formules et cours choisis
     * 
     * @return array Liste des abonnements avec leurs détails
     */
    private static function get_orders_with_courses() {
        global $wpdb;

        \FPR\Helpers\Logger::log("[Orders] Récupération des commandes avec les cours");

        // Récupérer les abonnements qui ont une saison sélectionnée
        $orders_query = "
            SELECT 
                p.ID as id,
                p.post_date as date,
                p.post_status as status,
                CONCAT(pm_first.meta_value, ' ', pm_last.meta_value) as customer_name,
                pm_email.meta_value as customer_email,
                pm_phone.meta_value as customer_phone,
                pm_total.meta_value as total,
                pm_saison.meta_value as saison_tag,
                pm_payment.meta_value as payment_method,
                cs.id as subscription_id,
                cs.status as subscription_status,
                cs.installments_paid,
                pp.installments as total_installments,
                pp.name as payment_plan_name
            FROM 
                {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm_saison ON p.ID = pm_saison.post_id AND pm_saison.meta_key = '_fpr_selected_saison'
                LEFT JOIN {$wpdb->postmeta} pm_first ON p.ID = pm_first.post_id AND pm_first.meta_key = '_billing_first_name'
                LEFT JOIN {$wpdb->postmeta} pm_last ON p.ID = pm_last.post_id AND pm_last.meta_key = '_billing_last_name'
                LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
                LEFT JOIN {$wpdb->postmeta} pm_phone ON p.ID = pm_phone.post_id AND pm_phone.meta_key = '_billing_phone'
                LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = '_order_total'
                LEFT JOIN {$wpdb->postmeta} pm_payment ON p.ID = pm_payment.post_id AND pm_payment.meta_key = '_payment_method_title'
                LEFT JOIN {$wpdb->prefix}fpr_customer_subscriptions cs ON p.ID = cs.order_id
                LEFT JOIN {$wpdb->prefix}fpr_payment_plans pp ON cs.payment_plan_id = pp.id
            WHERE 
                p.post_type = 'shop_order'
                AND pm_saison.meta_value IS NOT NULL
                AND p.post_status IN ('wc-processing', 'wc-completed')
            ORDER BY 
                p.post_date DESC
            LIMIT 100
        ";

        \FPR\Helpers\Logger::log("[Orders] Exécution de la requête: " . $orders_query);

        $orders = $wpdb->get_results($orders_query);

        \FPR\Helpers\Logger::log("[Orders] Nombre de commandes trouvées: " . count($orders));

        if (empty($orders)) {
            \FPR\Helpers\Logger::log("[Orders] Aucune commande trouvée");
            return [];
        }

        // Récupérer les détails des saisons
        $saison_tags = array_unique(array_column($orders, 'saison_tag'));
        $saisons = [];

        if (!empty($saison_tags)) {
            $placeholders = implode(',', array_fill(0, count($saison_tags), '%s'));
            $saisons_query = $wpdb->prepare(
                "SELECT tag, name FROM {$wpdb->prefix}fpr_saisons WHERE tag IN ($placeholders)",
                $saison_tags
            );
            $saisons_results = $wpdb->get_results($saisons_query);

            foreach ($saisons_results as $saison) {
                $saisons[$saison->tag] = $saison->name;
            }
        }

        // Récupérer les inscriptions Amelia pour chaque client
        $customer_emails = array_unique(array_filter(array_column($orders, 'customer_email')));
        $amelia_bookings = [];

        if (!empty($customer_emails)) {
            foreach ($customer_emails as $email) {
                $bookings = $wpdb->get_results($wpdb->prepare("
                    SELECT 
                        cb.id as booking_id,
                        c.email as customer_email,
                        e.name as event_name,
                        ep.periodStart as period_start,
                        cb.status as booking_status
                    FROM 
                        {$wpdb->prefix}amelia_customer_bookings cb
                        JOIN {$wpdb->prefix}amelia_customers c ON cb.customerId = c.id
                        JOIN {$wpdb->prefix}amelia_events_periods ep ON cb.eventPeriodId = ep.id
                        JOIN {$wpdb->prefix}amelia_events e ON ep.eventId = e.id
                    WHERE 
                        c.email = %s
                    ORDER BY 
                        ep.periodStart DESC
                ", $email));

                if (!empty($bookings)) {
                    $amelia_bookings[$email] = $bookings;
                }
            }
        }

        // Enrichir les commandes avec les détails des produits et cours
        foreach ($orders as &$order) {
            // Convertir le statut en format lisible
            $order->status = wc_get_order_status_name($order->status);

            // Ajouter le nom de la saison
            $order->saison = isset($saisons[$order->saison_tag]) ? $saisons[$order->saison_tag] : $order->saison_tag;

            // Récupérer les produits et cours de la commande
            $wc_order = wc_get_order($order->id);
            $order->formula = '';
            $order->courses = '';
            $order->amelia_status = '';

            if ($wc_order) {
                foreach ($wc_order->get_items() as $item) {
                    // Nom du produit/formule
                    $order->formula = $item->get_name();

                    // Récupérer les cours choisis dans les meta
                    $courses = [];
                    foreach ($item->get_meta_data() as $meta) {
                        if (strpos($meta->key, 'Cours') !== false) {
                            $courses[] = $meta->value;
                        }
                    }

                    if (!empty($courses)) {
                        $order->courses = implode("\n", $courses);
                    }

                    // On ne prend que le premier produit pour simplifier
                    break;
                }
            }

            // Ajouter les informations sur les réservations Amelia
            if (!empty($order->customer_email) && isset($amelia_bookings[$order->customer_email])) {
                $bookings_info = [];
                foreach ($amelia_bookings[$order->customer_email] as $booking) {
                    $status_text = '';
                    switch ($booking->booking_status) {
                        case 'approved': $status_text = '✅ Confirmé'; break;
                        case 'pending': $status_text = '⏳ En attente'; break;
                        case 'canceled': $status_text = '❌ Annulé'; break;
                        case 'rejected': $status_text = '❌ Rejeté'; break;
                        default: $status_text = $booking->booking_status;
                    }

                    $bookings_info[] = $booking->event_name . ' (' . date('d/m/Y H:i', strtotime($booking->period_start)) . ') - ' . $status_text;
                }

                if (!empty($bookings_info)) {
                    $order->amelia_status = implode("\n", $bookings_info);
                }
            }
        }

        \FPR\Helpers\Logger::log("[Orders] Fin de la récupération des commandes avec les cours. Nombre de commandes enrichies: " . count($orders));

        return $orders;
    }
}
