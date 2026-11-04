<?php
/**
 * Order Notes Awaiting Reply dashboard widget
 *
 * @var array $threads Orders whose latest note is from the customer, newest first
 * @var int   $total   Number of such orders
 */
if (!defined('ABSPATH')) die('!');
?>
<style>
    .wpdmpp-unreplied {
        --notes-primary: #6366f1;
        --notes-primary-light: #eef2ff;
        --notes-warning: #d97706;
        --notes-warning-light: #fffbeb;
        --notes-success: #10b981;
        --notes-text: #1e293b;
        --notes-text-muted: #64748b;
        --notes-border: #e2e8f0;
        --notes-bg: #f8fafc;
        --notes-radius: 10px;
        --notes-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.08);
        margin: -12px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
    }

    .wpdmpp-unreplied * {
        box-sizing: border-box;
    }

    .wpdmpp-unreplied-content {
        padding: 12px;
        background: var(--notes-bg);
    }

    .wpdmpp-unreplied-summary {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 2px 10px;
        font-size: 12px;
        color: var(--notes-text-muted);
    }

    .wpdmpp-unreplied-summary strong {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        padding: 0 7px;
        border-radius: 11px;
        background: var(--notes-warning-light);
        color: var(--notes-warning);
        font-size: 12px;
        font-weight: 700;
    }

    .wpdmpp-unreplied-list {
        background: #fff;
        border-radius: var(--notes-radius);
        box-shadow: var(--notes-shadow);
        border: 1px solid var(--notes-border);
        overflow: hidden;
    }

    .wpdmpp-unreplied-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 14px;
        border-bottom: 1px solid var(--notes-border);
        text-decoration: none;
        transition: background 150ms ease;
    }

    .wpdmpp-unreplied-item:last-child {
        border-bottom: none;
    }

    .wpdmpp-unreplied-item:hover {
        background: var(--notes-bg);
        text-decoration: none;
    }

    .wpdmpp-unreplied-item:focus {
        outline: none;
        background: var(--notes-primary-light);
    }

    .wpdmpp-unreplied-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--notes-primary-light);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
        border: 2px solid #fff;
        box-shadow: 0 1px 3px rgb(0 0 0 / 0.1);
    }

    .wpdmpp-unreplied-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .wpdmpp-unreplied-avatar svg {
        width: 16px;
        height: 16px;
        color: var(--notes-primary);
    }

    .wpdmpp-unreplied-info {
        flex: 1;
        min-width: 0;
    }

    .wpdmpp-unreplied-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 2px;
    }

    .wpdmpp-unreplied-name {
        font-size: 13px;
        font-weight: 600;
        color: var(--notes-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .wpdmpp-unreplied-count {
        flex-shrink: 0;
        font-size: 10px;
        font-weight: 600;
        padding: 2px 6px;
        border-radius: 10px;
        background: var(--notes-warning-light);
        color: var(--notes-warning);
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .wpdmpp-unreplied-note {
        font-size: 12px;
        line-height: 1.45;
        color: var(--notes-text);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin-bottom: 4px;
    }

    .wpdmpp-unreplied-note--empty {
        color: var(--notes-text-muted);
        font-style: italic;
    }

    .wpdmpp-unreplied-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        font-size: 11px;
        color: var(--notes-text-muted);
    }

    .wpdmpp-unreplied-meta-item {
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }

    .wpdmpp-unreplied-meta-item svg {
        width: 11px;
        height: 11px;
        opacity: 0.6;
    }

    .wpdmpp-unreplied-meta-divider {
        width: 3px;
        height: 3px;
        border-radius: 50%;
        background: var(--notes-border);
    }

    .wpdmpp-unreplied-reply {
        flex-shrink: 0;
        align-self: center;
        font-size: 12px;
        font-weight: 600;
        color: var(--notes-primary);
        padding: 5px 10px;
        border-radius: 6px;
        background: var(--notes-primary-light);
    }

    .wpdmpp-unreplied-item:hover .wpdmpp-unreplied-reply {
        background: var(--notes-primary);
        color: #fff;
    }

    .wpdmpp-unreplied-more {
        margin: 10px 2px 0;
        font-size: 12px;
        color: var(--notes-text-muted);
        text-align: center;
    }

    .wpdmpp-unreplied-empty {
        padding: 28px 16px;
        text-align: center;
        color: var(--notes-text-muted);
    }

    .wpdmpp-unreplied-empty svg {
        width: 40px;
        height: 40px;
        margin-bottom: 8px;
        color: var(--notes-success);
        opacity: 0.8;
    }

    .wpdmpp-unreplied-empty p {
        margin: 0;
        font-size: 13px;
    }
</style>

<div class="wpdmpp-unreplied">
    <div class="wpdmpp-unreplied-content">
        <?php if (empty($threads)): ?>
            <div class="wpdmpp-unreplied-list">
                <div class="wpdmpp-unreplied-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <p><?php _e('No order notes are waiting for a reply.', 'wpdm-premium-packages'); ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="wpdmpp-unreplied-summary">
                <strong><?php echo (int) $total; ?></strong>
                <?php echo esc_html(_n('order is waiting for your reply', 'orders are waiting for your reply', $total, 'wpdm-premium-packages')); ?>
            </div>
            <div class="wpdmpp-unreplied-list">
                <?php foreach ($threads as $thread): ?>
                <a href="<?php echo esc_url($thread['order_url']); ?>" class="wpdmpp-unreplied-item">
                    <div class="wpdmpp-unreplied-avatar">
                        <?php if ($thread['avatar']): ?>
                            <img src="<?php echo esc_url($thread['avatar']); ?>" alt="">
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        <?php endif; ?>
                    </div>

                    <div class="wpdmpp-unreplied-info">
                        <div class="wpdmpp-unreplied-header">
                            <span class="wpdmpp-unreplied-name"><?php echo esc_html($thread['customer']); ?></span>
                            <?php if ($thread['count'] > 1): ?>
                                <span class="wpdmpp-unreplied-count"><?php printf(esc_html__('%d notes', 'wpdm-premium-packages'), (int) $thread['count']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($thread['note'] !== ''): ?>
                            <div class="wpdmpp-unreplied-note"><?php echo esc_html($thread['note']); ?></div>
                        <?php else: ?>
                            <div class="wpdmpp-unreplied-note wpdmpp-unreplied-note--empty"><?php _e('Sent an attachment', 'wpdm-premium-packages'); ?></div>
                        <?php endif; ?>
                        <div class="wpdmpp-unreplied-meta">
                            <span class="wpdmpp-unreplied-meta-item">#<?php echo esc_html($thread['order_id']); ?></span>
                            <span class="wpdmpp-unreplied-meta-divider"></span>
                            <span class="wpdmpp-unreplied-meta-item" title="<?php echo esc_attr(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $thread['received'])); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <?php echo esc_html($thread['time_ago']); ?>
                            </span>
                            <?php if ($thread['files'] > 0): ?>
                            <span class="wpdmpp-unreplied-meta-divider"></span>
                            <span class="wpdmpp-unreplied-meta-item">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                                </svg>
                                <?php printf(esc_html(_n('%d file', '%d files', $thread['files'], 'wpdm-premium-packages')), (int) $thread['files']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <span class="wpdmpp-unreplied-reply"><?php _e('Reply', 'wpdm-premium-packages'); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php if ($total > count($threads)): ?>
                <div class="wpdmpp-unreplied-more">
                    <?php printf(esc_html(_n('%d more order is waiting', '%d more orders are waiting', $total - count($threads), 'wpdm-premium-packages')), (int) ($total - count($threads))); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
