<?php
/**
 * Setup Wizard - Final "Ready!" step.
 *
 * @package WPDMPP\Admin\Setup
 */

defined('ABSPATH') || exit;
?>
<div class="wz-panel">
    <div class="wz-done">
        <div class="wz-done__badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <p class="wz-eyebrow"><?php esc_html_e('Step 4 of 4 · Ready', 'wpdm-premium-packages'); ?></p>
        <h1 class="wz-title"><?php esc_html_e("You're ready to sell!", 'wpdm-premium-packages'); ?></h1>
        <p class="wz-sub" style="margin-inline:auto"><?php esc_html_e('Your store is configured. Add your first product, or explore what else Premium Package can do.', 'wpdm-premium-packages'); ?></p>

        <div class="wz-cta">
            <a class="wz-cta__card" href="<?php echo esc_url(admin_url('post-new.php?post_type=wpdmpro')); ?>">
                <span class="wz-cta__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg></span>
                <span>
                    <span class="wz-cta__t"><?php esc_html_e('Create your first product', 'wpdm-premium-packages'); ?></span>
                    <span class="wz-cta__d"><?php esc_html_e('Add a download and set a price.', 'wpdm-premium-packages'); ?></span>
                </span>
            </a>
            <a class="wz-cta__card" href="https://www.wpdownloadmanager.com/docsfor/premium-package/" target="_blank" rel="noopener">
                <span class="wz-cta__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg></span>
                <span>
                    <span class="wz-cta__t"><?php esc_html_e('Read the docs', 'wpdm-premium-packages'); ?></span>
                    <span class="wz-cta__d"><?php esc_html_e('Guides for every feature.', 'wpdm-premium-packages'); ?></span>
                </span>
            </a>
            <a class="wz-cta__card" href="https://www.wpdownloadmanager.com/download/verse-wordpress-theme-for-digital-shop/" target="_blank" rel="noopener">
                <span class="wz-cta__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="8" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.8 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5"/></svg></span>
                <span>
                    <span class="wz-cta__t"><?php esc_html_e('Verse theme', 'wpdm-premium-packages'); ?></span>
                    <span class="wz-cta__d"><?php esc_html_e('A free digital-shop theme.', 'wpdm-premium-packages'); ?></span>
                </span>
            </a>
            <a class="wz-cta__card" href="https://www.wpdownloadmanager.com/downloads/free-add-ons/" target="_blank" rel="noopener">
                <span class="wz-cta__ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.5 3.5a2.12 2.12 0 0 1 3 3L13 12l-4 1 1-4Z"/><path d="M20.5 8.5V19a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2v-13a2 2 0 0 1 2-2h10.5"/></svg></span>
                <span>
                    <span class="wz-cta__t"><?php esc_html_e('Free add-ons', 'wpdm-premium-packages'); ?></span>
                    <span class="wz-cta__d"><?php esc_html_e('Extend your store further.', 'wpdm-premium-packages'); ?></span>
                </span>
            </a>
        </div>
    </div>
</div>
