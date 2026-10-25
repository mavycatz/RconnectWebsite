<script>
/**
 * Shared OTP input handler for every Passenger OTP flow.
 *
 * Usage:
 *   PassengerOtp.init({
 *     ids: ['otp1', ..., 'otp6'],   // the individual boxes, in order
 *     combined: 'otpCombined',       // optional hidden input kept in sync
 *     onUpdate: function(code, inputs){}, // optional (styling hooks)
 *     autofocus: true                // optional, default true
 *   });
 *
 * Handles:
 *   - typing (hardware keyboard + mobile keyboards)
 *   - pasting a full code into any box (clipboard + mobile suggestion insert)
 *   - auto-advance and focus management
 *   - backspace (clears current or previous box) and digit replacement
 *   - keeping a hidden "combined" field in sync for form submission
 */
(function () {
    if (window.PassengerOtp) {
        return;
    }

    function init(options) {
        options = options || {};

        var ids = options.ids || [];
        var inputs = [];
        for (var i = 0; i < ids.length; i++) {
            var el = document.getElementById(ids[i]);
            if (el) {
                inputs.push(el);
            }
        }
        if (!inputs.length) {
            return null;
        }

        var combined = options.combined ? document.getElementById(options.combined) : null;
        var onUpdate = typeof options.onUpdate === 'function' ? options.onUpdate : null;
        var autofocus = options.autofocus !== false;

        function getCode() {
            var code = '';
            for (var i = 0; i < inputs.length; i++) {
                code += (inputs[i].value || '').replace(/\D/g, '');
            }
            return code;
        }

        function notify() {
            var code = getCode();
            if (combined) {
                combined.value = code;
            }
            if (onUpdate) {
                onUpdate(code, inputs);
            }
        }

        function focusIndex(idx) {
            idx = Math.max(0, Math.min(idx, inputs.length - 1));
            inputs[idx].focus();
            inputs[idx].select();
        }

        function deferFocusIndex(idx) {
            if (typeof requestAnimationFrame === 'function') {
                requestAnimationFrame(function () {
                    focusIndex(idx);
                });
            } else {
                setTimeout(function () {
                    focusIndex(idx);
                }, 0);
            }
        }

        function setDigit(idx, char) {
            if (idx < 0 || idx >= inputs.length) {
                return;
            }
            inputs[idx].value = char || '';
            notify();
        }

        function distributeFrom(startIdx, digits) {
            for (var i = 0; i < digits.length; i++) {
                var idx = startIdx + i;
                if (idx >= inputs.length) {
                    break;
                }
                inputs[idx].value = digits[i];
            }
            notify();
            deferFocusIndex(Math.min(startIdx + digits.length, inputs.length - 1));
        }

        function fill(digitsStr) {
            var cleaned = (digitsStr || '').replace(/\D/g, '').slice(0, inputs.length);
            if (!cleaned) {
                return;
            }
            for (var i = 0; i < inputs.length; i++) {
                inputs[i].value = '';
            }
            distributeFrom(0, cleaned);
        }

        function handlePaste(e) {
            var clipboard = e.clipboardData || window.clipboardData;
            if (e.preventDefault) {
                e.preventDefault();
            }
            fill(clipboard ? (clipboard.getData('text') || '') : '');
        }

        inputs.forEach(function (input, idx) {
            input.addEventListener('paste', handlePaste);

            input.addEventListener('focus', function () {
                this.select();
            });

            input.addEventListener('input', function () {
                var digits = (this.value || '').replace(/\D/g, '');

                if (digits === '') {
                    this.value = '';
                    notify();
                    return;
                }

                if (digits.length > 1) {
                    distributeFrom(idx, digits.slice(0, inputs.length - idx));
                    return;
                }

                this.value = digits;
                notify();
                if (idx < inputs.length - 1) {
                    deferFocusIndex(idx + 1);
                }
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace') {
                    e.preventDefault();
                    if (this.value) {
                        setDigit(idx, '');
                        if (idx > 0) {
                            focusIndex(idx - 1);
                        }
                    } else if (idx > 0) {
                        setDigit(idx - 1, '');
                        focusIndex(idx - 1);
                    }
                    return;
                }

                if (e.key === 'ArrowLeft' && idx > 0) {
                    e.preventDefault();
                    focusIndex(idx - 1);
                    return;
                }

                if (e.key === 'ArrowRight' && idx < inputs.length - 1) {
                    e.preventDefault();
                    focusIndex(idx + 1);
                    return;
                }

                if (e.key.length === 1 && /^[0-9]$/.test(e.key) && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    e.preventDefault();
                    setDigit(idx, e.key);
                    if (idx < inputs.length - 1) {
                        focusIndex(idx + 1);
                    }
                }
            });
        });

        var form = inputs[0].form || inputs[0].closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                notify();
            });
        }

        notify();

        if (autofocus) {
            setTimeout(function () {
                var target = inputs[0];
                for (var i = 0; i < inputs.length; i++) {
                    if (!inputs[i].value) {
                        target = inputs[i];
                        break;
                    }
                }
                target.focus();
            }, 100);
        }

        return {
            inputs: inputs,
            getCode: getCode,
            sync: notify,
            fill: fill,
            focusIndex: focusIndex
        };
    }

    window.PassengerOtp = { init: init };
})();
</script>
