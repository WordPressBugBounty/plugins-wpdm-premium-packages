<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div style="clear: both;margin-top:20px ;"></div>
<div class="panel panel-default">
    <div class="panel-body">
        <div class="media">
            <div class="pull-left">
                <i class="fas fa-coins fa-3x color-green"></i>
            </div>
            <div class="media-body">
                <h3 style="font-size: 16px;margin-bottom: 5px"><?php _e('Recalculate customer value'); ?></h3>
                <p class="note"><?php _e("This task will recalculate the total purchase amount of every customer", WPDM_TEXT_DOMAIN); ?></p>
            </div>
        </div>
    </div>
    <div class="panel-footer">
        <button type="button" id="reccv" class="btn btn-primary">Start Now</button>
        <div class="progress" id="recprogressbar" style="height: 43px !important;border-radius: 3px !important;margin: 0;position: relative;background: #0d406799;display: none;box-shadow: none">
            <div id="recprogress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;line-height: 43px;background-color: #007bff"></div>
            <div class="fetfont" id="proc" style="font-size:12px;position: absolute;line-height: 43px;height: 43px;width: 100%;z-index: 999;text-align: center;color: #ffffff;font-weight: 600;letter-spacing: 1px"><?= esc_attr__( 'Recalculating', WPDM_TEXT_DOMAIN ); ?>... <span id="recloaded">0</span>%</div>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-body">
        <div class="media">
            <div class="pull-left">
                <i class="fas fa-coins fa-3x color-green"></i>
            </div>
            <div class="media-body">
                <h3 style="font-size: 16px;margin-bottom: 5px"><?php _e('Update Order Renewal Table'); ?></h3>
                <p class="note"><?php _e("This task will update order total in order renew table", WPDM_TEXT_DOMAIN); ?></p>
            </div>
        </div>
    </div>
    <div class="panel-footer">
        <button type="button" id="upren" class="btn btn-primary">Start Now</button>
        <div class="progress" id="uprenprogressbar" style="height: 43px !important;border-radius: 3px !important;margin: 0;position: relative;background: #0d406799;display: none;box-shadow: none">
            <div id="uprenprogress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;line-height: 43px;background-color: #007bff"></div>
            <div class="fetfont" id="proc1" style="font-size:12px;position: absolute;line-height: 43px;height: 43px;width: 100%;z-index: 999;text-align: center;color: #ffffff;font-weight: 600;letter-spacing: 1px"><?= esc_attr__( 'Recalculating', WPDM_TEXT_DOMAIN ); ?>... <span id="renloaded">0</span>%</div>
        </div>
    </div>
</div>

<style>
    #intr_rate .chosen-disabled{display: none;}
    .del_rate{line-height: 2;padding: 8px;}
    .del_rate i{color: #ff1d1b;}
</style>
<script>
jQuery(function($){
    function calculate(page) {
        $.post(ajaxurl, {action: 'wpdmpp_recalculateCustomerValue', __rcvnonce: '<?= wp_create_nonce(WPDM_PRI_NONCE) ?>', cp: page}, function (res) {
            $('#recprogress').css('width', res.progress+"%");
            $('#recloaded').html(res.progress);
            if(res.progress == 100)
                $('#proc').html('Completed');
            if(res.continue)
                calculate(res.nextpage);
        })
    }
    function updateren(page) {
        $.post(ajaxurl, {action: 'wpdmpp_updateOrderRenews', __rennonce: '<?= wp_create_nonce(WPDM_PRI_NONCE) ?>', cp: page}, function (res) {
            $('#uprenprogress').css('width', res.progress+"%");
            $('#renloaded').html(res.progress);
            if(res.progress == 100)
                $('#proc1').html('Completed');
            if(res.continue)
                updateren(res.nextpage);
        })
    }
    $('#reccv').on('click', function () {
        $('#recprogressbar').show();
        $('#reccv').hide();
        calculate(1);
    });

    $('#upren').on('click', function () {
        $('#uprenprogressbar').show();
        $('#upren').hide();
        updateren(1);
    });
});
</script>

