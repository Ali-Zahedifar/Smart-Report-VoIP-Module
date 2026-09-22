// Live report - AMI polling + spy buttons
(function () {
    var cfg = window.SMR_LIVE || {};
    var timer = null;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s === null || s === undefined ? '' : String(s);
        return d.innerHTML;
    }

    function fmtDur(seconds) {
        seconds = parseInt(seconds, 10) || 0;
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        if (m >= 60) {
            var h = Math.floor(m / 60);
            m = m % 60;
            return h + 'h ' + (m < 10 ? '0' : '') + m + 'm';
        }
        return m + 'm ' + (s < 10 ? '0' : '') + s + 's';
    }

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function stateBadge(state) {
        var s = (state || '').toLowerCase();
        var cls = 'neutral';
        if (s === 'up') cls = 'success';
        else if (s === 'ringing' || s === 'ring') cls = 'warning';
        else if (s === 'busy') cls = 'danger';
        return '<span class="badge badge-' + cls + '">' + esc(state || '—') + '</span>';
    }

    function spyButtons(ch) {
        if (!cfg.canSpy) return '';
        var mode = 'listen';
        return '<button type="button" class="btn btn-ghost btn-sm spy-btn" data-channel="' + esc(ch) + '" data-mode="' + mode + '" title="' + esc(cfg.i18n.listen) + '">🎧 ' + esc(cfg.i18n.listen) + '</button>';
    }

    function renderCalls(channels) {
        var body = document.getElementById('liveCallsBody');
        if (!body) return;
        if (!channels || !channels.length) {
            body.innerHTML = '<tr><td colspan="7" class="text-muted">' + esc(cfg.i18n.empty) + '</td></tr>';
            return;
        }
        var rows = channels.map(function (c) {
            var peer = c.channel || '';
            var caller = (c.caller_name ? c.caller_name + ' ' : '') + (c.caller_num || '');
            return '<tr>' +
                '<td class="nowrap">' + esc(peer) + '</td>' +
                '<td>' + esc(caller) + '</td>' +
                '<td>' + esc(c.context || '') + '</td>' +
                '<td class="nowrap">' + esc(c.extension || '') + '</td>' +
                '<td>' + stateBadge(c.state) + '</td>' +
                '<td class="nowrap">' + esc(fmtDur(c.duration)) + '</td>' +
                (cfg.canSpy ? '<td class="nowrap">' + spyButtons(c.channel) + '</td>' : '') +
                '</tr>';
        });
        body.innerHTML = rows.join('');
        bindSpy();
    }

    function renderQueues(queues) {
        var wrap = document.getElementById('liveQueues');
        if (!wrap) return;
        if (!queues || !queues.length) {
            wrap.innerHTML = '<p class="empty-state">' + esc(cfg.i18n.empty) + '</p>';
            return;
        }
        var html = queues.map(function (q) {
            var members = (q.members || []).map(function (m) {
                var st = parseInt(m.status, 10);
                var cls = m.paused ? 'warning' : (st === 1 ? 'neutral' : (st === 2 || st === 6 ? 'danger' : (st === 5 ? 'success' : 'info')));
                var label = m.paused ? 'paused' : (st === 1 ? 'idle' : (st === 2 ? 'in use' : (st === 5 ? 'ringing' : (st === 6 ? 'busy' : 'state ' + st))));
                return '<span class="badge badge-' + cls + '" title="' + esc(m.location) + '">' + esc(m.name || m.location) + ' · ' + esc(label) + '</span>';
            }).join(' ');
            var waiting = (q.waiting || []).map(function (w) {
                return '<li>' + esc(w.caller || w.channel) + ' — ' + esc(fmtDur(w.wait)) + '</li>';
            }).join('');
            return '<div class="queue-block">' +
                '<div class="queue-head"><strong>' + esc(q.queue) + '</strong> ' +
                '<span class="muted">' + esc(cfg.i18n.waiting || '') + ': ' + (q.waiting || []).length +
                ' · ' + esc(q.completed) + ' ✓ / ' + esc(q.abandoned) + ' ✗ · SL ' + esc(q.sl_perf) + '%</span></div>' +
                '<div class="queue-members">' + members + '</div>' +
                (waiting ? '<ul class="queue-waiting">' + waiting + '</ul>' : '') +
                '</div>';
        });
        wrap.innerHTML = html.join('<hr class="soft">');
    }

    function bindSpy() {
        var buttons = document.querySelectorAll('.spy-btn');
        Array.prototype.forEach.call(buttons, function (btn) {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                if (cfg.i18n.confirm && !window.confirm(cfg.i18n.confirm)) return;
                var payload = 'channel=' + encodeURIComponent(btn.dataset.channel) +
                    '&mode=' + encodeURIComponent(btn.dataset.mode) +
                    '&_csrf=' + encodeURIComponent(cfg.csrf || '');
                fetch(cfg.spyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: payload
                }).then(function (r) { return r.json(); }).then(function (res) {
                    if (!res || !res.ok) {
                        window.alert((res && res.error) || 'spy failed');
                    }
                }).catch(function (e) { window.alert(String(e)); });
            });
        });
    }

    function poll() {
        fetch(cfg.pollUrl + (cfg.pollUrl.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                setText('sumActive', data.summary ? data.summary.active : '–');
                setText('sumInUse', data.summary ? data.summary.inuse : '–');
                setText('sumRinging', data.summary ? data.summary.ringing : '–');
                setText('sumWaiting', data.summary ? data.summary.waiting : '–');
                renderCalls(data.channels);
                renderQueues(data.queues);
            })
            .catch(function () { /* transient network hiccup; next tick retries */ });
    }

    function boot() {
        if (!document.getElementById('liveCallsBody')) return;
        poll();
        timer = window.setInterval(poll, cfg.pollMs || 5000);
        var form = document.getElementById('liveOptions');
        if (form) {
            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var payload = 'spy_enabled=' + (form.querySelector('[name=spy_enabled]').checked ? '1' : '0') +
                    '&spy_mode=' + encodeURIComponent(form.querySelector('[name=spy_mode]').value) +
                    '&_csrf=' + encodeURIComponent(cfg.csrf || '');
                fetch(cfg.settingsUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: payload
                }).then(function (r) { return r.json(); }).then(function (res) {
                    var saved = document.getElementById('liveSaved');
                    if (saved && res && res.ok) {
                        saved.style.display = '';
                        window.setTimeout(function () { saved.style.display = 'none'; }, 2500);
                    }
                }).catch(function () {});
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    window.addEventListener('beforeunload', function () { if (timer) window.clearInterval(timer); });
})();
