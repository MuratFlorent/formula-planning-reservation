<?php
// Fichier : admin-exclusions.php (dans ton plugin formula-planning-reservation)

if (!current_user_can('manage_options')) {
	return;
}

// Traitement de la soumission du formulaire
if (isset($_POST['fpr_excluded_courses']) && is_array($_POST['fpr_excluded_courses'])) {
	update_option('fpr_excluded_courses', array_map('sanitize_text_field', $_POST['fpr_excluded_courses']));
	echo '<div class="updated"><p>Exclusions mises à jour !</p></div>';
}

// Récupérer la liste des exclusions
$excluded = get_option('fpr_excluded_courses', []);

// Récupérer les titres des classes Amelia (ou autre plugin si à terme)
// Ici on simule avec des titres statiques pour l'exemple
$available_classes = [
	'Barres à terres',
	'Pilates Express',
	'Classique Inter',
	'Contemporain Ado',
	'Pilates',
	'Modern Jazz',
	'Hip Hop'
];

?>
<div class="wrap">
	<h1>Exclure certains cours des formules</h1>
	<form method="post">
		<p>Sélectionne les cours qui ne doivent <strong>pas</strong> être pris en compte dans les formules.</p>
		<table class="form-table">
			<tbody>
			<?php foreach ($available_classes as $title) : ?>
				<tr>
					<th scope="row">
						<label>
							<input type="checkbox" name="fpr_excluded_courses[]" value="<?php echo esc_attr($title); ?>" <?php checked(in_array($title, $excluded)); ?>>
							<?php echo esc_html($title); ?>
						</label>
					</th>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="submit" class="button-primary">Sauvegarder</button>
		</p>
	</form>
</div>
