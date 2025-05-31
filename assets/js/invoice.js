/**
 * Invoice generation script
 */
(function($) {
    'use strict';

    // Initialize the invoice functionality
    function initInvoice() {
        // Handle invoice button click
        $(document).on('click', '.generate-invoice', function(e) {
            e.preventDefault();
            
            var orderId = $(this).data('order-id');
            var $button = $(this);
            
            // Show loading state
            $button.addClass('loading').prop('disabled', true);
            
            // Remove any existing invoice modal
            $('#invoice-modal').remove();
            
            // Create a modal container
            var $modal = $('<div id="invoice-modal" class="fpr-modal"></div>');
            var $overlay = $('<div class="fpr-modal-overlay"></div>');
            var $content = $('<div class="fpr-modal-content"></div>');
            var $close = $('<button class="fpr-modal-close">&times;</button>');
            
            $modal.append($overlay).append($content.append($close));
            $('body').append($modal);
            
            // Generate the invoice
            $.ajax({
                url: fpr_invoice.ajax_url,
                type: 'POST',
                data: {
                    action: 'generate_invoice',
                    nonce: fpr_invoice.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        // Add the invoice HTML to the modal
                        $content.append(response.data.html);
                        
                        // Add print button
                        var $printButton = $('<button class="button print-invoice">' + 'Imprimer' + '</button>');
                        $content.append($printButton);
                        
                        // Show the modal
                        $modal.addClass('open');
                        
                        // Handle print button click
                        $printButton.on('click', function() {
                            var printWindow = window.open('', '_blank');
                            printWindow.document.write('<html><head><title>Facture</title>');
                            printWindow.document.write('<style>body { font-family: Arial, sans-serif; }</style>');
                            printWindow.document.write('</head><body>');
                            printWindow.document.write(response.data.html);
                            printWindow.document.write('</body></html>');
                            printWindow.document.close();
                            printWindow.focus();
                            printWindow.print();
                            printWindow.close();
                        });
                    } else {
                        alert('Erreur lors de la génération de la facture.');
                    }
                    
                    // Reset button state
                    $button.removeClass('loading').prop('disabled', false);
                },
                error: function() {
                    alert('Erreur lors de la génération de la facture.');
                    $button.removeClass('loading').prop('disabled', false);
                }
            });
        });
        
        // Handle modal close button click
        $(document).on('click', '.fpr-modal-close, .fpr-modal-overlay', function() {
            $('#invoice-modal').removeClass('open');
        });
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        initInvoice();
    });
    
})(jQuery);