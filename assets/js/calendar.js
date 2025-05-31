// calendar.js version consolidée et corrigée
document.addEventListener("DOMContentLoaded", function () {
    console.log("init JS");

    // Vérifier si l'utilisateur revient d'une commande (via l'URL, un cookie ou un paramètre)
    const urlParams = new URLSearchParams(window.location.search);

    // Fonction pour lire un cookie par son nom
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    // Si l'URL contient from_checkout=1, rediriger vers /planning sans paramètre
    // pour éviter les problèmes d'affichage
    if (urlParams.get('from_checkout') === '1') {
        // Définir le cookie avant la redirection
        document.cookie = "fpr_from_checkout=1; path=/; max-age=300";
        // Rediriger vers /planning sans paramètre
        window.location.href = '/planning';
        return; // Arrêter l'exécution du script
    }

    const fromCheckout = document.referrer.includes('/checkout/') || 
                         document.referrer.includes('/order-received/') ||
                         getCookie('fpr_from_checkout') === '1';

    // Si l'utilisateur revient d'une commande, nettoyer le localStorage
    // et afficher le toast de confirmation
    if (fromCheckout) {
        localStorage.removeItem('fpr_selected_courses');
        console.log('Données de sélection de cours nettoyées après commande');

        // Afficher le toast pour confirmer que les cours ont été enregistrés
        const toast = document.getElementById('fpr-toast');
        if (toast) {
            toast.style.visibility = 'visible';
            toast.classList.add('show');

            // Cacher le toast après 3 secondes
            setTimeout(function() {
                toast.classList.remove('show');
                setTimeout(function() {
                    toast.style.visibility = 'hidden';
                }, 500); // Attendre la fin de l'animation de fondu
            }, 3000);
        }
    }

    const selectableClasses = document.querySelectorAll(".wcs-class:not(.wcs-class--term-pas-de-cours)");

    // Assure que excluded_courses est bien un tableau
    const excludedNames = Array.isArray(fprAjax.excluded_courses)
        ? fprAjax.excluded_courses.map(name => name.trim().toLowerCase())
        : [];

    console.log("⛔️ Cours exclus :", excludedNames);

    // Ajouter des logs pour le débogage
    console.log("Cours exclus (brut) :", fprAjax.excluded_courses);
    console.log("Cours exclus (traités) :", excludedNames);

    const floatButton = document.createElement("div");
    floatButton.id = "fpr-float-button";
    floatButton.innerHTML = `
        <span id="fpr-count">0</span> cours sélectionné(s)
        <button id="fpr-validate-selection">Valider</button>
        <button id="fpr-clear-selection">Tout désélectionner</button>
    `;
    document.body.appendChild(floatButton);

    const style = document.createElement('style');
    style.textContent = `
        #fpr-float-button {
            position: fixed;
            top: 116px;
            right: 20px;
            background: #3498dbd6;
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 9999;
            display: none;
            font-size: 18px;
            font-weight: bold;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            transform: translateY(0);
            opacity: 1;
            max-height: 231px;
        }
        #fpr-float-button.show {
            animation: fpr-bounce 0.5s ease;
        }
        @keyframes fpr-bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-10px);}
            60% {transform: translateY(-5px);}
        }
        #fpr-count {
            font-size: 22px;
            font-weight: bold;
            color: #fff;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        #fpr-float-button button {
            margin-left: 10px;
            background: white;
            color: #3498db;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        #fpr-float-button button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
    `;
    document.head.appendChild(style);

    const updateCounter = () => {
        const stored = localStorage.getItem("fpr_selected_courses");
        let count = 0;
        if (stored) {
            try {
                const parsed = JSON.parse(stored);
                count = Array.isArray(parsed) ? parsed.length : 0;
            } catch (e) {}
        }

        // Mettre à jour le texte du compteur
        const countElement = document.getElementById("fpr-count");
        const oldCount = parseInt(countElement.textContent) || 0;
        countElement.textContent = count;

        // Afficher ou masquer le bouton flottant
        floatButton.style.display = count > 0 ? "flex" : "none";

        // Ajouter l'animation seulement si le compteur change
        if (count !== oldCount && count > 0) {
            // Supprimer la classe pour réinitialiser l'animation
            floatButton.classList.remove("show");

            // Forcer un reflow pour que l'animation se déclenche à nouveau
            void floatButton.offsetWidth;

            // Ajouter la classe pour déclencher l'animation
            floatButton.classList.add("show");
        }
    };

    const highlightSelections = () => {
        const stored = localStorage.getItem("fpr_selected_courses");
        if (!stored) return;
        try {
            const selectedCourses = JSON.parse(stored);
            selectableClasses.forEach(el => {
                const title = el.querySelector(".wcs-class__title")?.innerText.trim();
                const match = selectedCourses.find(c => c.title === title);
                if (match) el.classList.add("fpr-selected");
            });
        } catch (e) {}
    };

    selectableClasses.forEach((el) => {
        const title = el.querySelector(".wcs-class__title")?.innerText.trim();
        if (!title) return;

        // Ajouter des logs pour le débogage
        console.log("Vérification du cours :", title);
        console.log("Titre en minuscules :", title.toLowerCase());
        console.log("Est exclu ?", excludedNames.includes(title.toLowerCase()));

        if (excludedNames.includes(title.toLowerCase())) {
            console.log("✅ Cours exclu trouvé :", title);
            el.classList.add("fpr-excluded");

            // Ajouter une infobulle (tooltip) au survol
            el.addEventListener("mouseenter", function(e) {
                // Créer l'élément tooltip s'il n'existe pas déjà
                let tooltip = document.querySelector('#fpr-global-tooltip');
                if (!tooltip) {
                    tooltip = document.createElement('div');
                    tooltip.id = 'fpr-global-tooltip';
                    tooltip.className = 'fpr-tooltip';
                    document.body.appendChild(tooltip);
                }

                // Définir le contenu du tooltip
                tooltip.textContent = `Le cours ${title} est exclus des formules, réservation et paiement en direct.`;

                // Positionner le tooltip au-dessus de l'élément
                const rect = this.getBoundingClientRect();
                tooltip.style.top = (rect.top - 40) + 'px';
                tooltip.style.left = (rect.left + rect.width / 2) + 'px';
                tooltip.style.transform = 'translateX(-50%)';

                // Afficher le tooltip
                setTimeout(() => {
                    tooltip.classList.add('show');
                }, 50);
            });

            // Cacher l'infobulle quand la souris quitte l'élément
            el.addEventListener("mouseleave", function() {
                const tooltip = document.querySelector('#fpr-global-tooltip');
                if (tooltip) {
                    tooltip.classList.remove('show');
                }
            });

            // Conserver le comportement du clic pour afficher la modale
            el.addEventListener("click", function () {
                showExcludedModal(title);
            });

            return; // ne pas ajouter l'autre listener
        }


        // Ajouter une propriété pour suivre l'état du clic
        if (el.isProcessingClick) {
            return; // Si un clic est déjà en cours de traitement, ignorer ce clic
        }

        el.addEventListener("click", function () {
            // Éviter les clics multiples rapides
            if (this.isProcessingClick) {
                return;
            }

            // Marquer comme en cours de traitement
            this.isProcessingClick = true;

            const time = el.querySelector(".wcs-class__time")?.innerText;
            const duration = el.querySelector(".wcs-class__duration")?.innerText;
            const instructor = el.querySelector(".wcs-class__instructor")?.innerText;

            const newCourse = { title, time, duration, instructor };

            const stored = localStorage.getItem("fpr_selected_courses");
            let previous = [];

            if (stored) {
                try {
                    previous = JSON.parse(stored);
                    if (!Array.isArray(previous)) previous = [];
                } catch (e) {
                    previous = [];
                }
            }

            const index = previous.findIndex(c =>
                c.title === newCourse.title &&
                c.time === newCourse.time &&
                c.duration === newCourse.duration &&
                c.instructor === newCourse.instructor
            );

            if (index !== -1) {
                previous.splice(index, 1);
                el.classList.remove("fpr-selected");
            } else {
                previous.push(newCourse);
                el.classList.add("fpr-selected");
            }

            localStorage.setItem("fpr_selected_courses", JSON.stringify(previous));
            updateCounter();

            // Réinitialiser l'état après un court délai
            setTimeout(() => {
                this.isProcessingClick = false;
            }, 300); // 300ms de délai avant de permettre un nouveau clic
        });
    });

    document.getElementById("fpr-validate-selection").addEventListener("click", function () {
        console.log('[FPR Debug] 🔘 Clic sur le bouton "Valider"');

        const stored = localStorage.getItem("fpr_selected_courses");
        console.log('[FPR Debug] 📋 Données brutes du localStorage:', stored);

        let allCourses = [];

        if (stored) {
            try {
                allCourses = JSON.parse(stored);
                console.log('[FPR Debug] ✅ Parsing JSON réussi');

                if (!Array.isArray(allCourses)) {
                    console.log('[FPR Debug] ⚠️ Les données ne sont pas un tableau:', typeof allCourses);
                    allCourses = [];
                } else {
                    console.log('[FPR Debug] 📊 Nombre de cours récupérés:', allCourses.length);

                    // Log détaillé des cours
                    allCourses.forEach((course, index) => {
                        console.log(`[FPR Debug] 📌 Cours #${index + 1}:`, course);
                    });
                }
            } catch (e) {
                console.error('[FPR Debug] ❌ Erreur lors du parsing des cours:', e);
                allCourses = [];
            }
        } else {
            console.log('[FPR Debug] ℹ️ Aucune donnée de cours dans localStorage');
        }

        const count = allCourses.length;
        console.log('[FPR Debug] 📊 Nombre total de cours à envoyer:', count);

        // Vérifier si on doit réinitialiser la session
        const shouldReset = !document.referrer.includes('/mon-panier');
        console.log('[FPR Debug] 🔄 Réinitialisation de la session:', shouldReset ? 'Oui' : 'Non');

        console.log('[FPR Debug] 🔄 Envoi des données au serveur via AJAX');
        console.log('[FPR Debug] 📤 Données envoyées:', {
            action: 'fpr_get_product_id',
            count: count,
            courses: allCourses
        });

        jQuery.post(fprAjax.ajaxurl, {
            action: 'fpr_get_product_id',
            count: count,
            courses: allCourses
        }, function (response) {
            console.log('[FPR Debug] ✅ Réponse AJAX reçue:', response);

            if (response.success && response.data && response.data.added_product_id) {
                console.log('[FPR Debug] 🛒 Produit ajouté au panier, ID:', response.data.added_product_id);
                console.log('[FPR Debug] 🔄 Redirection vers la page panier');

                // Ajouter le paramètre de réinitialisation si nécessaire
                const resetParam = shouldReset ? '&fpr_reset=1' : '';
                window.location.href = '/mon-panier/?fpr_confirmed=1' + resetParam;
            } else {
                console.error('[FPR Debug] ❌ Erreur: Réponse invalide ou produit non ajouté');
                alert('Une erreur est survenue lors de l\'ajout des cours au panier. Veuillez réessayer.');
            }
        }).fail(function(xhr, status, error) {
            console.error('[FPR Debug] ❌ Erreur AJAX:', status, error);
            console.error('[FPR Debug] ❌ Détails de l\'erreur:', xhr.responseText);
            alert('Une erreur est survenue lors de l\'ajout des cours au panier. Veuillez réessayer.');
        });
    });

    document.getElementById("fpr-clear-selection").addEventListener("click", function () {
        localStorage.removeItem("fpr_selected_courses");
        document.querySelectorAll(".fpr-selected").forEach(el => el.classList.remove("fpr-selected"));
        updateCounter();
    });

    updateCounter();
    highlightSelections();

    // assets/js/calendar.js (extrait à insérer pour la modale)

    function showExcludedModal(title) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'fprExcludedModal';
        modal.tabIndex = -1;
        modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cours exclu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Le cours <strong>${title}</strong> est exclus des formules, réservation et paiement en direct.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    `;
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }

});
