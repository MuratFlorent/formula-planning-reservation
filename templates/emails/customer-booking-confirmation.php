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
?>

<div style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 30px;">
	<div style="background: #fff; max-width: 600px; margin: auto; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); padding: 30px;">
		<div style="text-align: center; margin-bottom: 20px;">
			<img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width: 180px; height: auto;" />
		</div>

		<h2 style="color: #5cb85c; border-bottom: 1px solid #eee; padding-bottom: 10px;">🎉 Merci pour votre inscription !</h2>

		<p style="font-size: 16px;">Bonjour <?php echo esc_html($firstName); ?> <?php echo esc_html($lastName); ?>,</p>
		<p style="font-size: 15px; color: #555;">Nous avons bien reçu votre commande <strong>#<?php echo esc_html($order_number); ?></strong>.</p>

		<h3 style="margin-top: 30px; font-size: 16px; color: #333;">🧘 Cours sélectionnés :</h3>
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

		<div style="margin-top: 25px; background-color: #f5f5f5; padding: 15px; border-radius: 5px; border-left: 4px solid #5cb85c;">
			<p style="font-size: 15px; margin: 0 0 10px 0;">Formule choisie : <strong><?php echo esc_html($formula); ?></strong></p>
			<p style="font-size: 15px; margin: 0 0 10px 0;">Montant : <strong><?php echo wc_price($price); ?></strong></p>
			<?php if (!empty($payment_plan_info)) : ?>
				<p style="font-size: 15px; margin: 0;">Plan de paiement : <strong><?php echo esc_html($payment_plan_info); ?></strong></p>
			<?php endif; ?>
		</div>

		<p style="margin-top: 30px; font-size: 14px; color: #777;">Vous recevrez prochainement les informations de début de session si besoin.</p>

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
