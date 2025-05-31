<?php
if (!defined('ABSPATH')) exit;
// Utiliser le logo spécifique du site
$logo_url = site_url('/wp-content/uploads/2024/05/accueil.jpg');

// S'assurer que toutes les variables nécessaires sont définies
$firstName = isset($firstName) ? $firstName : 'Murat';
$lastName = isset($lastName) ? $lastName : '';
$order_number = isset($order_number) ? $order_number : '1313';
$order_date = isset($order_date) ? $order_date : '29 mai 2025';
$formula = isset($formula) ? $formula : '4 cours/sem';
$price = isset($price) ? $price : 88.00;
$payment_method = isset($payment_method) ? $payment_method : 'Carte de crédit/débit';

// Cours prédéfinis pour la démo
$demo_courses = [
    'Jazz Enfant 1 | 17:30 - 18:30 | 1h | avec Vanessa',
    'Modern\'Jazz Enfant 3 | 17:30 - 18:30 | 1h | avec Vanessa',
    'Modern Jazz Ado 3 (16 / 18 ans) | 18:30 - 19:30 | 1h | avec Vanessa',
    'Pilates | 18:30 - 19:30 | 1h | avec Vanessa'
];

// Utiliser les cours fournis ou les cours de démo
$courses = isset($events) && is_array($events) && !empty($events) ? $events : $demo_courses;
?>

<div style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 30px;">
	<div style="background: #fff; max-width: 600px; margin: auto; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); padding: 30px;">
		<div style="text-align: center; margin-bottom: 20px;">
			<img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width: 180px; height: auto;" />
		</div>

		<h2 style="color: #5cb85c; border-bottom: 1px solid #eee; padding-bottom: 10px;">Merci pour votre commande</h2>

		<p style="font-size: 16px;">Bonjour <?php echo esc_html($firstName); ?>,</p>
		<p style="font-size: 15px; color: #555;">Pour information – nous avons reçu votre commande n°<?php echo esc_html($order_number); ?>, elle est maintenant en cours de traitement :</p>

		<div style="margin-top: 25px; border: 1px solid #ddd; border-radius: 5px; overflow: hidden;">
			<div style="background-color: #f5f5f5; padding: 10px 15px; border-bottom: 1px solid #ddd;">
				<h3 style="margin: 0; font-size: 16px; color: #333;">[Commande n°<?php echo esc_html($order_number); ?>] (<?php echo esc_html($order_date); ?>)</h3>
			</div>
			
			<div style="padding: 15px;">
				<table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">
					<thead>
						<tr>
							<th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Produit</th>
							<th style="text-align: center; padding: 8px; border-bottom: 1px solid #ddd;">Quantité</th>
							<th style="text-align: right; padding: 8px; border-bottom: 1px solid #ddd;">Prix</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td style="text-align: left; padding: 8px; vertical-align: top;">
								<p style="margin: 0 0 5px 0;"><strong><?php echo esc_html($formula); ?></strong></p>
								<?php foreach ($courses as $index => $course): ?>
									<p style="margin: 0 0 5px 0; font-size: 14px;">Cours sélectionné <?php echo $index + 1; ?>:<br><?php echo esc_html($course); ?></p>
								<?php endforeach; ?>
							</td>
							<td style="text-align: center; padding: 8px; vertical-align: top;">1</td>
							<td style="text-align: right; padding: 8px; vertical-align: top;"><?php echo number_format($price, 2, ',', ' '); ?> €</td>
						</tr>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="2" style="text-align: right; padding: 8px; border-top: 1px solid #ddd;"><strong>Sous-total :</strong></td>
							<td style="text-align: right; padding: 8px; border-top: 1px solid #ddd;"><?php echo number_format($price, 2, ',', ' '); ?> €</td>
						</tr>
						<tr>
							<td colspan="2" style="text-align: right; padding: 8px;"><strong>Moyen de paiement :</strong></td>
							<td style="text-align: right; padding: 8px;"><?php echo esc_html($payment_method); ?></td>
						</tr>
						<tr>
							<td colspan="2" style="text-align: right; padding: 8px; border-top: 1px solid #ddd;"><strong>Total :</strong></td>
							<td style="text-align: right; padding: 8px; border-top: 1px solid #ddd; font-weight: bold;"><?php echo number_format($price, 2, ',', ' '); ?> €</td>
						</tr>
					</tfoot>
				</table>
			</div>
		</div>

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