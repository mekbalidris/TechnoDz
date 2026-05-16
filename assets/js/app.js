/**
 * Nexus Shop - Public/Admin shared JavaScript
 *
 * Requirements:
 *   9.3  - Admin delete actions require confirmation before submission.
 *   11.1 - Public header search form auto-submits on category change.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // 9.3 Confirm before submitting any admin delete form.
        var deleteForms = document.querySelectorAll('form.js-confirm-delete');
        for (var i = 0; i < deleteForms.length; i++) {
            deleteForms[i].addEventListener('submit', function (event) {
                if (!window.confirm('Are you sure you want to delete this?')) {
                    event.preventDefault();
                }
            });
        }

        // 11.1 Auto-submit the public search form when the category changes.
        var categorySelects = document.querySelectorAll('.search-form select[name="category_id"]');
        for (var j = 0; j < categorySelects.length; j++) {
            categorySelects[j].addEventListener('change', function () {
                if (this.form) {
                    this.form.submit();
                }
            });
        }
    });
})();
