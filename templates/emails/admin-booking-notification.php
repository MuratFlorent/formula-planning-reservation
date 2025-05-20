<?php
if (!defined('ABSPATH')) exit;
$logo_url = get_custom_logo() ? wp_get_attachment_image_src(get_theme_mod('custom_logo'), 'full')[0] : get_site_url() . '/wp-content/uploads/logo.png';
?>

<div style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 30px;">
	<div style="background: #fff; max-width: 600px; margin: auto; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); padding: 30px;">
		<div style="text-align: center; margin-bottom: 20px;">
			<img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width: 150px; height: auto;" />
		</div>

		<h2 style="color: #d9534f; border-bottom: 1px solid #eee; padding-bottom: 10px;">📥 Nouvelle réservation reçue</h2>

		<p style="font-size: 16px;">Client : <strong><?php echo esc_html($firstName . ' ' . $lastName); ?></strong></p>
		<p style="font-size: 15px; color: #555;">Commande <strong>#<?php echo esc_html($order_number); ?></strong> validée.</p>

		<h3 style="margin-top: 30px; font-size: 16px; color: #333;">Cours sélectionnés :</h3>
		<ul style="font-size: 15px; color: #333; padding-left: 20px;">
			<?php foreach ($events as $event) echo '<li>' . esc_html($event) . '</li>'; ?>
		</ul>

		<p style="font-size: 15px;">Formule : <strong><?php echo esc_html($formula); ?></strong></p>
		<p style="font-size: 15px;">Montant payé : <strong><?php echo wc_price($price); ?></strong></p>

		<p style="margin-top: 30px; font-size: 14px; color: #777;">Vous pouvez retrouver cette commande dans WooCommerce ou l’interface abonnés.</p>

		<p style="font-size: 13px; color: #aaa; margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px;">
			Alerte automatique Formule Planning – <?php echo get_site_url(); ?>
		</p>
	</div>
</div>
