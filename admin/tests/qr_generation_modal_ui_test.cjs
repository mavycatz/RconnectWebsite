/*
 * QR Access Panel export harness (change fix-bus-qr-png-pdf-export, task 6.1).
 *
 * Runs the inline script of admin/modals/qr_generation_modal.php inside a vm
 * context with miniature DOM, QRCode, html2canvas, and jsPDF mocks, then
 * asserts:
 *   - the export QR is rendered from the exact preview payload string
 *   - the poster body contains exactly one QR and no website QR
 *   - exported details belong to the open bus and are HTML-escaped
 *   - a poster render failure alerts and downloads nothing
 *   - PNG and PDF downloads use the designed poster canvas
 *
 *   node admin/tests/qr_generation_modal_ui_test.cjs
 */

const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..', '..');
const php = fs.readFileSync(path.join(root, 'admin', 'modals', 'qr_generation_modal.php'), 'utf8');
const scriptStart = php.lastIndexOf('<script>');
const scriptEnd = php.indexOf('</script>', scriptStart);
assert.ok(scriptStart !== -1 && scriptEnd > scriptStart, 'modal inline script found');
const script = php.slice(scriptStart + '<script>'.length, scriptEnd);

const state = {
    downloads: [],
    alerts: [],
    qrRenders: [],
    posters: [],
    pdfs: [],
    fetchCalls: [],
    consoles: [],
    printWindows: [],
    html2canvasMode: 'ok',
    bus: null,
};

function parseImgs(html) {
    const imgs = [];
    const re = /<img\b[^>]*>/g;
    let match;
    while ((match = re.exec(html)) !== null) {
        imgs.push(makeElement('img'));
    }
    return imgs;
}

function makeCanvas() {
    const el = makeElement('canvas');
    el.width = 0;
    el.height = 0;
    el.getContext = () => ({
        fillStyle: '#000000',
        fillRect() {},
        drawImage() {},
        strokeRect() {},
        clearRect() {},
    });
    el.toDataURL = () => 'data:image/png;base64,MOCK';
    return el;
}

function makeElement(tag = 'div') {
    const el = {
        tagName: tag.toUpperCase(),
        children: [],
        parentNode: null,
        style: {},
        dataset: {},
        disabled: false,
        textContent: '',
        complete: true,
        naturalWidth: 10,
        src: '',
        _html: '',
        _imgs: [],
        listeners: {},
        classList: {
            _set: new Set(),
            add(c) { this._set.add(c); },
            remove(c) { this._set.delete(c); },
            toggle(c, on) {
                if (on === undefined) on = !this._set.has(c);
                on ? this._set.add(c) : this._set.delete(c);
                return on;
            },
            contains(c) { return this._set.has(c); },
        },
        appendChild(child) { this.children.push(child); child.parentNode = this; return child; },
        removeChild(child) { this.children = this.children.filter(c => c !== child); child.parentNode = null; return child; },
        remove() { if (this.parentNode) this.parentNode.removeChild(this); },
        addEventListener(type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn); },
        removeEventListener(type, fn) { this.listeners[type] = (this.listeners[type] || []).filter(f => f !== fn); },
        dispatch(type) { (this.listeners[type] || []).slice().forEach(fn => fn({ type })); },
        click() {
            if (tag === 'a') state.downloads.push({ download: this.download, href: this.href });
            else if (typeof this.onclick === 'function') this.onclick({});
        },
        focus() {},
        setAttribute() {},
        getAttribute() { return null; },
        setAttributeNS() {},
        querySelector(sel) { return this.querySelectorAll(sel)[0] || null; },
        querySelectorAll(sel) {
            if (sel === 'img') return this._imgs;
            if (sel === '.printable-qr-poster') {
                const poster = makeElement('div');
                poster._sourceHolder = this;
                return [poster];
            }
            const found = [];
            const walk = (node) => {
                node.children.forEach(child => {
                    if (sel === 'canvas' && child.tagName === 'CANVAS') found.push(child);
                    walk(child);
                });
            };
            walk(this);
            return found;
        },
    };
    Object.defineProperty(el, 'innerHTML', {
        get() { return el._html; },
        set(value) { el._html = String(value); el._imgs = parseImgs(el._html); },
    });
    return el;
}

const domReady = [];
const body = makeElement('body');
const elements = new Map();
const fmtCards = [makeElement('div'), makeElement('div')];
fmtCards[0].dataset.dlFormat = 'png';
fmtCards[1].dataset.dlFormat = 'pdf';
const fmtModal = makeElement('div');
fmtModal.querySelectorAll = (sel) => (sel === '.qr-dl-card' ? fmtCards : []);
elements.set('qrDownloadModal', fmtModal);

const documentMock = {
    documentElement: { tagName: 'DIV' },
    body,
    addEventListener(type, fn) { if (type === 'DOMContentLoaded') domReady.push(fn); },
    getElementById(id) {
        if (!elements.has(id)) elements.set(id, makeElement('div'));
        return elements.get(id);
    },
    createElement(tag) { return tag === 'canvas' ? makeCanvas() : makeElement(tag); },
    createElementNS(ns, tag) { return makeElement(tag); },
};

async function fetchMock(url) {
    state.fetchCalls.push(String(url));
    if (String(url).includes('getBusDetails.php')) {
        return { json: async () => ({ success: true, data: { ...state.bus } }) };
    }
    if (String(url).includes('save_bus_qr.php')) {
        return { json: async () => ({ success: true }) };
    }
    return { json: async () => ({ success: false, message: 'not mocked' }) };
}

const modalInstances = new Map();
function ModalMock(el) {
    this.el = el;
    this.isShown = false;
    modalInstances.set(el, this);
}
ModalMock.prototype.show = function () { this.isShown = true; };
ModalMock.prototype.hide = function () { this.isShown = false; };
ModalMock.getInstance = (el) => modalInstances.get(el) || null;
ModalMock.getOrCreateInstance = (el) => modalInstances.get(el) || new ModalMock(el);
const bootstrap = { Modal: ModalMock };

function QRCodeMock(el, options) {
    state.qrRenders.push({
        text: options.text,
        width: options.width,
        height: options.height,
        el,
    });
    el.appendChild(makeCanvas());
}
QRCodeMock.CorrectLevel = { L: 1, M: 0, Q: 3, H: 2 };

function html2canvasMock(el, options) {
    state.posters.push({
        html: el && el._sourceHolder ? el._sourceHolder._html : '',
        options,
    });
    if (state.html2canvasMode === 'fail') {
        return Promise.reject(new Error('poster render failed'));
    }
    const canvas = makeCanvas();
    canvas.toDataURL = () => 'data:image/png;base64,POSTER';
    return Promise.resolve(canvas);
}

function jsPDFMock(options) {
    this.options = options;
    this.images = [];
}
jsPDFMock.prototype.addImage = function (data, format, x, y, w, h) {
    this.images.push({ data, format, x, y, w, h });
};
jsPDFMock.prototype.save = function (name) {
    state.pdfs.push({ options: this.options, images: this.images, name });
};

const windowMock = {
    location: { origin: 'https://rconnect.example' },
    alert: (message) => state.alerts.push(message),
    open: () => {
        const win = {
            html: '',
            focused: false,
            printed: false,
            document: {
                readyState: 'complete',
                write(chunk) { win.html += String(chunk); },
                close() {},
            },
            focus() { win.focused = true; },
            print() { win.printed = true; },
        };
        state.printWindows.push(win);
        return win;
    },
    html2canvas: html2canvasMock,
    jspdf: { jsPDF: jsPDFMock },
    devicePixelRatio: 1,
};

const sandbox = {
    document: documentMock,
    window: windowMock,
    bootstrap,
    QRCode: QRCodeMock,
    html2canvas: html2canvasMock,
    fetch: fetchMock,
    console: {
        log: () => {},
        warn: () => {},
        error: (...args) => state.consoles.push(args.join(' ')),
    },
    alert: windowMock.alert,
    navigator: { userAgent: 'node' },
    setTimeout,
    clearTimeout,
};

vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(path.join(root, 'admin', 'assets', 'js', 'qr-poster.js'), 'utf8'), sandbox);
vm.runInContext(script, sandbox);
assert.ok(windowMock.RConnectQrPoster, 'shared poster module loaded');
assert.ok(domReady.length >= 1, 'DOMContentLoaded handler is registered by the modal script');
domReady.forEach(fn => fn());

const settle = (ms = 500) => new Promise(resolve => setTimeout(resolve, ms));

function pickFormat(format) {
    const card = fmtCards.find(c => c.dataset.dlFormat === format);
    assert.ok(card, `format card ${format} exists`);
    card.listeners.click[0]({});
}

function confirmDownload() {
    const confirm = elements.get('qrDlConfirmBtn');
    assert.ok(confirm && confirm.listeners.click && confirm.listeners.click.length, 'confirm button listener exists');
    confirm.listeners.click[0]({});
}

(async () => {
    const busA = {
        bus_id: 'BUS-001<&>',
        plate_number: 'NCR 1234',
        seats: 49,
        standing_capacity: 10,
        current_trip_id: 77,
        trip_route_name: 'Cubao - Baguio',
        trip_status: 'Active',
        driver: 'Juan Dela Cruz',
    };
    state.bus = busA;

    assert.equal(typeof windowMock.openQRModal, 'function', 'openQRModal is exposed on window');
    await windowMock.openQRModal(busA.bus_id);
    await settle(350);

    const previewPayload = 'https://rconnect.example/passenger/scan.php?bus=BUS-001%3C%26%3E&trip=77';
    const previewRender = state.qrRenders.find(r => r.width === 150);
    assert.ok(previewRender, 'preview QR is rendered at 150px');
    assert.equal(previewRender.text, previewPayload, 'preview encodes the bus payload');
    assert.ok(state.fetchCalls.some(u => u.includes('save_bus_qr.php')), 'preview QR is still persisted through save_bus_qr.php');

    elements.get('qrDownloadBtn').onclick();
    assert.ok(windowMock.__qrDlData && windowMock.__qrDlData.payloadUrl === previewPayload, 'download stores the exact preview payload string');

    /* ---------------------------- PNG export ---------------------------- */

    const downloadsBefore = state.downloads.length;
    pickFormat('png');
    confirmDownload();
    await settle(700);

    const exportRenders = state.qrRenders.filter(r => r.width === 1024);
    assert.equal(exportRenders.length, 1, 'PNG export renders one high-resolution QR');
    assert.equal(exportRenders[0].text, previewPayload, 'export QR encodes the exact preview payload');
    assert.equal(exportRenders[0].height, 1024, 'export QR is square at 1024px');

    const poster = state.posters[state.posters.length - 1];
    assert.ok(poster, 'poster render was invoked');
    assert.equal((poster.html.match(/class="qr-target"/g) || []).length, 1, 'poster contains exactly one QR image');
    assert.ok(!poster.html.includes('web-qr'), 'poster contains no secondary website QR');
    assert.ok(!poster.html.includes('<i class="bi'), 'poster uses inline SVG icons, not the icon font');
    assert.ok(poster.html.includes('BUS-001&lt;&amp;&gt;'), 'poster shows the HTML-escaped bus ID');
    assert.ok(!poster.html.includes('BUS-001<'), 'poster never interpolates raw bus markup');
    assert.equal(poster.options.scale, 3, 'poster is captured at scale 3');

    assert.equal(state.downloads.length, downloadsBefore + 1, 'PNG export downloads one file');
    assert.equal(state.downloads[state.downloads.length - 1].download, 'RConnect_BUS-001-_QR_Poster.png', 'PNG uses the designed poster filename');

    /* ---------------------------- PDF export ---------------------------- */

    pickFormat('pdf');
    confirmDownload();
    await settle(700);

    const pdf = state.pdfs[state.pdfs.length - 1];
    assert.ok(pdf, 'PDF export produced a document');
    assert.deepEqual(
        [pdf.options.format[0], pdf.options.format[1]],
        [250, 350],
        'PDF keeps the 250x350mm poster page'
    );
    assert.equal(pdf.images.length, 1, 'PDF embeds one poster image');
    assert.deepEqual(
        [pdf.images[0].x, pdf.images[0].y, pdf.images[0].w, pdf.images[0].h],
        [0, 0, 250, 350],
        'PDF embeds the poster full-page without stretching'
    );
    assert.equal(pdf.name, 'RConnect_BUS-001-_QR_Poster.pdf', 'PDF uses the designed poster filename');
    const pdfRender = state.qrRenders.filter(r => r.width === 1024).pop();
    assert.equal(pdfRender.text, previewPayload, 'PDF export QR also encodes the exact preview payload');

    /* ------------------------------ print flow ------------------------------ */

    elements.get('qrPrintBtn').onclick();
    await settle(400);
    const printed = state.printWindows[state.printWindows.length - 1];
    assert.ok(printed, 'print flow opens a window');
    assert.equal((printed.html.match(/class="qr-target"/g) || []).length, 1, 'print output contains exactly one QR');
    assert.ok(!printed.html.includes('web-qr'), 'print output contains no secondary website QR');
    assert.ok(printed.html.includes('data:image/png;base64,MOCK'), 'print output embeds the export-resolution QR');
    assert.ok(printed.printed, 'print is triggered after the layout is ready');

    /* ------------------- sequential exports of two buses ------------------- */

    const busB = {
        bus_id: 'BUS-002',
        plate_number: 'NCR 5678',
        seats: 45,
        standing_capacity: 8,
        current_trip_id: null,
        driver: null,
    };
    state.bus = busB;
    await windowMock.openQRModal(busB.bus_id);
    await settle(350);

    const busBPayload = 'https://rconnect.example/passenger/scan.php?bus=BUS-002&trip=0';
    elements.get('qrDownloadBtn').onclick();
    pickFormat('png');
    confirmDownload();
    await settle(700);

    const busBRender = state.qrRenders.filter(r => r.width === 1024).pop();
    assert.equal(busBRender.text, busBPayload, 'second bus export encodes the second bus payload');
    const busBPoster = state.posters[state.posters.length - 1];
    assert.ok(busBPoster.html.includes('BUS-002'), 'second poster shows the second bus ID');
    assert.ok(!busBPoster.html.includes('BUS-001'), 'second poster never shows the first bus data');

    /* --------------------- stale data is cleared on hide --------------------- */

    windowMock.__qrDlData = { payloadUrl: previewPayload, bus: busA };
    elements.get('qrModal').dispatch('hidden.bs.modal');
    assert.equal(windowMock.__qrDlData, null, 'hiding the QR modal clears the export data');

    /* ----------------------- render failure is visible ----------------------- */

    elements.get('qrDownloadBtn').onclick();
    const downloadsBeforeFailure = state.downloads.length;
    state.html2canvasMode = 'fail';
    pickFormat('png');
    confirmDownload();
    await settle(700);
    state.html2canvasMode = 'ok';

    assert.equal(state.downloads.length, downloadsBeforeFailure, 'failed poster render downloads nothing');
    assert.ok(state.alerts.some(m => /could not be generated/.test(m)), 'failed poster render alerts the admin');
    assert.ok(state.downloads.every(d => !/^QR-/.test(String(d.download))), 'no plain-QR fallback download ever happens');

    /* -------------------- missing export data is reported -------------------- */

    windowMock.__qrDlData = null;
    const downloadsBeforeMissing = state.downloads.length;
    pickFormat('png');
    confirmDownload();
    await settle(50);
    assert.equal(state.downloads.length, downloadsBeforeMissing, 'missing export data downloads nothing');
    assert.ok(state.alerts.some(m => /no longer available/.test(m)), 'missing export data alerts the admin');

    console.log('QR Access Panel export checks passed: preview/save unchanged, export QR uses the exact payload at 1024px, one QR per poster with escaped bus details, PNG + full-page PDF posters, stale data cleared, failures reported without plain-QR fallback.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
