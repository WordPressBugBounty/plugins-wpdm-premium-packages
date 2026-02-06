<?php
/**
 * Dashborad >> Downloads >> Settings >> Premium Package
 *
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$settings_page = version_compare(WPDM_VERSION, '5.0.0', '>') ? 'settings' : 'wpdm-settings';
$settings = maybe_unserialize(get_option('_wpdmpp_settings'));

// Determine active tab
$active_tab = wpdm_query_var('ppstab', 'txt', 'basic');
if ($active_tab === '') $active_tab = 'basic';

$tabs = [
    'basic' => [
        'id' => 'ppbasic',
        'label' => __("Basic", "wpdm-premium-packages"),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>'
    ],
    'pppayment' => [
        'id' => 'pppayment',
        'label' => __("Payment", "wpdm-premium-packages"),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>'
    ],
    'pptaxes' => [
        'id' => 'pptaxes',
        'label' => __("Taxes", "wpdm-premium-packages"),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" /></svg>'
    ],
    'pptasks' => [
        'id' => 'pptasks',
        'label' => __("Tasks", "wpdm-premium-packages"),
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>'
    ]
];
?>

<style>
/* Premium Packages Settings Tabs */
.wpdmpp-settings-tabs {
    display: flex;
    gap: 6px;
    padding: 4px;
    background: #f1f5f9;
    border-radius: 10px;
    margin-bottom: 20px;
    list-style: none;
}

.wpdmpp-settings-tabs li {
    flex: 1;
    margin: 0;
}

.wpdmpp-settings-tabs__link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 16px;
    background: transparent;
    border: none;
    border-radius: 8px;
    color: #64748b;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none !important;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
}

.wpdmpp-settings-tabs__link:hover {
    background: #e2e8f0;
    color: #475569;
    text-decoration: none;
}

.wpdmpp-settings-tabs__link:focus {
    outline: none;
    box-shadow: 0 0 0 2px #cbd5e1;
}

.wpdmpp-settings-tabs__link.active {
    background: #ffffff;
    color: #1e293b;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.wpdmpp-settings-tabs__icon {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.wpdmpp-settings-tabs__icon svg {
    width: 100%;
    height: 100%;
}

@media (max-width: 782px) {
    .wpdmpp-settings-tabs {
        flex-wrap: wrap;
    }

    .wpdmpp-settings-tabs li {
        flex: 1 1 calc(50% - 3px);
    }

    .wpdmpp-settings-tabs__link {
        padding: 10px 12px;
        font-size: 12px;
    }
}
</style>

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
    <ul id="wppmst" class="wpdmpp-settings-tabs">
        <?php foreach ($tabs as $tab_key => $tab):
            $is_active = ($active_tab === $tab_key) || ($active_tab === $tab['id']);
        ?>
        <li>
            <a class="wpdmpp-settings-tabs__link <?= $is_active ? 'active' : '' ?>"
               href="#<?= esc_attr($tab['id']) ?>"
               data-pptab="<?= esc_attr($tab['id']) ?>"
               data-target="#<?= esc_attr($tab['id']) ?>"
               data-toggle="tab">
                <span class="wpdmpp-settings-tabs__icon"><?= $tab['icon'] ?></span>
                <span><?= $tab['label'] ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <div class="tab-content">
        <section class="tab-pane in <?= wpdm_query_var('ppstab', 'txt', 'ppbasic') === 'ppbasic' ? 'active' : '' ?>" id="ppbasic">
            <?php include_once("basic-options.php"); ?>
        </section>
        <section class="tab-pane in <?= wpdm_query_var('ppstab', 'txt') === 'pppayment' ? 'active' : '' ?>" id="pppayment">
            <?php include_once("payment-options.php"); ?>
        </section>
        <section class="tab-pane in <?= wpdm_query_var('ppstab', 'txt') === 'pptaxes' ? 'active' : '' ?>" id="pptaxes">
            <?php include_once("tax-options.php"); ?>
        </section>
        <section class="tab-pane in <?= wpdm_query_var('ppstab', 'txt') === 'pptasks' ? 'active' : '' ?>" id="pptasks">
            <?php include_once("tasks.php"); ?>
        </section>
    </div>
</div>

<script>
    jQuery(function($){
        // Tab click handler
        $('.wpdmpp-settings-tabs__link').on('click', function(e) {
            e.preventDefault();
            var $this = $(this);
            var target = $this.attr('href');

            // Update active states
            $('.wpdmpp-settings-tabs__link').removeClass('active');
            $this.addClass('active');

            // Show/hide tab panes
            $('.tab-pane').removeClass('active');
            $(target).addClass('active');

            // Store in localStorage
            localStorage.setItem('wppmsta', target);

            // Update URL
            window.history.pushState({
                "html": $('#wpbody-content').html(),
                "pageTitle": "response.pageTitle"
            }, "", "edit.php?post_type=wpdmpro&page=<?= $settings_page ?>&tab=ppsettings&ppstab=" + $this.data('pptab'));

            // Trigger event for compatibility
            $this.trigger('shown.bs.tab');
        });

        // Restore last active tab from localStorage
        var wppmsta = localStorage.getItem('wppmsta');
        if(wppmsta && !window.location.search.includes('ppstab=')){
            var $tab = $('.wpdmpp-settings-tabs__link[href="' + wppmsta + '"]');
            if ($tab.length) {
                $tab.trigger('click');
            }
        }
    });
</script>
