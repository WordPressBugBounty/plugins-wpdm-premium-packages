<?php
/**
 * Login and Signup form Template. Applied during cart checkout if user is not logged in and guest checkout disabled.
 *
 * This template can be overridden by copying it to yourtheme/download-manager/checkout-cart/checkout-login-register.php.
 *
 * @version     3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

$redirect_url = get_permalink( get_the_ID() );
?>
<div id="checkout-login" class="wpdmpp-auth">

    <p class="wpdmpp-auth__note">
        <?php echo \WPDMPP\UI\Icons::get( 'info-circle', 16 ); ?>
        <?php _e( 'Please log in or create an account to complete your checkout.', 'wpdm-premium-packages' ); ?>
    </p>

    <ul class="nav wpdmpp-auth__tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link active" id="login-tab" data-toggle="tab" href="#cologin" role="tab" aria-controls="login" aria-selected="true">
                <?php echo \WPDMPP\UI\Icons::get( 'key', 15 ); ?>
                <span><?php _e( 'Login', 'wpdm-premium-packages' ); ?></span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="register-tab" data-toggle="tab" href="#coregister" role="tab" aria-controls="register" aria-selected="false">
                <?php echo \WPDMPP\UI\Icons::get( 'user-circle', 15 ); ?>
                <span><?php _e( 'Register', 'wpdm-premium-packages' ); ?></span>
            </a>
        </li>
    </ul>

    <div class="wpdmpp-auth__body" id="csl">
        <div class="tab-content">
            <div class="tab-pane active" id="cologin" role="tabpanel" aria-labelledby="login-tab">
                <?php echo WPDM()->user->login->form( [ 'logo' => '', 'captcha' => false, 'redirect' => $redirect_url ] ); ?>
            </div>
            <div class="tab-pane" id="coregister" role="tabpanel" aria-labelledby="register-tab">
                <?php echo WPDM()->user->register->form( [ 'logo' => '', 'captcha' => false, 'redirect' => $redirect_url, 'autologin' => 1 ] ); ?>
            </div>
        </div>
    </div>

</div>

<style>
    .w3eden .wpdm-auth-split{
        padding: 0 !important;
    }
    .w3eden .wpdm-auth-left{
        display: none;
    }
    .wpdmpp-auth {
        margin: 0 auto;
        text-align: center;
    }
    .w3eden .wpdm-auth-panel{
        box-shadow: none !important;
    }

    /* Short helper line above the toggle */
    .wpdmpp-auth__note {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0 auto 18px;
        padding: 9px 16px;
        border-radius: 9999px;
        background: rgba(var(--color-primary-rgb, 99, 102, 241), 0.08);
        color: var(--color-primary, #6366f1);
        font-size: 13.5px;
        font-weight: 500;
        line-height: 1.3;
    }

    .wpdmpp-auth__note svg { flex: 0 0 16px; opacity: 0.9; }

    /* Segmented pill toggle */
    .wpdmpp-auth__tabs.nav {
        display: inline-flex;
        gap: 4px;
        margin: 0 auto 24px;
        padding: 5px;
        border: 0;
        list-style: none;
        background: var(--bg-secondary, #f1f5f9);
        border-radius: 9999px;
    }

    .wpdmpp-auth__tabs .nav-item { margin: 0; }

    .wpdmpp-auth__tabs .nav-link {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 9px 26px;
        border: 0;
        border-radius: 9999px;
        background: transparent;
        color: var(--text-secondary, #475569);
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        transition: all 150ms ease;
    }

    .wpdmpp-auth__tabs .nav-link svg { width: 15px; height: 15px; opacity: 0.85; }

    .wpdmpp-auth__tabs .nav-link:hover { color: var(--color-primary, #6366f1); }

    .wpdmpp-auth__tabs .nav-link.active {
        color: var(--color-primary, #6366f1);
        background: var(--bg-body, #ffffff);
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
    }

    /* Let the inner login/register forms keep their own native design, just centered */
    .wpdmpp-auth__body { text-align: left; }
    .wpdmpp-auth__body .tab-pane { display: none; }
    .wpdmpp-auth__body .tab-pane.active { display: flex; justify-content: center; }

    @media (max-width: 575px) {
        .wpdmpp-auth__tabs .nav-link { padding: 9px 18px; }
    }
</style>
<script>
(function () {
    var root = document.getElementById('checkout-login');
    if (!root || root.dataset.wpdmppAuthInit) return;
    root.dataset.wpdmppAuthInit = '1';

    var tabs  = root.querySelectorAll('.wpdmpp-auth__tabs .nav-link');
    var panes = root.querySelectorAll('.wpdmpp-auth__body .tab-pane');

    function activate(tab) {
        var targetSel = tab.getAttribute('href');
        tabs.forEach(function (t) {
            var on = t === tab;
            t.classList.toggle('active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panes.forEach(function (p) {
            p.classList.toggle('active', '#' + p.id === targetSel);
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            activate(tab);
        });
    });
})();
</script>
