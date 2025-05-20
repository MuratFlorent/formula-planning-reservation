<?php
// File: templates/email-order-details.php
if (!defined('ABSPATH')) exit;

$order_id = $order->get_id();
$courses = [];

foreach ($order->get_items() as $item) {
	foreach ($item->get_meta_data() as $meta) {
		if (strpos($meta->key, 'Cours') !== false) {
			$courses[] = $meta->value;
		}
	}
}
?>

<div style="font-family: Arial, sans-serif; background-color: #f8f8f8; padding: 40px;">
	<div style="background: white; max-width: 600px; margin: auto; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
		<h2 style="color: #5cb85c; border-bottom: 1px solid #eee; padding-bottom: 10px;">🎉 Merci pour votre inscription !</h2>

		<p style="font-size: 16px;">Bonjour <?php echo $order->get_billing_first_name(); ?>,</p>
		<p style="font-size: 15px; color: #444;">Nous avons bien reçu votre commande <strong>#<?php echo $order_id; ?></strong> du <?php echo date_i18n('d/m/Y', strtotime($order->get_date_created())); ?>.</p>

		<h3 style="margin-top: 30px; font-size: 16px; color: #5cb85c;">Vos cours sélectionnés :</h3>
		<ul style="font-size: 15px; padding-left: 20px;">
			<?php foreach ($courses as $c) echo '<li>' . esc_html($c) . '</li>'; ?>
		</ul>

		<p style="margin-top: 30px; font-size: 14px; color: #666;">Un e-mail de confirmation d'inscription vous sera envoyé automatiquement par notre équipe si nécessaire.</p>

		<div style="margin-top: 40px; font-size: 13px; color: #999; border-top: 1px solid #eee; padding-top: 20px;">
			Centre Choréame - Pour toute question : <a href="mailto:contact@choreame.fr">contact@choreame.fr</a>
		</div>
	</div>
</div>
