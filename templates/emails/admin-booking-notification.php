<?php
if (!defined('ABSPATH')) exit;
// Utiliser le logo spécifique du site
$logo_url = site_url('/wp-content/uploads/2024/05/accueil.jpg');

// Récupérer les informations du plan de paiement si disponible
$payment_plan_info = '';
// Vérifier si l'order_id est disponible directement ou via l'order_number
$current_order_id = isset($order_id) ? $order_id : (isset($order_number) ? $order_number : null);

if ($current_order_id) {
    $payment_plan_id = get_post_meta($current_order_id, '_fpr_selected_payment_plan', true);
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
}

// S'assurer que toutes les variables nécessaires sont définies
$firstName = isset($firstName) ? $firstName : '';
$lastName = isset($lastName) ? $lastName : '';
$order_number = isset($order_number) ? $order_number : '';
$events = isset($events) && is_array($events) ? $events : [];
$formula = isset($formula) ? $formula : '';
$price = isset($price) ? $price : 0;

// Récupérer les informations de contact du client si disponibles
$email = isset($email) ? $email : '';
$phone = isset($phone) ? $phone : '';
?>

<div style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 30px;">
	<div style="background: #fff; max-width: 600px; margin: auto; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); padding: 30px;">
		<div style="text-align: center; margin-bottom: 20px;">
			<img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width: 180px; height: auto;" />
		</div>

		<h2 style="color: #d9534f; border-bottom: 1px solid #eee; padding-bottom: 10px;">📥 Nouvelle réservation reçue</h2>

		<div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
			<h3 style="margin-top: 0; font-size: 16px; color: #333;">Informations client :</h3>
			<p style="font-size: 15px; margin: 5px 0;">Nom : <strong><?php echo esc_html($firstName . ' ' . $lastName); ?></strong></p>
			<?php if (!empty($email)) : ?>
				<p style="font-size: 15px; margin: 5px 0;">Email : <a href="mailto:<?php echo esc_attr($email); ?>" style="color: #d9534f;"><?php echo esc_html($email); ?></a></p>
			<?php endif; ?>
			<?php if (!empty($phone)) : ?>
				<p style="font-size: 15px; margin: 5px 0;">Téléphone : <strong><?php echo esc_html($phone); ?></strong></p>
			<?php endif; ?>
		</div>

		<p style="font-size: 15px; color: #555; background-color: #f9f9f9; padding: 10px; border-radius: 5px; border-left: 4px solid #d9534f;">
			Commande <strong>#<?php echo esc_html($order_number); ?></strong> validée le <?php echo date_i18n('d/m/Y à H:i'); ?>
		</p>

		<h3 style="margin-top: 30px; font-size: 16px; color: #333;">Cours sélectionnés :</h3>
		<ul style="font-size: 15px; color: #333; padding-left: 20px; background-color: #f9f9f9; padding: 15px; border-radius: 5px;">
			<?php 
			if (!empty($events)) {
				foreach ($events as $event) {
					echo '<li style="margin-bottom: 8px;">' . esc_html($event) . '</li>';
				}
			} else {
				echo '<li style="margin-bottom: 8px;">Aucun cours sélectionné</li>';
			}
			?>
		</ul>

		<div style="margin-top: 25px; background-color: #f5f5f5; padding: 15px; border-radius: 5px; border-left: 4px solid #d9534f;">
			<p style="font-size: 15px; margin: 0 0 10px 0;">Formule : <strong><?php echo esc_html($formula); ?></strong></p>
			<p style="font-size: 15px; margin: 0 0 10px 0;">Montant payé : <strong><?php echo wc_price($price); ?></strong></p>
			<?php if (!empty($payment_plan_info)) : ?>
				<p style="font-size: 15px; margin: 0;">Plan de paiement : <strong><?php echo esc_html($payment_plan_info); ?></strong></p>
			<?php endif; ?>
		</div>

		<p style="margin-top: 30px; font-size: 14px; color: #777;">Vous pouvez retrouver cette commande dans WooCommerce ou l’interface abonnés.</p>

		<div style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px; text-align: center;">
			<p style="font-size: 13px; color: #888;">
				Alerte automatique Formule Planning – <a href="<?php echo esc_url(get_site_url()); ?>" style="color: #d9534f; text-decoration: none;"><?php echo esc_html(parse_url(get_site_url(), PHP_URL_HOST)); ?></a>
			</p>
			<p style="font-size: 12px; color: #aaa; margin-top: 5px;">
				<?php echo date('Y'); ?> © Tous droits réservés
			</p>
		</div>
	</div>
</div>
