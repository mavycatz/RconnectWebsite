<?php
if (!function_exists("passenger_csrf_token")) {
    http_response_code(403);
    exit();
}
$passengerCsrfToken = passenger_csrf_token();
?>
<meta name="csrf-token" content="<?php echo htmlspecialchars($passengerCsrfToken, ENT_QUOTES, "UTF-8"); ?>">
<link rel="stylesheet" href="/public/login/custom-alert.css">
<script src="/passenger/public/js/passenger-storage.js?v=20261016"></script>
<script>
(function () {
    /* ==============================================================
       R-CONNECT PASSENGER CUSTOM ALERT COMPONENT
       Matches /public/login/custom-alert.css & TMS styling
       ============================================================== */
    function getOrCreateCustomAlertOverlay() {
        var overlay = document.getElementById('customAlertOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'customAlertOverlay';
            overlay.className = 'custom-alert-overlay';
            overlay.innerHTML =
                '<div class="custom-alert-card">' +
                    '<div class="custom-alert-header">' +
                        '<div class="custom-alert-icon"></div>' +
                        '<h3 class="custom-alert-title"></h3>' +
                    '</div>' +
                    '<div class="custom-alert-body">' +
                        '<p class="custom-alert-message"></p>' +
                        '<div class="custom-alert-actions">' +
                            '<button type="button" class="custom-alert-btn"></button>' +
                            '<button type="button" class="custom-alert-btn-cancel"></button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            if (document.body) {
                document.body.appendChild(overlay);
            } else {
                document.addEventListener('DOMContentLoaded', function() {
                    if (!document.getElementById('customAlertOverlay') && document.body) {
                        document.body.appendChild(overlay);
                    }
                });
            }
        }
        return overlay;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', getOrCreateCustomAlertOverlay);
    } else {
        getOrCreateCustomAlertOverlay();
    }

    window.showAlert = function(type, title, message, onConfirm, buttonText) {
        var overlay = getOrCreateCustomAlertOverlay();
        if (!overlay) return;

        var header = overlay.querySelector('.custom-alert-header');
        var iconEl = overlay.querySelector('.custom-alert-icon');
        var titleEl = overlay.querySelector('.custom-alert-title');
        var msgEl = overlay.querySelector('.custom-alert-message');
        var btn = overlay.querySelector('.custom-alert-btn');
        var btnCancel = overlay.querySelector('.custom-alert-btn-cancel');
        var actions = overlay.querySelector('.custom-alert-actions');

        var alertType = type || 'error';
        if (alertType === 'danger') alertType = 'error';
        if (alertType === 'info') alertType = 'warning';

        header.className = 'custom-alert-header ' + alertType;

        if (alertType === 'success') {
            iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#2e4836" stroke-width="3"/><path d="M16 24l6 6 10-10" stroke="#2e4836" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
            if (btnCancel) btnCancel.style.display = 'none';
            if (actions) actions.style.display = '';
            btn.className = 'custom-alert-btn success';
            btn.textContent = buttonText || 'Continue';
        } else if (alertType === 'warning') {
            iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#92400e" stroke-width="3"/><path d="M24 16v10M24 30v2" stroke="#92400e" stroke-width="3" stroke-linecap="round"/></svg>';
            if (btnCancel) btnCancel.style.display = 'none';
            if (actions) actions.style.display = '';
            btn.className = 'custom-alert-btn confirm';
            btn.textContent = buttonText || 'OK';
        } else {
            iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#fff" stroke-width="3"/><path d="M18 18l12 12M30 18l-12 12" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>';
            if (btnCancel) btnCancel.style.display = 'none';
            if (actions) actions.style.display = '';
            btn.className = 'custom-alert-btn error';
            btn.textContent = buttonText || 'Close';
        }

        titleEl.textContent = title || '';
        msgEl.textContent = message || '';

        btn.onclick = function () {
            overlay.classList.remove('active');
            if (typeof onConfirm === 'function') onConfirm();
        };

        overlay.classList.add('active');
    };

    window.showConfirm = function(title, message, onConfirm, onCancel, confirmText, cancelText) {
        var overlay = getOrCreateCustomAlertOverlay();
        if (!overlay) return;

        var header = overlay.querySelector('.custom-alert-header');
        var iconEl = overlay.querySelector('.custom-alert-icon');
        var titleEl = overlay.querySelector('.custom-alert-title');
        var msgEl = overlay.querySelector('.custom-alert-message');
        var btn = overlay.querySelector('.custom-alert-btn');
        var btnCancel = overlay.querySelector('.custom-alert-btn-cancel');
        var actions = overlay.querySelector('.custom-alert-actions');

        header.className = 'custom-alert-header warning';
        iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#92400e" stroke-width="3"/><path d="M24 16v10M24 30v2" stroke="#92400e" stroke-width="3" stroke-linecap="round"/></svg>';

        if (actions) actions.style.display = 'flex';
        if (btnCancel) {
            btnCancel.style.display = 'block';
            btnCancel.className = 'custom-alert-btn cancel';
            btnCancel.textContent = cancelText || 'Cancel';
            btnCancel.onclick = function() {
                overlay.classList.remove('active');
                if (typeof onCancel === 'function') onCancel();
            };
        }

        titleEl.textContent = title || '';
        msgEl.textContent = message || '';

        btn.className = 'custom-alert-btn confirm';
        btn.textContent = confirmText || 'Confirm';
        btn.onclick = function() {
            overlay.classList.remove('active');
            if (typeof onConfirm === 'function') onConfirm();
        };

        overlay.classList.add('active');
    };

    /* ==============================================================
       PASSENGER LOCAL STORAGE REGISTRY (R-CONNECT)
       The registry lives in /passenger/public/js/passenger-storage.js
       (loaded above) so the login redirect pages can reuse the exact
       same reset routine. See passenger/docs/passenger-local-state.md.
       ============================================================== */

    /* ==============================================================
       CENTRALIZED PASSENGER SESSION & API ERROR HANDLER (R-CONNECT)
       ============================================================== */
    var meta = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.getAttribute('content') : '';

    var PassengerSession = {
        isInvalidated: false,
        hasAlert: false,
        cleanupHooks: [],

        onInvalidate: function(fn) {
            if (typeof fn === 'function') {
                this.cleanupHooks.push(fn);
            }
        },

        runCleanups: function() {
            for (var i = 0; i < this.cleanupHooks.length; i++) {
                try {
                    this.cleanupHooks[i]();
                } catch(err) {
                    console.error('Session cleanup error:', err);
                }
            }
        },

        clearSession: function() {
            /* One reset routine: removes every passenger-scoped local key
               (trip, payment, one-time, continuation, identity token) and
               clears sessionStorage, while preference keys survive. The
               routine itself lives in passenger-storage.js, loaded above. */
            if (window.PassengerStorage && typeof window.PassengerStorage.reset === 'function') {
                window.PassengerStorage.reset();
                return;
            }

            try { sessionStorage.clear(); } catch (e) {}
        },

        isLoginPage: function() {
            var path = window.location.pathname.toLowerCase();
            return path.indexOf('/login') !== -1 ||
                   path.indexOf('/forgot-password') !== -1 ||
                   path.indexOf('/register') !== -1 ||
                   path.indexOf('/passengers/create') !== -1;
        },

        showModal: function(opts, onConfirm) {
            var icon = opts.icon || 'warning';
            var title = opts.title || 'Notice';
            var text = opts.text || '';
            var buttonText = opts.buttonText || (icon === 'warning' ? 'OK' : 'Close');

            window.showAlert(icon, title, text, onConfirm, buttonText);
        },

        handleSessionInvalidated: function(customMessage) {
            if (this.isInvalidated) return;
            this.isInvalidated = true;
            this.hasAlert = true;

            this.runCleanups();
            this.clearSession();

            if (this.isLoginPage()) return;

            var msg = customMessage || 'Your account is currently active on another device or browser. Please log in again.';
            this.showModal({
                icon: 'warning',
                title: 'Session Ended',
                text: msg,
                buttonText: 'Log In Again'
            }, function() {
                window.location.href = '/passenger/login?reason=session_invalidated';
            });
        },

        handleSessionExpired: function(customMessage) {
            if (this.isInvalidated) return;
            this.isInvalidated = true;
            this.hasAlert = true;

            this.runCleanups();
            this.clearSession();

            if (this.isLoginPage()) return;

            var msg = customMessage || 'Your session is no longer valid. Please log in again.';
            this.showModal({
                icon: 'warning',
                title: 'Session Expired',
                text: msg,
                buttonText: 'Log In Again'
            }, function() {
                window.location.href = '/passenger/login?reason=expired';
            });
        },

        sanitizeErrorMessage: function(rawError, fallback) {
            var defaultMsg = fallback || "We couldn't load this information right now. Please try again.";
            if (!rawError) return defaultMsg;
            var str = typeof rawError === 'string' ? rawError : (rawError.message || String(rawError));
            
            // Check for technical error signatures that must NEVER be shown to the passenger
            var technicalPatterns = [
                /40[0-9]/i, /50[0-9]/i, /http\s*error/i, /forbidden/i, /unauthorized/i,
                /sqlstate/i, /pdoexception/i, /syntax\s*error/i, /failed\s*to\s*fetch/i,
                /networkerror/i, /uncaught/i, /stack\s*trace/i, /\{.*"success"/i,
                /php\s*warning/i, /php\s*fatal/i, /call\s*to\s*undefined/i,
                /database/i, /select\s+.*from/i, /insert\s+into/i, /update\s+.*set/i,
                /\.php/i, /exception/i
            ];
            for (var i = 0; i < technicalPatterns.length; i++) {
                if (technicalPatterns[i].test(str)) {
                    return defaultMsg;
                }
            }
            return str;
        },

        showFriendlyError: function(title, message, icon) {
            if (this.isInvalidated) return;
            var safeTitle = title || 'Something went wrong';
            var safeMessage = this.sanitizeErrorMessage(message, "We couldn't load this information right now. Please try again.");
            this.showModal({
                icon: icon || 'error',
                title: safeTitle,
                text: safeMessage,
                buttonText: 'OK'
            });
        }
    };

    window.PassengerSession = PassengerSession;

    // Friendly replacement for browser alert()
    window.alert = function(msg) {
        PassengerSession.showFriendlyError('Notice', msg, 'info');
    };

    // Centralized fetch interceptor
    if (window.fetch) {
        var nativeFetch = window.fetch;
        window.fetch = function (input, init) {
            var url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
            var isPassengerApi = url.indexOf('/passenger/') !== -1 || url.indexOf('passenger') !== -1;
            var isAuthEndpoint = url.indexOf('/login') !== -1 ||
                                 url.indexOf('/passengers/store') !== -1 ||
                                 url.indexOf('/register') !== -1 ||
                                 url.indexOf('/forgot-password') !== -1;

            // If session was already marked invalidated, block background and secondary calls
            if (PassengerSession.isInvalidated && isPassengerApi && !isAuthEndpoint) {
                return Promise.reject(new Error('Session invalidated'));
            }

            init = init || {};
            var method = String(init.method || 'GET').toUpperCase();
            if (token && method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
                var headers = init.headers;
                if (headers instanceof Headers) {
                    headers.set('X-CSRF-Token', token);
                } else if (Array.isArray(headers)) {
                    headers.push(['X-CSRF-Token', token]);
                } else {
                    headers = headers || {};
                    headers['X-CSRF-Token'] = token;
                    init.headers = headers;
                }
            }

            return nativeFetch.call(this, input, init).then(function (response) {
                if (!isPassengerApi || isAuthEndpoint) {
                    return response;
                }

                if (response.status === 401) {
                    PassengerSession.handleSessionExpired();
                    return response;
                }

                if (response.status === 403) {
                    try {
                        response.clone().json().then(function (data) {
                            var code = (data && data.code) ? String(data.code).toLowerCase() : '';
                            var msg = (data && data.message) ? String(data.message).toLowerCase() : '';

                            if (code === 'session_invalidated' || code === 'session_revoked' || msg.indexOf('device') !== -1 || msg.indexOf('another session') !== -1) {
                                PassengerSession.handleSessionInvalidated();
                            } else if (code === 'csrf' || code === 'mobile_only') {
                                if (!PassengerSession.isInvalidated && !PassengerSession.hasAlert) {
                                    PassengerSession.showModal({
                                        icon: 'error',
                                        title: 'Security Notice',
                                        text: data.message || 'Security check failed. Please refresh the page and try again.',
                                        buttonText: 'OK'
                                    });
                                }
                            } else if (code === 'auth') {
                                PassengerSession.handleSessionExpired();
                            } else {
                                if (!PassengerSession.isInvalidated && !PassengerSession.hasAlert) {
                                    PassengerSession.showModal({
                                        icon: 'error',
                                        title: 'Access Denied',
                                        text: "You don't have permission to access this information.",
                                        buttonText: 'OK'
                                    });
                                }
                            }
                        }).catch(function () {
                            PassengerSession.handleSessionInvalidated();
                        });
                    } catch (e) {
                        PassengerSession.handleSessionInvalidated();
                    }
                    return response;
                }

                return response;
            }).catch(function (err) {
                if (PassengerSession.isInvalidated) {
                    return Promise.reject(err);
                }
                if (!navigator.onLine) {
                    console.warn('Network offline during passenger request:', url);
                }
                return Promise.reject(err);
            });
        };
    }
})();
</script>
