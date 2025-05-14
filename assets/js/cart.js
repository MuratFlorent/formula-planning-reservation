document.addEventListener("DOMContentLoaded", function () {
    const previewContainer = document.getElementById("fpr-selection-preview");

    // Fonction utilitaire pour parser les cours stockés
    function getSelectedCourses() {
        try {
            const stored = localStorage.getItem("fpr_selected_courses");
            const parsed = JSON.parse(stored);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    // Affichage des cours dans le panier
    function displayCourses(courses) {
        if (!previewContainer || courses.length === 0) return;
        let html = `<div class="fpr-courses-wrapper"><h3>Vos cours sélectionnés :</h3><ul>`;
        courses.forEach((course) => {
            html += `<li>
                <strong>${course.title}</strong> - ${course.time} (${course.duration}) avec ${course.instructor}
            </li>`;
        });
        html += `</ul></div>`;
        previewContainer.innerHTML = html;
    }

    // Envoi AJAX au backend pour traitement produit WooCommerce
    function sendCoursesToBackend(courses) {
        const count = courses.length;

        jQuery.post(fprAjax.ajax_url, {
            action: 'fpr_get_product_id',
            count: count,
            courses: courses, // si besoin plus tard pour log/debug
        }).done(function (response) {
            console.log("Produit lié récupéré avec succès.");
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.error("Erreur AJAX :", textStatus, errorThrown);
        });
    }

    // Initialisation
    const selectedCourses = getSelectedCourses();
    displayCourses(selectedCourses);

    // Ne pas envoyer plusieurs fois si déjà confirmé
    const urlParams = new URLSearchParams(window.location.search);
    const alreadySent = urlParams.has("fpr_confirmed");

    if (!alreadySent && selectedCourses.length > 0) {
        sendCoursesToBackend(selectedCourses);
        // Nettoyage de l'URL pour éviter la répétition
        const newUrl = window.location.origin + window.location.pathname + "?fpr_confirmed=1";
        window.history.replaceState({}, document.title, newUrl);
    }
});
