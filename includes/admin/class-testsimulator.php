<?php
namespace FPR\Admin;

use FPR\Helpers\Logger;

class TestSimulator {
	public static function render() {
		?>
        <div class="wrap">
            <h2>Test de simulation d'une commande WooCommerce validée</h2>
            <p>Cliquez sur le bouton ci-dessous pour simuler le processus complet de validation d'une commande WooCommerce, ajout des cours sélectionnés dans Amelia, etc.</p>

            <form method="post">
				<?php submit_button('Lancer le test de validation'); ?>
                <input type="hidden" name="fpr_run_test" value="1">
            </form>

			<?php if (!empty($_POST['fpr_run_test'])): ?>
                <div style="background:#fff; border:1px solid #ccd0d4; padding:15px; margin-top:20px;">
                    <p><strong>🔄 Lancement du test...</strong></p>
					<?php
					Logger::log('--- 🧪 Lancement du test WooCommerce ---');
					flush(); ob_flush();

					echo "<p>✅ Création d'une commande test...</p>";
					Logger::log('✅ Création d\'une commande test...');

					$order_id = self::create_test_order();
					if (!$order_id) {
						echo "<p style='color:red;'>❌ Erreur lors de la création de la commande test.</p>";
						Logger::log('❌ Erreur : impossible de créer une commande test.');
						return;
					}

					echo "<p>📦 Commande test créée avec ID : <code>$order_id</code></p>";
					Logger::log("📦 Commande test créée avec ID : $order_id");
					flush(); ob_flush();

					$order = wc_get_order($order_id);
					if ($order) {
						$order->update_status('completed', 'Test manuel');
						echo "<p>🚀 Statut de la commande mis à jour à <code>completed</code>.</p>";
						Logger::log("🚀 Statut de la commande #$order_id mis à jour : completed");
					} else {
						echo "<p style='color:red;'>❌ Impossible de charger la commande.</p>";
						Logger::log("❌ Erreur : impossible de charger la commande #$order_id");
					}

					echo "<p><strong>🎉 Test terminé. Consulte le fichier <code>/wp-content/logs/fpr-debug.log</code> pour les détails.</strong></p>";
					Logger::log('🎉 Test terminé.');
					?>
                </div>
			<?php endif; ?>
        </div>
		<?php
	}

	private static function create_test_order() {
		$address = [
			'first_name' => 'Test',
			'last_name'  => 'User',
			'email'      => 'test@example.com',
			'phone'      => '0600000000',
			'address_1'  => '123 Rue de Test',
			'city'       => 'Paris',
			'postcode'   => '75000',
			'country'    => 'FR'
		];

		$order = wc_create_order();
		$order->set_address($address, 'billing');

		$products = wc_get_products(['limit' => 1, 'return' => 'ids']);
		if (empty($products)) {
			Logger::log('⚠️ Aucun produit WooCommerce trouvé.');
			return false;
		}

		$product_id = $products[0];
		$item_id = $order->add_product(wc_get_product($product_id), 1);
		$order->calculate_totals();

		$item = $order->get_item($item_id);
		if ($item) {
			$item->add_meta_data('Cours sélectionné 1', 'Pilates - 12:00 - 13:00 (1h) avec Sophie', true);
			$item->save();
			Logger::log('📝 Métadonnée ajoutée au produit : Cours sélectionné 1');
		}

		$order->save();
		return $order->get_id();
	}
}
