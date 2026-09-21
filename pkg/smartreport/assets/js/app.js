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

        document.addEventListener('click', function (event) {
            var btn = event.target.closest('.btn-legs[data-target]');
            if (btn) {
                toggleLegsRow(btn);
                return;
            }
            var playBtn = event.target.closest('[data-audio-url]');
            if (playBtn) {
                openAudioModal(playBtn.getAttribute('data-audio-url'), playBtn.getAttribute('data-audio-title'));
                return;
            }
            var closer = event.target.closest('[data-audio-close]');
            if (closer) {
                closeAudioModal();
            }
        });

        var modal = document.getElementById('audioModal');
        if (modal) {
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' || event.key === 'Esc' || event.keyCode === 27) {
                    closeAudioModal();
                }
            });
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeAudioModal();
                }
            });
        }
    });

    // Legs expander: resolve by id (no querySelector) so linkedids containing a
    // dot cannot produce an invalid CSS selector. Rows may be hidden either by
    // the .is-open class (calls table) or inline display (missed/internal).
    function toggleLegsRow(btn) {
        var raw = btn.getAttribute('data-target') || '';
        var id = raw.replace(/^#/, '');
        if (id === '') {
            return;
        }
        var target = document.getElementById(id);
        if (!target) {
            return;
        }
        var open = !target.classList.contains('is-open');
        target.classList.toggle('is-open', open);
        target.style.display = open ? '' : 'none';
        if (open && target.scrollIntoView) {
            target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    // In-page recording playback. The <audio> element starts without a src and
    // is only shown once metadata loads, so a missing/invalid file surfaces as
    // a friendly "no recording" state instead of an empty player or navigation.
    function openAudioModal(url, title) {
        var modal = document.getElementById('audioModal');
        if (!modal) {
            return;
        }
        var player = modal.querySelector('audio');
        var box = modal.querySelector('.modal-box');
        var titleEl = modal.querySelector('[data-audio-title]');
        var download = modal.querySelector('[data-audio-download]');
        var empty = modal.querySelector('.modal-empty');
        if (!player || !box) {
            return;
        }
        if (titleEl && title) {
            titleEl.textContent = title;
        }
        if (download) {
            download.href = url.replace(/([?&])mode=inline(&|$)/, '$1mode=download$2');
        }
        if (empty) {
            empty.style.display = 'none';
        }
        player.classList.add('is-hidden');
        player.pause();
        if (player.currentSrc) {
            player.removeAttribute('src');
            player.load();
        }
        modal.classList.add('is-open');

        player.addEventListener('loadedmetadata', function () {
            player.classList.remove('is-hidden');
            if (empty) {
                empty.style.display = 'none';
            }
        }, { once: true });
        player.addEventListener('error', function () {
            player.classList.add('is-hidden');
            if (empty) {
                empty.style.display = '';
            }
        }, { once: true });
        player.src = url;
        player.load();
    }

    function closeAudioModal() {
        var modal = document.getElementById('audioModal');
        if (!modal || !modal.classList.contains('is-open')) {
            return;
        }
        modal.classList.remove('is-open');
        var player = modal.querySelector('audio');
        if (player) {
            player.pause();
            player.removeAttribute('src');
            player.load();
        }
    }
})();