(function () {
    'use strict';

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

        requestAnimationFrame(function () {
            toast.classList.add('toast-show');
        });

        setTimeout(function () {
            toast.classList.remove('toast-show');
            setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 250);
        }, 2500);
    }

    function updateCartBadge(count) {
        var badge = document.querySelector('[data-cart-badge]');
        if (!badge) return;
        badge.textContent = count;
        if (count > 0) {
            badge.removeAttribute('hidden');
            badge.classList.remove('cart-badge-pulse');
            void badge.offsetWidth;
            badge.classList.add('cart-badge-pulse');
        } else {
            badge.setAttribute('hidden', '');
        }
    }

    function submitCartForm(form, options) {
        options = options || {};

        var data = new FormData(form);
        data.append('ajax', '1');

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
                if (submitBtn) submitBtn.disabled = false;
                showToast('Something went wrong. Reloading...', 'err');
                setTimeout(function () { form.submit(); }, 600);
            })
            .finally(function () {
                if (submitBtn) submitBtn.disabled = false;
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form.js-confirm-delete').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm('Are you sure you want to delete this?')) {
                    event.preventDefault();
                }
            });
        });

        document
            .querySelectorAll('.search-form select[name="category_id"]')
            .forEach(function (sel) {
                sel.addEventListener('change', function () {
                    if (this.form) this.form.submit();
                });
            });

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

        document.querySelectorAll('form.js-cart-qty-form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                submitCartForm(form, {
                    onSuccess: function (json) {
                        var pid = form.querySelector('input[name="product_id"]').value;
                        var cell = document.querySelector('[data-line-total="' + pid + '"]');
                        if (cell && json.line_totals && json.line_totals[pid] !== undefined) {
                            var dzd = Math.round(Number(json.line_totals[pid]) * 260);
                            cell.textContent = dzd.toLocaleString('en-US') + ' DZD';
                        }
                        var totalEl = document.querySelector('[data-cart-total-value]');
                        if (totalEl) totalEl.textContent = json.total_money;

                        showToast('Cart updated');

                        if (json.line_qtys && json.line_qtys[pid] === undefined) {
                            removeRow(pid);
                        }
                    }
                });
            });
        });

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

                        if (json.count === 0) {
                            setTimeout(function () { window.location.reload(); }, 400);
                        }
                    }
                });
            });
        });
    });

    function removeRow(pid) {
        var row = document.querySelector('tr[data-pid="' + pid + '"]');
        if (!row) return;
        row.style.transition = 'opacity 0.2s';
        row.style.opacity = '0';
        setTimeout(function () { if (row.parentNode) row.parentNode.removeChild(row); }, 220);
    }
})();
