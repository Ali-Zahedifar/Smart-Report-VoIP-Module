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
                var base = window.SMR_BASE || '';
                window.location.href = base + '/lang/' + encodeURIComponent(langSelect.value);
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

        var playerBar = document.getElementById('player');
        var playerAudio = document.getElementById('playerAudio');
        var playerClose = document.getElementById('playerCloseBtn');

        var playButtons = document.querySelectorAll('.btn-play[data-audio]');
        Array.prototype.forEach.call(playButtons, function (btn) {
            btn.addEventListener('click', function () {
                var src = btn.getAttribute('data-audio');
                if (!playerBar || !playerAudio || !src) {
                    return;
                }
                playerAudio.src = src;
                playerBar.hidden = false;
                playerAudio.play().catch(function () {});
            });
        });

        if (playerClose && playerBar && playerAudio) {
            playerClose.addEventListener('click', function () {
                playerAudio.pause();
                playerAudio.removeAttribute('src');
                playerAudio.load();
                playerBar.hidden = true;
            });
        }
    });
})();