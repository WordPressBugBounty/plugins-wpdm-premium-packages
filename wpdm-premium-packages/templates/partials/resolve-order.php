<?php
/**
 * Form to connect unaasigned order to an user account
 *
 * This template can be overridden by copying it to yourtheme/download-manager/partials/resolve-order.php.
 *
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<style>
    /* Self-contained input + button group so it renders the same regardless
       of which Bootstrap version (if any) the host theme ships. */
    .wpdmpp-resolve { display: flex; align-items: stretch; width: 100%; gap: 0; }
    .wpdmpp-resolve__field {
        flex: 1 1 auto;
        min-width: 0;
        padding: 10px 14px;
        font-size: 16px;
        line-height: 1.4;
        border: 1px solid var(--color-border, #e2e8f0);
        border-right: 0;
        border-radius: 8px 0 0 8px;
        background: var(--color-bg-card, #fff);
        color: var(--color-text, #1e293b);
        outline: none;
        box-shadow: none;
    }
    .wpdmpp-resolve__field:focus {
        border-color: var(--color-primary, #6366f1);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
        z-index: 1;
    }
    .wpdmpp-resolve__btn {
        flex: 0 0 auto;
        padding: 8px 22px;
        font-size: 15px;
        font-weight: 600;
        line-height: 1.4;
        border: 1px solid var(--color-primary, #6366f1);
        border-radius: 0 8px 8px 0;
        background: var(--color-primary, #6366f1);
        color: #fff;
        cursor: pointer;
        transition: background 120ms ease, border-color 120ms ease;
    }
    .wpdmpp-resolve__btn:hover,
    .wpdmpp-resolve__btn:focus {
        background: var(--color-primary-active, #4f46e5);
        border-color: var(--color-primary-active, #4f46e5);
        outline: none;
    }
    .wpdmpp-resolve__status { margin-top: 10px; min-height: 22px; line-height: 22px; cursor: pointer; }
    .wpdmpp-resolve__status:empty { display: none; }
</style>
<div class='card card-default mb-3'>
    <div class='card-header'><?php _e('If you do not see your order:', 'wpdm-premium-packages'); ?></div>
    <div class='card-body'>
        <form id='resolveorder' method='post'>
            <input type='hidden' name='action' value='resolveorder'/>
            <div class='wpdmpp-resolve'>
                <input type='text' name='orderid' value='' placeholder='<?php esc_attr_e('Enter Your Order/Invoice ID Here', 'wpdm-premium-packages'); ?>' class='wpdmpp-resolve__field'>
                <button class='wpdmpp-resolve__btn' type='submit'><?php _e('Resolve', 'wpdm-premium-packages'); ?></button>
            </div>
        </form>
        <div id='w8o' class='wpdmpp-resolve__status text-danger' style='display:none;'>
            <?php echo \WPDMPP\UI\Icons::spinner(16); ?> <?php _e('Please Wait...', 'wpdm-premium-packages'); ?>
        </div>
    </div>
</div>
