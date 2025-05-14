// calendar.js version consolidée et corrigée
document.addEventListener("DOMContentLoaded", function () {
    console.log("init JS");

    const selectableClasses = document.querySelectorAll(".wcs-class:not(.wcs-class--term-pas-de-cours)");

    // Assure que excluded_courses est bien un tableau
    const excludedNames = Array.isArray(fprAjax.excluded_courses)
        ? fprAjax.excluded_courses.map(name => name.trim().toLowerCase())
        : [];

    console.log("⛔️ Cours exclus :", excludedNames);

    console.log(excludedNames);

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
            bottom: 30px;
            right: 30px;
            background: #5cb85c;
            color: white;
            padding: 15px 20px;
            border-radius: 50px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 9999;
            display: none;
            font-size: 16px;
            align-items: center;
            gap: 10px;
        }
        #fpr-float-button button {
            margin-left: 10px;
            background: white;
            color: #5cb85c;
            border: none;
            padding: 6px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
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
        document.getElementById("fpr-count").textContent = count;
        floatButton.style.display = count > 0 ? "flex" : "none";
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

        if (excludedNames.includes(title.toLowerCase())) {
            el.classList.add("fpr-excluded");
            el.addEventListener("click", function () {
                alert("❌ Ce cours est hors formule : merci de le régler directement au centre.");
            });
            return; // ne pas ajouter l'autre listener
        }


        el.addEventListener("click", function () {
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
        });
    });

    document.getElementById("fpr-validate-selection").addEventListener("click", function () {
        const stored = localStorage.getItem("fpr_selected_courses");
        let allCourses = [];

        if (stored) {
            try {
                allCourses = JSON.parse(stored);
                if (!Array.isArray(allCourses)) allCourses = [];
            } catch (e) {
                allCourses = [];
            }
        }

        const count = allCourses.length;

        jQuery.post(fprAjax.ajax_url, {
            action: 'fpr_get_product_id',
            count: count,
            courses: allCourses
        }, function () {
            window.location.href = '/mon-panier/?fpr_confirmed=1';
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
                    <h5 class="modal-title">Cours non disponible dans les formules</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Le cours <strong>${title}</strong> n'est pas disponible via une formule d'abonnement. Merci de contacter le centre pour plus d'information.</p>
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

// Dans la boucle selectableClasses.forEach
    if (excludedNames.includes(title.toLowerCase())) {
        el.addEventListener("click", function () {
            showExcludedModal(title);
        });
        return;
    }

});
