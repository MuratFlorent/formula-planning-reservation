<?php
// File: templates/email-order-details.php
if (!defined('ABSPATH')) exit;

// Utiliser le logo spécifique du site
$logo_url = site_url('/wp-content/uploads/2024/05/accueil.jpg');

$order_id = $order->get_id();
$courses = [];

foreach ($order->get_items() as $item) {
	foreach ($item->get_meta_data() as $meta) {
		if (strpos($meta->key, 'Cours') !== false) {
			$courses[] = $meta->value;
		}
	}
}

// Récupérer les informations du plan de paiement si disponible
$payment_plan_info = '';
$payment_plan_id = get_post_meta($order_id, '_fpr_selected_payment_plan', true);
if (!empty($payment_plan_id)) {
    global $wpdb;
    $payment_plan = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}fpr_payment_plans WHERE id = %d",
        $payment_plan_id
    ));

    if ($payment_plan) {
        $term_text = !empty($payment_plan->term) ? '/' . $payment_plan->term : '';
        if (empty($term_text)) {
            $frequency_label = [
                'hourly' => '/heure',
                'daily' => '/jour',
                'weekly' => '/sem',
                'monthly' => '/mois',
                'quarterly' => '/trim',
                'annual' => '/an'
            ];
            $term_text = isset($frequency_label[$payment_plan->frequency]) ? $frequency_label[$payment_plan->frequency] : '';
        }
        $payment_plan_info = $payment_plan->name . ' (' . $payment_plan->installments . ' versements' . ($term_text ? ' ' . $term_text : '') . ')';
    }
}

// Récupérer la formule (premier produit de la commande)
$formula = '';
foreach ($order->get_items() as $item) {
    $formula = $item->get_name();
    break;
}
?>

<div style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 30px;">
	<div style="background: #fff; max-width: 600px; margin: auto; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); padding: 30px;">
		<div style="text-align: center; margin-bottom: 20px;">
			<img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width: 180px; height: auto;" />
		</div>

		<h2 style="color: #5cb85c; border-bottom: 1px solid #eee; padding-bottom: 10px;">🎉 Merci pour votre inscription !</h2>

		<p style="font-size: 16px;">Bonjour <?php echo esc_html($order->get_billing_first_name()); ?>,</p>
		<p style="font-size: 15px; color: #555;">Nous avons bien reçu votre commande <strong>#<?php echo esc_html($order_id); ?></strong> du <?php echo date_i18n('d/m/Y', strtotime($order->get_date_created())); ?>.</p>

		<h3 style="margin-top: 30px; font-size: 16px; color: #333;">🧘 Cours sélectionnés :</h3>
		<ul style="font-size: 15px; color: #333; padding-left: 20px; background-color: #f9f9f9; padding: 15px; border-radius: 5px;">
			<?php 
			if (!empty($courses)) {
				foreach ($courses as $c) {
					echo '<li style="margin-bottom: 8px;">' . esc_html($c) . '</li>';
				}
			} else {
				echo '<li style="margin-bottom: 8px;">Aucun cours sélectionné</li>';
			}
			?>
		</ul>

		<div style="margin-top: 25px; background-color: #f5f5f5; padding: 15px; border-radius: 5px; border-left: 4px solid #5cb85c;">
			<?php if (!empty($formula)) : ?>
				<p style="font-size: 15px; margin: 0 0 10px 0;">Formule choisie : <strong><?php echo esc_html($formula); ?></strong></p>
			<?php endif; ?>
			<p style="font-size: 15px; margin: 0 0 10px 0;">Montant : <strong><?php echo wc_price($order->get_total()); ?></strong></p>
			<?php if (!empty($payment_plan_info)) : ?>
				<p style="font-size: 15px; margin: 0;">Plan de paiement : <strong><?php echo esc_html($payment_plan_info); ?></strong></p>
			<?php endif; ?>
		</div>

		<p style="margin-top: 30px; font-size: 14px; color: #777;">Un e-mail de confirmation d'inscription vous sera envoyé automatiquement par notre équipe si nécessaire.</p>

		<div style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px; text-align: center;">
			<p style="font-size: 13px; color: #888;">
				Centre Choréame – <a href="mailto:contact@choreame.fr" style="color: #5cb85c; text-decoration: none;">contact@choreame.fr</a>
			</p>
			<p style="font-size: 12px; color: #aaa; margin-top: 5px;">
				<?php echo date('Y'); ?> © Tous droits réservés
			</p>
		</div>
	</div>
</div>
