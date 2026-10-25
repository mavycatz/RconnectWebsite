/* Passenger personal QR helper.
 * Renders the deterministic passenger QR (qrcodejs) into a container and
 * provides Download (PNG card: QR + name + code + type) and Print
 * (clean card-only window) actions. Used by the homepage first-login intro
 * and the profile "My Passenger QR" section.
 */
(function () {
    'use strict';

    function getQrEl(container) {
        if (typeof container === 'string') {
            container = document.getElementById(container);
        }
        return container || null;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function qrStrings() {
        var s = (typeof window !== 'undefined' && window.PASSENGER_QR_STRINGS) ? window.PASSENGER_QR_STRINGS : {};
        return {
            alt: s.alt || 'Passenger QR',
            title: s.title || 'R-CONNECT Passenger QR',
            head: s.head || 'R-CONNECT Passenger ID'
        };
    }

    // Render the QR code (qrcodejs) into the container. Deterministic payload
    // -> identical QR on every visit.
    window.renderPassengerQr = function (payload, container) {
        var el = getQrEl(container);
        if (!el || typeof QRCode === 'undefined') {
            return false;
        }
        el.innerHTML = '';
        new QRCode(el, {
            text: String(payload),
            width: 210,
            height: 210,
            colorDark: '#24513c',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
        return true;
    };

    // Text that survives inside a standalone print/download window.
    function qrImageMarkup(el) {
        var canvas = el ? el.getElementsByTagName('canvas')[0] : null;
        if (canvas) {
            try {
                return '<img src="' + canvas.toDataURL('image/png') + '" alt="' + escapeHtml(qrStrings().alt) + '" style="width:220px;height:220px;">';
            } catch (e) {
                // Canvas tainted/unusable — fall through to inner content.
            }
        }
        return el ? el.innerHTML : '';
    }

    function drawRoundRect(ctx, x, y, w, h, r) {
        r = Math.min(r, w / 2, h / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function fitTextLines(ctx, text, maxWidth, maxLines) {
        var words = String(text).split(/\s+/).filter(function (s) { return s.length > 0; });
        var lines = [];
        var line = '';
        for (var i = 0; i < words.length; i++) {
            var test = line ? line + ' ' + words[i] : words[i];
            if (line && ctx.measureText(test).width > maxWidth) {
                if (lines.length >= maxLines - 1) { break; }
                lines.push(line);
                line = words[i];
            } else {
                line = test;
            }
        }
        if (line && lines.length < maxLines) {
            lines.push(line);
        }
        return lines;
    }

    // Download the whole card (QR + name + code + type) as a PNG file.
    window.downloadPassengerQr = function (name, code, container, type) {
        var el = getQrEl(container);
        var canvas = el ? el.getElementsByTagName('canvas')[0] : null;

        if (!canvas) {
            // Older browsers render the QR as DOM (no canvas) — degrade to print.
            printPassengerQr(name, code, container, type);
            return;
        }

        var W = 680;
        var pad = 40;
        var qr = Math.min(canvas.width, W - pad * 2);
        var nameY = pad + qr + 64;
        var H = nameY + 108 + 26;

        var c = document.createElement('canvas');
        c.width = W;
        c.height = H;
        var ctx = c.getContext('2d');
        if (!ctx) {
            printPassengerQr(name, code, container, type);
            return;
        }

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, W, H);

        ctx.strokeStyle = '#c6d48c';
        ctx.lineWidth = 3;
        drawRoundRect(ctx, 14, 14, W - 28, H - 28, 16);
        ctx.stroke();

        var qrX = (W - qr) / 2;
        ctx.drawImage(canvas, qrX, pad, qr, qr);

        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        ctx.fillStyle = '#24513c';
        ctx.font = '600 34px Poppins, Arial, sans-serif';
        var nameLines = fitTextLines(ctx, name, W - pad * 2 - 40, 2);
        nameLines.forEach(function (line, idx) {
            ctx.fillText(line, W / 2, nameY + idx * 40 - (nameLines.length - 1) * 20);
        });

        ctx.fillStyle = '#7b8a74';
        ctx.font = '600 24px Poppins, Arial, sans-serif';
        ctx.fillText(String(code || ''), W / 2, nameY + 52);

        if (type) {
            ctx.fillStyle = '#2f6b43';
            ctx.font = '600 18px Poppins, Arial, sans-serif';
            ctx.fillText(String(type), W / 2, nameY + 92);
        }

        var link = document.createElement('a');
        link.href = c.toDataURL('image/png');
        link.download = 'rconnect-passenger-qr-' + (code || 'passenger') + '.png';
        document.body.appendChild(link);
        link.click();
        link.remove();
    };

    // Print a clean card-only window (no surrounding page UI).
    window.printPassengerQr = function (name, code, container, type) {
        var el = getQrEl(container);
        var qrHtml = qrImageMarkup(el);

        var win = window.open('', '_blank');
        if (!win) {
            return;
        }

        var css =
            'body{font-family:Poppins,Arial,sans-serif;background:#f2f2f2;margin:0;padding:24px;}' +
            '.qr-print-card{width:320px;margin:0 auto;background:#fff;border:2px solid #c6d48c;' +
            'border-radius:18px;padding:22px 18px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,.08);}' +
            '.qr-head{font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;' +
            'color:#2f6b43;margin-bottom:14px;}' +
            '.qr-body{width:220px;height:220px;margin:0 auto 14px;}' +
            '.qr-body canvas,.qr-body img,.qr-body table{max-width:100%;height:auto;margin:0 auto;}' +
            '.qr-name{font-size:16px;font-weight:700;color:#24513c;overflow-wrap:anywhere;word-break:break-word;}' +
            '.qr-code{font-size:13px;font-weight:600;color:#7b8a74;margin-top:4px;}' +
            '.qr-type{display:inline-block;font-size:11px;font-weight:600;color:#2f6b43;' +
            'background:#eef4e6;border:1px solid #d5e4c6;border-radius:999px;padding:3px 14px;margin-top:12px;}' +
            '@media print{body{background:#fff;padding:0;}}';

        win.document.open();
        win.document.write(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' + escapeHtml(qrStrings().title) + '</title>' +
            '<style>' + css + '</style></head><body>' +
            '<div class="qr-print-card">' +
            '<div class="qr-head">' + escapeHtml(qrStrings().head) + '</div>' +
            '<div class="qr-body">' + qrHtml + '</div>' +
            '<div class="qr-name">' + escapeHtml(name) + '</div>' +
            '<div class="qr-code">' + escapeHtml(code) + '</div>' +
            (type ? '<div class="qr-type">' + escapeHtml(type) + '</div>' : '') +
            '</div></body></html>'
        );
        win.document.close();
        win.focus();
        setTimeout(function () { win.print(); }, 300);
    };

    // Shared action wiring for any passenger-qr-card on the page.
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-action="download"], [data-action="print"]') : null;
        if (!btn) {
            return;
        }
        var card = btn.closest('.passenger-qr-card');
        if (!card) {
            return;
        }
        var qrEl = card.querySelector('.passenger-qr-code');
        if (!qrEl) {
            return;
        }
        var name = qrEl.getAttribute('data-name') || '';
        var code = qrEl.getAttribute('data-code') || '';
        var type = qrEl.getAttribute('data-type') || '';
        if (btn.getAttribute('data-action') === 'download') {
            downloadPassengerQr(name, code, qrEl, type);
        } else {
            printPassengerQr(name, code, qrEl, type);
        }
        // Page-level hook (homepage intro) runs after a successful attempt.
        if (typeof window.__passengerQrActionDone === 'function') {
            window.__passengerQrActionDone(btn.getAttribute('data-action'));
        }
    });
})();