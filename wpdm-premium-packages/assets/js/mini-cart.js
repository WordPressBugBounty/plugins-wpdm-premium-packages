/**
 * WPDM Premium Packages - Mini Cart JavaScript
 *
 * Handles all mini cart interactions:
 * - Toggle cart panel
 * - Real-time updates via REST API
 * - Add to cart integration
 * - Remove items
 * - Mobile full-screen mode
 *
 * @package WPDMPP
 * @since 6.2.0
 */

(function($) {
    'use strict';

    // Mini Cart Object
    window.WPDMPPMiniCart = {
        // Settings from PHP
        settings: {},
        strings: {},

        // State
        isOpen: false,
        isLoading: false,

        // Elements
        $cart: null,
        $trigger: null,
        $panel: null,
        $overlay: null,
        $items: null,
        $count: null,
        $subtotal: null,

        /**
         * Initialize mini cart
         */
        init: function() {
            var self = this;

            // Check if mini cart config exists
            if (typeof wpdmppMiniCart === 'undefined') {
                return;
            }

            this.settings = wpdmppMiniCart.settings || {};
            this.strings = wpdmppMiniCart.strings || {};

            // Find all mini cart instances
            $('.wpdmpp-mini-cart').each(function() {
                self.initInstance($(this));
            });

            // Inject mini cart into nav menu items with .wpdmpp-minicart class
            this.injectIntoMenuItems();

            // Listen for add to cart events
            this.bindAddToCart();

            // Create toast container
            this.createToast();
        },

        /**
         * Inject mini cart into nav menu items with .wpdmpp-minicart class
         */
        injectIntoMenuItems: function() {
            var self = this;
            var $menuItems = $('.wpdmpp-minicart, .menu-item.wpdmpp-minicart, li.wpdmpp-minicart');

            if ($menuItems.length === 0) {
                return;
            }

            $menuItems.each(function() {
                var $menuItem = $(this);

                // Skip if already initialized
                if ($menuItem.hasClass('wpdmpp-minicart-initialized')) {
                    return;
                }

                // Mark as initialized
                $menuItem.addClass('wpdmpp-minicart-initialized');

                // Get cart data
                var itemCount = wpdmppMiniCart.cartData ? wpdmppMiniCart.cartData.item_count : 0;
                var cartTotal = wpdmppMiniCart.cartData ? wpdmppMiniCart.cartData.total_formatted : '';

                // Build the mini cart HTML
                var miniCartHtml = self.buildMenuItemCart(itemCount, cartTotal);

                // Find the anchor link and hide its text or replace content
                var $link = $menuItem.find('> a').first();
                if ($link.length) {
                    // Store original content for potential restoration
                    $menuItem.data('original-content', $link.html());

                    // Replace link content with mini cart
                    $link.html(miniCartHtml);
                    $link.addClass('wpdmpp-minicart-link');

                    // Prevent default link behavior
                    $link.attr('href', '#');
                    $link.on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        self.toggleMenuCart($menuItem);
                    });
                } else {
                    // No link found, append directly
                    $menuItem.html(miniCartHtml);
                    $menuItem.on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        self.toggleMenuCart($menuItem);
                    });
                }

                // Create and append the dropdown panel
                var $panel = self.buildMenuCartPanel();
                $menuItem.append($panel);
                $menuItem.addClass('wpdmpp-minicart-container');

                // Initialize this menu cart instance
                self.initMenuCartInstance($menuItem);
            });

            // Close menu cart when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.wpdmpp-minicart-container').length) {
                    $('.wpdmpp-minicart-container').removeClass('wpdmpp-minicart-open');
                }
            });

            // Close on escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.wpdmpp-minicart-container').removeClass('wpdmpp-minicart-open');
                }
            });
        },

        /**
         * Build mini cart trigger HTML for menu item
         */
        buildMenuItemCart: function(itemCount, cartTotal) {
            var showCount = this.settings.showItemCount !== false;
            var showTotal = this.settings.showSubtotal !== false;

            var html = '<span class="wpdmpp-minicart-trigger">';
            html += '<span class="wpdmpp-minicart-icon">';
            html += '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
            html += '<circle cx="9" cy="21" r="1"></circle>';
            html += '<circle cx="20" cy="21" r="1"></circle>';
            html += '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>';
            html += '</svg>';
            html += '</span>';

            if (showCount) {
                var countClass = itemCount === 0 ? 'wpdmpp-minicart-count wpdmpp-minicart-count--empty' : 'wpdmpp-minicart-count';
                html += '<span class="' + countClass + '" data-count="' + itemCount + '">' + itemCount + '</span>';
            }

            if (showTotal && cartTotal) {
                html += '<span class="wpdmpp-minicart-total">' + cartTotal + '</span>';
            }

            html += '</span>';
            return html;
        },

        /**
         * Build menu cart dropdown panel
         */
        buildMenuCartPanel: function() {
            var html = '<div class="wpdmpp-minicart-panel">';
            html += '<div class="wpdmpp-minicart-panel-header">';
            html += '<span class="wpdmpp-minicart-panel-title">' + this.strings.cartTitle + ' <span class="wpdmpp-minicart-panel-count">(0)</span></span>';
            html += '<button type="button" class="wpdmpp-minicart-panel-close" aria-label="' + this.strings.close + '">';
            html += '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            html += '</button>';
            html += '</div>';
            html += '<div class="wpdmpp-minicart-panel-items"></div>';
            html += '<div class="wpdmpp-minicart-panel-footer">';
            html += '<div class="wpdmpp-minicart-panel-subtotal">';
            html += '<span>' + this.strings.subtotal + '</span>';
            html += '<span class="wpdmpp-minicart-panel-subtotal-value"></span>';
            html += '</div>';
            html += '<div class="wpdmpp-minicart-panel-actions">';
            html += '<a href="' + wpdmppMiniCart.cartUrl + '" class="wpdmpp-minicart-btn wpdmpp-minicart-btn--secondary">' + this.strings.viewCart + '</a>';
            html += '<a href="' + wpdmppMiniCart.checkoutUrl + '" class="wpdmpp-minicart-btn wpdmpp-minicart-btn--primary">' + this.strings.checkout + '</a>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            return $(html);
        },

        /**
         * Initialize menu cart instance
         */
        initMenuCartInstance: function($menuItem) {
            var self = this;

            // Close button
            $menuItem.find('.wpdmpp-minicart-panel-close').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $menuItem.removeClass('wpdmpp-minicart-open');
            });

            // Remove item buttons
            $menuItem.on('click', '.wpdmpp-minicart-item-remove', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var productId = $(this).data('product-id');
                self.removeItemFromMenu(productId, $(this).closest('.wpdmpp-minicart-item'), $menuItem);
            });

            // Load initial cart data
            this.fetchCartForMenu($menuItem);
        },

        /**
         * Toggle menu cart dropdown
         */
        toggleMenuCart: function($menuItem) {
            var isOpen = $menuItem.hasClass('wpdmpp-minicart-open');

            // Close all other menu carts
            $('.wpdmpp-minicart-container').not($menuItem).removeClass('wpdmpp-minicart-open');

            if (isOpen) {
                $menuItem.removeClass('wpdmpp-minicart-open');
            } else {
                $menuItem.addClass('wpdmpp-minicart-open');
                // Refresh cart data when opening
                this.fetchCartForMenu($menuItem);
            }
        },

        /**
         * Fetch cart data for menu cart
         */
        fetchCartForMenu: function($menuItem) {
            var self = this;

            $.ajax({
                url: wpdmppMiniCart.restUrl,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpdmppMiniCart.nonce);
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.updateMenuCart($menuItem, response.data);
                    }
                }
            });
        },

        /**
         * Update menu cart display
         */
        updateMenuCart: function($menuItem, cartData) {
            var self = this;
            var itemCount = cartData.item_count || 0;
            var totalFormatted = cartData.total_formatted || '';
            var showSubtotal = this.settings.showSubtotal !== false;

            // Update count badge
            var $count = $menuItem.find('.wpdmpp-minicart-count');
            $count.text(itemCount).attr('data-count', itemCount);
            if (itemCount === 0) {
                $count.addClass('wpdmpp-minicart-count--empty');
            } else {
                $count.removeClass('wpdmpp-minicart-count--empty');
            }

            // Update total (only if showSubtotal is enabled)
            var $total = $menuItem.find('.wpdmpp-minicart-total');
            if (showSubtotal) {
                $total.text(totalFormatted).show();
            } else {
                $total.hide();
            }

            // Update panel count
            $menuItem.find('.wpdmpp-minicart-panel-count').text('(' + itemCount + ')');

            // Update panel subtotal (always show in panel footer)
            $menuItem.find('.wpdmpp-minicart-panel-subtotal-value').text(totalFormatted);

            // Update items
            var $itemsContainer = $menuItem.find('.wpdmpp-minicart-panel-items');
            if (cartData.is_empty || !cartData.items || cartData.items.length === 0) {
                $itemsContainer.html(this.buildEmptyCartHtml());
                $menuItem.find('.wpdmpp-minicart-panel-footer').hide();
            } else {
                $itemsContainer.html(this.buildMenuCartItemsHtml(cartData.items));
                $menuItem.find('.wpdmpp-minicart-panel-footer').show();
            }
        },

        /**
         * Build empty cart HTML
         */
        buildEmptyCartHtml: function() {
            var html = '<div class="wpdmpp-minicart-empty">';
            html += '<div class="wpdmpp-minicart-empty-icon">';
            html += '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">';
            html += '<circle cx="9" cy="21" r="1"></circle>';
            html += '<circle cx="20" cy="21" r="1"></circle>';
            html += '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>';
            html += '</svg>';
            html += '</div>';
            html += '<p>' + this.strings.emptyCart + '</p>';
            html += '<a href="' + (wpdmppMiniCart.shopUrl || '/') + '" class="wpdmpp-minicart-btn wpdmpp-minicart-btn--secondary">' + this.strings.continueShopping + '</a>';
            html += '</div>';
            return html;
        },

        /**
         * Build menu cart items HTML
         */
        buildMenuCartItemsHtml: function(items) {
            var self = this;
            var html = '';

            $.each(items, function(index, item) {
                html += '<div class="wpdmpp-minicart-item" data-product-id="' + item.product_id + '">';

                if (self.settings.showThumbnails && item.thumbnail) {
                    html += '<div class="wpdmpp-minicart-item-thumb">';
                    html += '<img src="' + item.thumbnail + '" alt="' + self.escapeHtml(item.name) + '">';
                    html += '</div>';
                }

                html += '<div class="wpdmpp-minicart-item-details">';
                html += '<a href="' + item.url + '" class="wpdmpp-minicart-item-name">' + self.escapeHtml(item.name) + '</a>';
                html += '<div class="wpdmpp-minicart-item-meta">';
                html += '<span class="wpdmpp-minicart-item-qty">' + item.quantity + ' &times; </span>';
                html += '<span class="wpdmpp-minicart-item-price">' + item.unit_price_formatted + '</span>';
                html += '</div>';
                html += '</div>';

                html += '<div class="wpdmpp-minicart-item-actions">';
                html += '<span class="wpdmpp-minicart-item-total">' + item.line_total_formatted + '</span>';
                html += '<button type="button" class="wpdmpp-minicart-item-remove" data-product-id="' + item.product_id + '" aria-label="' + wpdmppMiniCart.strings.remove + '">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
                html += '</button>';
                html += '</div>';

                html += '</div>';
            });

            return html;
        },

        /**
         * Remove item from menu cart
         */
        removeItemFromMenu: function(productId, $item, $menuItem) {
            var self = this;

            $item.addClass('wpdmpp-minicart-item--removing');

            $.ajax({
                url: wpdmppMiniCart.restUrl + '/' + productId,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpdmppMiniCart.nonce);
                },
                success: function(response) {
                    if (response.success && response.cart) {
                        $item.slideUp(200, function() {
                            $(this).remove();
                            self.updateMenuCart($menuItem, response.cart);
                            // Also update any other mini cart instances
                            self.updateCart(response.cart);
                        });
                        self.showToast(self.strings.itemRemoved);
                    }
                },
                error: function() {
                    $item.removeClass('wpdmpp-minicart-item--removing');
                    self.showToast('Error removing item', 'error');
                }
            });
        },

        /**
         * Update all menu carts when cart changes
         */
        updateAllMenuCarts: function(cartData) {
            var self = this;
            $('.wpdmpp-minicart-container').each(function() {
                self.updateMenuCart($(this), cartData);
            });
        },

        /**
         * Initialize a single mini cart instance
         */
        initInstance: function($cart) {
            var self = this;

            this.$cart = $cart;
            this.$trigger = $cart.find('.wpdmpp-mc-trigger');
            this.$panel = $cart.find('.wpdmpp-mc-panel');
            this.$overlay = $cart.find('.wpdmpp-mc-overlay');
            this.$items = $cart.find('.wpdmpp-mc-items');
            this.$count = $cart.find('.wpdmpp-mc-count');

            // Toggle on trigger click
            this.$trigger.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.toggle();
            });

            // Close on close button click
            $cart.find('.wpdmpp-mc-close').on('click', function(e) {
                e.preventDefault();
                self.close();
            });

            // Close on overlay click
            this.$overlay.on('click', function() {
                self.close();
            });

            // Close on escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.isOpen) {
                    self.close();
                }
            });

            // Remove item handler
            $cart.on('click', '.wpdmpp-mc-item-remove', function(e) {
                e.preventDefault();
                var productId = $(this).data('product-id');
                self.removeItem(productId, $(this).closest('.wpdmpp-mc-item'));
            });

            // Handle external triggers
            if (this.settings.triggerSelector) {
                $(this.settings.triggerSelector).on('click', function(e) {
                    e.preventDefault();
                    self.toggle();
                });
            }

            // Handle window resize for mobile
            $(window).on('resize', $.debounce(250, function() {
                self.handleResize();
            }));
        },

        /**
         * Toggle cart panel
         */
        toggle: function() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        },

        /**
         * Open cart panel
         */
        open: function() {
            this.isOpen = true;
            this.$trigger.attr('aria-expanded', 'true');
            this.$panel.attr('aria-hidden', 'false');
            this.$cart.addClass('wpdmpp-mc-open');

            // Prevent body scroll on mobile full screen
            if (this.isMobile() && this.settings.mobileFullScreen) {
                $('body').css('overflow', 'hidden');
            }

            // Focus first focusable element
            var $focusable = this.$panel.find('button, a, input').first();
            if ($focusable.length) {
                setTimeout(function() {
                    $focusable.focus();
                }, 100);
            }
        },

        /**
         * Close cart panel
         */
        close: function() {
            this.isOpen = false;
            this.$trigger.attr('aria-expanded', 'false');
            this.$panel.attr('aria-hidden', 'true');
            this.$cart.removeClass('wpdmpp-mc-open');

            // Restore body scroll
            $('body').css('overflow', '');

            // Return focus to trigger
            this.$trigger.focus();
        },

        /**
         * Check if on mobile
         */
        isMobile: function() {
            return window.innerWidth <= (this.settings.mobileBreakpoint || 768);
        },

        /**
         * Handle window resize
         */
        handleResize: function() {
            // Close cart if resize changes mobile state and full screen is on
            if (this.isOpen && this.settings.mobileFullScreen) {
                // Let CSS handle the layout change
            }
        },

        /**
         * Fetch cart data from API
         */
        fetchCart: function(callback) {
            var self = this;

            if (this.isLoading) {
                return;
            }

            this.isLoading = true;
            this.$cart.addClass('wpdmpp-mc-loading');

            $.ajax({
                url: wpdmppMiniCart.restUrl,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpdmppMiniCart.nonce);
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.updateCart(response.data.cart || response.data);
                    }
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Mini Cart Error:', error);
                },
                complete: function() {
                    self.isLoading = false;
                    self.$cart.removeClass('wpdmpp-mc-loading');
                }
            });
        },

        /**
         * Update cart display
         */
        updateCart: function(cartData) {
            var self = this;

            if (!cartData) {
                return;
            }

            // Update item count
            var itemCount = cartData.item_count || 0;
            this.$count.text(itemCount).attr('data-count', itemCount);

            if (itemCount === 0) {
                this.$count.addClass('wpdmpp-mc-count--empty');
            } else {
                this.$count.removeClass('wpdmpp-mc-count--empty');
            }

            // Animate count update
            this.$count.addClass('wpdmpp-mc-count-updated');
            setTimeout(function() {
                self.$count.removeClass('wpdmpp-mc-count-updated');
            }, 400);

            // Update panel count
            this.$cart.find('.wpdmpp-mc-panel-count').text('(' + itemCount + ')');

            // Update subtotal
            var subtotal = cartData.total_formatted || cartData.subtotal_formatted || '';
            this.$cart.find('.wpdmpp-mc-subtotal-value').text(subtotal);
            this.$cart.find('.wpdmpp-mc-total').text(subtotal);

            // Update items
            if (cartData.is_empty) {
                this.renderEmptyCart();
            } else if (cartData.items) {
                this.renderItems(cartData.items);
            }

            // Show/hide footer
            if (itemCount > 0) {
                this.$cart.find('.wpdmpp-mc-panel-footer').show();
            } else {
                this.$cart.find('.wpdmpp-mc-panel-footer').hide();
            }

            // Also update any nav menu carts
            this.updateAllMenuCarts(cartData);
        },

        /**
         * Render cart items
         */
        renderItems: function(items) {
            var html = '';
            var showThumbnails = this.settings.showThumbnails;

            $.each(items, function(index, item) {
                html += '<div class="wpdmpp-mc-item" data-product-id="' + item.product_id + '">';

                if (showThumbnails && item.thumbnail) {
                    html += '<div class="wpdmpp-mc-item-thumb">';
                    html += '<img src="' + item.thumbnail + '" alt="' + self.escapeHtml(item.name) + '">';
                    html += '</div>';
                }

                html += '<div class="wpdmpp-mc-item-details">';
                html += '<a href="' + item.url + '" class="wpdmpp-mc-item-name">' + self.escapeHtml(item.name) + '</a>';
                html += '<div class="wpdmpp-mc-item-meta">';
                html += '<span class="wpdmpp-mc-item-qty">' + item.quantity + ' &times; </span>';
                html += '<span class="wpdmpp-mc-item-price">' + item.unit_price_formatted + '</span>';
                html += '</div>';
                html += '</div>';

                html += '<div class="wpdmpp-mc-item-actions">';
                html += '<span class="wpdmpp-mc-item-total">' + item.line_total_formatted + '</span>';
                html += '<button type="button" class="wpdmpp-mc-item-remove" data-product-id="' + item.product_id + '" aria-label="' + wpdmppMiniCart.strings.remove + '">';
                html += '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
                html += '</button>';
                html += '</div>';

                html += '</div>';
            });

            this.$items.html(html);
        },

        /**
         * Render empty cart state
         */
        renderEmptyCart: function() {
            var html = '<div class="wpdmpp-mc-empty">';
            html += '<div class="wpdmpp-mc-empty-icon">';
            html += '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
            html += '<circle cx="9" cy="21" r="1"></circle>';
            html += '<circle cx="20" cy="21" r="1"></circle>';
            html += '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>';
            html += '</svg>';
            html += '</div>';
            html += '<p class="wpdmpp-mc-empty-text">' + this.strings.emptyCart + '</p>';
            html += '<a href="' + (wpdmppMiniCart.shopUrl || '/') + '" class="wpdmpp-mc-btn wpdmpp-mc-btn--secondary">';
            html += this.strings.continueShopping;
            html += '</a>';
            html += '</div>';

            this.$items.html(html);
        },

        /**
         * Remove item from cart
         */
        removeItem: function(productId, $item) {
            var self = this;

            if (!productId) {
                return;
            }

            // Add removing state
            $item.addClass('wpdmpp-mc-item--removing');

            $.ajax({
                url: wpdmppMiniCart.restUrl + '/' + productId,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpdmppMiniCart.nonce);
                },
                success: function(response) {
                    if (response.success && response.cart) {
                        // Animate removal
                        $item.slideUp(200, function() {
                            $(this).remove();
                            self.updateCart(response.cart);
                        });

                        self.showToast(self.strings.itemRemoved);
                    }
                },
                error: function() {
                    $item.removeClass('wpdmpp-mc-item--removing');
                    self.showToast('Error removing item', 'error');
                }
            });
        },

        /**
         * Bind to add to cart events
         */
        bindAddToCart: function() {
            var self = this;

            // Intercept WPDM add to cart forms
            $(document).on('submit', '.wpdm_cart_form, form[name="cart_form"]', function(e) {
                var $form = $(this);
                var productId = $form.find('input[name="addtocart"]').val();

                // If AJAX is enabled, handle it
                if ($form.hasClass('wpdm-ajax-cart') || wpdmppMiniCart.settings.ajaxAddToCart) {
                    e.preventDefault();
                    self.addToCart(productId, $form);
                    return false;
                }
            });

            // Listen for postMessage cart updates (legacy support)
            window.addEventListener('message', function(event) {
                if (event.data === 'cart_updated') {
                    self.fetchCart();
                }
            });

            // Listen for custom cart update event
            $(document).on('wpdmpp:cart:updated', function(e, cartData) {
                self.updateCart(cartData);

                if (self.settings.autoOpenOnAdd) {
                    self.open();

                    // Auto close after delay
                    if (self.settings.autoCloseDelay > 0) {
                        setTimeout(function() {
                            self.close();
                        }, self.settings.autoCloseDelay);
                    }
                }
            });
        },

        /**
         * Add item to cart via API
         */
        addToCart: function(productId, $form) {
            var self = this;
            var data = {
                product_id: parseInt(productId),
                quantity: parseInt($form.find('input[name="quantity"]').val()) || 1,
                license: $form.find('input[name="license"]:checked, select[name="license"]').val() || '',
                variation: {}
            };

            // Collect variations
            $form.find('input[name^="variation"], select[name^="variation"]').each(function() {
                var name = $(this).attr('name').replace(/variation\[|\]/g, '');
                data.variation[name] = $(this).val();
            });

            $.ajax({
                url: wpdmppMiniCart.restUrl,
                method: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpdmppMiniCart.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        // Update cart
                        self.updateCart(response.cart);

                        // Show toast
                        self.showToast(self.strings.itemAdded, 'success');

                        // Shake trigger animation
                        self.$trigger.addClass('wpdmpp-mc-shake');
                        setTimeout(function() {
                            self.$trigger.removeClass('wpdmpp-mc-shake');
                        }, 500);

                        // Auto open if enabled
                        if (self.settings.autoOpenOnAdd) {
                            self.open();

                            // Auto close after delay
                            if (self.settings.autoCloseDelay > 0) {
                                setTimeout(function() {
                                    self.close();
                                }, self.settings.autoCloseDelay);
                            }
                        }

                        // Trigger event for other scripts
                        $(document).trigger('wpdmpp:cart:item:added', [response]);
                    }
                },
                error: function(xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Error adding to cart';
                    self.showToast(message, 'error');
                }
            });
        },

        /**
         * Create toast container
         */
        createToast: function() {
            if ($('#wpdmpp-mc-toast').length === 0) {
                $('body').append('<div id="wpdmpp-mc-toast" class="wpdmpp-mc-toast"></div>');
            }
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            var $toast = $('#wpdmpp-mc-toast');
            type = type || 'success';

            $toast
                .text(message)
                .removeClass('wpdmpp-mc-toast--success wpdmpp-mc-toast--error')
                .addClass('wpdmpp-mc-toast--' + type)
                .addClass('wpdmpp-mc-toast--visible');

            // Auto hide after 3 seconds
            clearTimeout(this.toastTimeout);
            this.toastTimeout = setTimeout(function() {
                $toast.removeClass('wpdmpp-mc-toast--visible');
            }, 3000);
        },

        /**
         * Escape HTML entities
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Debounce utility
    $.debounce = function(delay, callback) {
        var timer;
        return function() {
            var args = arguments;
            var context = this;
            clearTimeout(timer);
            timer = setTimeout(function() {
                callback.apply(context, args);
            }, delay);
        };
    };

    // Reference for external use
    var self = window.WPDMPPMiniCart;

    // Initialize on DOM ready
    $(document).ready(function() {
        WPDMPPMiniCart.init();
    });

    // Also initialize when dynamic content is loaded (for AJAX page loads)
    $(document).on('ajaxComplete', function() {
        // Re-initialize if new mini cart elements are added
        if ($('.wpdmpp-mini-cart').length && !WPDMPPMiniCart.$cart) {
            WPDMPPMiniCart.init();
        }
    });

})(jQuery);
