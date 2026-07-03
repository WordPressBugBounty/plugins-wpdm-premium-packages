<?php
if(!defined('ABSPATH')) die('Dream more!');
use WPDMPP\UI\Icons;

global $wpdb;
$page = wpdm_query_var('paged', 'int', 1);
$ipp = 20;
$start = ($page-1)*$ipp;

$abandoned_orders = $wpdb->get_results( "select * from {$wpdb->prefix}ahm_acr_emails order by ID desc limit $start, $ipp" );
$total = $wpdb->get_var( "select count(ID) from {$wpdb->prefix}ahm_acr_emails" );

?>
<div class="row">
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-body"><?= __('Total Steps:', WPDMPP_TEXT_DOMAIN) ?> <strong><?= get_wpdmpp_option( 'acre_count', 0, 'int' ) ?></strong></div>
        </div>
    </div>
    <div class="col-md-9">
        <div class="panel panel-default">
            <div class="panel-body"><?= __('Edit Email Templates:', WPDMPP_TEXT_DOMAIN) ?>
                <?php
                for($i = 1; $i <= get_wpdmpp_option( 'acre_count', 0, 'int' ); $i++) {
                    echo "[ <a target='_blank' href='".admin_url("edit.php?post_type=wpdmpro&page=templates&_type=email&task=EditEmailTemplate&id=order-recovery-email-{$i}")."'>Stage #{$i}</a> ] ";
                } ?></div>
        </div>
    </div>
</div>
<div class="panel panel-default">
    <table class="table table-striped">
        <thead>
        <tr>
            <th><?= __('Order ID', WPDMPP_TEXT_DOMAIN) ?></th>
            <th><?= __('Date', WPDMPP_TEXT_DOMAIN) ?></th>
            <th><?= __('Customer Name', WPDMPP_TEXT_DOMAIN) ?></th>
            <th><?= __('Customer Email', WPDMPP_TEXT_DOMAIN) ?></th>
            <th><?= __('Stage', WPDMPP_TEXT_DOMAIN) ?></th>
            <th><?= __('Email Status', WPDMPP_TEXT_DOMAIN) ?></th>
            <th class="text-right"><?= __('Activity Log', WPDMPP_TEXT_DOMAIN) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($abandoned_orders as $abandoned_order) { ?>
        <tr>
            <td><?php echo esc_html($abandoned_order->order_id); ?></td>
            <td><?php echo esc_html(wp_date(get_option('date_format'), strtotime($abandoned_order->order_date))); ?></td>
            <td><?php echo esc_html($abandoned_order->name); ?></td>
            <td><?php echo esc_html($abandoned_order->email); ?></td>
            <td>#<?php echo (int)$abandoned_order->stage; ?></td>
            <td><?php echo $abandoned_order->sent ? Icons::get('check-double', 14, 'color-green') . ' ' . esc_html__('Sent on', WPDMPP_TEXT_DOMAIN) : Icons::get('clock', 14, 'color-info') . ' ' . esc_html__('Scheduled for', WPDMPP_TEXT_DOMAIN); ?> <?php echo esc_html(wp_date(get_option('date_format'), strtotime($abandoned_order->email_date))); ?></td>
            <td class="text-right"><button class="btn btn-xs btn-info" type="button" onclick="WPDM.bootAlert('<?php echo esc_js(__('Activity Log', WPDMPP_TEXT_DOMAIN)); ?>', {url: ajaxurl + '?action=wpdmpp_acr_activity_log&order_id=<?php echo esc_js($abandoned_order->order_id); ?>&__acr_nonce=<?php echo esc_js(wp_create_nonce(WPDM_PRI_NONCE)); ?>'})"><?php esc_html_e('View', WPDMPP_TEXT_DOMAIN); ?></button></td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<div class="text-center">
	<?php

	$page_links = paginate_links( array(
		'base' => add_query_arg( 'paged', '%#%' ),
		'format' => '',
		'prev_text' => '&laquo;',
		'next_text' => '&raquo;',
		'total' => ceil($total/$ipp),
		'current' => $page
	));
	?>

    <div class="tablenav">
		<?php
		if ( $page_links ) {
			?>
            <div class="tablenav-pages">
				<?php
				$page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
					number_format_i18n( ( $page - 1 ) * $ipp + 1 ),
					number_format_i18n( min( $page * $ipp, $total ) ),
					number_format_i18n( $total ),
					$page_links
				);

				echo $page_links_text; ?>
            </div>
		<?php } ?>

        <div class="alignleft actions" style="height: 35px;"></div>


        <br class="clear">
    </div>
</div>
