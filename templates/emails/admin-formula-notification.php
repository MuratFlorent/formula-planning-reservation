<?php
if (!defined('ABSPATH')) exit;
// Utiliser le logo spécifique du site
$logo_url = site_url('/wp-content/uploads/2024/05/accueil.jpg');

// S'assurer que toutes les variables nécessaires sont définies
$firstName = isset($firstName) ? $firstName : '';
$lastName = isset($lastName) ? $lastName : '';
$email = isset($email) ? $email : '';
$phone = isset($phone) ? $phone : '';
$order_number = isset($order_number) ? $order_number : '';
$events = isset($events) && is_array($events) ? $events : [];
$formula = isset($formula) ? $formula : '';
$price = isset($price) ? $price : 0;
$payment_plan_info = isset($payment_plan_info) ? $payment_plan_info : '';
$order_date = isset($order_date) ? $order_date : date_i18n('d/m/Y à H:i');
?>

<div style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 30px;">
	<div style="background: #fff; max-width: 600px; margin: auto; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); padding: 30px;">
		<div style="text-align: center; margin-bottom: 20px;">
			<img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width: 180px; height: auto;" />
		</div>

		<h2 style="color: #3498db; border-bottom: 1px solid #eee; padding-bottom: 10px;">📋 Détails de la formule et des cours</h2>

		<div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
			<h3 style="margin-top: 0; font-size: 16px; color: #333;">Informations client :</h3>
			<p style="font-size: 15px; margin: 5px 0;">Nom : <strong><?php echo esc_html($firstName . ' ' . $lastName); ?></strong></p>
			<?php if (!empty($email)) : ?>
				<p style="font-size: 15px; margin: 5px 0;">Email : <a href="mailto:<?php echo esc_attr($email); ?>" style="color: #3498db;"><?php echo esc_html($email); ?></a></p>
			<?php endif; ?>
			<?php if (!empty($phone)) : ?>
				<p style="font-size: 15px; margin: 5px 0;">Téléphone : <strong><?php echo esc_html($phone); ?></strong></p>
			<?php endif; ?>
		</div>

		<p style="font-size: 15px; color: #555; background-color: #f9f9f9; padding: 10px; border-radius: 5px; border-left: 4px solid #3498db;">
			Commande <strong>#<?php echo esc_html($order_number); ?></strong> du <?php echo $order_date; ?>
		</p>

		<div style="margin-top: 25px; background-color: #f5f5f5; padding: 15px; border-radius: 5px; border-left: 4px solid #3498db;">
			<h3 style="margin-top: 0; font-size: 16px; color: #333;">Détails de la formule :</h3>
			<p style="font-size: 15px; margin: 5px 0;">Formule : <strong><?php echo esc_html($formula); ?></strong></p>
			<p style="font-size: 15px; margin: 5px 0;">Montant : <strong><?php echo wc_price($price); ?></strong></p>
			<?php if (!empty($payment_plan_info)) : ?>
				<p style="font-size: 15px; margin: 5px 0;">Plan de paiement : <strong><?php echo esc_html($payment_plan_info); ?></strong></p>
			<?php endif; ?>
		</div>

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

		<p style="margin-top: 30px; font-size: 14px; color: #777;">Ces informations sont également disponibles dans l'interface d'administration.</p>

		<div style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px; text-align: center;">
			<p style="font-size: 13px; color: #888;">
				Notification automatique – <a href="<?php echo esc_url(get_site_url()); ?>" style="color: #3498db; text-decoration: none;"><?php echo esc_html(parse_url(get_site_url(), PHP_URL_HOST)); ?></a>
			</p>
			<p style="font-size: 12px; color: #aaa; margin-top: 5px;">
				<?php echo date('Y'); ?> © Tous droits réservés
			</p>
		</div>
	</div>
</div>
