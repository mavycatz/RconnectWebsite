async function openQRModal(busId, seatsCount, standingCapacity, btnEl) {
    console.log('openQRModal fired for', busId);

    const modalEl = document.getElementById('qrModal');
    if (!modalEl) return console.error('QR modal element not found');

    const modal = new bootstrap.Modal(modalEl);
    const container = document.getElementById('qrSeatsContainer');
    const summary = document.getElementById('qrSummary');

    container.innerHTML = '<p>Loading seats...</p>';
    summary.textContent = '';

    let permanentQR = null;

    try {
        const qrRes = await fetch(
        `/admin/api/generate_bus_qr.php?bus_id=${encodeURIComponent(busId)}`
        );
        const qrData = await qrRes.json();

        if (!qrData.success) {
            alert('Failed to generate QR: ' + qrData.message);
        } else {
            permanentQR = qrData.qr_data;

            if (btnEl) {
                btnEl.innerHTML = '<i class="bi bi-check-circle"></i> QR Generated';
                btnEl.disabled = true;
            }
        }
    } catch (err) {
        console.error('Permanent QR generation failed:', err);
    }

    container.innerHTML = '';

    for (let i = 1; i <= seatsCount; i++) {
        const card = document.createElement('div');
        card.className = 'qr-seat-card';
        card.style.cssText = `
            width:140px;
            padding:8px;
            margin:6px;
            display:inline-block;
            text-align:center;
            border-radius:12px;
            border:1px solid #b8e405;
            background:#faf9e5;
            vertical-align:top;
        `;

        const qrText = `https://rconnecttms.online/passenger/log?passenger_id=${encodeURIComponent(passengerId)}&trip_id=${encodeURIComponent(tripId)}`;

        const qrDiv = document.createElement('div');
        new QRCode(qrDiv, { text: qrText, width: 110, height: 110 });
        card.appendChild(qrDiv);

        const label = document.createElement('p');
        label.style.cssText =
        'margin-top:6px;font-weight:600;font-size:0.85rem;color:#2f4f2f;';
        label.innerHTML = `Seat ${i}`;
        card.appendChild(label);

        const actions = document.createElement('div');
        actions.style.cssText =
        'display:flex;justify-content:center;gap:6px;margin-top:6px;';

        const printBtn = document.createElement('button');
        printBtn.innerHTML = '🖨 Print';
        printBtn.style.cssText = actionBtn('#a3d9a5', '#2f4f2f');
        printBtn.onclick = e => {
            e.stopPropagation();
            printSingleQR(qrDiv.outerHTML, busId, i);
        };

        const downloadBtn = document.createElement('button');
        downloadBtn.innerHTML = '⬇ Download';
        downloadBtn.style.cssText = actionBtn('#d1e7ff', '#084298');
        downloadBtn.onclick = e => {
            e.stopPropagation();
            downloadQR(qrDiv, busId, i);
        };

        actions.appendChild(printBtn);
        actions.appendChild(downloadBtn);
        card.appendChild(actions);

        container.appendChild(card);
    }

    const standingCard = document.createElement('div');
    standingCard.style.cssText = `
        width:140px;
        padding:8px;
        margin:6px;
        display:inline-block;
        text-align:center;
        border-radius:12px;
        border:1px solid #2f4f2f;
        background:#dff4d6;
        vertical-align:top;
    `;

    const standingQR = document.createElement('div');
    new QRCode(standingQR, {
        text: `https://rconnecttms.online/passenger/log?bus_id=${encodeURIComponent(busId)}&seat=Standing`,
        width: 110,
        height: 110
    });
    standingCard.appendChild(standingQR);

    const standingLabel = document.createElement('p');
    standingLabel.style.cssText =
    'margin-top:6px;font-weight:600;font-size:0.85rem;color:#2f4f2f;';
    standingLabel.innerHTML = `Standing (${standingCapacity})`;
    standingCard.appendChild(standingLabel);

    const standingActions = document.createElement('div');
    standingActions.style.cssText =
    'display:flex;justify-content:center;margin-top:6px;';

    const standingDownload = document.createElement('button');
    standingDownload.innerHTML = '⬇ Download';
    standingDownload.style.cssText = actionBtn('#d1e7ff', '#084298');
    standingDownload.onclick = e => {
        e.stopPropagation();
        downloadQR(standingQR, busId, 'Standing');
    };

    standingActions.appendChild(standingDownload);
    standingCard.appendChild(standingActions);

    container.appendChild(standingCard);

    summary.textContent = `Total Seats: ${seatsCount} | Standing Capacity: ${standingCapacity}`;
    modal.show();


    function actionBtn(bg, color) {
        return `
            padding:6px 10px;
            font-size:0.75rem;
            border:none;
            border-radius:6px;
            background:${bg};
            color:${color};
            cursor:pointer;
            box-shadow:0 1px 3px rgba(0,0,0,.25);
        `;
    }

    function downloadQR(qrDiv, busId, seat) {
        const canvas = qrDiv.querySelector('canvas');
        if (!canvas) return alert('QR not ready');

        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        link.download = `bus_${busId}_seat_${seat}.png`;
        link.click();
    }

    function printSingleQR(qrHtml, busId, seatNumber) {
        const win = window.open('', '', 'width=420,height=600');

        win.document.write(`
<!DOCTYPE html>
<html>
<head>
<title>Scan to Log Boarding</title>
<style>
@page { size: A6; margin: 10mm; }
body { font-family: Arial, sans-serif; text-align:center; }
.ticket {
    border:2px solid #000;
    border-radius:12px;
    padding:16px;
}
.headline { font-size:18px;font-weight:bold;margin-bottom:4px; }
.sub { font-size:13px;margin-bottom:12px; }
canvas { width:180px!important;height:180px!important; }
.details { margin-top:12px;font-size:12px; }
.footer { margin-top:10px;font-size:11px;color:#555; }
</style>
</head>
<body>
<div class="ticket">
    <div class="headline">PLEASE SCAN THIS CODE</div>
    <div class="sub">to log passenger boarding</div>
    ${qrHtml}
    <div class="details">
        Bus ID: <strong>${busId}</strong><br>
        Seat: <strong>${seatNumber}</strong>
    </div>
    <div class="footer">Valid for this trip only</div>
</div>
</body>
</html>
        `);

        win.document.close();
        win.focus();
        win.print();
        win.close();
    }
}
