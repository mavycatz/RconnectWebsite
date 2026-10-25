/* Add reason entry to the existing custom confirmation overlay, not a new modal. */
(function () {
    window.adminPromptReason = function (title, message, confirmText, maxLength) {
        return new Promise(function (resolve) {
            if (typeof window.showConfirm !== 'function') { console.error('Custom confirmation unavailable'); resolve(null); return; }
            var input;
            window.showConfirm(title, message, function () { resolve(input.value.trim()); }, function () { resolve(null); });
            var overlay = document.getElementById('customAlertOverlay');
            var host = overlay.querySelector('.custom-alert-message');
            var label = document.createElement('label');
            label.textContent = 'Reason (required, recorded with your username)';
            label.style.cssText = 'display:block;margin-top:14px;margin-bottom:6px;font-size:12px;font-weight:600;';
            input = document.createElement('textarea'); input.rows = 3; input.maxLength = maxLength || 500;
            input.required = true; input.setAttribute('aria-label', 'Required reason');
            input.placeholder = 'Explain why this record is being changed...';
            input.style.cssText = 'width:100%;box-sizing:border-box;padding:10px;border:1px solid #cdd9b5;border-radius:9px;resize:vertical;';
            var error = document.createElement('div'); error.setAttribute('role', 'alert'); error.style.cssText = 'color:#b34040;font-size:12px;margin-top:6px;';
            host.appendChild(label); host.appendChild(input); host.appendChild(error);
            var button = overlay.querySelector('.custom-alert-btn');
            button.textContent = confirmText || 'Confirm';
            var confirm = button.onclick;
            button.onclick = function () {
                if (input.value.trim().length < 5) { error.textContent = 'Please give a reason of at least 5 characters.'; input.focus(); return; }
                confirm();
            };
            input.focus();
        });
    };
})();
