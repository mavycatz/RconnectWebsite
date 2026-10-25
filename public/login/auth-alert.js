/* =============================================================
   SHARED AUTH ALERT HELPER  (R-Connect TMS)
   =============================================================
   Renders the .auth-alert inline component into a container and
   drives live countdowns.

   IMPORTANT (security): the countdown is ONLY a visual layer.
   The backend remains authoritative for lock/OTP state. When a
   countdown reaches 0 this helper simply reloads the page so the
   server revalidates the real state. It never unlocks anything.
================================================================= */

/* ESP-escape values written into HTML/CDATA (prevents breaking the
   inline <script> that PHP pages use to seed its data). */
function authEscJs(value) {
    return String(value == null ? '' : value)
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/</g, '\\x3C')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '\\r');
}

/* Default icon map (Bootstrap Icons) by variant */
var AUTH_ALERT_ICONS = {
    error:   'bi bi-exclamation-triangle-fill',
    warning: 'bi bi-exclamation-triangle-fill',
    locked:  'bi bi-lock-fill',
    expired: 'bi bi-clock-history',
    info:    'bi bi-info-circle-fill',
    success: 'bi bi-check-circle-fill'
};

/* -----------------------------------------------------------------
   Loading helper: disables a submit control, shows an inline themed
   spinner and an optional label, and prevents duplicate submission.
   Returns true the FIRST time it applies; false afterwards so the
   caller can stop a repeated submit.
   - button  : the <button>/submit control
   - label   : optional replacement label (e.g. "Signing in...")
   The button's existing text element (a span with .auth-btn-label or
   #loginBtnText) is reused when present, otherwise a label span is
   appended after the spinner.
------------------------------------------------------------------ */
function authLoading(button, label) {
    if (!button) return false;
    if (button.dataset.authBusy) return false;

    button.dataset.authBusy = 'true';
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    if (!button.querySelector('.auth-spinner')) {
        var spinner = document.createElement('span');
        spinner.className = 'auth-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        button.insertBefore(spinner, button.firstChild);
    }

    if (typeof label === 'string' && label.length) {
        var labelEl = button.querySelector('.auth-btn-label, #loginBtnText');
        if (!labelEl) {
            labelEl = document.createElement('span');
            labelEl.className = 'auth-btn-label';
            button.appendChild(labelEl);
        }
        // Remove the button's original static text so the loading label
        // replaces it instead of running into it (e.g. the previous
        // "Verify OTPPlease wait" with no space).
        Array.prototype.slice.call(button.childNodes).forEach(function (node) {
            if (node === button.querySelector('.auth-spinner')) return;
            if (node === labelEl) return;
            if (node.nodeType === 3) {
                node.textContent = '';
            } else if (node.classList && !node.classList.contains('auth-spinner') && !node.classList.contains('auth-btn-label')) {
                node.style.display = 'none';
            }
        });
        labelEl.textContent = label;
    }

    return true;
}

/* Build a fresh .auth-alert element (does NOT attach it). */
function buildAuthAlert(type, title, detail) {
    type = AUTH_ALERT_ICONS[type] ? type : 'error';

    var el = document.createElement('div');
    el.className = 'auth-alert auth-alert--' + type;
    el.setAttribute('role', 'alert');

    var icon = document.createElement('span');
    icon.className = 'auth-alert--icon';
    icon.setAttribute('aria-hidden', 'true');
    var i = document.createElement('i');
    i.className = AUTH_ALERT_ICONS[type];
    icon.appendChild(i);

    var body = document.createElement('span');
    body.className = 'auth-alert--body';

    if (title != null && String(title).length) {
        var t = document.createElement('strong');
        t.className = 'auth-alert--title';
        t.textContent = title;
        body.appendChild(t);
    }
    if (detail != null && String(detail).length) {
        var d = document.createElement('span');
        d.className = 'auth-alert--detail';
        d.textContent = detail;
        body.appendChild(d);
    }

    el.appendChild(icon);
    el.appendChild(body);
    return el;
}

/* Replace a container's contents with a single alert. */
function showAuthAlert(containerId, type, title, detail) {
    var c = document.getElementById(containerId);
    if (!c) return;
    c.innerHTML = '';
    var alert = buildAuthAlert(type, title, detail);
    c.appendChild(alert);
}

/* -----------------------------------------------------------------
   Lockout progress: refreshes every second, purely visual.
   - lockUntilSecs : epoch seconds (from backend lock_until)
   - containerId   : where the .auth-alert lives
   - relabelDetail : optional string that may contain %s for the time
   When it hits 0 the page reloads so the server revalidates.
----------------------------------------------------------------- */
var authLockTimers = {};

function stopAuthCountdown(key) {
    if (authLockTimers[key]) { clearInterval(authLockTimers[key]); delete authLockTimers[key]; }
}

/* Persistent element used to render the live time.
   - relabel mode : reuses the alert's first .auth-alert--detail element
     and rewrites its text each tick with the relabel phrase.
   - numeric mode : keeps a dedicated .auth-alert--countdown span appended
     to the detail (or the container) and updates just that span. */
function getCountdownNode(containerId, key, relabelDetail) {
    var id = 'countdown-' + key;
    var el = document.getElementById(id);
    if (el) return el;
    var c = document.getElementById(containerId);
    if (!c) return null;

    if (relabelDetail) {
        var firstDetail = c.querySelector('.auth-alert--detail');
        if (firstDetail) {
            firstDetail.id = id;
            return firstDetail;
        }
    }

    var parent = c.querySelector('.auth-alert--detail') || c;
    el = document.createElement('span');
    el.className = relabelDetail ? 'auth-alert--detail' : 'auth-alert--countdown';
    el.id = id;
    parent.appendChild(el);
    return el;
}

function startLockCountdown(key, containerId, lockUntilSecs, relabelDetail) {
    stopAuthCountdown(key);
    lockUntilSecs = Math.floor(lockUntilSecs || 0);

    function formatDuration(totalSecs) {
        totalSecs = Math.max(0, Math.floor(totalSecs));
        var m = Math.floor(totalSecs / 60);
        var s = totalSecs % 60;
        return ('0' + m).slice(-2) + ':' + ('0' + s).slice(-2);
    }

    function remainingNow() {
        return lockUntilSecs - Math.floor(Date.now() / 1000);
    }

    function tick() {
        var c = document.getElementById(containerId);
        if (!c) { stopAuthCountdown(key); return; }
        var remaining = remainingNow();

        if (remaining <= 0) {
            stopAuthCountdown(key);
            var cd = document.getElementById('countdown-' + key);
            if (cd) { cd.textContent = relabelDetail ? 'Time elapsed. Rechecking your account...' : '00:00'; }
            setTimeout(function () { window.location.reload(); }, 800);
            return;
        }

        var cd = getCountdownNode(containerId, key, relabelDetail);
        if (!cd) return;

        if (relabelDetail) {
            var mins = Math.floor(remaining / 60);
            var label = (mins > 1 ? mins + ' minutes' : (mins === 1 ? '1 minute' : 'under a minute'));
            cd.textContent = relabelDetail.replace('%s', label);
        } else {
            cd.textContent = formatDuration(remaining);
        }
    }

    tick();
    authLockTimers[key] = setInterval(tick, 1000);
}

/* -----------------------------------------------------------------
   OTP expiry countdown.
   - otpExpires: epoch seconds from backend
   - relabelDetail may contain %s
   On reaching 0 the page reloads so the backend can flag expiry.
----------------------------------------------------------------- */
var authOtpTimers = {};

function startOtpCountdown(key, containerId, otpExpiresSecs, relabelDetail) {
    startLockCountdown(key, containerId, otpExpiresSecs, relabelDetail);
}