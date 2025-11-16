/**
 * TrustFlowPay Checkout Handler
 * Handles iframe mode checkout integration
 */
jQuery(function($) {
    'use strict';

    // Only proceed for iframe mode
    if (typeof tfpCheckout === 'undefined' || tfpCheckout.mode !== 'iframe') {
        return;
    }

    var tfpHandler = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Hook into WooCommerce checkout process
            var checkoutForm = $('form.checkout');

            checkoutForm.on('checkout_place_order_trustflowpay', function() {
                // Allow WooCommerce to proceed with AJAX order creation
                return true;
            });

            // Listen for AJAX checkout response
            $(document.body).on('checkout_error', function() {
                // Hide iframe and form on error
                $('#trustflowpay-checkout-iframe, #trustflowpay-payment-form').hide();
                $('#trustflowpay-loading').show();
            });

            // Handle successful order creation
            $(document.body).on('payment_method_selected', function() {
                // Reset iframe when payment method changes
                var selectedMethod = $('input[name="payment_method"]:checked').val();
                if (selectedMethod !== 'trustflowpay') {
                    $('#trustflowpay-checkout-iframe, #trustflowpay-payment-form').hide();
                    $('#trustflowpay-loading').show();
                }
            });
        },

        loadIframe: function(params) {
            console.log('TrustFlowPay: Loading iframe with payment form');

            var $form = $('#trustflowpay-payment-form');
            var $iframe = $('#trustflowpay-checkout-iframe');
            var $loading = $('#trustflowpay-loading');

            if ($form.length === 0 || $iframe.length === 0) {
                console.error('TrustFlowPay: Form or iframe element not found');
                return;
            }

            // Clear any existing form fields
            $form.find('input[type=hidden]').remove();

            // Add all payment params as hidden fields
            $.each(params, function(key, value) {
                $('<input>', {
                    type: 'hidden',
                    name: key,
                    value: value
                }).appendTo($form);
            });

            // Show iframe and hide loading
            $loading.hide();
            $iframe.show();
            $form.show();

            // Submit form to iframe
            // Try to use TrustFlowPay's checkoutSubmitHandler if available
            if (typeof checkoutSubmitHandler === 'function') {
                try {
                    console.log('TrustFlowPay: Using checkoutSubmitHandler');
                    checkoutSubmitHandler($form[0]);
                } catch(e) {
                    console.warn('TrustFlowPay: checkoutSubmitHandler error, using fallback:', e);
                    $form[0].submit();
                }
            } else {
                console.log('TrustFlowPay: checkoutSubmitHandler not available, using standard submit');
                $form[0].submit();
            }
        }
    };

    // Initialize
    tfpHandler.init();

    // Override WooCommerce checkout submit handler for TrustFlowPay iframe mode
    var originalSubmit = $.fn.wc_checkout_form.prototype.submit;

    $.fn.wc_checkout_form.prototype.submit = function() {
        var $form = $(this);

        // Check if TrustFlowPay is selected
        var selectedPaymentMethod = $form.find('input[name="payment_method"]:checked').val();

        if (selectedPaymentMethod === 'trustflowpay' && tfpCheckout.mode === 'iframe') {
            // For iframe mode, we need to handle the response differently
            var xhr = $.ajax({
                type: $form.attr('method'),
                url: $form.attr('action'),
                data: $form.serialize(),
                dataType: 'json',
                success: function(result) {
                    try {
                        if (result.result === 'success') {
                            if (result.trustflowpay_mode === 'iframe' && result.trustflowpay_params) {
                                // Stay on checkout page and load iframe
                                tfpHandler.loadIframe(result.trustflowpay_params);

                                // Scroll to payment section
                                $('html, body').animate({
                                    scrollTop: $('#trustflowpay-iframe-wrapper').offset().top - 100
                                }, 500);

                                // Unblock the checkout form
                                $form.removeClass('processing').unblock();
                            } else if (result.redirect) {
                                // Redirect mode or other response
                                window.location = result.redirect;
                            }
                        } else if (result.result === 'failure') {
                            throw new Error('Payment failed');
                        }
                    } catch(err) {
                        console.error('TrustFlowPay checkout error:', err);

                        if (result.messages) {
                            // Show error messages
                            $form.removeClass('processing').unblock();
                            $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
                            $form.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + result.messages + '</div>');
                            $form.removeClass('processing').unblock();
                            $('html, body').animate({
                                scrollTop: ($form.offset().top - 100)
                            }, 1000);
                        }
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('TrustFlowPay AJAX error:', textStatus, errorThrown);
                    $form.removeClass('processing').unblock();
                }
            });

            return false;
        }

        // For other payment methods or redirect mode, use original handler
        return originalSubmit.apply(this, arguments);
    };

    // Listen for messages from TrustFlowPay iframe (for success/failure)
    window.addEventListener('message', function(event) {
        // Verify the origin if needed
        // if (event.origin !== tfpCheckout.base_url) return;

        var data = event.data;

        if (typeof data === 'string') {
            try {
                data = JSON.parse(data);
            } catch(e) {
                return;
            }
        }

        // Handle payment completion messages from TrustFlowPay
        if (data && data.status) {
            console.log('TrustFlowPay payment status:', data.status);

            // TrustFlowPay will handle the redirect via RETURN_URL
            // So we just wait for it
        }
    });
});
