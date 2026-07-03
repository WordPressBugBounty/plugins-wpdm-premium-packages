/**
 * Premium Package - Setup Wizard interactions (v7.0.0 redesign).
 * Vanilla JS, no dependencies. Printed in <head>, so wait for the DOM.
 */
(function () {
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function () {
        // Highlight active toggle rows.
        document.querySelectorAll('.wz-opt input[type="checkbox"]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var row = cb.closest('.wz-opt');
                if (row) {
                    row.classList.toggle('is-on', cb.checked);
                }
            });
        });

        // Expand / collapse a gateway card's settings when it's toggled on.
        document.querySelectorAll('[data-gw-toggle]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var card = cb.closest('[data-gw]');
                if (card) {
                    card.classList.toggle('is-on', cb.checked);
                }
            });
        });
    });
})();
