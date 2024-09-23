<?php
/**
 * Dashborad >> Downloads >> Settings >> Premium Package
 *
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$settings_page = version_compare(WPDM_VERSION, '5.0.0', '>') ? 'settings' : 'wpdm-settings';
$settings = maybe_unserialize(get_option('_wpdmpp_settings'));
?>
<div class="wrap">
	<?php
	if(isset($show_db_update_notice) && $show_db_update_notice) {
		?>
        <div class="alert alert-success">
			<?= __('Premium Packages database has been updated successfully', WPDM_TEXT_DOMAIN); ?>
        </div>
		<?php
	}
	?>
    <ul id="wppmst" class="nav nav-pills nav-justified">
        <li <?= wpdm_query_var('ppstab', 'txt', 'basic') === 'basic' ? 'class="active"' : '' ?> ><a href="#ppbasic" data-pptab="ppbasic" data-target="#ppbasic" data-toggle="tab"><?php _e("Basic", "wpdm-premium-packages"); ?></a></li>
        <li <?= wpdm_query_var('ppstab', 'txt') === 'pppayment' ? 'class="active"' : '' ?>><a href="#pppayment" data-pptab="pppayment" data-target="#pppayment" data-toggle="tab"><?php _e("Payment", "wpdm-premium-packages"); ?></a></li>
        <li <?= wpdm_query_var('ppstab', 'txt') === 'pptaxes' ? 'class="active"' : '' ?>><a href="#pptaxes" data-pptab="pptaxes" data-target="#pptaxes" data-toggle="tab"><?php _e("Taxes", "wpdm-premium-packages"); ?></a></li>
        <li <?= wpdm_query_var('ppstab', 'txt') === 'pptasks' ? 'class="active"' : '' ?>><a href="#pptasks" data-pptab="pptasks" data-target="#pptasks" data-toggle="tab"><?php _e("Tasks", "wpdm-premium-packages"); ?></a></li>
    </ul>
    <div class="tab-content">
        <section class="tab-pane <?= wpdm_query_var('ppstab', 'txt', 'basic') === 'basic' ? 'active' : '' ?>" id="ppbasic">
            <?php include_once("basic-options.php"); ?>
        </section>
        <section class="tab-pane <?= wpdm_query_var('ppstab', 'txt') === 'pppayment' ? 'active' : '' ?>" id="pppayment">
            <?php include_once("payment-options.php"); ?>
        </section>
        <section class="tab-pane <?= wpdm_query_var('ppstab', 'txt') === 'pptaxes' ? 'active' : '' ?>" id="pptaxes">
            <?php include_once("tax-options.php"); ?>
        </section>
        <section class="tab-pane <?= wpdm_query_var('ppstab', 'txt') === 'pptasks' ? 'active' : '' ?>" id="pptasks">
            <?php include_once("tasks.php"); ?>
        </section>
    </div>
</div>

<script>
    jQuery(function($){
        $('a[data-toggle="tab"]').on('show.bs.tab', function(e) {
            localStorage.setItem('wppmsta', $(e.target).attr('href'));
            window.history.pushState({
                "html": $('#wpbody-content').html(),
                "pageTitle": "response.pageTitle"
            }, "", "edit.php?post_type=wpdmpro&page=<?= $settings_page ?>&tab=ppsettings&ppstab=" + $(e.target).data('pptab'));
        });
        let wppmsta = localStorage.getItem('wppmsta');
        if(wppmsta){
            $('#wppmst a[href="' + wppmsta + '"]').tab('show');
            window.history.pushState({
                "html": $('#wpbody-content').html(),
                "pageTitle": "response.pageTitle"
            }, "", "edit.php?post_type=wpdmpro&page=<?= $settings_page ?>&tab=ppsettings&ppstab=" + wppmsta.replace('#', ''));
        }
    });
</script>
