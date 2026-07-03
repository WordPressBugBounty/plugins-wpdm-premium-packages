<?php
/**
 * Link to the guest order page.
 *
 * This template is active only when you set Guest Order page in Premium Package >> Basic Settings >> Frontend Settings panel.
 *
 * This template can be overridden by copying it to yourtheme/download-manager/partials/guest_order_page_link.php.
 *
 * @version     2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

$guest_order_page_link = wpdmpp_guest_order_page();

$wpdmpp_gol_desc  = apply_filters( 'wpdmpp_guest_order_page_link_description', __( "Don't have an account? Access your purchase and downloads instantly with your order email and ID.", 'wpdm-premium-packages' ) );
$wpdmpp_gol_label = apply_filters( 'wpdmpp_guest_order_page_link_label', __( 'Go to Guest Download Page', 'wpdm-premium-packages' ) );

// Print the scoped styles only once per request.
static $wpdmpp_gol_styled = false;
if ( ! $wpdmpp_gol_styled ) :
    $wpdmpp_gol_styled = true;
    ?>
    <style>
        /* Selectors are intentionally high-specificity: when this partial renders
           inside the login form, core's auth-forms.css universal reset
           (.w3eden .wpdm-auth-split * { margin:0; padding:0 }) would otherwise
           zero out the card and button spacing. */
        .w3eden div.wpdmpp-gol{
            display:flex;align-items:center;gap:16px;flex-wrap:wrap;
            margin:0 0 16px;padding:18px 20px;box-sizing:border-box;
            background:var(--bg-body,#fff);
            border:1px solid var(--border-color,#e2e8f0);
            border-radius:12px;
            box-shadow:0 1px 2px rgba(0,0,0,.04),0 8px 24px rgba(0,0,0,.04);
        }
        .w3eden .wpdmpp-gol .wpdmpp-gol__icon{
            display:flex;align-items:center;justify-content:center;flex-shrink:0;
            width:44px;height:44px;margin:0;border-radius:11px;
            background:rgba(var(--color-primary-rgb,99,102,241),.1);
            color:var(--color-primary,#6366f1);
        }
        .w3eden .wpdmpp-gol .wpdmpp-gol__icon svg{width:22px;height:22px;}
        .w3eden .wpdmpp-gol .wpdmpp-gol__body{flex:1 1 240px;min-width:0;margin:0;padding:0;}
        .w3eden .wpdmpp-gol .wpdmpp-gol__title{margin:0 0 2px;padding:0;font-size:15px;font-weight:600;line-height:1.3;color:var(--text-primary,#1e293b);}
        .w3eden .wpdmpp-gol .wpdmpp-gol__desc{margin:0;padding:0;font-size:13.5px;line-height:1.5;color:var(--text-muted,#64748b);}
        .w3eden .wpdmpp-gol a.wpdmpp-gol__btn{
            display:inline-flex;align-items:center;gap:8px;flex-shrink:0;
            margin:0;padding:10px 18px;border-radius:9px;box-sizing:border-box;
            font-size:14px;font-weight:600;text-decoration:none;line-height:1;
            color:#fff !important;background:var(--color-primary,#6366f1);
            border:1px solid var(--color-primary,#6366f1);
            transition:transform 150ms ease,box-shadow 150ms ease,background 150ms ease;
        }
        .w3eden .wpdmpp-gol a.wpdmpp-gol__btn:hover{
            background:var(--color-primary-hover,#4f46e5);
            box-shadow:0 4px 12px rgba(var(--color-primary-rgb,99,102,241),.3);
            transform:translateY(-1px);color:#fff !important;
        }
        .w3eden .wpdmpp-gol a.wpdmpp-gol__btn svg{width:16px;height:16px;}
        @media (max-width:540px){.w3eden .wpdmpp-gol a.wpdmpp-gol__btn{width:100%;justify-content:center;}}
    </style>
    <?php
endif;
?>

<div class="w3eden">
    <div class="wpdmpp-gol">
        <span class="wpdmpp-gol__icon"><?php echo \WPDMPP\UI\Icons::get( 'shopping-bag', 22 ); ?></span>
        <div class="wpdmpp-gol__body">
            <p class="wpdmpp-gol__title"><?php esc_html_e( 'Need to download quickly?', 'wpdm-premium-packages' ); ?></p>
            <p class="wpdmpp-gol__desc"><?php echo esc_html( $wpdmpp_gol_desc ); ?></p>
        </div>
        <a class="wpdmpp-gol__btn" href="<?php echo esc_url( $guest_order_page_link ); ?>">
            <?php echo \WPDMPP\UI\Icons::get( 'arrow-right', 16 ); ?>
            <span><?php echo esc_html( $wpdmpp_gol_label ); ?></span>
        </a>
    </div>
</div>
