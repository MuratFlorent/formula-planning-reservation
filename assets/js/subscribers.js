// subscribers.js - Améliore l'interface de gestion des abonnés
jQuery(document).ready(function($) {
    // Initialiser Select2 pour tous les champs de sélection
    $('.fpr-form select, .payment-test-form select').select2({
        width: '100%',
        dropdownAutoWidth: true,
        placeholder: 'Sélectionner...',
        allowClear: true
    });

    // Configuration spécifique pour les sélecteurs du test de paiement récurrent
    // Utiliser setTimeout pour s'assurer que le DOM est complètement chargé
    setTimeout(function() {
        // Configuration spécifique pour le sélecteur d'utilisateurs
        if ($('#test_user_id').length) {
            $('#test_user_id').select2('destroy'); // Détruire l'instance existante si elle existe
            $('#test_user_id').select2({
                width: '100%',
                dropdownAutoWidth: true,
                placeholder: 'Sélectionner un utilisateur...',
                allowClear: true,
                dropdownCssClass: 'select2-dropdown-large',
                minimumInputLength: 0,
                language: {
                    inputTooShort: function() {
                        return "Veuillez saisir au moins un caractère...";
                    },
                    noResults: function() {
                        return "Aucun utilisateur trouvé";
                    },
                    searching: function() {
                        return "Recherche en cours...";
                    }
                }
            });
            console.log('Select2 initialisé pour #test_user_id');
        }

        // Configuration spécifique pour le sélecteur d'abonnements
        if ($('#test_subscription_id').length) {
            $('#test_subscription_id').select2('destroy'); // Détruire l'instance existante si elle existe
            $('#test_subscription_id').select2({
                width: '100%',
                dropdownAutoWidth: true,
                placeholder: 'Sélectionner un abonnement...',
                allowClear: true,
                dropdownCssClass: 'select2-dropdown-large',
                minimumInputLength: 0,
                language: {
                    inputTooShort: function() {
                        return "Veuillez saisir au moins un caractère...";
                    },
                    noResults: function() {
                        return "Aucun abonnement trouvé";
                    },
                    searching: function() {
                        return "Recherche en cours...";
                    }
                }
            });
            console.log('Select2 initialisé pour #test_subscription_id');
        }

        // Configuration pour les autres sélecteurs du formulaire de test
        if ($('#test_payment_plan_id').length) {
            $('#test_payment_plan_id').select2('destroy');
            $('#test_payment_plan_id').select2({
                width: '100%',
                dropdownAutoWidth: true,
                placeholder: 'Sélectionner un plan de paiement...',
                allowClear: true,
                dropdownCssClass: 'select2-dropdown-large'
            });
            console.log('Select2 initialisé pour #test_payment_plan_id');
        }

        if ($('#test_saison_id').length) {
            $('#test_saison_id').select2('destroy');
            $('#test_saison_id').select2({
                width: '100%',
                dropdownAutoWidth: true,
                placeholder: 'Sélectionner une saison...',
                allowClear: true,
                dropdownCssClass: 'select2-dropdown-large'
            });
            console.log('Select2 initialisé pour #test_saison_id');
        }

        if ($('#test_frequency').length) {
            $('#test_frequency').select2('destroy');
            $('#test_frequency').select2({
                width: '100%',
                dropdownAutoWidth: true,
                placeholder: 'Sélectionner une fréquence...',
                allowClear: true,
                dropdownCssClass: 'select2-dropdown-large'
            });
            console.log('Select2 initialisé pour #test_frequency');
        }
    }, 100); // Délai de 100ms

    // Gestionnaire d'événement pour le bouton "Créer un utilisateur"
    $(document).on('click', '.create-new-user-btn', function(e) {
        e.preventDefault();

        // Créer le modal pour la création d'utilisateur
        var modalHtml = `
            <div id="create-user-modal" class="modal fade" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Créer un nouvel utilisateur</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="create-user-form">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Nom d'utilisateur</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">Prénom</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name">
                                </div>
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Nom</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name">
                                </div>
                            </form>
                            <div id="create-user-message" class="alert" style="display: none;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="button button-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="button" class="button button-primary" id="create-user-submit">Créer l'utilisateur</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Ajouter le modal au body s'il n'existe pas déjà
        if (!$('#create-user-modal').length) {
            $('body').append(modalHtml);
        }

        // Afficher le modal
        var createUserModal = new bootstrap.Modal(document.getElementById('create-user-modal'));
        createUserModal.show();

        // Gestionnaire d'événement pour le bouton de soumission du formulaire
        $('#create-user-submit').off('click').on('click', function() {
            var form = $('#create-user-form');
            var messageContainer = $('#create-user-message');

            // Vérifier que les champs requis sont remplis
            if (!form.find('#username').val() || !form.find('#email').val()) {
                messageContainer.removeClass('alert-success').addClass('alert-danger')
                    .text('Le nom d\'utilisateur et l\'email sont requis')
                    .show();
                return;
            }

            // Désactiver le bouton pendant la soumission
            $(this).prop('disabled', true).text('Création en cours...');

            // Envoyer la requête AJAX
            $.ajax({
                url: fprAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'fpr_create_user',
                    nonce: fprAdmin.nonce,
                    username: form.find('#username').val(),
                    email: form.find('#email').val(),
                    first_name: form.find('#first_name').val(),
                    last_name: form.find('#last_name').val()
                },
                success: function(response) {
                    if (response.success) {
                        // Afficher un message de succès
                        messageContainer.removeClass('alert-danger').addClass('alert-success')
                            .text(response.data.message)
                            .show();

                        // Réinitialiser le formulaire
                        form[0].reset();

                        // Ajouter le nouvel utilisateur à la liste déroulante
                        var newOption = new Option(
                            response.data.user.display_name + ' (' + response.data.user.email + ')', 
                            response.data.user.id, 
                            true, 
                            true
                        );

                        // Ajouter l'option à tous les sélecteurs d'utilisateurs
                        $('select[name="test_user_id"]').each(function() {
                            $(this).append(newOption).trigger('change');
                        });

                        // Fermer le modal après un court délai
                        setTimeout(function() {
                            createUserModal.hide();
                        }, 2000);
                    } else {
                        // Afficher un message d'erreur
                        messageContainer.removeClass('alert-success').addClass('alert-danger')
                            .text(response.data.message)
                            .show();
                    }
                },
                error: function() {
                    // Afficher un message d'erreur générique
                    messageContainer.removeClass('alert-success').addClass('alert-danger')
                        .text('Une erreur est survenue lors de la création de l\'utilisateur')
                        .show();
                },
                complete: function() {
                    // Réactiver le bouton
                    $('#create-user-submit').prop('disabled', false).text('Créer l\'utilisateur');
                }
            });
        });
    });

    // Fonction pour ouvrir le formulaire d'ajout d'utilisateur en modal
    function openUserModal() {
        // Créer le modal pour l'ajout d'abonnement
        var modalHtml = `
            <div id="fprUserModal" class="modal fade" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Ajouter un nouvel abonnement</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="fpr-form-container"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="button button-secondary" data-bs-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Ajouter le modal au body s'il n'existe pas déjà
        if (!$('#fprUserModal').length) {
            $('body').append(modalHtml);
        }

        // Détruire les instances Select2 existantes avant de cloner le formulaire
        $('.fpr-form:first select').each(function() {
            if ($(this).hasClass('select2-hidden-accessible')) {
                $(this).select2('destroy');
            }
        });

        // Récupérer uniquement le premier formulaire (formulaire de création)
        const formContent = $('.fpr-form:first').clone();

        // S'assurer que le formulaire est visible
        formContent.css({
            'display': 'block',
            'visibility': 'visible',
            'opacity': '1'
        });

        // Modifier le formulaire pour le modal
        formContent.find('.submit').addClass('modal-submit');

        // Vider le conteneur de formulaire et ajouter le formulaire cloné
        $('#fpr-form-container').empty().append(formContent);

        // Afficher le modal
        var userModal = new bootstrap.Modal(document.getElementById('fprUserModal'));
        userModal.show();

        // Réinitialiser Select2 dans le modal
        setTimeout(() => {
            $('#fpr-form-container select').each(function() {
                // S'assurer que Select2 n'est pas déjà initialisé sur cet élément
                if ($(this).data('select2')) {
                    $(this).select2('destroy');
                }

                // Initialiser Select2 avec le modal comme parent pour le dropdown
                $(this).select2({
                    width: '100%',
                    dropdownAutoWidth: true,
                    placeholder: 'Sélectionner...',
                    allowClear: true,
                    dropdownParent: $('#fprUserModal'),
                    containerCssClass: 'select2-container-modal',
                    dropdownCssClass: 'select2-dropdown-modal'
                });
            });
        }, 100);

        // Nettoyer la modal lorsqu'elle est fermée
        $('#fprUserModal').on('hidden.bs.modal', function() {
            // Réinitialiser Select2 pour le formulaire original
            $('.fpr-form:first select').select2({
                width: '100%',
                dropdownAutoWidth: true,
                placeholder: 'Sélectionner...',
                allowClear: true
            });
        });
    }

    // Cacher uniquement le formulaire de création d'abonnement
    $('h2:contains("Créer un nouvel abonnement")').next('.fpr-form').hide();

    // Ajouter le bouton pour ouvrir le modal d'ajout d'utilisateur
    const addButton = $('<button>', {
        type: 'button',
        id: 'fpr-add-user',
        class: 'button button-primary',
        html: '<span class="dashicons dashicons-plus"></span>'
    });

    // Insérer le bouton après le titre "Créer un nouvel abonnement"
    $('h2:contains("Créer un nouvel abonnement")').after(addButton);

    // Ajouter l'événement de clic pour ouvrir le modal
    $('#fpr-add-user').on('click', openUserModal);

    // Ajouter des styles personnalisés pour les sélecteurs et boutons
    const customStyles = `
        <style>
            /* Styles pour Select2 - plus élégant et léger, sans bordure */
            .select2-container--default .select2-selection--single {
                height: 38px;
                border: none;
                border-radius: 6px;
                padding: 4px 8px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
                transition: all 0.3s ease;
                font-family: 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
                background-color: #f8f9fa;
            }

            .select2-container--default .select2-selection--single:hover {
                background-color: #f1f3f5;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            }

            .select2-container--default .select2-selection--single:focus,
            .select2-container--default.select2-container--open .select2-selection--single {
                background-color: #fff;
                box-shadow: 0 2px 10px rgba(52, 152, 219, 0.15);
            }

            .select2-container--default .select2-selection--single .select2-selection__rendered {
                line-height: 30px;
                color: #444;
                font-size: 14px;
                font-weight: 500;
            }

            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 36px;
            }

            /* Styles pour le dropdown Select2 */
            .select2-dropdown {
                border: none;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                border-radius: 6px;
                overflow: hidden;
                z-index: 100000; /* Valeur élevée pour s'assurer qu'il est au-dessus du modal */
            }

            /* Styles pour les résultats Select2 */
            .select2-results__option {
                padding: 8px 12px;
                font-size: 14px;
                transition: all 0.2s ease;
            }

            .select2-container--default .select2-results__option--highlighted[aria-selected] {
                background-color: #3498db;
                color: white;
            }

            /* Styles spécifiques pour Select2 dans le modal */
            #fprUserModal .select2-container,
            #create-user-modal .select2-container {
                z-index: 100001 !important;
            }

            #fprUserModal .select2-dropdown,
            #create-user-modal .select2-dropdown {
                z-index: 100002 !important;
            }

            /* Classes personnalisées pour les Select2 dans le modal */
            .select2-container-modal {
                margin-bottom: 10px;
            }

            .select2-dropdown-modal {
                border: none !important;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1) !important;
                border-radius: 8px !important;
                overflow: hidden !important;
                background-color: white !important;
            }

            /* Assurer que les éléments Select2 sont au-dessus du modal */
            .select2-container--open {
                z-index: 100001 !important;
            }

            .select2-dropdown {
                z-index: 100002 !important;
            }

            /* Style pour le placeholder Select2 */
            .select2-container--default .select2-selection--single .select2-selection__placeholder {
                color: #6c757d;
            }

            /* Style pour le bouton d'ajout */
            #fpr-add-user {
                margin: 10px 0 20px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                padding: 0;
                background-color: #3498db;
                color: white;
                border: none;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                transition: all 0.3s ease;
            }

            #fpr-add-user:hover {
                background-color: #2980b9;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            }

            #fpr-add-user .dashicons {
                font-size: 20px;
                width: 20px;
                height: 20px;
                margin: 0;
            }

            /* Styles pour les modals */
            .modal {
                backdrop-filter: blur(5px);
            }

            .modal-content {
                border: none;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                overflow: hidden;
                animation: modalFadeIn 0.3s ease;
            }

            @keyframes modalFadeIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .modal-header {
                background-color: #3498db;
                color: white;
                padding: 15px 20px;
                border-bottom: none;
            }

            .modal-title {
                font-weight: 600;
                font-size: 18px;
            }

            .modal-body {
                padding: 20px;
            }

            .modal-footer {
                border-top: none;
                padding: 15px 20px;
                background-color: #f8f9fa;
            }

            .btn-close {
                color: white;
                opacity: 0.8;
                transition: all 0.2s ease;
            }

            .btn-close:hover {
                opacity: 1;
                transform: rotate(90deg);
            }

            /* Styles pour les formulaires dans les modals */
            .modal .form-control {
                border: none;
                border-radius: 8px;
                padding: 12px 15px;
                background-color: #f8f9fa;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                transition: all 0.3s ease;
            }

            .modal .form-control:focus {
                background-color: #fff;
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
            }

            .modal .form-label {
                font-weight: 500;
                color: #495057;
                margin-bottom: 8px;
            }

            .modal .mb-3 {
                margin-bottom: 20px !important;
            }

            /* Styles pour les boutons du modal */
            .modal-submit .button,
            .modal .button {
                padding: 10px 20px;
                border-radius: 30px;
                font-weight: 500;
                transition: all 0.3s ease;
                border: none;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            }

            .modal-submit .button-primary,
            .modal .button-primary {
                background-color: #3498db;
                color: white;
            }

            .modal-submit .button-primary:hover,
            .modal .button-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
                background-color: #2980b9;
            }

            .modal-submit .button-secondary,
            .modal .button-secondary {
                background-color: #e9ecef;
                color: #495057;
            }

            .modal-submit .button-secondary:hover,
            .modal .button-secondary:hover {
                background-color: #dee2e6;
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }

            /* Styles pour les alertes dans les modals */
            .modal .alert {
                border-radius: 8px;
                padding: 12px 15px;
                margin-top: 15px;
                border: none;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            }

            .modal .alert-success {
                background-color: #d4edda;
                color: #155724;
            }

            .modal .alert-danger {
                background-color: #f8d7da;
                color: #721c24;
            }
        </style>
    `;

    // Ajouter les styles personnalisés à la page
    $('head').append(customStyles);

    // Gestion des factures
    // Fonction pour ouvrir le modal des factures
    function openInvoicesModal(userId, userName) {
        // Mettre à jour le titre du modal avec le nom de l'utilisateur
        $('#invoice-user-name').text(userName);

        // Afficher le modal
        $('#invoices-modal').show();

        // Afficher le message de chargement
        $('#invoices-container .loading').show();
        $('.invoices-table').hide();
        $('#no-invoices').hide();

        // Charger les factures via AJAX
        $.ajax({
            url: fprAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'fpr_get_user_invoices',
                user_id: userId,
                nonce: fprAdmin.nonce
            },
            success: function(response) {
                // Cacher le message de chargement
                $('#invoices-container .loading').hide();

                if (response.success && response.data.invoices && response.data.invoices.length > 0) {
                    // Vider la liste des factures
                    $('#invoices-list').empty();

                    // Ajouter chaque facture à la liste
                    $.each(response.data.invoices, function(index, invoice) {
                        var row = $('<tr>');
                        row.append($('<td>').text(invoice.invoice_number));
                        row.append($('<td>').text(invoice.invoice_date));
                        row.append($('<td>').text(invoice.amount));
                        row.append($('<td>').text(invoice.status));
                        row.append($('<td>').text(invoice.payment_plan));
                        row.append($('<td>').text(invoice.saison));

                        // Ajouter le bouton de téléchargement
                        var downloadButton = $('<a>', {
                            href: invoice.download_url,
                            class: 'button',
                            target: '_blank',
                            text: 'Télécharger'
                        });

                        row.append($('<td>').append(downloadButton));

                        // Ajouter la ligne au tableau
                        $('#invoices-list').append(row);
                    });

                    // Afficher le tableau
                    $('.invoices-table').show();
                } else {
                    // Afficher le message "Aucune facture"
                    $('#no-invoices').show();
                }
            },
            error: function() {
                // Cacher le message de chargement
                $('#invoices-container .loading').hide();

                // Afficher un message d'erreur
                $('#no-invoices').text(fprAdmin.i18n.error).show();
            }
        });
    }

    // Gestionnaire d'événement pour le bouton "Voir" des factures
    $(document).on('click', '.view-invoices', function() {
        var userId = $(this).data('user-id');
        var userName = $(this).data('user-name');

        openInvoicesModal(userId, userName);
    });

    // Fermer le modal des factures lorsqu'on clique sur la croix
    $(document).on('click', '.fpr-modal-close', function() {
        $('.fpr-modal').hide();
    });

    // Fermer le modal des factures lorsqu'on clique en dehors du contenu
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('fpr-modal')) {
            $('.fpr-modal').hide();
        }
    });

    // Ajouter des styles pour le modal des factures
    const invoiceStyles = `
        <style>
            /* Styles pour le modal des factures */
            .fpr-modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0, 0, 0, 0.4);
            }

            .fpr-modal-content {
                background-color: #fefefe;
                margin: 5% auto;
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 8px;
                width: 80%;
                max-width: 1000px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                position: relative;
            }

            .fpr-modal-close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
                position: absolute;
                top: 10px;
                right: 20px;
            }

            .fpr-modal-close:hover,
            .fpr-modal-close:focus {
                color: black;
                text-decoration: none;
            }

            #invoices-container {
                margin-top: 20px;
            }

            .loading {
                text-align: center;
                padding: 20px;
                font-style: italic;
                color: #666;
            }

            #no-invoices {
                text-align: center;
                padding: 20px;
                font-style: italic;
                color: #666;
            }

            .invoices-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }

            .invoices-table th,
            .invoices-table td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }

            .invoices-table th {
                background-color: #f2f2f2;
                font-weight: bold;
            }

            .invoices-table tr:hover {
                background-color: #f9f9f9;
            }

            .invoices-column .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 5px 10px;
                background-color: #3498db;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .invoices-column .button:hover {
                background-color: #2980b9;
            }

            .invoices-column .dashicons {
                margin-right: 5px;
            }
        </style>
    `;

    // Ajouter les styles pour le modal des factures
    $('head').append(invoiceStyles);
});
