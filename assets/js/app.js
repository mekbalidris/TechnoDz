/**
 * Nexus Shop - public/admin shared JavaScript.
 *
 * Handlers wired here:
 *   1. Confirm before submitting any admin "delete" form  (Req 9.3).
 *   2. Auto-submit the public search form when the category dropdown
 *      changes  (Req 11.1, legacy - the dropdown was later replaced with
 *      a chip strip so this is now a no-op when no <select> is present).
 *   3. AJAX add-to-cart from the product detail page.
 *   4. AJAX update/remove from the cart page.
 *
 * The AJAX handlers POST to /cart_action.php and rely on the same handler
 * the classic forms already use - cart_action.php returns JSON whenever the
 * request includes the X-Requested-With: XMLHttpRequest header. If
 * JavaScript is disabled or the fetch fails, the form's regular submit
 * still runs and the page reloads, so the no-JS fallback always works.
 */
(function () {
    'use strict';

    // ----------------------------------------------------------------------
    // Tiny toast notifier
    // ----------------------------------------------------------------------
    function ensureToastContainer() {
        var existing = document.getElementById('js-toast-container');
        if (existing) return existing;
        var c = document.createElement('div');
        c.id = 'js-toast-container';
        c.className = 'toast-container';
        document.body.appendChild(c);
        return c;
    }

    function showToast(message, kind) {
        var container = ensureToastContainer();
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + (kind || 'ok');
        toast.innerHTML =
            '<i class="bi ' +
            (kind === 'err'
                ? 'bi-exclamation-triangle-fill'
                : 'bi-check-circle-fill') +
            '"></i> <span></span>';
        toast.querySelector('span').textContent = message;
        container.appendChild(toast);

        // Animate in on the next frame.
        requestAnimationFrame(function () {
            toast.classList.add('toast-show');
        });

        // Remove after 2.5s.
        setTimeout(function () {
            toast.classList.remove('toast-show');
            setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 250);
        }, 2500);
    }

    // ----------------------------------------------------------------------
    // Cart badge updater
    // ----------------------------------------------------------------------
    function updateCartBadge(count) {
        var badge = document.querySelector('[data-cart-badge]');
        if (!badge) return;
        badge.textContent = count;
        if (count > 0) {
            badge.removeAttribute('hidden');
            // Tiny pulse animation on update.
            badge.classList.remove('cart-badge-pulse');
            // force reflow so the animation can replay
            void badge.offsetWidth;
            badge.classList.add('cart-badge-pulse');
        } else {
            badge.setAttribute('hidden', '');
        }
    }

    // ----------------------------------------------------------------------
    // Submit a cart-action form via fetch and apply the JSON response.
    // ----------------------------------------------------------------------
    function submitCartForm(form, options) {
        options = options || {};

        var data = new FormData(form);
        // Mark the request as AJAX so cart_action.php returns JSON.
        data.append('ajax', '1');

        // Disable the submit button while in flight to prevent double clicks.
        var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        return fetch(form.action, {
            method: 'POST',
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (resp) {
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                return resp.json();
            })
            .then(function (json) {
                if (!json || json.ok !== true) {
                    throw new Error('Bad response');
                }
                updateCartBadge(json.count);
                if (typeof options.onSuccess === 'function') {
                    options.onSuccess(json);
                }
            })
            .catch(function () {
                // Hard fallback: let the browser submit the form normally.
                // We restore the button first so the form is submittable.
                if (submitBtn) submitBtn.disabled = false;
                showToast('Something went wrong. Reloading...', 'err');
                setTimeout(function () { form.submit(); }, 600);
            })
            .finally(function () {
                if (submitBtn) submitBtn.disabled = false;
            });
    }

    // ----------------------------------------------------------------------
    // Wire everything up on DOMContentLoaded
    // ----------------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        // (1) Confirm before submitting any admin delete form.
        document.querySelectorAll('form.js-confirm-delete').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm('Are you sure you want to delete this?')) {
                    event.preventDefault();
                }
            });
        });

        // (2) Legacy: auto-submit on category-select change (no-op when the
        // dropdown isn't present, which is the current default).
        document
            .querySelectorAll('.search-form select[name="category_id"]')
            .forEach(function (sel) {
                sel.addEventListener('change', function () {
                    if (this.form) this.form.submit();
                });
            });

        // (3) AJAX "Add to cart" - product detail (and anywhere else with
        //     class js-add-to-cart on a form posting to cart_action.php).
        document.querySelectorAll('form.js-add-to-cart').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitCartForm(form, {
                    onSuccess: function (json) {
                        showToast('Added to cart (' + json.count + ' item' + (json.count === 1 ? '' : 's') + ')');
                    }
                });
            });
        });

        // (4a) AJAX cart line update.
        document.querySelectorAll('form.js-cart-qty-form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitCartForm(form, {
                    onSuccess: function (json) {
                        // Update the line total cell for this product.
                        var pid = form.querySelector('input[name="product_id"]').value;
                        var cell = document.querySelector('[data-line-total="' + pid + '"]');
                        if (cell && json.line_totals && json.line_totals[pid] !== undefined) {
                            // Server returns USD; format as DZD just like money() does in PHP.
                            var dzd = Math.round(Number(json.line_totals[pid]) * 260);
                            cell.textContent = dzd.toLocaleString('en-US') + ' DZD';
                        }
                        // Update the overall total.
                        var totalEl = document.querySelector('[data-cart-total-value]');
                        if (totalEl) totalEl.textContent = json.total_money;

                        showToast('Cart updated');

                        // If the line was effectively removed (qty < 1), drop the row.
                        if (json.line_qtys && json.line_qtys[pid] === undefined) {
                            removeRow(pid);
                        }
                    }
                });
            });
        });

        // (4b) AJAX cart line remove.
        document.querySelectorAll('form.js-cart-remove-form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitCartForm(form, {
                    onSuccess: function (json) {
                        var pid = form.querySelector('input[name="product_id"]').value;
                        removeRow(pid);
                        var totalEl = document.querySelector('[data-cart-total-value]');
                        if (totalEl) totalEl.textContent = json.total_money;
                        showToast('Item removed');

                        // If the cart is now empty, gently reload so the
                        // PHP view can render the proper "empty cart" state
                        // (with the dashed empty-state box).
                        if (json.count === 0) {
                            setTimeout(function () { window.location.reload(); }, 400);
                        }
                    }
                });
            });
        });
    });

    // Helper used by both update (qty=0 edge case) and remove handlers.
    function removeRow(pid) {
        var row = document.querySelector('tr[data-pid="' + pid + '"]');
        if (!row) return;
        row.style.transition = 'opacity 0.2s';
        row.style.opacity = '0';
        setTimeout(function () { if (row.parentNode) row.parentNode.removeChild(row); }, 220);
    }
})();
