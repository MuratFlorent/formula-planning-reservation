/**
 * JavaScript for handling payment plan selection and price updates
 */
jQuery(document).ready(function($) {
    // Handle payment plan selection change
    $('#fpr-payment-plan-select').on('change', function() {
        // Show loading indicator
        if (typeof showLoader === 'function') {
            showLoader();
        } else {
            $('body').append('<div class="fpr-loading-overlay"><div class="fpr-loading-spinner"></div></div>');
        }
        
        // Get the selected payment plan ID
        var planId = $(this).val();
        
        // Send AJAX request to update prices
        $.ajax({
            url: fprProductPaymentPlans.ajax_url,
            type: 'POST',
            data: {
                action: 'fpr_update_product_prices',
                plan_id: planId,
                security: fprProductPaymentPlans.security
            },
            success: function(response) {
                if (response.success) {
                    // Update cart item prices
                    $.each(response.data.items, function(cartItemKey, itemData) {
                        var $row = $('tr.cart_item[data-cart-item-key="' + cartItemKey + '"]');
                        if ($row.length) {
                            $row.find('.product-price .amount').html(itemData.price);
                            $row.find('.product-subtotal .amount').html(itemData.subtotal);
                        }
                    });
                    
                    // Update cart totals
                    $('.cart_totals .cart-subtotal .amount').html(response.data.subtotal);
                    $('.cart_totals .order-total .amount').html(response.data.total);
                    
                    // If WooCommerce updates the cart fragments, this will handle it
                    if (response.fragments) {
                        $.each(response.fragments, function(key, value) {
                            $(key).replaceWith(value);
                        });
                    }
                    
                    // Add a visual indicator that prices have been updated
                    $('.cart_totals').addClass('fpr-updated').delay(1000).queue(function(){
                        $(this).removeClass('fpr-updated').dequeue();
                    });
                } else {
                    console.error('Error updating prices:', response.data.message);
                }
                
                // Hide loading indicator
                if (typeof hideLoader === 'function') {
                    hideLoader();
                } else {
                    $('.fpr-loading-overlay').remove();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                
                // Hide loading indicator
                if (typeof hideLoader === 'function') {
                    hideLoader();
                } else {
                    $('.fpr-loading-overlay').remove();
                }
            }
        });
    });
    
    // Add cart item keys as data attributes for easier targeting
    $('.woocommerce-cart-form__cart-item').each(function() {
        var cartItemKey = $(this).find('input[name="cart[' + $(this).index() + '][key]"]').val();
        if (cartItemKey) {
            $(this).attr('data-cart-item-key', cartItemKey);
        }
    });
    
    // Add some basic styles for the loading overlay
    $('<style>')
        .text(`
            .fpr-loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(255, 255, 255, 0.7);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            }
            .fpr-loading-spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                animation: fpr-spin 2s linear infinite;
            }
            @keyframes fpr-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .fpr-updated {
                animation: fpr-highlight 1s ease-out;
            }
            @keyframes fpr-highlight {
                0% { background-color: rgba(52, 152, 219, 0.2); }
                100% { background-color: transparent; }
            }
        `)
        .appendTo('head');
});