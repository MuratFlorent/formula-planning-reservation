// cart-loader.js - Améliore l'expérience utilisateur avec des transitions fluides
document.addEventListener('DOMContentLoaded', function() {
    // Créer l'élément loader
    const loader = document.createElement('div');
    loader.className = 'fpr-page-loader';
    loader.innerHTML = `
        <div class="fpr-loader-spinner"></div>
        <div class="fpr-loader-text">Chargement en cours...</div>
    `;
    document.body.appendChild(loader);

    // Fonction pour afficher le loader
    function showLoader() {
        loader.style.display = 'flex';
    }

    // Fonction pour cacher le loader
    function hideLoader() {
        loader.style.display = 'none';
    }

    // Cacher le loader initialement
    hideLoader();

    // Vérifier si nous sommes sur la page de commande (checkout)
    const isCheckoutPage = window.location.href.includes('/commander') || 
                          document.querySelector('.woocommerce-checkout') !== null ||
                          document.querySelector('.wc-block-checkout') !== null;

    // Vérifier si nous sommes sur la page de confirmation de commande
    const isOrderReceivedPage = window.location.href.includes('/order-received/');

    // Intercepter les clics sur les liens de navigation
    document.querySelectorAll('a[href]:not([target="_blank"])').forEach(link => {
        link.addEventListener('click', function(e) {
            // Ne pas afficher le loader pour les liens d'ancrage ou les liens externes
            if (this.getAttribute('href').startsWith('#') || 
                this.getAttribute('href').startsWith('mailto:') ||
                this.getAttribute('href').startsWith('tel:')) {
                return;
            }

            showLoader();
            // Le loader sera caché automatiquement quand la nouvelle page sera chargée
        });
    });

    // Intercepter les soumissions de formulaire
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            showLoader();
        });
    });

    // Intercepter les clics sur les boutons d'ajout au panier
    document.querySelectorAll('.add_to_cart_button, .single_add_to_cart_button').forEach(button => {
        button.addEventListener('click', function() {
            showLoader();
            // Cacher le loader après un délai pour les actions AJAX
            setTimeout(hideLoader, 1500);
        });
    });

    // Cacher le loader quand la page est complètement chargée
    window.addEventListener('load', hideLoader);

    // Ajouter des animations aux éléments du panier
    const cartItems = document.querySelectorAll('.cart_item');
    cartItems.forEach((item, index) => {
        item.style.animationDelay = (index * 0.1) + 's';
        item.classList.add('fpr-fade-in');
    });

    // Vérifier si nous sommes sur la page panier
    const isCartPage = window.location.href.includes('mon-panier') || 
                       document.querySelector('.woocommerce-cart-form') !== null;

    // Vérifier si le panier est vide
    const isCartEmpty = document.querySelector('.cart-empty') !== null || 
                       (document.querySelector('.woocommerce-cart-form') === null && isCartPage);

    // Vérifier si l'utilisateur revient d'une commande validée
    const fromCheckout = document.referrer.includes('/checkout/') || 
                         document.referrer.includes('/order-received/');

    console.log('[FPR Debug] 🔍 Page panier:', isCartPage);
    console.log('[FPR Debug] 🔍 Panier vide:', isCartEmpty);
    console.log('[FPR Debug] 🔍 Utilisateur vient d\'une commande:', fromCheckout);

    // Si nous sommes sur la page panier
    if (isCartPage) {
        // Modifier la structure du panier selon les nouvelles spécifications

        // Supprimer les doublons dans les variations de produits
        const variations = document.querySelectorAll('.woocommerce-cart-form__cart-item .variation');
        variations.forEach(variation => {
            // Créer un ensemble pour suivre les valeurs uniques
            const uniqueValues = new Set();
            const uniqueCourses = new Set();
            const items = variation.querySelectorAll('dt, dd');

            // Parcourir tous les éléments dt et dd
            for (let i = 0; i < items.length; i += 2) {
                if (i + 1 < items.length) {
                    const dt = items[i];
                    const dd = items[i + 1];
                    const value = dd.textContent.trim();

                    // Vérifier si c'est un cours sélectionné
                    if (dt.textContent.includes('Cours sélectionné')) {
                        // Extraire les informations essentielles du cours (titre, heure, durée, instructeur)
                        // Format attendu: "Titre | [Jour |] Heure | Durée | avec Instructeur"
                        const parts = value.split('|').map(part => part.trim());

                        // Le titre est toujours la première partie
                        const title = parts[0];

                        // Trouver l'heure (contient généralement ":")
                        let time = '';
                        let instructor = '';
                        let duration = '';

                        for (let j = 1; j < parts.length; j++) {
                            const part = parts[j];
                            if (part.includes(':')) {
                                time = part;
                            } else if (part.includes('avec')) {
                                instructor = part;
                            } else if (part.includes('h')) {
                                duration = part;
                            }
                        }

                        // Créer une clé normalisée pour le cours
                        const courseKey = `${title}|${time}|${duration}|${instructor}`;

                        console.log(`[FPR Debug] 🔍 Variation - Texte original: "${value}"`);
                        console.log(`[FPR Debug] 🔑 Variation - Clé normalisée: "${courseKey}"`);

                        // Si ce cours a déjà été vu, supprimer ce dt et dd
                        if (uniqueCourses.has(courseKey)) {
                            console.log(`[FPR Debug] ❌ Variation - Doublon détecté, suppression`);
                            dt.remove();
                            dd.remove();
                        } else {
                            console.log(`[FPR Debug] ✅ Variation - Unique, conservé`);
                            uniqueCourses.add(courseKey);
                        }
                    } else {
                        // Pour les autres types de variations, utiliser la logique originale
                        if (uniqueValues.has(value)) {
                            dt.remove();
                            dd.remove();
                        } else {
                            uniqueValues.add(value);
                        }
                    }
                }
            }
        });

        // Ajouter une ligne de sous-total sous le prix
        const priceColumns = document.querySelectorAll('.woocommerce-cart-form__cart-item .product-price');
        priceColumns.forEach(priceCol => {
            const priceAmount = priceCol.querySelector('.woocommerce-Price-amount');
            if (priceAmount) {
                const priceClone = priceAmount.cloneNode(true);
                const subtotalRow = document.createElement('div');
                subtotalRow.className = 'cart-subtotal-row';

                const subtotalLabel = document.createElement('span');
                subtotalLabel.className = 'subtotal-label';
                subtotalLabel.textContent = 'Sous-total:';

                const subtotalValue = document.createElement('span');
                subtotalValue.className = 'subtotal-value';
                subtotalValue.appendChild(priceClone);

                subtotalRow.appendChild(subtotalLabel);
                subtotalRow.appendChild(subtotalValue);

                priceCol.appendChild(subtotalRow);
            }
        });

        // Récupérer les plans de paiement via AJAX pour afficher les termes
        fetch(fprAjax.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=fpr_get_payment_plans'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.plans) {
                const plans = data.data.plans;
                const selectedPlanId = data.data.selected_plan_id;

                // Trouver le plan sélectionné
                const selectedPlan = plans.find(plan => plan.id == selectedPlanId) || plans[0];

                // Déterminer le terme à afficher
                let term = '';
                if (selectedPlan.term) {
                    term = '/' + selectedPlan.term;
                } else {
                    const frequencyTerms = {
                        'monthly': '/mois',
                        'quarterly': '/trim',
                        'annual': '/an'
                    };
                    term = frequencyTerms[selectedPlan.frequency] || '';
                }

                // Ajouter le terme à tous les prix - essayer plusieurs sélecteurs pour trouver tous les prix
                const priceSelectors = [
                    '.woocommerce-Price-amount',
                    '.wc-block-components-formatted-money-amount',
                    '.product-price .amount'
                ];

                // Utiliser un Set pour éviter les doublons
                const processedElements = new Set();

                priceSelectors.forEach(selector => {
                    document.querySelectorAll(selector).forEach(priceEl => {
                        // Éviter de traiter le même élément plusieurs fois
                        if (processedElements.has(priceEl)) return;
                        processedElements.add(priceEl);

                        // Supprimer les termes existants
                        const nextSibling = priceEl.nextElementSibling;
                        if (nextSibling && nextSibling.classList.contains('fpr-payment-term')) {
                            nextSibling.remove();
                        }

                        // Ajouter le nouveau terme
                        const termSpan = document.createElement('span');
                        termSpan.className = 'fpr-payment-term';
                        termSpan.textContent = term;
                        priceEl.insertAdjacentElement('afterend', termSpan);
                    });
                });
            }
        })
        .catch(error => {
            console.error('[FPR Debug] ❌ Erreur AJAX:', error);
        });
    }

    // Ne supprimer les cours sélectionnés que si l'utilisateur vient de valider une commande
    if (isCartEmpty && fromCheckout) {
        console.log('[FPR Debug] 🗑️ Panier vide après commande, suppression des cours du localStorage');
        localStorage.removeItem('fpr_selected_courses');
    }

    // Supprimer l'élément fpr-selection-preview s'il existe
    // pour éviter la duplication avec l'affichage WooCommerce
    const coursesList = document.getElementById('fpr-selection-preview');
    if (coursesList) {
        coursesList.remove();
    }

    // Si nous sommes sur la page de commande (checkout)
    if (isCheckoutPage) {
        console.log('[FPR Debug] 🔍 Page de commande détectée');

        // Gérer le bouton "Commander" et son loader
        function setupCommanderButton() {
            const commanderButton = document.querySelector('.wc-block-components-checkout-place-order-button');
            if (commanderButton) {
                console.log('[FPR Debug] ✅ Bouton Commander trouvé');

                // Ajouter un gestionnaire d'événements pour le clic sur le bouton
                commanderButton.addEventListener('click', function(e) {
                    // Ne pas ajouter la classe is-loading si le bouton est déjà en cours de chargement
                    if (!this.classList.contains('is-loading')) {
                        console.log('[FPR Debug] 🔄 Clic sur le bouton Commander, ajout de la classe is-loading');
                        this.classList.add('is-loading');

                        // Afficher également le loader global pour la transition de page
                        showLoader();
                    }
                });
            } else {
                console.log('[FPR Debug] ❌ Bouton Commander non trouvé, nouvelle tentative dans 500ms');
                // Si le bouton n'est pas encore dans le DOM, réessayer plus tard
                setTimeout(setupCommanderButton, 500);
            }
        }

        // Initialiser la configuration du bouton Commander
        setupCommanderButton();

        // Attendre que le DOM soit complètement chargé et que les blocs WooCommerce soient rendus
        const checkSidebar = setInterval(function() {
            // Essayer plusieurs sélecteurs pour trouver le sidebar
            const sidebar = document.querySelector('.wc-block-components-sidebar.wc-block-checkout__sidebar') || 
                           document.querySelector('.wc-block-components-sidebar.wp-block-woocommerce-checkout-totals-block') ||
                           document.querySelector('.wp-block-woocommerce-checkout-totals-block');

            if (sidebar) {
                console.log('[FPR Debug] ✅ Sidebar trouvé');
                clearInterval(checkSidebar);

                // Récupérer les plans de paiement via AJAX
                fetch(fprAjax.ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=fpr_get_payment_plans'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.plans) {
                        const plans = data.data.plans;
                        const selectedPlanId = data.data.selected_plan_id;

                        // Trouver le plan sélectionné
                        const selectedPlan = plans.find(plan => plan.id == selectedPlanId) || plans[0];

                        // Modifier l'affichage des prix pour inclure le terme du plan
                        updatePriceDisplay(selectedPlan);

                        // Ajouter le sélecteur de plan de paiement dans le sidebar
                        addPaymentPlanSelector(sidebar, plans, selectedPlan);
                    } else {
                        console.log('[FPR Debug] ❌ Erreur lors de la récupération des plans de paiement');
                    }
                })
                .catch(error => {
                    console.error('[FPR Debug] ❌ Erreur AJAX:', error);
                });
            }
        }, 500); // Vérifier toutes les 500ms

        // Fonction pour mettre à jour l'affichage des prix avec le terme du plan
        function updatePriceDisplay(plan) {
            // Déterminer le terme à afficher
            let term = '';
            if (plan.term) {
                term = '/' + plan.term;
            } else {
                const frequencyTerms = {
                    'monthly': '/mois',
                    'quarterly': '/trim',
                    'annual': '/an'
                };
                term = frequencyTerms[plan.frequency] || '';
            }

            // Mettre à jour tous les éléments de prix - essayer plusieurs sélecteurs pour trouver tous les prix
            const priceSelectors = [
                '.wc-block-components-totals-item__value .woocommerce-Price-amount',
                '.woocommerce-Price-amount',
                '.wc-block-components-formatted-money-amount'
            ];

            // Utiliser un Set pour éviter les doublons
            const processedElements = new Set();

            priceSelectors.forEach(selector => {
                document.querySelectorAll(selector).forEach(priceEl => {
                    // Éviter de traiter le même élément plusieurs fois
                    if (processedElements.has(priceEl)) return;
                    processedElements.add(priceEl);

                    // Supprimer les termes existants
                    const nextSibling = priceEl.nextElementSibling;
                    if (nextSibling && nextSibling.classList.contains('fpr-payment-term')) {
                        nextSibling.remove();
                    }

                    // Ajouter le nouveau terme
                    const termSpan = document.createElement('span');
                    termSpan.className = 'fpr-payment-term';
                    termSpan.textContent = term;
                    priceEl.insertAdjacentElement('afterend', termSpan);
                });
            });
        }

        // Fonction pour ajouter le sélecteur de plan de paiement
        function addPaymentPlanSelector(sidebar, plans, selectedPlan) {
            // Créer le bloc de résumé du plan actuel
            const summaryDiv = document.createElement('div');
            summaryDiv.className = 'fpr-sidebar-summary';
            summaryDiv.innerHTML = `
                <p>Formule <span class="fpr-plan-name">${selectedPlan.name}</span></p>
                <p>${selectedPlan.installments} versements${selectedPlan.term ? ' /' + selectedPlan.term : ''}</p>
            `;

            // Créer le sélecteur de plan
            const selectorDiv = document.createElement('div');
            selectorDiv.className = 'fpr-payment-plan-selector';

            let selectorHtml = '<h4>Changer de plan de paiement</h4>';
            selectorHtml += '<select id="fpr-payment-plan-select">';

            plans.forEach(plan => {
                // Déterminer le terme à afficher
                let termText = '';
                if (plan.term) {
                    termText = '/' + plan.term;
                } else {
                    const frequencyTerms = {
                        'monthly': '/mois',
                        'quarterly': '/trim',
                        'annual': '/an'
                    };
                    termText = frequencyTerms[plan.frequency] || '';
                }

                const selected = plan.id == selectedPlan.id ? 'selected' : '';
                selectorHtml += `<option value="${plan.id}" ${selected}>${plan.name} (${plan.installments} versements${termText})</option>`;
            });

            selectorHtml += '</select>';
            selectorDiv.innerHTML = selectorHtml;

            // Ajouter les éléments au sidebar
            const totalsBlock = sidebar.querySelector('.wp-block-woocommerce-checkout-totals-block');
            if (totalsBlock) {
                totalsBlock.insertBefore(summaryDiv, totalsBlock.firstChild);
                totalsBlock.insertBefore(selectorDiv, totalsBlock.firstChild);

                // Ajouter l'écouteur d'événement pour le changement de plan
                const select = document.getElementById('fpr-payment-plan-select');
                if (select) {
                    select.addEventListener('change', function() {
                        const planId = this.value;
                        const plan = plans.find(p => p.id == planId);

                        if (plan) {
                            // Mettre à jour l'affichage des prix
                            updatePriceDisplay(plan);

                            // Mettre à jour le résumé
                            const termText = plan.term ? '/' + plan.term : '';
                            summaryDiv.innerHTML = `
                                <p>Formule <span class="fpr-plan-name">${plan.name}</span></p>
                                <p>${plan.installments} versements${termText}</p>
                            `;

                            // Enregistrer le plan sélectionné via AJAX
                            fetch(fprAjax.ajaxurl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `action=fpr_save_payment_plan_session&plan_id=${planId}&security=${fprAjax.nonce}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    console.log('[FPR Debug] ✅ Plan de paiement enregistré en session');

                                    // Recharger la page pour mettre à jour les prix
                                    showLoader();
                                    location.reload();
                                } else {
                                    console.log('[FPR Debug] ❌ Erreur lors de l\'enregistrement du plan de paiement');
                                }
                            })
                            .catch(error => {
                                console.error('[FPR Debug] ❌ Erreur AJAX:', error);
                            });
                        }
                    });
                }
            }
        }
    }

    // Si nous sommes sur la page de confirmation de commande
    if (isOrderReceivedPage) {
        console.log('[FPR Debug] 🔍 Page de confirmation de commande détectée');

        // Trouver l'élément où insérer le bouton de retour au planning
        const orderDetails = document.querySelector('.woocommerce-order-details') || 
                            document.querySelector('.woocommerce-order') || 
                            document.querySelector('.entry-content');

        if (orderDetails) {
            console.log('[FPR Debug] ✅ Élément parent trouvé pour le bouton de retour');

            // Créer le bouton de retour au planning
            const backButton = document.createElement('a');
            backButton.href = '/planning';
            backButton.className = 'fpr-back-to-planning-button';
            backButton.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                </svg>
                Retour au planning
            `;

            // Insérer le bouton au début de l'élément parent
            orderDetails.insertBefore(backButton, orderDetails.firstChild);

            // Ajouter une classe pour le style amélioré à la page
            document.body.classList.add('fpr-order-received-page');
        } else {
            console.log('[FPR Debug] ❌ Élément parent non trouvé pour le bouton de retour');
        }
    }
});
