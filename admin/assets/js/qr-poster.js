/*
 * R-Connect designed QR poster module.
 *
 * Single source of truth for the bus QR poster: high-resolution export QR from
 * the exact payload string, the designed 25x35cm poster layout, PNG/PDF export,
 * and print. Used by the legacy QR Access Panel modal and by the live Bus
 * Management QR tab (admin/assets/js/bus-management.js).
 *
 * Requires qrcodejs (window.QRCode). Loads html2canvas and jsPDF on demand.
 * Exposes window.RConnectQrPoster.
 */
(function () {
    'use strict';

    var EXPORT_QR_SIZE = 1024;
    var EXPORT_QR_QUIET_ZONE_RATIO = 0.125;

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showExportError(message) {
        console.error('QR export failed:', message);
        if (window.alert) window.alert(message);
    }

    function drawQrToCanvas(text, size, correctLevel) {
        if (typeof QRCode === 'undefined' || !text) return null;
        var holder = document.createElement('div');
        holder.style.cssText = 'position:fixed;left:-9999px;top:0;';
        document.body.appendChild(holder);
        try {
            new QRCode(holder, {
                text: text,
                width: size,
                height: size,
                colorDark: '#295738',
                colorLight: '#ffffff',
                correctLevel: correctLevel || QRCode.CorrectLevel.H
            });
            return holder.querySelector('canvas');
        } finally {
            holder.remove();
        }
    }

    function makeExportQrDataUrl(text) {
        var qr = drawQrToCanvas(text, EXPORT_QR_SIZE, typeof QRCode !== 'undefined' ? QRCode.CorrectLevel.H : null);
        if (!qr) return '';
        var margin = Math.round(EXPORT_QR_SIZE * EXPORT_QR_QUIET_ZONE_RATIO);
        var out = document.createElement('canvas');
        out.width = EXPORT_QR_SIZE + margin * 2;
        out.height = EXPORT_QR_SIZE + margin * 2;
        var ctx = out.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, out.width, out.height);
        ctx.drawImage(qr, margin, margin, EXPORT_QR_SIZE, EXPORT_QR_SIZE);
        return out.toDataURL('image/png');
    }

    var POSTER_ICONS = {
        bus: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M16 7a1 1 0 0 1-1 1v3.5c0 .818-.393 1.544-1 2v2a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1-.5-.5V14H5v1.5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1-.5-.5v-2a2.5 2.5 0 0 1-1-2V8a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1V2.64C1 1.452 1.845.408 3.064.268A44 44 0 0 1 8 0c2.1 0 3.792.136 4.936.268C14.155.408 15 1.452 15 2.64V4a1 1 0 0 1 1 1zM3.552 3.22A43 43 0 0 1 8 3c1.837 0 3.353.107 4.448.22a.5.5 0 0 0 .104-.994A44 44 0 0 0 8 2c-1.876 0-3.426.109-4.552.226a.5.5 0 1 0 .104.994M8 4c-1.876 0-3.426.109-4.552.226A.5.5 0 0 0 3 4.723v3.554a.5.5 0 0 0 .448.497C4.574 8.891 6.124 9 8 9s3.426-.109 4.552-.226A.5.5 0 0 0 13 8.277V4.723a.5.5 0 0 0-.448-.497A44 44 0 0 0 8 4m-3 7a1 1 0 1 0-2 0 1 1 0 0 0 2 0m8 0a1 1 0 1 0-2 0 1 1 0 0 0 2 0m-7 0a1 1 0 0 0 1 1h2a1 1 0 1 0 0-2H7a1 1 0 0 0-1 1"/></svg>',
        globe: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m7.5-6.923c-.67.204-1.335.82-1.887 1.855q-.215.403-.395.872c.705.157 1.472.257 2.282.287zM4.249 3.539q.214-.577.481-1.078a7 7 0 0 1 .597-.933A7 7 0 0 0 3.051 3.05q.544.277 1.198.49zM3.509 7.5c.036-1.07.188-2.087.436-3.008a9 9 0 0 1-1.565-.667A6.96 6.96 0 0 0 1.018 7.5zm1.4-2.741a12.3 12.3 0 0 0-.4 2.741H7.5V5.091c-.91-.03-1.783-.145-2.591-.332M8.5 5.09V7.5h2.99a12.3 12.3 0 0 0-.399-2.741c-.808.187-1.681.301-2.591.332zM4.51 8.5c.035.987.176 1.914.399 2.741A13.6 13.6 0 0 1 7.5 10.91V8.5zm3.99 0v2.409c.91.03 1.783.145 2.591.332.223-.827.364-1.754.4-2.741zm-3.282 3.696q.18.469.395.872c.552 1.035 1.218 1.65 1.887 1.855V11.91c-.81.03-1.577.13-2.282.287zm.11 2.276a7 7 0 0 1-.598-.933 9 9 0 0 1-.481-1.079 8.4 8.4 0 0 0-1.198.49 7 7 0 0 0 2.276 1.522zm-1.383-2.964A13.4 13.4 0 0 1 3.508 8.5h-2.49a6.96 6.96 0 0 0 1.362 3.675c.47-.258.995-.482 1.565-.667m6.728 2.964a7 7 0 0 0 2.275-1.521 8.4 8.4 0 0 0-1.197-.49 9 9 0 0 1-.481 1.078 7 7 0 0 1-.597.933M8.5 11.909v3.014c.67-.204 1.335-.82 1.887-1.855q.216-.403.395-.872A12.6 12.6 0 0 0 8.5 11.91zm3.555-.401c.57.185 1.095.409 1.565.667A6.96 6.96 0 0 0 14.982 8.5h-2.49a13.4 13.4 0 0 1-.437 3.008M14.982 7.5a6.96 6.96 0 0 0-1.362-3.675c-.47.258-.995.482-1.565.667.248.92.4 1.938.437 3.008zM11.27 2.461q.266.502.482 1.078a8.4 8.4 0 0 0 1.196-.49 7 7 0 0 0-2.275-1.52c.218.283.418.597.597.932m-.488 1.343a8 8 0 0 0-.395-.872C9.835 1.897 9.17 1.282 8.5 1.077V4.09c.81-.03 1.577-.13 2.282-.287z"/></svg>',
        shield: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5.338 1.59a61 61 0 0 0-2.837.856.48.48 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.7 10.7 0 0 0 2.287 2.233c.346.244.652.42.893.533q.18.085.293.118a1 1 0 0 0 .101.025 1 1 0 0 0 .1-.025q.114-.034.294-.118c.24-.113.547-.29.893-.533a10.7 10.7 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.596 4.477-.787 7.795-2.465 9.99a11.8 11.8 0 0 1-2.517 2.453 7 7 0 0 1-1.048.625c-.28.132-.581.24-.829.24s-.548-.108-.829-.24a7 7 0 0 1-1.048-.625 11.8 11.8 0 0 1-2.517-2.453C1.928 10.487.545 7.169 1.141 2.692A1.54 1.54 0 0 1 2.185 1.43 63 63 0 0 1 5.072.56"/><path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0"/></svg>',
        clock: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0 0-.985-.299l.219-.976q.576.129 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8 8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zm1.834 1.79a7 7 0 0 0-.653-.796l.724-.69q.406.429.747.91zm.744 1.352a7 7 0 0 0-.214-.468l.893-.45a8 8 0 0 1 .45 1.088l-.95.313a7 7 0 0 0-.179-.483m.53 2.507a7 7 0 0 0-.1-1.025l.985-.17q.1.58.116 1.17zm-.131 1.538q.05-.254.081-.51l.993.123a8 8 0 0 1-.23 1.155l-.964-.267q.069-.247.12-.501m-.952 2.379q.276-.436.486-.908l.914.405q-.24.54-.555 1.038zm-.964 1.205q.183-.183.35-.378l.758.653a8 8 0 0 1-.401.432z"/><path d="M8 1a7 7 0 1 0 4.95 11.95l.707.707A8.001 8.001 0 1 1 8 0z"/><path d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5 0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5"/></svg>',
        people: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5.784 6A2.24 2.24 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.3 6.3 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5"/></svg>'
    };

    function posterIcon(name) {
        return POSTER_ICONS[name] || '';
    }

    function buildPosterStyle(origin) {
        return '\n@page { size: 25cm 35cm; margin: 0; }\n' +
'* { margin:0; padding:0; box-sizing:border-box; -webkit-print-color-adjust:exact; print-color-adjust:exact; }\n' +
'@import url(\'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap\');\n' +
'html, body { width:25cm; height:35cm; }\n' +
'body { font-family:\'Poppins\',sans-serif; background:#faf9e5; color:#223028; text-align:center; margin:0; }\n' +
'\n' +
'.printable-qr-poster { position:relative; width:25cm; height:35cm; overflow:hidden; background:#faf9e5; }\n' +
'\n' +
'.poster-bg { position:absolute; top:0; left:0; width:100%; height:100%; z-index:0; background-image:url(\'' + origin + '/assets/images/login/backgroundforlogin2.png\'); background-size:cover; background-position:center; background-repeat:no-repeat; }\n' +
'.poster-bg::after { content:\'\'; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(250,249,229,.9); }\n' +
'\n' +
'.poster-content { position:relative; z-index:1; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:space-between; padding:8mm 14mm; }\n' +
'\n' +
'.brand { display:flex; flex-direction:column; align-items:center; }\n' +
'.brand-logo { height:19mm; width:auto; object-fit:contain; display:block; }\n' +
'.brand-tagline { font-size:3.6mm; font-weight:600; color:#295738; letter-spacing:1.8mm; margin-top:3mm; text-transform:uppercase; }\n' +
'\n' +
'.hero { display:flex; flex-direction:column; align-items:center; }\n' +
'.hero-title { font-size:17mm; line-height:1; font-weight:800; color:#295738; letter-spacing:3mm; text-transform:uppercase; }\n' +
'.hero-sub { font-size:4.4mm; color:#223028; margin-top:4mm; }\n' +
'.hero-hint { font-size:3.6mm; color:#7b8a5e; margin-top:2.5mm; }\n' +
'\n' +
'.qr-wrap { position:relative; display:flex; flex-direction:column; align-items:center; }\n' +
'.qr-card { background:#fff; border:1mm solid #dde7c8; border-radius:6mm; padding:7mm; box-shadow:0 1.5mm 5mm rgba(46,72,54,.12); }\n' +
'.qr-card img { width:106mm; height:106mm; display:block; image-rendering:auto; }\n' +
'.bus-badge { position:relative; margin-top:-9mm; z-index:2; width:17mm; height:17mm; border-radius:50%; background:#295738; border:2.5mm solid #faf9e5; display:flex; align-items:center; justify-content:center; }\n' +
'.bus-badge svg { width:8.5mm; height:8.5mm; color:#fff; display:block; }\n' +
'\n' +
'.info-panel { width:100%; background:#2e4836; color:#fff; border-radius:5mm; display:flex; align-items:stretch; padding:6mm 3mm; }\n' +
'.info-col { flex:1; display:flex; flex-direction:column; justify-content:center; min-width:0; }\n' +
'.info-lbl { font-size:3.2mm; font-weight:600; color:#cfe4a6; letter-spacing:.8mm; text-transform:uppercase; }\n' +
'.info-val { font-size:8mm; font-weight:700; line-height:1.1; margin-top:1.5mm; }\n' +
'.info-sep { width:.3mm; background:rgba(255,255,255,.25); margin:0 2mm; }\n' +
'\n' +
'.web-card { width:100%; display:flex; align-items:center; justify-content:center; gap:6mm; background:#eaf2dd; border:1mm solid #d4e3b3; border-radius:5mm; padding:6mm 4mm; }\n' +
'.web-card svg { width:11mm; height:11mm; color:#2e7a4a; flex:0 0 auto; }\n' +
'.web-body { text-align:left; }\n' +
'.web-title { font-size:5.2mm; font-weight:700; color:#295738; }\n' +
'.web-url { font-size:5mm; font-weight:600; color:#2e4836; margin-top:.8mm; }\n' +
'.web-sub { font-size:3.4mm; color:#5f7250; margin-top:.8mm; }\n' +
'\n' +
'.feature-bar { width:100%; background:#295738; color:#fff; border-radius:5mm; display:flex; align-items:stretch; padding:5mm 2mm; }\n' +
'.feature { flex:1; display:flex; flex-direction:column; justify-content:center; align-items:center; min-width:0; }\n' +
'.feature svg { width:8mm; height:8mm; color:#cfe4a6; }\n' +
'.feature .t { font-size:4.4mm; font-weight:700; margin-top:1.2mm; }\n' +
'.feature .s { font-size:3mm; opacity:.85; margin-top:.5mm; }\n' +
'.feature-sep { width:.3mm; background:rgba(255,255,255,.25); margin:0 1mm; }\n' +
'\n' +
'@media print {\n' +
'  * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }\n' +
'  html, body { margin:0; padding:0; width:25cm; height:35cm; }\n' +
'  body > *:not(.printable-qr-poster) { display:none !important; }\n' +
'  .printable-qr-poster { page-break-inside:avoid; break-inside:avoid; }\n' +
'}';
    }

    function buildPosterBody(dataUrl, bus) {
        var busId = escapeHtml(bus && bus.bus_id);
        var plate = escapeHtml(bus && (bus.plate_number || bus.plate));
        var seats = escapeHtml(bus && bus.seats);
        var standing = escapeHtml(bus && bus.standing_capacity);
        return '<div class="printable-qr-poster">' +
  '<div class="poster-bg"></div>' +
  '<div class="poster-content">' +

    '<div class="brand">' +
      '<img class="brand-logo" src="' + window.location.origin + '/assets/images/logo/logoR.png" alt="R-Connect"/>' +
      '<div class="brand-tagline">SMART \u2022 SAFE \u2022 CONNECTED</div>' +
    '</div>' +

    '<div class="hero">' +
      '<div class="hero-title">SCAN TO RIDE</div>' +
      '<div class="hero-sub">Scan the QR code when boarding the bus.</div>' +
      '<div class="hero-hint">No app needed \u2022 Opens in your browser</div>' +
    '</div>' +

    '<div class="qr-wrap">' +
      '<div class="qr-card"><img class="qr-target" src="' + dataUrl + '" alt="Bus QR Code"/></div>' +
      '<div class="bus-badge">' + posterIcon('bus') + '</div>' +
    '</div>' +

    '<div class="info-panel">' +
      '<div class="info-col">' +
        '<div class="info-lbl">BUS ID</div>' +
        '<div class="info-val">' + busId + '</div>' +
      '</div>' +
      '<div class="info-sep"></div>' +
      '<div class="info-col">' +
        '<div class="info-lbl">PLATE NO.</div>' +
        '<div class="info-val">' + plate + '</div>' +
      '</div>' +
      '<div class="info-sep"></div>' +
      '<div class="info-col">' +
        '<div class="info-lbl">CAPACITY</div>' +
        '<div class="info-val">' + seats + ' + ' + standing + '</div>' +
      '</div>' +
    '</div>' +

    '<div class="web-card">' +
      posterIcon('globe') +
      '<div class="web-body">' +
        '<div class="web-title">Visit R-Connect Online</div>' +
        '<div class="web-url">rconnecttms.online</div>' +
        '<div class="web-sub">Scan. Ride. Stay Connected.</div>' +
      '</div>' +
    '</div>' +

    '<div class="feature-bar">' +
      '<div class="feature">' +
        posterIcon('shield') +
        '<div class="t">Safe</div>' +
        '<div class="s">Secure Boarding</div>' +
      '</div>' +
      '<div class="feature-sep"></div>' +
      '<div class="feature">' +
        posterIcon('clock') +
        '<div class="t">Smart</div>' +
        '<div class="s">Track Your Trip</div>' +
      '</div>' +
      '<div class="feature-sep"></div>' +
      '<div class="feature">' +
        posterIcon('people') +
        '<div class="t">Connected</div>' +
        '<div class="s">Better Commute</div>' +
      '</div>' +
    '</div>' +

  '</div>' +
'</div>';
    }

    function waitForImage(img, timeoutMs) {
        return new Promise(function (resolve) {
            if (img.complete && img.naturalWidth > 0) { resolve(); return; }
            var settled = false;
            var timer = null;
            var done = function () {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                if (typeof img.removeEventListener === 'function') {
                    img.removeEventListener('load', done);
                    img.removeEventListener('error', done);
                }
                resolve();
            };
            timer = setTimeout(done, timeoutMs);
            if (typeof img.addEventListener === 'function') {
                img.addEventListener('load', done);
                img.addEventListener('error', done);
            } else {
                img.onload = done;
                img.onerror = done;
            }
        });
    }

    function waitForPosterAssets(holder) {
        var fontSizes = ['800 17mm Poppins', '700 8mm Poppins', '600 5.2mm Poppins', '600 3.6mm Poppins', '400 4.4mm Poppins'];
        var fonts = (document.fonts && typeof document.fonts.load === 'function')
            ? Promise.all(fontSizes.map(function (spec) {
                try { return document.fonts.load(spec).catch(function () {}); } catch (e) { return Promise.resolve(); }
            }))
            : Promise.resolve();
        var ready = (document.fonts && document.fonts.ready)
            ? Promise.race([document.fonts.ready.catch(function () {}), new Promise(function (r) { setTimeout(r, 3000); })])
            : Promise.resolve();
        var images = Array.prototype.slice.call(holder.querySelectorAll('img')).map(function (img) { return waitForImage(img, 5000); });
        return Promise.all([fonts, ready, Promise.all(images)]);
    }

    function renderPosterCanvas(dataUrl, bus) {
        return new Promise(function (resolve, reject) {
            var origin = window.location.origin;
            var holder = document.createElement('div');
            holder.style.cssText = 'position:fixed;left:-9999px;top:0;width:25cm;height:35cm;overflow:hidden;z-index:-9999;';
            holder.innerHTML = '<style>' + buildPosterStyle(origin) + '</style>' + buildPosterBody(dataUrl, bus);
            document.body.appendChild(holder);
            var cleanup = function () { if (holder.parentNode) holder.parentNode.removeChild(holder); };

            waitForPosterAssets(holder)
                .then(function () { return new Promise(function (r) { setTimeout(r, 200); }); })
                .then(function () { return html2canvas(holder.querySelector('.printable-qr-poster'), { scale: 3, useCORS: true, allowTaint: true, backgroundColor: '#faf9e5', logging: false }); })
                .then(function (canvas) { cleanup(); resolve(canvas); })
                .catch(function (err) { cleanup(); reject(err); });
        });
    }

    function loadHtml2canvas() {
        return new Promise(function (res, rej) {
            if (window.html2canvas) return res();
            var s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
            s.onload = res;
            s.onerror = rej;
            document.head.appendChild(s);
        });
    }

    function loadJspdf() {
        return new Promise(function (res, rej) {
            if (window.jspdf && window.jspdf.jsPDF) return res();
            var s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
            s.onload = res;
            s.onerror = rej;
            document.head.appendChild(s);
        });
    }

    function posterFilename(bus, extension) {
        var safe = String((bus && bus.bus_id) || 'BUS').replace(/[^a-zA-Z0-9_-]+/g, '-');
        return 'RConnect_' + safe + '_QR_Poster.' + extension;
    }

    function exportPosterPNG(dataUrl, bus) {
        loadHtml2canvas()
            .then(function () { return renderPosterCanvas(dataUrl, bus); })
            .then(function (canvas) {
                var a = document.createElement('a');
                a.download = posterFilename(bus, 'png');
                a.href = canvas.toDataURL('image/png');
                a.click();
            })
            .catch(function (err) {
                console.error(err);
                showExportError('The QR poster PNG could not be generated. Please try again.');
            });
    }

    function exportPosterPDF(dataUrl, bus) {
        Promise.all([loadHtml2canvas(), loadJspdf()])
            .then(function () { return renderPosterCanvas(dataUrl, bus); })
            .then(function (canvas) {
                var doc = new window.jspdf.jsPDF({ orientation: 'portrait', unit: 'mm', format: [250, 350] });
                doc.addImage(canvas.toDataURL('image/png'), 'PNG', 0, 0, 250, 350);
                doc.save(posterFilename(bus, 'pdf'));
            })
            .catch(function (err) {
                console.error(err);
                showExportError('The QR poster PDF could not be generated. Please try again.');
            });
    }

    function printPoster(dataUrl, bus) {
        var w = window.open('', '', 'width=720,height=1000');
        if (!w) return;
        var origin = window.location.origin;
        w.document.write(
'<!DOCTYPE html>\n' +
'<html>\n' +
'<head>\n' +
'<title>R-Connect - Scan to Ride</title>\n' +
'<style>\n' +
buildPosterStyle(origin) + '\n' +
'</style>\n' +
'</head>\n' +
'<body>\n' +
buildPosterBody(dataUrl, bus) + '\n' +
'</body>\n' +
'</html>');
        w.document.close();
        var printed = false;
        var printIt = function () { if (printed) return; printed = true; w.focus(); w.print(); };
        var start = function () {
            if (w.document.readyState === 'complete') { printIt(); }
            else { w.onload = printIt; setTimeout(printIt, 2500); }
        };
        var fontsReady = (w.document.fonts && w.document.fonts.ready) ? w.document.fonts.ready.catch(function () {}) : Promise.resolve();
        fontsReady.then(function () { setTimeout(start, 150); });
    }

    window.RConnectQrPoster = {
        EXPORT_QR_SIZE: EXPORT_QR_SIZE,
        EXPORT_QR_QUIET_ZONE_RATIO: EXPORT_QR_QUIET_ZONE_RATIO,
        escapeHtml: escapeHtml,
        showExportError: showExportError,
        makeExportQrDataUrl: makeExportQrDataUrl,
        buildPosterStyle: buildPosterStyle,
        buildPosterBody: buildPosterBody,
        renderPosterCanvas: renderPosterCanvas,
        exportPosterPNG: exportPosterPNG,
        exportPosterPDF: exportPosterPDF,
        printPoster: printPoster,
        posterFilename: posterFilename
    };
})();
