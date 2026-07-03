<?php
/**
 * Setup Wizard - shell header (brand rail + vertical stepper).
 *
 * @package WPDMPP\Admin\Setup
 *
 * @var array  $steps   Steps definition (slug => ['name' => ...]).
 * @var string $current Current step slug.
 */

defined('ABSPATH') || exit;

$step_keys     = array_keys($steps);
$current_index = (int) array_search($current, $step_keys, true);

$subtitles = array(
    'basics'  => __('Store settings', 'wpdm-premium-packages'),
    'pages'   => __('Cart & purchases', 'wpdm-premium-packages'),
    'payment' => __('Get paid', 'wpdm-premium-packages'),
    'ready'   => __('All set', 'wpdm-premium-packages'),
);

$check_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta name="viewport" content="width=device-width" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title><?php esc_html_e('Premium Package &rsaquo; Setup Wizard', 'wpdm-premium-packages'); ?></title>
    <?php
    // Print only the wizard's own assets. Firing the broad admin_print_styles /
    // admin_head actions pulls in WP's legacy print_emoji_styles / wp_admin_bar_header
    // callbacks, which are deprecated since WP 6.4 — and the self-contained wizard
    // does not need them. Passing a handle prints just that item without the action.
    wp_print_styles('wpdmpp-wizard');
    wp_print_scripts('wpdmpp-wizard');
    ?>
</head>
<body class="wpdmpp-setup">
<div class="wz-shell">
    <aside class="wz-side">
        <div class="wz-brand">
            <span class="wz-brand__mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            </span>
            <span class="wz-brand__name"><?php esc_html_e('Premium Package', 'wpdm-premium-packages'); ?><span><?php esc_html_e('Setup wizard', 'wpdm-premium-packages'); ?></span></span>
        </div>

        <ul class="wz-steps">
            <?php foreach ($step_keys as $i => $key) :
                $is_done = $i < $current_index;
                $state   = ($key === $current) ? 'is-current' : ($is_done ? 'is-done' : '');
                $sub     = isset($subtitles[$key]) ? $subtitles[$key] : '';
                // Completed steps are links so users can jump back and revise.
                $tag  = $is_done ? 'a' : 'span';
                $href = $is_done ? ' href="' . esc_url(add_query_arg('step', $key, remove_query_arg('activate_error'))) . '"' : '';
                ?>
                <li class="wz-step <?php echo esc_attr($state); ?>">
                    <<?php echo $tag . $href; // phpcs:ignore -- tag name is a literal, href is escaped above ?> class="wz-step__in">
                        <span class="wz-step__dot"><?php echo $is_done ? $check_svg : esc_html($i + 1); ?></span>
                        <span class="wz-step__label">
                            <span class="wz-step__t"><?php echo esc_html($steps[$key]['name']); ?></span>
                            <?php if ($sub !== '') : ?><span class="wz-step__s"><?php echo esc_html($sub); ?></span><?php endif; ?>
                        </span>
                    </<?php echo $tag; ?>>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="wz-side__foot">
            <?php esc_html_e('Need a hand?', 'wpdm-premium-packages'); ?><br>
            <a href="https://www.wpdownloadmanager.com/docsfor/premium-package/" target="_blank" rel="noopener"><?php esc_html_e('Read the setup guide →', 'wpdm-premium-packages'); ?></a>
        </div>
    </aside>

    <div class="wz-main">
        <div class="wz-scroll">
