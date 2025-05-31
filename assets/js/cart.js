document.addEventListener("DOMContentLoaded", function () {
    console.log('[FPR Cart Debug] 🚀 Initialisation du script cart.js');

    const previewContainer = document.getElementById("fpr-selection-preview");
    console.log('[FPR Cart Debug] 🔍 Container de prévisualisation:', previewContainer ? 'trouvé' : 'non trouvé');

    // Fonction utilitaire pour parser les cours stockés
    function getSelectedCourses() {
        console.log('[FPR Cart Debug] 📋 Récupération des cours depuis localStorage');

        try {
            const stored = localStorage.getItem("fpr_selected_courses");
            console.log('[FPR Cart Debug] 📋 Données brutes du localStorage:', stored);

            if (!stored) {
                console.log('[FPR Cart Debug] ℹ️ Aucune donnée de cours dans localStorage');
                return [];
            }

            const parsed = JSON.parse(stored);
            console.log('[FPR Cart Debug] ✅ Parsing JSON réussi');

            if (!Array.isArray(parsed)) {
                console.log('[FPR Cart Debug] ⚠️ Les données ne sont pas un tableau:', typeof parsed);
                return [];
            }

            console.log('[FPR Cart Debug] 📊 Nombre de cours récupérés:', parsed.length);

            // Log détaillé des cours
            parsed.forEach((course, index) => {
                console.log(`[FPR Cart Debug] 📌 Cours #${index + 1}:`, course);
            });

            return parsed;
        } catch (e) {
            console.error('[FPR Cart Debug] ❌ Erreur lors du parsing des cours:', e);
            return [];
        }
    }

    // Affichage des cours dans le panier
    function displayCourses(courses) {
        console.log('[FPR Cart Debug] 🖥️ Affichage des cours dans le panier');

        if (!previewContainer) {
            console.log('[FPR Cart Debug] ⚠️ Container de prévisualisation non trouvé, impossible d\'afficher les cours');
            return;
        }

        if (courses.length === 0) {
            console.log('[FPR Cart Debug] ℹ️ Aucun cours à afficher');
            return;
        }

        console.log('[FPR Cart Debug] 📊 Nombre de cours à afficher:', courses.length);

        let html = `<div class="fpr-courses-wrapper"><h3>Vos cours sélectionnés :</h3><ul>`;
        courses.forEach((course, index) => {
            const courseHtml = `<li>
                <strong>${course.title}</strong> - ${course.time} (${course.duration}) avec ${course.instructor}
            </li>`;
            html += courseHtml;
            console.log(`[FPR Cart Debug] 📝 HTML généré pour le cours #${index + 1}:`, courseHtml);
        });
        html += `</ul></div>`;

        console.log('[FPR Cart Debug] 📝 HTML complet généré:', html);
        previewContainer.innerHTML = html;
        console.log('[FPR Cart Debug] ✅ HTML injecté dans le container');
    }

    // Envoi AJAX au backend pour traitement produit WooCommerce
    function sendCoursesToBackend(courses) {
        console.log('[FPR Cart Debug] 🔄 Envoi des cours au backend');
        console.log('[FPR Cart Debug] 📊 Nombre de cours à envoyer:', courses.length);

        const count = courses.length;

        console.log('[FPR Cart Debug] 📤 Données à envoyer:', {
            action: 'fpr_get_product_id',
            count: count,
            courses: courses
        });

        jQuery.post(fprAjax.ajaxurl, {
            action: 'fpr_get_product_id',
            count: count,
            courses: courses,
        }).done(function (response) {
            console.log('[FPR Cart Debug] ✅ Réponse AJAX reçue:', response);
            console.log('[FPR Cart Debug] ✅ Produit lié récupéré avec succès');

            if (response.success && response.data && response.data.added_product_id) {
                console.log('[FPR Cart Debug] 🛒 Produit ajouté au panier, ID:', response.data.added_product_id);

                // Rafraîchir la page pour afficher le panier mis à jour
                console.log('[FPR Cart Debug] 🔄 Rafraîchissement de la page pour afficher le panier mis à jour');
                window.location.reload();
            } else {
                console.error('[FPR Cart Debug] ❌ Erreur: Réponse invalide ou produit non ajouté');
                alert('Une erreur est survenue lors de l\'ajout des cours au panier. Veuillez réessayer.');
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.error('[FPR Cart Debug] ❌ Erreur AJAX:', textStatus, errorThrown);
            console.error('[FPR Cart Debug] ❌ Détails de l\'erreur:', jqXHR.responseText);
            alert('Une erreur est survenue lors de l\'ajout des cours au panier. Veuillez réessayer.');
        });
    }

    // Initialisation
    console.log('[FPR Cart Debug] 🔄 Initialisation du processus');

    const selectedCourses = getSelectedCourses();
    console.log('[FPR Cart Debug] 📊 Nombre total de cours sélectionnés:', selectedCourses.length);

    displayCourses(selectedCourses);
    console.log('[FPR Cart Debug] ✅ Affichage des cours terminé');

    // Ne pas envoyer plusieurs fois si déjà confirmé
    const urlParams = new URLSearchParams(window.location.search);
    const alreadySent = urlParams.has("fpr_confirmed");
    console.log('[FPR Cart Debug] 🔍 Paramètre "fpr_confirmed" dans l\'URL:', alreadySent ? 'présent' : 'absent');

    if (!alreadySent && selectedCourses.length > 0) {
        console.log('[FPR Cart Debug] 🔄 Conditions remplies pour l\'envoi des cours au backend');
        sendCoursesToBackend(selectedCourses);

        // Nettoyage de l'URL pour éviter la répétition
        const newUrl = window.location.origin + window.location.pathname + "?fpr_confirmed=1";
        console.log('[FPR Cart Debug] 🔄 Mise à jour de l\'URL:', newUrl);
        window.history.replaceState({}, document.title, newUrl);
        console.log('[FPR Cart Debug] ✅ URL mise à jour');
    } else {
        console.log('[FPR Cart Debug] ℹ️ Pas d\'envoi au backend:', 
            alreadySent ? 'déjà confirmé' : 'aucun cours à envoyer');
    }
});
