<?php
/**
 * Storefront currency switcher.
 *
 * @package WPDMPP\Currency
 * @since   7.2.0
 */

namespace WPDMPP\Currency;

use WPDMPP\Core\CurrencyService;

defined('ABSPATH') || exit;

class CurrencySwitcher
{
    /**
     * @var CurrencySwitcher|null
     */
    private static $instance = null;

    /**
     * @var bool
     */
    private bool $registered = false;

    private function __construct()
    {
    }

    /**
     * @return CurrencySwitcher
     */
    public static function getInstance(): CurrencySwitcher
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return void
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        add_shortcode('wpdmpp_currency_switcher', [$this, 'render']);
        add_action('wp_ajax_wpdmpp_switch_currency', [$this, 'ajaxSwitch']);
        add_action('wp_ajax_nopriv_wpdmpp_switch_currency', [$this, 'ajaxSwitch']);

        // A shortcode alone is not a feature: it only appears for someone who
        // already knows to place it. Offer it as a widget and, optionally, as the
        // floating panel.
        //
        // Deliberately not placed on the cart and checkout pages. A cart with items
        // in it is pinned to the currency its amounts were captured in, so a
        // switcher there can only refuse - the one place it is guaranteed useless.
        add_action('widgets_init', [$this, 'registerWidget']);

        if ($this->floatingEnabled()) {
            add_action('wp_footer', [$this, 'renderFloating']);
        }
    }

    /**
     * Whether the floating panel is switched on.
     *
     * @return bool
     */
    public function floatingEnabled(): bool
    {
        return (int) get_wpdmpp_option('currency_float_enabled', 0, 'int') === 1;
    }

    /**
     * Corner the floating panel sits in.
     *
     * @return string
     */
    public function floatingPosition(): string
    {
        $position = (string) get_wpdmpp_option('currency_float_position', 'middle-left');
        $allowed  = ['bottom-left', 'bottom-right', 'middle-left', 'middle-right', 'top-right'];

        return in_array($position, $allowed, true) ? $position : 'middle-left';
    }

    /**
     * A currency panel pinned to the edge of every page.
     *
     * Separate from render(): the inline switcher is a form control that sits in
     * the page flow, whereas this has to survive on top of any theme, so it brings
     * its own positioning, stacking and dismissal.
     *
     * @return void
     */
    public function renderFloating(): void
    {
        $presentment = PresentmentService::getInstance();

        if (!$presentment->isEnabled()) {
            return;
        }

        $available = $presentment->getAvailable();
        if (count($available) < 2) {
            return;
        }

        $current  = $presentment->getCurrent();
        $service  = CurrencyService::getInstance();
        $position = $this->floatingPosition();
        // Other floating elements - a chat bubble, a cart tab - claim the corners on
        // a real site, so the distance from the edge is adjustable.
        $offset   = max(0, min(400, (int) get_wpdmpp_option('currency_float_offset', 20, 'int')));
        // The admin bar is fixed across the top of the page for logged-in users, so
        // a panel pinned up there would sit behind it. Carried as a class rather
        // than an inline value because its height changes with viewport width, and
        // an inline custom property would outrank the media query that changes it.
        $adminBar = is_admin_bar_showing() ? ' wpdmpp-cfloat--adminbar' : '';
        $symbol   = $service->getCurrencySymbol($current);
        $nonce    = wp_create_nonce('wpdmpp_switch_currency');

        // The file cart pins itself to the middle right at z-index 9999, so the
        // default corner here is the opposite one and the stacking sits just below
        // it rather than fighting for the same space.
        ?>
        <div class="wpdmpp-cfloat wpdmpp-cfloat--<?php echo esc_attr($position) . esc_attr($adminBar); ?>"
             style="--wpdmpp-cfloat-offset:<?php echo (int) $offset; ?>px"
             data-current="<?php echo esc_attr($current); ?>">
            <button type="button" class="wpdmpp-cfloat__toggle" aria-expanded="false"
                    aria-controls="wpdmpp-cfloat-list"
                    aria-label="<?php esc_attr_e('Change currency', 'wpdm-premium-packages'); ?>">
                <span class="wpdmpp-cfloat__symbol"><?php echo esc_html($symbol !== '' ? $symbol : $current); ?></span>
                <span class="wpdmpp-cfloat__code"><?php echo esc_html($current); ?></span>
                <svg class="wpdmpp-cfloat__caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
                </svg>
            </button>

            <div class="wpdmpp-cfloat__list" id="wpdmpp-cfloat-list" role="listbox" hidden>
                <?php foreach ($available as $code) : ?>
                    <?php $sym = $service->getCurrencySymbol($code); ?>
                    <button type="button" role="option"
                            aria-selected="<?php echo $code === $current ? 'true' : 'false'; ?>"
                            class="wpdmpp-cfloat__item<?php echo $code === $current ? ' is-current' : ''; ?>"
                            data-currency="<?php echo esc_attr($code); ?>">
                        <span class="wpdmpp-cfloat__item-sym"><?php echo esc_html($sym !== '' ? $sym : $code); ?></span>
                        <span class="wpdmpp-cfloat__item-code"><?php echo esc_html($code); ?></span>
                        <span class="wpdmpp-cfloat__item-name"><?php echo esc_html($service->getCurrencyName($code)); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
            .wpdmpp-cfloat{position:fixed;z-index:9998;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;}
            .wpdmpp-cfloat--bottom-left{bottom:var(--wpdmpp-cfloat-offset,20px);left:20px;}
            .wpdmpp-cfloat--bottom-right{bottom:var(--wpdmpp-cfloat-offset,20px);right:20px;}
            .wpdmpp-cfloat--middle-left{top:50%;left:var(--wpdmpp-cfloat-offset,20px);transform:translateY(-50%);}
            .wpdmpp-cfloat--middle-right{top:50%;right:var(--wpdmpp-cfloat-offset,20px);transform:translateY(-50%);}
            .wpdmpp-cfloat--top-right{top:calc(var(--wpdmpp-cfloat-offset,20px) + var(--wpdmpp-cfloat-adminbar,0px));right:20px;}
            .wpdmpp-cfloat__toggle{display:flex;align-items:center;gap:7px;background:#fff;color:#1e293b;
                border:1px solid #e2e8f0;border-radius:999px;padding:9px 14px;cursor:pointer;
                box-shadow:0 4px 14px rgba(15,23,42,.14);font-size:14px;line-height:1;transition:box-shadow .15s,transform .15s;}
            .wpdmpp-cfloat__toggle:hover{box-shadow:0 6px 20px rgba(15,23,42,.2);transform:translateY(-1px);}
            .wpdmpp-cfloat__toggle:focus-visible{outline:2px solid #4f46e5;outline-offset:2px;}
            .wpdmpp-cfloat__symbol{font-weight:700;font-size:15px;}
            .wpdmpp-cfloat__code{font-weight:600;letter-spacing:.02em;}
            .wpdmpp-cfloat__caret{width:14px;height:14px;opacity:.55;transition:transform .15s;}
            .wpdmpp-cfloat.is-open .wpdmpp-cfloat__caret{transform:rotate(180deg);}
            .wpdmpp-cfloat__list{position:absolute;min-width:210px;max-height:280px;overflow-y:auto;
                background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:6px;
                box-shadow:0 12px 34px rgba(15,23,42,.18);}
            .wpdmpp-cfloat--bottom-left .wpdmpp-cfloat__list,
            .wpdmpp-cfloat--bottom-right .wpdmpp-cfloat__list{bottom:calc(100% + 8px);}
            .wpdmpp-cfloat--middle-left .wpdmpp-cfloat__list,
            .wpdmpp-cfloat--middle-right .wpdmpp-cfloat__list,
            .wpdmpp-cfloat--top-right .wpdmpp-cfloat__list{top:calc(100% + 8px);}
            .wpdmpp-cfloat--bottom-left .wpdmpp-cfloat__list,
            .wpdmpp-cfloat--middle-left .wpdmpp-cfloat__list{left:0;}
            .wpdmpp-cfloat--bottom-right .wpdmpp-cfloat__list,
            .wpdmpp-cfloat--middle-right .wpdmpp-cfloat__list,
            .wpdmpp-cfloat--top-right .wpdmpp-cfloat__list{right:0;}
            .wpdmpp-cfloat__item{display:flex;align-items:center;gap:9px;width:100%;background:none;border:0;
                padding:9px 11px;border-radius:8px;cursor:pointer;text-align:left;color:#1e293b;font-size:14px;line-height:1.2;}
            .wpdmpp-cfloat__item:hover{background:#f1f5f9;}
            .wpdmpp-cfloat__item.is-current{background:#eef2ff;color:#4338ca;font-weight:600;}
            .wpdmpp-cfloat__item-sym{width:20px;text-align:center;font-weight:700;flex:none;}
            .wpdmpp-cfloat__item-code{font-weight:600;flex:none;}
            .wpdmpp-cfloat__item-name{color:#64748b;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
            .wpdmpp-cfloat__item.is-current .wpdmpp-cfloat__item-name{color:#6366f1;}
            .wpdmpp-cfloat.is-busy{opacity:.6;pointer-events:none;}
            .wpdmpp-cfloat--adminbar{--wpdmpp-cfloat-adminbar:32px;}
            @media (max-width:782px){
                .wpdmpp-cfloat--adminbar{--wpdmpp-cfloat-adminbar:46px;}
            }
            @media (max-width:600px){
                .wpdmpp-cfloat--bottom-left,.wpdmpp-cfloat--middle-left{bottom:14px;left:14px;top:auto;transform:none;}
                .wpdmpp-cfloat--bottom-right,.wpdmpp-cfloat--middle-right{bottom:14px;right:14px;top:auto;transform:none;}
                .wpdmpp-cfloat--top-right{right:14px;}
            }
            @media (prefers-reduced-motion:reduce){
                .wpdmpp-cfloat__toggle,.wpdmpp-cfloat__caret{transition:none;}
            }
        </style>

        <script>
            (function () {
                var root = document.querySelector('.wpdmpp-cfloat');
                if (!root) { return; }
                var toggle = root.querySelector('.wpdmpp-cfloat__toggle');
                var list = root.querySelector('.wpdmpp-cfloat__list');

                function close() {
                    list.hidden = true;
                    root.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                }

                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var open = list.hidden;
                    list.hidden = !open;
                    root.classList.toggle('is-open', open);
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                });

                document.addEventListener('click', function (e) {
                    if (!root.contains(e.target)) { close(); }
                });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') { close(); }
                });

                root.querySelectorAll('.wpdmpp-cfloat__item').forEach(function (item) {
                    item.addEventListener('click', function () {
                        var code = item.getAttribute('data-currency');
                        if (code === root.getAttribute('data-current')) { close(); return; }

                        root.classList.add('is-busy');

                        var body = new URLSearchParams();
                        body.append('action', 'wpdmpp_switch_currency');
                        body.append('currency', code);
                        body.append('_wpnonce', <?php echo wp_json_encode($nonce); ?>);

                        fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
                            method: 'POST', credentials: 'same-origin', body: body
                        }).then(function (r) { return r.json(); }).then(function (res) {
                            if (res && res.success) {
                                // Prices render server side, so the page must be refetched.
                                window.location.reload();
                                return;
                            }
                            root.classList.remove('is-busy');
                            close();
                            if (res && res.data && res.data.message) { window.alert(res.data.message); }
                        }).catch(function () {
                            root.classList.remove('is-busy');
                            close();
                        });
                    });
                });
            })();
        </script>
        <?php
    }




    /**
     * Register the sidebar widget.
     *
     * @return void
     */
    public function registerWidget(): void
    {
        if (class_exists('\WPDMPP\Currency\CurrencySwitcherWidget')) {
            register_widget(\WPDMPP\Currency\CurrencySwitcherWidget::class);
        }
    }

    /**
     * Render the switcher.
     *
     * Renders nothing at all when there is nothing to choose between — a single
     * currency, or rates too stale to price against. An inert control that cannot
     * change anything is worse than no control.
     *
     * @param array $atts
     *
     * @return string
     */
    public function render($atts = []): string
    {
        $presentment = PresentmentService::getInstance();

        if (!$presentment->isEnabled()) {
            return '';
        }

        $available = $presentment->getAvailable();
        if (count($available) < 2) {
            return '';
        }

        $atts = shortcode_atts([
            'label' => __('Currency', 'wpdm-premium-packages'),
            'style' => 'select',
        ], (array) $atts, 'wpdmpp_currency_switcher');

        $current         = $presentment->getCurrent();
        $currencyService = CurrencyService::getInstance();
        $id              = 'wpdmpp-currency-' . wp_rand(1000, 9999);

        ob_start();
        ?>
        <div class="wpdmpp-currency-switcher w3eden" data-current="<?php echo esc_attr($current); ?>">
            <?php if ($atts['label'] !== '') : ?>
                <label for="<?php echo esc_attr($id); ?>" class="wpdmpp-currency-switcher__label">
                    <?php echo esc_html($atts['label']); ?>
                </label>
            <?php endif; ?>
            <select id="<?php echo esc_attr($id); ?>" class="wpdmpp-currency-switcher__select form-control">
                <?php foreach ($available as $code) : ?>
                    <?php $symbol = $currencyService->getCurrencySymbol($code); ?>
                    <option value="<?php echo esc_attr($code); ?>" <?php selected($current, $code); ?>>
                        <?php echo esc_html($symbol !== '' && $symbol !== $code ? $code . ' (' . $symbol . ')' : $code); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="wpdmpp-currency-switcher__msg" aria-live="polite"></span>
        </div>
        <script>
            (function () {
                var root = document.currentScript.parentNode;
                var select = root.querySelector('.wpdmpp-currency-switcher__select');
                var msg = root.querySelector('.wpdmpp-currency-switcher__msg');
                if (!select) { return; }

                select.addEventListener('change', function () {
                    var chosen = select.value;
                    select.disabled = true;
                    msg.textContent = <?php echo wp_json_encode(__('Updating…', 'wpdm-premium-packages')); ?>;

                    var body = new URLSearchParams();
                    body.append('action', 'wpdmpp_switch_currency');
                    body.append('currency', chosen);
                    body.append('_wpnonce', <?php echo wp_json_encode(wp_create_nonce('wpdmpp_switch_currency')); ?>);

                    fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: body
                    }).then(function (r) { return r.json(); }).then(function (res) {
                        if (res && res.success) {
                            // Prices are rendered server side, so the page has to be
                            // re-fetched for the new currency to take effect.
                            window.location.reload();
                            return;
                        }
                        msg.textContent = (res && res.data && res.data.message) ? res.data.message : '';
                        select.value = root.getAttribute('data-current');
                        select.disabled = false;
                    }).catch(function () {
                        msg.textContent = <?php echo wp_json_encode(__('Could not change currency.', 'wpdm-premium-packages')); ?>;
                        select.value = root.getAttribute('data-current');
                        select.disabled = false;
                    });
                });
            })();
        </script>
        <style>
            .wpdmpp-currency-switcher { display: inline-flex; align-items: center; gap: 8px; }
            .wpdmpp-currency-switcher__label { font-size: 13px; color: #64748b; margin: 0; }
            .wpdmpp-currency-switcher__select { width: auto; min-width: 92px; }
            .wpdmpp-currency-switcher__msg { font-size: 12px; color: #94a3b8; }
        </style>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Accept a currency change.
     *
     * @return void
     */
    public function ajaxSwitch(): void
    {
        if (!check_ajax_referer('wpdmpp_switch_currency', '_wpnonce', false)) {
            wp_send_json_error(['message' => __('Your session expired. Please reload the page.', 'wpdm-premium-packages')]);
        }

        $code        = strtoupper(sanitize_text_field($_POST['currency'] ?? ''));
        $presentment = PresentmentService::getInstance();

        // A cart in progress fixes the currency; changing it now would re-price
        // items the shopper has already decided on.
        if (!$presentment->setCurrent($code)) {
            wp_send_json_error([
                'message' => __('That currency is not available.', 'wpdm-premium-packages'),
            ]);
        }

        wp_send_json_success(['currency' => $code]);
    }
}
