<?php
/**
 *  Template for Saved Cart UI
 *
 * This template can be overridden by copying it to yourtheme/download-manager/checkout-cart-2col/cart-save.php.
 *
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
} ?>

<div id="wpdm-save-cart" class="d-none m-3">
    <div class="card">
        <div class="card-body">
            <input type=hidden id="cartid"  class="form-control group-item" value="">
            <div class="input-group">
                <div class="input-group-prepend"><span class="input-group-text"><strong><?php _e('Saved Cart URL','wpdm-premium-packages'); ?></strong></span></div>
                <input type=text readonly=readonly style="background: #fff;cursor: copy" onclick="this.select();" id="carturl"  class="form-control group-item" value="">
                <div class="input-group-append"><button type="button" onclick="WPDM.copy('carturl')" class="btn btn-success"><i class="fa fa-copy"></i></button></div>
            </div>
        </div>

    </div>
</div>
