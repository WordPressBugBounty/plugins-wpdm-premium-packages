<?php
/**
 * Login and Signup form Template. Applied during cart checkout if user is not logged in and guest checkout disabled.
 *
 * This template can be overridden by copying it to yourtheme/download-manager/checkout-cart-2col/checkout-login-register.php.
 *
 * @version     2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

?>
<div id="checkout-login" class="card card-default">
    <div class="card-header"><?php _e('Please register or login to checkout','wpdm-prepmium-packages'); ?></div>
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="login-tab" data-toggle="tab" href="#cologin" role="tab" aria-controls="login" aria-selected="true">Login</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="register-tab" data-toggle="tab" href="#coregister" role="tab" aria-controls="register" aria-selected="false">Register</a>
            </li>
        </ul>
    </div>
    <div class="card-body" id="csl">
        <div class="tab-content">
            <div class="tab-pane active" id="cologin" role="tabpanel" aria-labelledby="login-tab">
                <?php echo WPDM()->user->login->form(['logo' => '', 'captcha' =>  false, 'redirect' => get_permalink(get_the_ID())]); ?>
            </div>
            <div class="tab-pane" id="coregister" role="tabpanel" aria-labelledby="register-tab">
                <?php echo WPDM()->user->register->form(['logo' => '', 'captcha' =>  false, 'redirect' =>  get_permalink(get_the_ID()), 'autologin' => 1]); ?>
            </div>
        </div>
    </div>


</div>

<style>
     #coregister .w3eden #wpdmreg,
     #cologin .w3eden #wpdmlogin{
         box-shadow: none;
         border: 0;
         max-width: 500px;
     }
</style>
