(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function () {
        var app = document.getElementById('app');
        var navToggle = document.getElementById('navToggle');
        var navClose = document.getElementById('navCloseBtn');

        function toggleNav(open) {
            if (!app) {
                return;
            }
            if (typeof open === 'boolean') {
                app.classList.toggle('nav-open', open);
            } else {
                app.classList.toggle('nav-open');
            }
        }

        if (navToggle) {
            navToggle.addEventListener('click', function () { toggleNav(); });
        }
        if (navClose) {
            navClose.addEventListener('click', function () { toggleNav(false); });
        }
        if (app) {
            app.addEventListener('click', function (event) {
                if (app.classList.contains('nav-open') && event.target.closest('.app-main')) {
                    toggleNav(false);
                }
            });
        }

        var langSelect = document.getElementById('langSelect');
        if (langSelect) {
            langSelect.addEventListener('change', function () {
                var option = langSelect.options[langSelect.selectedIndex];
                var target = option ? option.getAttribute('data-url') : '';
                if (target) {
                    window.location.href = target;
                }
            });
        }

        var flashClose = document.querySelectorAll('.alert-close');
        Array.prototype.forEach.call(flashClose, function (btn) {
            btn.addEventListener('click', function () {
                var alert = btn.closest('.alert');
                if (alert) {
                    alert.remove();
                }
            });
        });

        var timers = document.querySelectorAll('.alert');
        timers.forEach(function (alert) {
            setTimeout(function () {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 8000);
        });

        var confirmForms = document.querySelectorAll('form[data-confirm]');
        Array.prototype.forEach.call(confirmForms, function (form) {
            form.addEventListener('submit', function (event) {
                var message = form.getAttribute('data-confirm') || 'Are you sure?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });

        var legButtons = document.querySelectorAll('.btn-legs[data-target]');
        Array.prototype.forEach.call(legButtons, function (btn) {
            btn.addEventListener('click', function () {
                var target = document.querySelector(btn.getAttribute('data-target'));
                if (target) {
                    var open = target.classList.toggle('is-open');
                    if (open && target.scrollIntoView) {
                        target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                }
            });
        });
    });
})();