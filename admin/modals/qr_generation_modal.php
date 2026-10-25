<style>
    #qrModal { font-family: 'Poppins', sans-serif; }
    #qrModal .qr-modal-surface { border-radius: 18px; border: 1px solid #d2e4a8; background: #faf9e5; overflow: hidden; box-shadow: 0 8px 30px rgba(46, 72, 54, 0.12); }
    #qrModal .qr-header { display: flex; align-items: center; gap: 12px; padding: 14px 20px; background: #eceed9; border-bottom: 1px solid #d2e4a8; }
    #qrModal .qr-header-icon { color: #2c7a4a; font-size: 1.2rem; line-height: 1; }
    #qrModal .qr-header-title { color: #295738; font-weight: 600; font-size: 0.95rem; line-height: 1.2; }
    #qrModal .qr-header-sub { color: #7b8a5e; font-size: 0.7rem; margin-top: 1px; }
    #qrModal .qr-header-close { margin-left: auto; opacity: 0.45; }
    #qrModal .qr-body { padding: 20px; }
    #qrModal .qr-grid { display: grid; grid-template-columns: 1fr 1.1fr; gap: 18px; align-items: stretch; }
    @media(max-width:768px) { #qrModal .qr-grid { grid-template-columns: 1fr; } }
    #qrModal .qr-card { background: #fff; border: 1px solid #dce8c8; border-radius: 14px; padding: 16px; }
    #qrModal .qr-card-head { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; font-weight: 600; color: #295738; letter-spacing: 0.4px; text-transform: uppercase; margin-bottom: 4px; }
    #qrModal .qr-card-head i { color: #2e7a4a; font-size: 0.95rem; }
    #qrModal .qr-card-sub { font-size: 0.68rem; color: #8a9e7a; margin-bottom: 12px; }
    #qrModal .qr-row { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid #eef2e0; }
    #qrModal .qr-row:last-child { border-bottom: none; }
    #qrModal .qr-row-icon { color: #4a7a5a; font-size: 0.95rem; width: 18px; text-align: center; flex: 0 0 auto; }
    #qrModal .qr-label { font-size: 0.6rem; font-weight: 600; color: #8a9e7a; text-transform: uppercase; letter-spacing: 0.4px; }
    #qrModal .qr-value { font-size: 0.88rem; font-weight: 600; color: #223028; }
    #qrModal .qr-badge { display: inline-flex; align-items: center; font-size: 0.68rem; font-weight: 600; padding: 3px 10px; border-radius: 999px; background: #e8f4ea; border: 1px solid #bfe0c2; color: #2c5234; }
    #qrModal .qr-badge-amber { background: #fdf0d0; border-color: #f0d9a6; color: #a86b09; }
    #qrModal .qr-note { display: flex; gap: 8px; margin-top: 14px; background: #eef6df; border: 1px solid #dce9c2; border-radius: 10px; padding: 10px 12px; font-size: 0.65rem; color: #5f7250; line-height: 1.55; }
    #qrModal .qr-note i { color: #2e7a4a; margin-top: 1px; font-size: 0.85rem; }
    #qrModal .qr-right { display: flex; flex-direction: column; align-items: center; text-align: center; }
    #qrModal .qr-box { width: 200px; height: 200px; background: #fff; border: 1px solid #dde7c8; border-radius: 12px; display: flex; align-items: center; justify-content: center; padding: 10px; margin: 6px auto 14px; }
    #qrModal .qr-box canvas { width: 150px !important; height: 150px !important; display: block; }
    #qrModal .qr-spinner-text { color: #8a9e7a; font-size: 0.72rem; margin-top: 6px; }
    #qrModal .qr-error { color: #b85454; font-size: 0.72rem; text-align: center; padding: 8px; }
    #qrModal .qr-caption { font-size: 0.85rem; font-weight: 700; color: #295738; }
    #qrModal .qr-pill { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; padding: 4px 12px; border-radius: 999px; background: #e8f4e8; border: 1px solid #bfe0c2; color: #295738; font-size: 0.68rem; font-weight: 600; }
    #qrModal .qr-pill-dot { width: 7px; height: 7px; border-radius: 50%; background: #2e9e4f; }
    #qrModal .qr-actions { display: flex; gap: 10px; width: 100%; margin-top: 16px; }
    #qrModal .qr-btn,
    #qrDownloadModal .qr-btn { height: 42px; flex: 1; border-radius: 10px; font-size: 0.76rem; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer; transition: background 0.15s ease, color 0.15s ease; }
    #qrModal .qr-btn-primary,
    #qrDownloadModal .qr-btn-primary { background: #295738; border: 1px solid #295738; color: #fff; }
    #qrModal .qr-btn-primary:hover,
    #qrDownloadModal .qr-btn-primary:hover { background: #1f4630; border-color: #1f4630; }
    #qrModal .qr-btn-secondary,
    #qrDownloadModal .qr-btn-secondary { background: #fff; border: 1px solid #295738; color: #295738; }
    #qrModal .qr-btn-secondary:hover,
    #qrDownloadModal .qr-btn-secondary:hover { background: #f4f6f0; }
</style>

<style>
    #qrDownloadModal { font-family: 'Poppins', sans-serif; }
    #qrDownloadModal .qr-dl-surface { border-radius: 16px; border: 1px solid #d2e4a8; background: #faf9e5; overflow: hidden; box-shadow: 0 8px 30px rgba(46, 72, 54, 0.12); }
    #qrDownloadModal .qr-dl-header { position: relative; padding: 16px 22px 10px; background: #eceed9; border-bottom: 1px solid #d2e4a8; }
    #qrDownloadModal .qr-dl-title { font-size: 1rem; font-weight: 600; color: #295738; }
    #qrDownloadModal .qr-dl-sub { font-size: 0.72rem; color: #7b8a5e; margin-top: 2px; }
    #qrDownloadModal .qr-dl-close { position: absolute; top: 14px; right: 14px; opacity: 0.45; }
    #qrDownloadModal .qr-dl-body { padding: 16px 20px; }
    #qrDownloadModal .qr-dl-options { display: flex; flex-direction: column; gap: 12px; }
    #qrDownloadModal .qr-dl-card { display: flex; align-items: flex-start; gap: 12px; padding: 14px; border: 1px solid #dce8c8; border-radius: 12px; background: #fff; cursor: pointer; transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease; }
    #qrDownloadModal .qr-dl-card:hover { border-color: #a9c47e; background: #f5f8ec; }
    #qrDownloadModal .qr-dl-card.is-selected { border-color: #295738; background: #eaf2dd; box-shadow: inset 0 0 0 1px #295738; }
    #qrDownloadModal .qr-dl-icon { font-size: 1.4rem; color: #2e7a4a; line-height: 1.3; }
    #qrDownloadModal .qr-dl-name { font-size: 0.95rem; font-weight: 700; color: #295738; }
    #qrDownloadModal .qr-dl-type { font-size: 0.7rem; font-weight: 600; color: #7b8a5e; text-transform: uppercase; letter-spacing: 0.5px; }
    #qrDownloadModal .qr-dl-desc { font-size: 0.72rem; color: #5f7250; margin-top: 6px; line-height: 1.5; }
    #qrDownloadModal .qr-dl-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 12px 20px 16px; border-top: 1px solid #d2e4a8; }
    #qrDownloadModal .qr-dl-confirm:disabled { opacity: 0.55; cursor: not-allowed; }
</style>

<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content qr-modal-surface">

            <div class="qr-header">
                <i class="bi bi-qr-code-scan qr-header-icon"></i>
                <div>
                    <div class="qr-header-title">QR Access Panel</div>
                    <div class="qr-header-sub">Manage passenger boarding QR and bus details.</div>
                </div>
                <button type="button" class="btn-close qr-header-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body qr-body">
                <div class="qr-grid">

                    <div class="qr-card">
                        <div class="qr-card-head"><i class="bi bi-bus-front"></i> Bus Information</div>

                        <div class="qr-row">
                            <i class="bi bi-hash qr-row-icon"></i>
                            <div style="min-width:0;">
                                <div class="qr-label">Bus ID</div>
                                <div class="qr-value" id="busIdText">—</div>
                            </div>
                        </div>

                        <div class="qr-row">
                            <i class="bi bi-card-text qr-row-icon"></i>
                            <div style="min-width:0;">
                                <div class="qr-label">Plate Number</div>
                                <div class="qr-value" id="plateText">—</div>
                            </div>
                        </div>

                        <div class="qr-row">
                            <i class="bi bi-people-fill qr-row-icon"></i>
                            <div style="min-width:0;">
                                <div class="qr-label">Capacity</div>
                                <div class="qr-value" id="capacityText">—</div>
                            </div>
                        </div>

                        <div class="qr-row">
                            <i class="bi bi-person-badge qr-row-icon"></i>
                            <div style="min-width:0;">
                                <div class="qr-label">Driver</div>
                                <div class="qr-value" id="driverText">—</div>
                            </div>
                        </div>

                        <div class="qr-row">
                            <i class="bi bi-arrow-repeat qr-row-icon"></i>
                            <div style="min-width:0;">
                                <div class="qr-label">Trip Status</div>
                                <div style="margin-top:2px;">
                                    <span class="qr-badge" id="tripStatusBadge"><span id="tripStatusText">—</span></span>
                                </div>
                            </div>
                        </div>

                        <div class="qr-note">
                            <i class="bi bi-info-circle"></i>
                            <span>Passengers scan this QR using the R-Connect app when boarding. The system automatically links the scan to the bus and active trip.</span>
                        </div>
                    </div>

                    <div class="qr-card qr-right">
                        <div class="qr-card-head"><i class="bi bi-qr-code-scan"></i> Scan to Board</div>
                        <div class="qr-card-sub">Passengers scan this QR to log their trip.</div>

                        <div id="qrBox" class="qr-box">
                            <div style="text-align:center;">
                                <div class="spinner-border spinner-border-sm" style="color:#4a7a5a;"></div>
                                <div class="qr-spinner-text">Loading QR...</div>
                            </div>
                        </div>

                        <div class="qr-caption" id="qrCaption">Bus — • —</div>

                        <div class="qr-pill"><span class="qr-pill-dot"></span> QR Active</div>

                        <div class="qr-actions">
                            <button type="button" class="qr-btn qr-btn-primary" id="qrDownloadBtn">
                                <i class="bi bi-download"></i> Download QR
                            </button>
                            <button type="button" class="qr-btn qr-btn-secondary" id="qrPrintBtn">
                                <i class="bi bi-printer"></i> Print QR
                            </button>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="qrDownloadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content qr-dl-surface">

            <div class="qr-dl-header">
                <div class="qr-dl-title">Download QR Poster</div>
                <div class="qr-dl-sub">Choose your preferred file format.</div>
                <button type="button" class="btn-close qr-dl-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body qr-dl-body">
                <div class="qr-dl-options">
                    <div class="qr-dl-card" data-dl-format="png" role="button" tabindex="0">
                        <i class="bi bi-image qr-dl-icon"></i>
                        <div style="min-width:0;">
                            <div class="qr-dl-name">PNG</div>
                            <div class="qr-dl-type">Image</div>
                            <div class="qr-dl-desc">High-quality image suitable for sharing and printing.</div>
                        </div>
                    </div>
                    <div class="qr-dl-card" data-dl-format="pdf" role="button" tabindex="0">
                        <i class="bi bi-file-earmark-pdf qr-dl-icon"></i>
                        <div style="min-width:0;">
                            <div class="qr-dl-name">PDF</div>
                            <div class="qr-dl-type">Document</div>
                            <div class="qr-dl-desc">Print-ready poster optimized for physical display.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="qr-dl-footer">
                <button type="button" class="qr-btn qr-btn-secondary qr-dl-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="qr-btn qr-btn-primary qr-dl-confirm" id="qrDlConfirmBtn" disabled>
                    <i class="bi bi-download"></i> Download
                </button>
            </div>

        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="/admin/assets/js/qr-poster.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {

        const poster = window.RConnectQrPoster;

        function requirePoster() {
            if (poster) return true;
            window.alert('The QR poster module failed to load. Please refresh the page and try again.');
            return false;
        }

        const fmtModal = document.getElementById('qrDownloadModal');
        const fmtCards = fmtModal?fmtModal.querySelectorAll('.qr-dl-card'):[];
        const fmtConfirm = document.getElementById('qrDlConfirmBtn');
        let pickedFormat = null;
        function setPicked(f) {
            pickedFormat = f;
            fmtCards.forEach(c =>c.classList.toggle('is-selected', c.dataset.dlFormat === f));
            if (fmtConfirm) fmtConfirm.disabled = !f;
        }
        fmtCards.forEach(c => {
            c.addEventListener('click', () =>setPicked(c.dataset.dlFormat));
            c.addEventListener('keydown', e => { if (e.key === 'Enter'||e.key === ' ') { e.preventDefault(); setPicked(c.dataset.dlFormat); } });
        });
        if (fmtConfirm) fmtConfirm.addEventListener('click', () => {
            const p = window.__qrDlData;
            if (!pickedFormat) return;
            if (!requirePoster()) return;
            if (!p || !p.payloadUrl || !p.bus) {
                poster.showExportError('The QR information is no longer available. Please reopen the bus QR panel and try again.');
                return;
            }
            const chosen = pickedFormat;
            const m = bootstrap.Modal.getInstance(fmtModal);
            if (m) m.hide();
            setPicked(null);
            const posterQr = poster.makeExportQrDataUrl(p.payloadUrl);
            if (!posterQr) {
                poster.showExportError('The QR code could not be generated. Please try again.');
                return;
            }
            if (chosen === 'png') poster.exportPosterPNG(posterQr, p.bus);
            else if (chosen === 'pdf') poster.exportPosterPDF(posterQr, p.bus);
        });
        if (fmtModal) fmtModal.addEventListener('hidden.bs.modal', () => { setPicked(null); window.__qrDlData = null; });

        const qrModalEl = document.getElementById('qrModal');
        if (qrModalEl) qrModalEl.addEventListener('hidden.bs.modal', () => { window.__qrDlData = null; });

        function sv(b, i, t, c) {
            fetch("/admin/ajax/save_bus_qr.php", { method:"POST", headers: { "Content-Type":"application/json" }, body:JSON.stringify({ bus_id:b, trip_id:i, qr_code:`QR-${b}-${i||'NOTRIP'}.png`, qr_data:t, image:c.toDataURL("image/png") }) })
            .then(r =>r.json()).then(d => { if (d&&!d.success)console.error(d.message) }).catch (e =>console.error(e));
        }

        window.openQRModal = async function (busId) {
            const el = document.getElementById('qrModal'), qb = document.getElementById('qrBox');
            if (!el||!qb) return;
            const tripBadge = document.getElementById('tripStatusBadge');
            const tripText = document.getElementById('tripStatusText');
            new bootstrap.Modal(el).show();
            window.__qrDlData = null;

            tripBadge.classList.remove('qr-badge-amber');
            tripText.textContent = '—';
            qb.innerHTML = '<div style="text-align:center;"><div class="spinner-border spinner-border-sm" style="color:#4a7a5a;"></div><div class="qr-spinner-text">Loading QR...</div></div>';

            try {
                const r = await fetch(`/admin/ajax/getBusDetails.php?bus_id=${busId}`);
                const d = await r.json();
                if (!d.success) { qb.innerHTML = `<div class="qr-error">${d.message||'Failed'}</div>`; return; }
                const bus = d.data;

                document.getElementById('busIdText').textContent = bus.bus_id;
                document.getElementById('plateText').textContent = bus.plate_number;
                document.getElementById('capacityText').textContent = `${bus.seats} Seats • ${bus.standing_capacity} Standing`;
                document.getElementById('driverText').textContent = bus.driver || 'Unassigned';
                document.getElementById('qrCaption').textContent = `Bus ${bus.bus_id} • ${bus.plate_number}`;

                const tid = bus.current_trip_id;
                if (tid) {
                    let info = `Trip #${tid}`;
                    if (bus.trip_route_name) info+= ` • ${bus.trip_route_name}`;
                    if (bus.trip_status) info+= ` • ${bus.trip_status}`;
                    tripText.textContent = info;
                } else {
                    tripText.textContent = 'No Active Trip';
                    tripBadge.classList.add('qr-badge-amber');
                }

                const url = `${window.location.origin}/passenger/scan.php?bus=${encodeURIComponent(bus.bus_id)}&trip=${encodeURIComponent(tid||0)}`;

                qb.innerHTML = '';
                new QRCode(qb, { text:url, width:150, height:150, colorDark:"#295738", colorLight:"#ffffff", correctLevel:QRCode.CorrectLevel.H });
                setTimeout(() => { const c = qb.querySelector('canvas'); if (c) sv(bus.bus_id, tid, url, c);}, 300);

                const db = document.getElementById('qrDownloadBtn');
                if (db) db.onclick = () => {
                    const c = qb.querySelector('canvas');
                    if (!c) return;
                    window.__qrDlData = { payloadUrl: url, bus };
                    const fm = document.getElementById('qrDownloadModal');
                    if (fm) new bootstrap.Modal(fm).show();
                };

                const pb = document.getElementById('qrPrintBtn');
                if (pb) pb.onclick = () => {
                    const c = qb.querySelector('canvas');
                    if (!c) return;
                    if (!requirePoster()) return;
                    const posterQr = poster.makeExportQrDataUrl(url);
                    if (!posterQr) { poster.showExportError('The QR code could not be generated. Please try again.'); return; }
                    poster.printPoster(posterQr, bus);
                };

            } catch (e) {
                qb.innerHTML = '<div class="qr-error">Failed to load data</div>';
                console.error(e);
            }
        };

    });
</script>
