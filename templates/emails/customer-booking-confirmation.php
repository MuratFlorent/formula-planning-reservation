<?php
if (!defined('ABSPATH')) exit;
$logo_url = get_custom_logo() ? wp_get_attachment_image_src(get_theme_mod('custom_logo'), 'full')[0] : get_site_url() . '/wp-content/uploads/logo.png';
?>

<div style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 30px;">
	<div style="background: #fff; max-width: 600px; margin: auto; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); padding: 30px;">
		<div style="text-align: center; margin-bottom: 20px;">
			<img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width: 150px; height: auto;" />
		</div>

		<h2 style="color: #5cb85c; border-bottom: 1px solid #eee; padding-bottom: 10px;">🎉 Merci pour votre inscription !</h2>

		<p style="font-size: 16px;">Bonjour <?php echo esc_html($firstName); ?> <?php echo esc_html($lastName); ?>,</p>
		<p style="font-size: 15px; color: #555;">Nous avons bien reçu votre commande <strong>#<?php echo esc_html($order_number); ?></strong>.</p>

		<h3 style="margin-top: 30px; font-size: 16px; color: #333;">🧘 Cours sélectionnés :</h3>
		<ul style="font-size: 15px; color: #333; padding-left: 20px;">
			<?php foreach ($events as $event) echo '<li>' . esc_html($event) . '</li>'; ?>
		</ul>

		<p style="font-size: 15px; margin-top: 20px;">Formule choisie : <strong><?php echo esc_html($formula); ?></strong></p>
		<p style="font-size: 15px;">Montant : <strong><?php echo wc_price($price); ?></strong></p>

		<p style="margin-top: 30px; font-size: 14px; color: #777;">Vous recevrez prochainement les informations de début de session si besoin.</p>

		<p style="font-size: 13px; color: #aaa; margin-top: 40px; border-top: 1px solid #eee; padding-top: 20px;">
			Centre Choréame – <a href="mailto:contact@choreame.fr">contact@choreame.fr</a>
		</p>
	</div>
</div>
