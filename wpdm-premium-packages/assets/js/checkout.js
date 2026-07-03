/**
 * Modern Checkout JavaScript
 *
 * Handles AJAX checkout flow with PayPal Smart Buttons integration.
 *
 * @package WPDMPP
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Exit if config not available
    if (typeof wpdmppCheckout === 'undefined') {
        console.error('WPDMPP Checkout: Configuration not found');
        return;
    }

    const config = wpdmppCheckout;
    let paypalButtonsRendered = false;
    let isProcessing = false;
    let currentOrderId = null; // Track local order ID for PayPal flow

    /**
     * Initialize checkout
     */
    function init() {
        bindEvents();
        bindCartEvents();
        initPaymentMethodSelection();
        initTax();

        // Load PayPal SDK and toggle buttons if PayPal is selected and available
        if (config.hasPayPal && config.paypalClientId) {
            const selectedMethod = getSelectedPaymentMethod();
            if (selectedMethod === 'PayPal') {
                loadPayPalSDK();
                $('#wpdmpp-paypal-buttons').show();
                $('#checkout-submit').hide();
            }
        }
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Payment method selection
        $(document).on('change', 'input[name="payment_method"]', handlePaymentMethodChange);

        // Submit button
        $(document).on('click', '#checkout-submit', handleCheckoutSubmit);

        // Input validation on blur
        $(document).on('blur', '.wpdmpp-checkout__input', validateField);

        // Clear error on input
        $(document).on('input', '.wpdmpp-checkout__input', function() {
            clearFieldError($(this).attr('name'));
        });

        // Tax: recalculate when billing country/state changes
        $(document).on('change', '#checkout-country', function() {
            populateStates($(this).val());
        });
        $(document).on('change', '#checkout-state, #checkout-state-text', recalculateTax);
    }

    /**
     * Initialize payment method selection UI
     */
    function initPaymentMethodSelection() {
        const $methods = $('.wpdmpp-checkout__payment-method');
        const $checked = $('input[name="payment_method"]:checked');

        $methods.removeClass('wpdmpp-checkout__payment-method--selected');
        if ($checked.length) {
            $checked.closest('.wpdmpp-checkout__payment-method').addClass('wpdmpp-checkout__payment-method--selected');
        }
    }

    /**
     * Handle payment method change
     */
    function handlePaymentMethodChange(e) {
        const method = $(e.target).val();

        // Update UI
        $('.wpdmpp-checkout__payment-method').removeClass('wpdmpp-checkout__payment-method--selected');
        $(e.target).closest('.wpdmpp-checkout__payment-method').addClass('wpdmpp-checkout__payment-method--selected');

        // Toggle gateway-specific fields
        $('.wpdmpp-checkout__gateway-fields').hide();
        $('.wpdmpp-checkout__gateway-fields[data-gateway="' + method + '"]').show();

        // Show/hide PayPal buttons
        if (method === 'PayPal' && config.hasPayPal && config.paypalClientId) {
            loadPayPalSDK();
            $('#wpdmpp-paypal-buttons').show();
            $('#checkout-submit').hide();
        } else {
            $('#wpdmpp-paypal-buttons').hide();
            $('#checkout-submit').show();
        }
    }

    /**
     * Load PayPal SDK
     */
    function loadPayPalSDK() {
        if (paypalButtonsRendered) return;
        if (typeof paypal !== 'undefined') {
            renderPayPalButtons();
            return;
        }

        const intent = config.isRecurring ? 'subscription' : 'capture';
        const vaultParam = config.isRecurring ? '&vault=true' : '';
        const sdkUrl = `https://www.paypal.com/sdk/js?client-id=${config.paypalClientId}&components=buttons&currency=${config.currencyCode}&intent=${intent}${vaultParam}`;

        loadScript(sdkUrl).then(() => {
            renderPayPalButtons();
        }).catch((err) => {
            console.error('Failed to load PayPal SDK:', err);
            showAlert('error', config.strings.error);
        });
    }

    /**
     * Load external script
     */
    function loadScript(src) {
        return new Promise((resolve, reject) => {
            if (document.querySelector(`script[src="${src}"]`)) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * Render PayPal Smart Buttons
     */
    function renderPayPalButtons() {
        if (paypalButtonsRendered || typeof paypal === 'undefined') return;

        const container = document.getElementById('paypal-button-container');
        if (!container) return;

        const buttonConfig = {
            style: {
                layout: 'horizontal',
                color: 'blue',
                shape: 'rect',
                label: 'pay',
                tagline: false
            },

            // On cancel
            onCancel: function(data) {
                showLoading(false);
                showAlert('warning', config.strings.paypalCancelled || 'Payment cancelled.');
            },

            // On error
            onError: function(err) {
                showLoading(false);
                showAlert('error', config.strings.paypalError);
                console.error('PayPal error:', err);
            }
        };

        if (config.isRecurring) {
            // Subscription flow
            buttonConfig.createSubscription = function(data, actions) {
                if (!validateForm()) {
                    return Promise.reject(new Error(config.strings.validationError));
                }

                showLoading(true);
                const formData = getFormData();

                return fetch(config.restUrl + '/paypal/create-subscription', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.restNonce
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => response.json())
                .then(result => {
                    if (!result.success) {
                        throw new Error(result.message || config.strings.error);
                    }
                    const d = result.data || result;
                    currentOrderId = d.order_id;
                    showLoading(false);
                    return actions.subscription.create({ 'plan_id': d.plan_id });
                })
                .catch(err => {
                    showLoading(false);
                    showAlert('error', err.message);
                    throw err;
                });
            };

            buttonConfig.onApprove = function(data, actions) {
                showLoading(true, config.strings.processing);

                return fetch(config.restUrl + '/paypal/activate-subscription', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.restNonce
                    },
                    body: JSON.stringify({
                        subscription_id: data.subscriptionID,
                        order_id: currentOrderId
                    })
                })
                .then(response => response.json())
                .then(result => {
                    showLoading(false);

                    if (result.success) {
                        const d = result.data || result;
                        showAlert('success', result.message || 'Subscription activated!');
                        const redirectUrl = d.redirect_url || result.redirect_url;
                        if (redirectUrl) {
                            setTimeout(() => {
                                window.location.href = redirectUrl;
                            }, 1000);
                        }
                    } else {
                        showAlert('error', result.message || config.strings.paypalError);
                    }
                })
                .catch(err => {
                    showLoading(false);
                    showAlert('error', config.strings.paypalError);
                    console.error('PayPal subscription error:', err);
                });
            };
        } else {
            // One-time payment flow
            buttonConfig.createOrder = function(data, actions) {
                if (!validateForm()) {
                    return Promise.reject(new Error(config.strings.validationError));
                }

                showLoading(true);
                const formData = getFormData();

                return fetch(config.restUrl + '/paypal/create-order', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.restNonce
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => response.json())
                .then(result => {
                    if (!result.success) {
                        throw new Error(result.message || config.strings.error);
                    }
                    const d = result.data || result;
                    currentOrderId = d.order_id;
                    return d.paypal_order_id;
                })
                .catch(err => {
                    showLoading(false);
                    showAlert('error', err.message);
                    throw err;
                });
            };

            buttonConfig.onApprove = function(data, actions) {
                showLoading(true, config.strings.processing);

                return fetch(config.restUrl + '/paypal/capture', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.restNonce
                    },
                    body: JSON.stringify({
                        paypal_order_id: data.orderID,
                        order_id: currentOrderId
                    })
                })
                .then(response => response.json())
                .then(result => {
                    showLoading(false);

                    if (result.success) {
                        const d = result.data || result;
                        showAlert('success', result.message || 'Payment completed!');
                        const redirectUrl = d.redirect_url || result.redirect_url;
                        if (redirectUrl) {
                            setTimeout(() => {
                                window.location.href = redirectUrl;
                            }, 1000);
                        }
                    } else {
                        showAlert('error', result.message || config.strings.paypalError);
                    }
                })
                .catch(err => {
                    showLoading(false);
                    showAlert('error', config.strings.paypalError);
                    console.error('PayPal capture error:', err);
                });
            };
        }

        paypal.Buttons(buttonConfig).render('#paypal-button-container');

        paypalButtonsRendered = true;
    }

    /**
     * Handle checkout submit (non-PayPal methods)
     */
    function handleCheckoutSubmit(e) {
        e.preventDefault();

        if (isProcessing) return;

        // Validate form
        if (!validateForm()) {
            return;
        }

        const paymentMethod = getSelectedPaymentMethod();
        if (!paymentMethod) {
            showAlert('error', config.strings.error);
            return;
        }

        // Skip submit for PayPal (uses smart buttons)
        if (paymentMethod === 'PayPal') {
            return;
        }

        isProcessing = true;
        showLoading(true);
        setSubmitLoading(true);

        const formData = getFormData();
        formData.payment_method = paymentMethod;

        fetch(config.restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.restNonce
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(result => {
            showLoading(false);
            setSubmitLoading(false);
            isProcessing = false;

            if (result.success) {
                const data = result.data || result;

                // Handle redirect-based payment methods (Cash, Cheque, etc.)
                if (data.redirect_url) {
                    showAlert('success', result.message || config.strings.processing);
                    window.location.href = data.redirect_url;
                } else if (data.payment_type === 'paypal_smart_buttons') {
                    showAlert('success', result.message);
                } else {
                    // Fallback: redirect to order page
                    showAlert('success', result.message || 'Order created successfully!');
                    if (data.order_id) {
                        setTimeout(() => {
                            window.location.href = config.cartUrl + '?step=complete&id=' + data.order_id;
                        }, 1000);
                    }
                }
            } else {
                // Handle validation errors
                const errorData = result.data || {};
                if (errorData.errors) {
                    Object.keys(errorData.errors).forEach(field => {
                        showFieldError(field, errorData.errors[field]);
                    });
                }
                showAlert('error', result.message || config.strings.error);
            }
        })
        .catch(err => {
            showLoading(false);
            setSubmitLoading(false);
            isProcessing = false;
            showAlert('error', config.strings.error);
            console.error('Checkout error:', err);
        });
    }

    /**
     * Get form data
     */
    function getFormData() {
        const data = {
            first_name: $('#checkout-first-name').val().trim(),
            last_name: $('#checkout-last-name').val().trim(),
            email: $('#checkout-email').val().trim(),
            privacy_agreed: $('#checkout-privacy').is(':checked')
        };

        // Billing address (only collected when tax is enabled)
        if (config.taxActive) {
            data.country = $('#checkout-country').val() || '';
            data.state = getSelectedState();
            data.city = $('#checkout-city').val().trim();
            data.address_1 = $('#checkout-address-1').val().trim();
            data.address_2 = $('#checkout-address-2').val().trim();
            data.postcode = $('#checkout-postcode').val().trim();
        }

        return data;
    }

    /**
     * Get selected payment method
     */
    function getSelectedPaymentMethod() {
        return $('input[name="payment_method"]:checked').val();
    }

    /**
     * Validate entire form
     */
    function validateForm() {
        let isValid = true;
        clearAllErrors();

        // First name
        const firstName = $('#checkout-first-name').val().trim();
        if (!firstName) {
            showFieldError('first_name', config.strings.validationError);
            isValid = false;
        }

        // Email
        const email = $('#checkout-email').val().trim();
        if (!email) {
            showFieldError('email', config.strings.validationError);
            isValid = false;
        } else if (!isValidEmail(email)) {
            showFieldError('email', 'Please enter a valid email address.');
            isValid = false;
        }

        // Billing address (required for tax calculation)
        if (config.taxActive) {
            if (!$('#checkout-country').val()) {
                showFieldError('country', config.strings.validationError);
                isValid = false;
            }
            if (!getSelectedState()) {
                showFieldError('state', config.strings.validationError);
                isValid = false;
            }
            if (!$('#checkout-address-1').val().trim()) {
                showFieldError('address_1', config.strings.validationError);
                isValid = false;
            }
            if (!$('#checkout-city').val().trim()) {
                showFieldError('city', config.strings.validationError);
                isValid = false;
            }
            if (!$('#checkout-postcode').val().trim()) {
                showFieldError('postcode', config.strings.validationError);
                isValid = false;
            }
        }

        // Privacy checkbox
        if (config.requirePrivacy && !$('#checkout-privacy').is(':checked')) {
            showFieldError('privacy', config.strings.privacyRequired);
            isValid = false;
        }

        return isValid;
    }

    /**
     * Validate single field
     */
    function validateField(e) {
        const $input = $(e.target);
        const name = $input.attr('name');
        const value = $input.val().trim();

        clearFieldError(name);

        if ($input.prop('required') && !value) {
            showFieldError(name, 'This field is required.');
            return false;
        }

        if (name === 'email' && value && !isValidEmail(value)) {
            showFieldError(name, 'Please enter a valid email address.');
            return false;
        }

        return true;
    }

    /**
     * Check if email is valid
     */
    function isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    // -------------------------------------------------------------------------
    // Tax
    // -------------------------------------------------------------------------

    /**
     * Initialize tax: populate states for the saved country and compute tax.
     */
    function initTax() {
        if (!config.taxActive) return;
        const country = $('#checkout-country').val();
        if (country) {
            populateStates(country);
        }
    }

    /**
     * Populate the state dropdown for the given country, then recalculate tax.
     * Falls back to a free-text input for countries with no state list.
     */
    function populateStates(countryCode) {
        const $stateSelect = $('#checkout-state');
        const $stateText = $('#checkout-state-text');
        if ($stateSelect.length === 0) {
            recalculateTax();
            return;
        }

        // Preserve any previously selected/typed state across re-population.
        const selected = $stateText.val() || $stateSelect.val() || '';

        if (!countryCode) {
            $stateSelect.hide().prop('disabled', true).empty();
            $stateText.show().prop('disabled', false);
            recalculateTax();
            return;
        }

        $.getJSON(config.dataUrl + 'countries.json', function(countries) {
            let filename = '';
            $.each(countries, function(i, country) {
                if (country.code === countryCode && country.filename) {
                    filename = country.filename;
                }
            });

            if (filename) {
                $stateText.hide().prop('disabled', true);
                $stateSelect.show().prop('disabled', false);
                $.getJSON(config.dataUrl + 'countries/' + filename + '.json', function(states) {
                    let options = '';
                    $.each(states, function(i, state) {
                        const scode = state.code.replace(countryCode + '-', '');
                        const sel = scode === selected ? ' selected="selected"' : '';
                        options += '<option value="' + scode + '"' + sel + '>' + state.name + '</option>';
                    });
                    $stateSelect.html(options);
                    recalculateTax();
                }).fail(recalculateTax);
            } else {
                // No state list for this country — use the free-text field.
                $stateSelect.hide().prop('disabled', true).empty();
                $stateText.show().prop('disabled', false);
                recalculateTax();
            }
        }).fail(recalculateTax);
    }

    /**
     * Get the currently selected/typed billing state code.
     */
    function getSelectedState() {
        const $stateSelect = $('#checkout-state');
        if ($stateSelect.is(':visible') && !$stateSelect.prop('disabled')) {
            return $stateSelect.val() || '';
        }
        return $('#checkout-state-text').val() || '';
    }

    /**
     * Recalculate tax against the current billing country/state and refresh totals.
     */
    function recalculateTax() {
        if (!config.taxActive) return;

        const country = $('#checkout-country').val();
        const state = getSelectedState();

        if (!country) {
            applyTax(0, null);
            return;
        }

        fetch(config.cartRestUrl + '/tax', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.restNonce
            },
            body: JSON.stringify({ country: country, state: state })
        })
        .then(function(res) { return res.json(); })
        .then(function(result) {
            const data = (result && result.data) ? result.data : result;
            if (!data) return;
            applyTax(parseFloat(data.tax) || 0, data);
        })
        .catch(function(err) {
            console.error('Tax calculation error:', err);
        });
    }

    /**
     * Update the tax row, total and submit price from a tax calculation result.
     */
    function applyTax(tax, data) {
        const $taxRow = $('#checkout-tax-row');
        if (tax > 0) {
            $('#checkout-tax').text((data && data.tax_formatted) ? data.tax_formatted : (config.currency + tax.toFixed(2)));
            $taxRow.show();
        } else {
            $taxRow.hide();
        }

        if (data && data.total_with_tax_formatted) {
            $('#checkout-total').text(data.total_with_tax_formatted);
            $('.wpdmpp-checkout__submit-price').text(data.total_with_tax_formatted);
        }
    }

    /**
     * Show field error
     */
    function showFieldError(field, message) {
        const $input = $(`[name="${field}"]`);
        const $error = $(`.wpdmpp-checkout__error[data-field="${field}"]`);

        $input.addClass('has-error');
        $error.text(message).addClass('visible');
    }

    /**
     * Clear field error
     */
    function clearFieldError(field) {
        const $input = $(`[name="${field}"]`);
        const $error = $(`.wpdmpp-checkout__error[data-field="${field}"]`);

        $input.removeClass('has-error');
        $error.text('').removeClass('visible');
    }

    /**
     * Clear all errors
     */
    function clearAllErrors() {
        $('.wpdmpp-checkout__input').removeClass('has-error');
        $('.wpdmpp-checkout__error').text('').removeClass('visible');
        $('#checkout-alerts').empty();
    }

    /**
     * Show alert message
     */
    function showAlert(type, message) {
        const $alerts = $('#checkout-alerts');
        const alertClass = `wpdmpp-checkout__alert wpdmpp-checkout__alert--${type}`;

        const $alert = $(`<div class="${alertClass}">${escapeHtml(message)}</div>`);
        $alerts.empty().append($alert);

        // Scroll to alert
        $('html, body').animate({
            scrollTop: $alerts.offset().top - 100
        }, 300);

        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => $alert.fadeOut(), 5000);
        }
    }

    /**
     * Show/hide loading overlay
     */
    function showLoading(show, text) {
        const $loading = $('#checkout-loading');
        if (show) {
            if (text) {
                $loading.find('.wpdmpp-checkout__loading-text').text(text);
            }
            $loading.fadeIn(200);
        } else {
            $loading.fadeOut(200);
        }
    }

    /**
     * Set submit button loading state
     */
    function setSubmitLoading(loading) {
        const $btn = $('#checkout-submit');
        if (loading) {
            $btn.addClass('wpdmpp-checkout__submit--loading').prop('disabled', true);
        } else {
            $btn.removeClass('wpdmpp-checkout__submit--loading').prop('disabled', false);
        }
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /* =========================================================
     * Cart Editing Module
     * ========================================================= */

    let popoverProductId = null;
    let popoverQty = 1;

    /**
     * Bind cart editing event listeners
     */
    function bindCartEvents() {
        // Edit button click
        $(document).on('click', '.wpdmpp-checkout__item-edit', handleEditClick);

        // Popover qty stepper
        $(document).on('click', '.wpdmpp-checkout__qty-btn', handleQtyStep);

        // Popover update
        $(document).on('click', '#popover-update', handleUpdateQuantity);

        // Popover remove
        $(document).on('click', '#popover-remove', handleRemoveItem);

        // Close popover on outside click
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.wpdmpp-checkout__item-popover, .wpdmpp-checkout__item-edit').length) {
                closePopover();
            }
        });

        // Coupon apply
        $(document).on('click', '#coupon-apply', handleApplyCoupon);

        // Coupon remove
        $(document).on('click', '#coupon-remove', handleRemoveCoupon);

        // Coupon input enter key
        $(document).on('keydown', '#coupon-input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleApplyCoupon();
            }
        });
    }

    /**
     * Handle edit button click — toggle popover below clicked item
     */
    function handleEditClick(e) {
        e.stopPropagation();

        var $item = $(this).closest('.wpdmpp-checkout__item');
        var productId = parseInt($item.attr('data-product-id'), 10);
        var $popover = $('#checkout-item-popover');

        // If clicking the same item, close
        if (popoverProductId === productId && $popover.is(':visible')) {
            closePopover();
            return;
        }

        // Close previous
        closePopover();

        // Read current qty from item text
        var qtyText = $item.find('.wpdmpp-checkout__item-qty').text();
        var match = qtyText.match(/(\d+)/);
        popoverQty = match ? parseInt(match[1], 10) : 1;
        popoverProductId = productId;

        // Update popover state
        $('#popover-qty').text(popoverQty);
        updateMinusState();

        // Highlight item
        $item.addClass('wpdmpp-checkout__item--editing');

        // Position popover after this item
        $popover.detach().insertAfter($item).slideDown(150);
    }

    /**
     * Close popover and remove item highlight
     */
    function closePopover() {
        var $popover = $('#checkout-item-popover');
        $popover.slideUp(150);
        $('.wpdmpp-checkout__item--editing').removeClass('wpdmpp-checkout__item--editing');
        popoverProductId = null;
    }

    /**
     * Handle qty +/− buttons
     */
    function handleQtyStep() {
        var action = $(this).attr('data-action');
        if (action === 'plus') {
            popoverQty++;
        } else if (action === 'minus' && popoverQty > 1) {
            popoverQty--;
        }
        $('#popover-qty').text(popoverQty);
        updateMinusState();
    }

    /**
     * Disable minus at qty=1
     */
    function updateMinusState() {
        $('.wpdmpp-checkout__qty-btn[data-action="minus"]').prop('disabled', popoverQty <= 1);
    }

    /**
     * PUT /cart/{id} — update quantity
     */
    function handleUpdateQuantity() {
        if (!popoverProductId) return;

        var $btn = $('#popover-update');
        $btn.prop('disabled', true).text('...');

        fetch(config.cartRestUrl + '/' + popoverProductId, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.restNonce
            },
            body: JSON.stringify({ quantity: popoverQty })
        })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            $btn.prop('disabled', false).text(config.strings.cartUpdated ? 'Update' : 'Update');
            if (result.success && result.data && result.data.cart) {
                updateCartUI(result.data.cart);
            }
            closePopover();
        })
        .catch(function() {
            $btn.prop('disabled', false).text('Update');
        });
    }

    /**
     * DELETE /cart/{id} — remove item
     */
    function handleRemoveItem() {
        if (!popoverProductId) return;

        var pid = popoverProductId;
        var $btn = $('#popover-remove');
        $btn.prop('disabled', true);

        closePopover();

        var $item = $('.wpdmpp-checkout__item[data-product-id="' + pid + '"]');

        fetch(config.cartRestUrl + '/' + pid, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': config.restNonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            $btn.prop('disabled', false);
            if (result.success && result.data) {
                // Animate item removal
                $item.slideUp(200, function() {
                    $(this).remove();
                });

                if (result.data.is_empty) {
                    setTimeout(showEmptyCart, 250);
                } else if (result.data.cart) {
                    updateCartUI(result.data.cart);
                }
            }
        })
        .catch(function() {
            $btn.prop('disabled', false);
        });
    }

    /**
     * POST /cart/coupon — apply coupon
     */
    function handleApplyCoupon() {
        var code = $.trim($('#coupon-input').val());
        if (!code) return;

        var $btn = $('#coupon-apply');
        $btn.prop('disabled', true).text('...');

        // Email-restricted coupons validate against the billing email
        var email = $.trim($('#checkout-email').val() || '');

        fetch(config.cartRestUrl + '/coupon', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.restNonce
            },
            body: JSON.stringify({ code: code, email: email })
        })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            $btn.prop('disabled', false).text('Apply');
            if (result.success && result.data && result.data.cart) {
                showAppliedCoupon(code);
                updateCartUI(result.data.cart);
                showCouponMessage(result.message || config.strings.couponApplied, 'success');
            } else {
                showCouponMessage(result.message || config.strings.error, 'error');
            }
        })
        .catch(function() {
            $btn.prop('disabled', false).text('Apply');
            showCouponMessage(config.strings.error, 'error');
        });
    }

    /**
     * DELETE /cart/coupon — remove coupon
     */
    function handleRemoveCoupon() {
        var $btn = $('#coupon-remove');
        $btn.prop('disabled', true);

        fetch(config.cartRestUrl + '/coupon', {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': config.restNonce }
        })
        .then(function(r) { return r.json(); })
        .then(function(result) {
            $btn.prop('disabled', false);
            if (result.success && result.data && result.data.cart) {
                showCouponInput();
                updateCartUI(result.data.cart);
                showCouponMessage(result.message || config.strings.couponRemoved, 'success');
            }
        })
        .catch(function() {
            $btn.prop('disabled', false);
        });
    }

    /**
     * Update all cart UI from API response
     */
    function updateCartUI(cart) {
        if (!cart) return;

        // Update each item price + qty badge
        if (cart.items && cart.items.length) {
            cart.items.forEach(function(item) {
                var $el = $('.wpdmpp-checkout__item[data-product-id="' + item.product_id + '"]');
                if ($el.length) {
                    $el.find('.wpdmpp-checkout__item-price').text(item.line_total_formatted);
                    $el.find('.wpdmpp-checkout__item-qty').text('Qty: ' + item.quantity);
                }
            });
        }

        // Subtotal
        if (cart.subtotal_formatted) {
            $('#checkout-subtotal').text(cart.subtotal_formatted);
        }

        // Discount row
        var totalDiscount = (parseFloat(cart.role_discount) || 0) + (parseFloat(cart.coupon_discount) || 0);
        var $discountRow = $('#checkout-discount-row');
        if (totalDiscount > 0) {
            var discountFormatted = config.currency + totalDiscount.toFixed(2);
            // Try to use formatted values from API
            if (cart.role_discount_formatted && cart.coupon_discount_formatted) {
                // Just show combined
            }
            $('#checkout-discount').text('-' + discountFormatted);
            $discountRow.show();
        } else {
            $discountRow.hide();
        }

        // Total
        var totalFormatted = config.taxActive ? cart.total_with_tax_formatted : cart.total_formatted;
        if (totalFormatted) {
            $('#checkout-total').text(totalFormatted);
        }

        // Submit button price badge
        var submitPrice = config.taxActive ? cart.total_with_tax_formatted : cart.total_formatted;
        if (submitPrice) {
            $('.wpdmpp-checkout__submit-price').text(submitPrice);
        }

        // Tax: the taxable base (subtotal/discount/qty) may have changed, so
        // recompute tax for the current billing location and refresh the row.
        if (config.taxActive) {
            recalculateTax();
        }
    }

    /**
     * Replace coupon input with applied badge
     */
    function showAppliedCoupon(code) {
        $('#coupon-code-text').text(code);
        $('#coupon-form').hide();
        $('#coupon-badge').show();
        $('#coupon-input').val('');
    }

    /**
     * Replace coupon badge with input
     */
    function showCouponInput() {
        $('#coupon-badge').hide();
        $('#coupon-form').show();
    }

    /**
     * Show coupon feedback message (auto-hide 4s)
     */
    function showCouponMessage(msg, type) {
        var $el = $('#coupon-msg');
        $el.text(msg)
           .removeClass('wpdmpp-checkout__coupon-msg--success wpdmpp-checkout__coupon-msg--error')
           .addClass('wpdmpp-checkout__coupon-msg--' + type)
           .show();
        setTimeout(function() { $el.fadeOut(200); }, 4000);
    }

    /**
     * Replace checkout with empty cart state
     */
    function showEmptyCart() {
        var url = config.continueShoppingUrl || '/';
        $('#wpdmpp-checkout').html(
            '<div class="wpdmpp-checkout__empty">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />' +
                '</svg>' +
                '<p>' + escapeHtml(config.strings.emptyCart) + '</p>' +
                '<a href="' + escapeHtml(url) + '" class="wpdmpp-checkout__empty-link">' +
                    escapeHtml(config.strings.continueShopping || 'Continue Shopping') +
                '</a>' +
            '</div>'
        );
    }

    // Initialize on DOM ready
    $(document).ready(init);

})(jQuery);
