<?php
/**
 * Setup Wizard - shell footer (action bar + close).
 *
 * The step forms carry id="wzform"; the Continue button lives here and submits
 * that form via the HTML5 `form` attribute so the action bar stays consistent.
 *
 * @package WPDMPP\Admin\Setup
 *
 * @var string $step    Current step slug.
 * @var string $prevUrl URL of the previous step (empty on first step).
 * @var bool   $isLast  Whether this is the final step.
 * @var int    $current Current step index (0-based).
 * @var int    $total   Total number of steps.
 */

defined('ABSPATH') || exit;

$arrow_left  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>';
$arrow_right = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';
?>
        </div><!-- /.wz-scroll -->

        <div class="wz-foot">
            <?php if (!$isLast) : ?>
                <a class="wz-skip" href="<?php echo esc_url(admin_url()); ?>"><?php esc_html_e('Skip setup', 'wpdm-premium-packages'); ?></a>
            <?php endif; ?>
            <span class="wz-spacer"></span>
            <span class="wz-foot__count"><?php printf(esc_html__('Step %1$d of %2$d', 'wpdm-premium-packages'), $current + 1, $total); ?></span>

            <?php if ($prevUrl !== '') : ?>
                <a class="wz-btn wz-btn--ghost" href="<?php echo esc_url($prevUrl); ?>"><?php echo $arrow_left; ?> <?php esc_html_e('Back', 'wpdm-premium-packages'); ?></a>
            <?php endif; ?>

            <?php if ($isLast) : ?>
                <a class="wz-btn wz-btn--primary" href="<?php echo esc_url(admin_url()); ?>"><?php esc_html_e('Go to dashboard', 'wpdm-premium-packages'); ?> <?php echo $arrow_right; ?></a>
            <?php else : ?>
                <button class="wz-btn wz-btn--primary" type="submit" form="wzform" name="save_step" value="1"><?php esc_html_e('Continue', 'wpdm-premium-packages'); ?> <?php echo $arrow_right; ?></button>
            <?php endif; ?>
        </div>
    </div><!-- /.wz-main -->
</div><!-- /.wz-shell -->
</body>
</html>
