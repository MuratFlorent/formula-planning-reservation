a# Algorithme de Sélection de Cours à la Confirmation de Commande

Ce document détaille le flux complet de l'algorithme depuis la sélection des cours jusqu'à la confirmation de commande et l'enregistrement des réservations.

## 1. Sélection des Cours (Frontend)

### Fichiers impliqués
- `/assets/js/calendar.js` - Gestion de la sélection des cours côté client
- `/shortcodes/fpr-planning.php` - Shortcode pour afficher le calendrier

### Processus
1. L'utilisateur accède à la page contenant le shortcode `[fpr_planning]`
2. Le script `calendar.js` est chargé et initialise l'interface de sélection
3. L'utilisateur clique sur les cours pour les sélectionner/désélectionner
4. Les cours sélectionnés sont stockés dans le localStorage sous la clé `fpr_selected_courses`
5. Un bouton flottant affiche le nombre de cours sélectionnés et permet de valider la sélection

### Fonctions clés
- `document.addEventListener("DOMContentLoaded", function () {...})` - Initialisation du script
- Événement de clic sur les cours : `el.addEventListener("click", function () {...})`
- Validation de la sélection : `document.getElementById("fpr-validate-selection").addEventListener("click", function () {...})`

## 2. Traitement AJAX de la Sélection

### Fichiers impliqués
- `/includes/modules/class-form-handler.php` - Traitement de la sélection côté serveur

### Processus
1. Lorsque l'utilisateur clique sur "Valider", une requête AJAX est envoyée au serveur
2. L'action AJAX `fpr_get_product_id` est traitée par la méthode `FormHandler::handle_ajax_selection()`
3. Cette méthode standardise le format des cours et élimine les doublons
4. Elle analyse les durées des cours pour déterminer le produit WooCommerce à ajouter au panier

### Fonctions clés
- `FormHandler::handle_ajax_selection()` - Point d'entrée pour le traitement AJAX
- `FormHandler::maybe_store_selection()` - Traitement central de la sélection
- `FormHandler::standardize_time_format()` - Standardisation du format horaire

## 3. Détermination du Produit WooCommerce

### Fichiers impliqués
- `/helpers/product-mapper.php` - Détermination du produit WooCommerce à ajouter au panier

### Processus
1. La méthode `ProductMapper::get_product_id_smart()` analyse les cours sélectionnés
2. Elle compte les cours de 1h et les cours de plus d'1h
3. Si le nombre total de cours dépasse un seuil (par défaut 5), elle recherche un produit "à volonté"
4. Sinon, elle recherche un produit correspondant au nombre de cours de 1h

### Fonctions clés
- `ProductMapper::get_product_id_smart()` - Détermination intelligente du produit
- `ProductMapper::get_product_id_by_course_count()` - Recherche d'un produit par nombre de cours

## 4. Ajout au Panier WooCommerce

### Fichiers impliqués
- `/includes/modules/class-form-handler.php` - Ajout au panier
- `/includes/modules/class-woocommerce-handler.php` - Gestion des interactions avec WooCommerce

### Processus
1. Une fois le produit déterminé, le panier WooCommerce est vidé
2. Le produit est ajouté au panier avec les données des cours sélectionnés
3. L'utilisateur est redirigé vers la page panier

### Fonctions clés
- Dans `FormHandler::maybe_store_selection()` :
  - `WC()->cart->empty_cart()` - Vidage du panier
  - `WC()->cart->add_to_cart($product_id)` - Ajout du produit au panier

## 5. Affichage et Gestion du Panier

### Fichiers impliqués
- `/includes/modules/class-woocommerce-handler.php` - Gestion du panier et du checkout

### Processus
1. Les données des cours sont injectées dans les métadonnées de l'article du panier via `WooCommerceHandler::inject_course_data()`
2. Les cours sont affichés dans le panier via `WooCommerceHandler::display_course_data()`
3. L'utilisateur peut sélectionner un plan de paiement (mais pas une saison, ce qui peut bloquer les étapes suivantes)
4. Ces sélections sont stockées en session et affichées dans le résumé de la commande

### Fonctions clés
- `WooCommerceHandler::inject_course_data()` - Injection des données de cours dans l'article du panier
- `WooCommerceHandler::display_course_data()` - Affichage des cours dans le panier
- `WooCommerceHandler::add_saison_selector_checkout()` - Ajout du sélecteur de saison (non fonctionnel actuellement)
- `WooCommerceHandler::add_payment_plan_selector_checkout()` - Ajout du sélecteur de plan de paiement

## 6. Processus de Commande et Confirmation

### Fichiers impliqués
- `/includes/modules/class-woocommerce-handler.php` - Sauvegarde des données de commande
- `/includes/modules/class-fpr-amelia.php` - Création des réservations

### Processus
1. Lors de la validation de la commande, la saison et le plan de paiement sont sauvegardés dans les métadonnées de la commande
2. Lorsque la commande passe au statut "processing" ou "completed", `FPRAmelia::handle_order_status_change()` est appelé
3. Cette méthode traite chaque article de la commande et extrait les cours sélectionnés
4. Pour chaque cours, elle appelle `FPRAmelia::register_customer_for_course()` pour créer la réservation

### Fonctions clés
- `WooCommerceHandler::save_saison_checkout()` - Sauvegarde de la saison
- `WooCommerceHandler::save_payment_plan_checkout()` - Sauvegarde du plan de paiement
- `FPRAmelia::handle_order_status_change()` - Traitement du changement de statut de commande
- `FPRAmelia::register_customer_for_course()` - Enregistrement du client pour un cours

## 7. Création des Réservations

### Fichiers impliqués
- `/includes/modules/class-fpr-amelia.php` - Création des réservations

### Processus
1. Pour chaque cours, `FPRAmelia::register_customer_for_course()` est appelé
2. Cette méthode trouve ou crée un client FPR via `FPRAmelia::find_or_create_customer()`
3. Elle trouve ou crée un événement via `FPRAmelia::find_event_by_name_and_tag()` ou `FPRAmelia::create_event()`
4. Elle trouve ou crée une période d'événement via `FPRAmelia::find_or_create_period()`
5. Enfin, elle crée une réservation pour le client pour cette période d'événement
6. **Problème actuel**: L'utilisateur n'est pas correctement ajouté au cours Amelia qu'il a sélectionné, probablement en raison de l'absence de sélection de saison fonctionnelle

### Fonctions clés
- `FPRAmelia::register_customer_for_course()` - Enregistrement du client pour un cours
- `FPRAmelia::find_or_create_customer()` - Recherche ou création d'un client
- `FPRAmelia::find_event_by_name_and_tag()` - Recherche d'un événement par nom et tag
- `FPRAmelia::create_event()` - Création d'un événement
- `FPRAmelia::find_or_create_period()` - Recherche ou création d'une période d'événement

## Résumé du Flux

1. **Sélection des Cours** : L'utilisateur sélectionne des cours sur la page de planning
2. **Validation** : L'utilisateur valide sa sélection, déclenchant une requête AJAX
3. **Traitement** : Le serveur traite la sélection, détermine le produit approprié et l'ajoute au panier
4. **Panier** : L'utilisateur est redirigé vers le panier où il peut voir les cours sélectionnés
5. **Checkout** : L'utilisateur sélectionne un plan de paiement (la sélection de saison est actuellement non fonctionnelle), puis finalise sa commande
6. **Confirmation** : Lorsque la commande est confirmée, le système crée les réservations pour chaque cours
7. **Réservation** : Les réservations sont créées dans le système FPR Amelia, liant le client, l'événement et la période
