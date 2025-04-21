<?php

namespace FPR\Modules;

class FormHandler {
    public static function init() {
        // Hooks pour Formidable Forms ici
        add_action('frm_after_create_entry', [__CLASS__, 'handle_form_submission'], 30, 2);
    }

    public static function handle_form_submission($entry_id, $form_id) {
        // Exemple de traitement de formulaire : loguer l'entrée
        \FPR\Helpers\Logger::log("Formulaire soumis : ID form = $form_id, ID entry = $entry_id");

        // TODO : implémenter la logique spécifique par ID de formulaire
    }
}
