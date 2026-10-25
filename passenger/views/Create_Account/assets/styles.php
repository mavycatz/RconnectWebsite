<style>
.privacy-overlay {
    position:fixed;
    inset:0;
    z-index:999999;
    background:rgba(0,0,0,0.5);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:0;
    overflow:hidden;
    transition: opacity 0.4s ease, transform 0.4s ease;
}
.privacy-overlay.fade-out {
    opacity:0;
    transform:scale(0.95);
}
.privacy-overlay-inner {
    width:100%;
    max-width:100%;
    height:100vh;
    height:100dvh;
    max-height:100vh;
    max-height:100dvh;
    background:#fef8f3;
    border:none;
    border-radius:0;
    overflow:hidden;
    box-shadow:0 10px 40px rgba(0,0,0,0.12);
    display:flex;
    flex-direction:column;
}
.privacy-overlay-header {
    padding:12px clamp(18px,6vw,30px);
    background:#f5efe6;
    border-bottom:1px solid #cdd9b5;
    color:#24513c;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-shrink:0;
}
.privacy-overlay-header h3 {
    margin:0;
    font-size:15px;
    font-weight:600;
    font-family:'Poppins',sans-serif;
    line-height:1.3;
}
#privacyLangSelect {
    width:68px;
    flex-shrink:0;
    border-radius:8px;
    border:1px solid #c6d48c;
    font-size:11.5px;
    font-weight:600;
    padding:5px 10px;
    cursor:pointer;
    background:#fff;
    color:#47613b;
}
.privacy-overlay-body {
    flex:1 1 auto;
    min-height:0;
    overflow-y:auto;
    -webkit-overflow-scrolling:touch;
    overscroll-behavior:contain;
    background:#fff;
    padding:18px clamp(18px,6vw,30px) 24px;
    font-size:13.5px;
    line-height:1.65;
    color:#333;
}
.privacy-body-inner {
    width:100%;
    max-width:760px;
    margin:0 auto;
}
.privacy-overlay-body p {
    margin:0 0 12px;
}
.privacy-overlay-body strong {
    color:#1d3d2c;
}
.privacy-intro {
    display:flex;
    align-items:flex-start;
    gap:12px;
    background:#f5efe6;
    border:1px solid #e4dfd0;
    border-radius:14px;
    padding:14px 16px;
    margin:0 0 14px;
}
.privacy-intro-icon {
    flex-shrink:0;
    width:40px;
    height:40px;
    border-radius:12px;
    background:#fff;
    border:1px solid #c6d48c;
    color:#47613b;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:18px;
}
.privacy-intro p {
    margin:0;
    font-size:13px;
    line-height:1.55;
    color:#5a5146;
    font-weight:500;
}
.privacy-effective-date {
    display:block;
    color:#47613b;
    font-size:12.5px;
    font-weight:600;
    margin-bottom:12px;
}
.privacy-overlay-body h4 {
    font-size:14px;
    font-weight:600;
    color:#24513c;
    border-left:3px solid #47613b;
    padding-left:10px;
    margin:0 0 10px;
    line-height:1.4;
}
.privacy-section {
    border:1px solid #ebe6d6;
    border-radius:14px;
    background:#fdfcf8;
    padding:16px 18px;
    margin:0 0 16px;
}
.privacy-section > :last-child {
    margin-bottom:0;
}
.privacy-overlay-body ul {
    padding-left:22px;
    margin:0 0 12px;
}
.privacy-overlay-body li {
    margin-bottom:7px;
    padding-left:4px;
    line-height:1.55;
}
.privacy-overlay-footer {
    flex-shrink:0;
    background:#fdfbf4;
    border-top:1px solid #e3eabb;
    box-shadow:0 -8px 20px rgba(60,80,40,0.08);
    padding:14px clamp(18px,6vw,30px) 18px;
    display:flex;
    flex-direction:column;
    gap:14px;
}
.privacy-overlay-footer .privacy-agree-row {
    font-size:13.5px;
    color:#333;
    display:flex;
    align-items:flex-start;
    gap:12px;
    cursor:pointer;
    user-select:none;
    -webkit-user-select:none;
    line-height:1.5;
    font-weight:500;
}
.privacy-overlay-footer input[type="checkbox"] {
    width:20px;
    height:20px;
    accent-color:#47613b;
    flex-shrink:0;
    margin-top:1px;
    cursor:pointer;
}
.privacy-scroll-hint {
    font-size:12px;
    font-weight:600;
    color:#8a6d1f;
    background:#fff8e1;
    border:1px solid #f0d060;
    border-radius:10px;
    padding:8px 12px;
    line-height:1.4;
}
.privacy-accept-btn {
    width:100%;
    min-height:42px;
    border:none;
    border-radius:10px;
    background:linear-gradient(135deg,#47613b,#5d8550);
    color:#fff;
    font-weight:700;
    font-size:14.5px;
    font-family:'Poppins',sans-serif;
    letter-spacing:.2px;
    transition:background .2s ease, transform .05s ease, filter .2s ease;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    box-shadow:none;
}
.privacy-accept-btn:hover:not(:disabled) {
    filter:brightness(1.08);
}
.privacy-accept-btn:active:not(:disabled) {
    transform:scale(.99);
}
.privacy-accept-btn:disabled {
    background:#b9c6a5;
    opacity:1;
}
.discount-card {
    border:1.5px solid #c6d48c;
    border-radius:16px;
    padding:12px 8px;
    text-align:center;
    background:#fcf5dd;
    transition:.2s;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:6px;
}
.discount-card i {
    width:42px;
    height:42px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:12px;
    background:#fff;
    border:1.5px solid #c6d48c;
    color:#47613b;
    font-size:18px;
}
.discount-card span {
    font-size:12px;
    font-weight:600;
}
.discount-card.active {
    background:#4b794d;
    color:#fff;
    border-color:#4b794d;
}
.discount-card.active i {
    background:#4b794d;
    border-color:#fff;
    color:#fff;
}
.discount-btn {
    border:1px solid #ddd;
    border-radius:10px;
    padding:8px;
    font-size:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:5px;
    cursor:pointer;
    background:#fff;
    transition:0.2s;
}
.discount-btn.active {
    background:#4b794d;
    color:#fff;
    border-color:#4b794d;
}
@media (min-width: 768px) {
    .privacy-overlay { padding:28px; }
    .privacy-overlay-inner {
        width:min(880px,100%);
        max-width:min(880px,100%);
        height:min(86vh,740px);
        max-height:min(86vh,740px);
        border:1px solid #c6d48c;
        border-radius:18px;
        box-shadow:0 20px 60px rgba(0,0,0,0.18);
    }
    .privacy-overlay-header { padding:16px 24px; }
    .privacy-overlay-header h3 { font-size:17px; }
    #privacyLangSelect { width:78px; font-size:12.5px; padding:6px 12px; }
    .privacy-overlay-body { padding:24px 28px 28px; font-size:14px; line-height:1.7; }
    .privacy-overlay-body h4 { font-size:15px; }
    .privacy-intro { padding:16px 18px; }
    .privacy-intro p { font-size:13.5px; }
    .privacy-section { padding:18px 22px; margin-bottom:18px; }
    .privacy-overlay-footer { padding:16px 28px 20px; }
    .privacy-accept-btn { min-height:44px; font-size:15px; }
}

/* ==========================================================================
   ID Upload, Camera Viewport & OCR Review Styles (R-Connect Modern Theme)
   ========================================================================== */

#uploadID {
    border: 1px solid #d9e6cc !important;
    border-radius: 22px !important;
    padding: 20px !important;
    background: #ffffff !important;
    box-shadow: 0 4px 20px rgba(50, 77, 40, 0.05) !important;
    margin-bottom: 14px;
}

/* Header */
.id-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    gap: 10px;
}

.id-card-header-main {
    display: flex;
    align-items: center;
    gap: 12px;
}

.id-header-icon-circle {
    width: 44px;
    height: 44px;
    border-radius: 13px;
    background: #eef5e7;
    border: 1px solid #d3e2c3;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #38562d;
    font-size: 20px;
    flex-shrink: 0;
}

.id-header-title {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #24513c;
    line-height: 1.25;
    font-family: 'Poppins', sans-serif;
}

.id-header-subtitle {
    margin: 2px 0 0;
    font-size: 11.5px;
    color: #63755f;
    line-height: 1.35;
    font-family: 'Poppins', sans-serif;
}

.id-step-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    background: #f0f6e9;
    color: #2e6035;
    border: 1px solid #cfe0c2;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .3px;
    white-space: nowrap;
    font-family: 'Poppins', sans-serif;
}

/* Camera Stage & Viewport */
#idCameraStage {
    position: relative;
    background: #090e0a;
    border: 1.5px solid #bdd2a5;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.16);
}

#cameraPreview {
    width: 100%;
    height: 290px;
    min-height: 280px;
    object-fit: cover;
    display: block;
    background: #000000;
}

@media (min-width: 480px) {
    #cameraPreview {
        height: 320px;
    }
}

/* Fullscreen Button Integrated in Camera Stage */
.id-stage-fullscreen-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 6;
    background: rgba(18, 28, 18, 0.68);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #eaf2e3;
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 11px;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all .2s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
}

.id-stage-fullscreen-btn:hover {
    background: rgba(36, 81, 60, 0.92);
    color: #ffffff;
    border-color: #c6d48c;
    transform: translateY(-1px);
}

/* Guide Overlay & Framing */
.id-guide-overlay {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.id-guide-frame {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    border: 2px solid rgba(255, 255, 255, 0.92);
    border-radius: 16px;
    box-shadow: 0 0 0 9999px rgba(8, 14, 9, 0.48), 0 0 16px rgba(198, 212, 140, 0.35);
    transition: width .25s ease, height .25s ease;
    overflow: hidden;
}

.id-guide-frame.landscape {
    height: 74%;
    width: auto;
    max-width: 88%;
    aspect-ratio: 1.586 / 1;
}

.id-guide-frame.portrait {
    height: 80%;
    width: auto;
    max-width: 66%;
    aspect-ratio: 1 / 1.586;
}

/* Reinforced ID Frame Corners */
.id-guide-corner {
    position: absolute;
    width: 24px;
    height: 24px;
    border: 4px solid #b6cc80;
    border-radius: 6px;
    box-shadow: 0 0 10px rgba(182, 204, 128, 0.85);
    z-index: 2;
}

.id-guide-corner.tl { top: -2px; left: -2px; border-right: none; border-bottom: none; }
.id-guide-corner.tr { top: -2px; right: -2px; border-left: none; border-bottom: none; }
.id-guide-corner.bl { bottom: -2px; left: -2px; border-right: none; border-top: none; }
.id-guide-corner.br { bottom: -2px; right: -2px; border-left: none; border-top: none; }

/* Subtle Animated ID Card Scanner Beam */
.id-scan-line {
    position: absolute;
    left: 6px;
    right: 6px;
    height: 2px;
    background: linear-gradient(90deg, transparent, #c6d48c, #ffffff, #c6d48c, transparent);
    box-shadow: 0 0 12px 2px rgba(198, 212, 140, 0.7);
    border-radius: 999px;
    top: 10%;
    animation: idScanBeam 3.2s ease-in-out infinite alternate;
    pointer-events: none;
    opacity: 0.7;
}

@keyframes idScanBeam {
    0% { top: 12%; opacity: 0.3; }
    50% { opacity: 0.85; }
    100% { top: 88%; opacity: 0.3; }
}

/* Subtle Centered ID Card Watermark */
.id-card-watermark {
    position: absolute;
    inset: 12px;
    border: 1.5px dashed rgba(255, 255, 255, 0.22);
    border-radius: 12px;
    pointer-events: none;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 12px;
}

.id-card-watermark-row {
    height: 5px;
    background: rgba(255, 255, 255, 0.18);
    border-radius: 4px;
    margin-top: 5px;
    width: 55%;
}

.id-card-watermark-row.short {
    width: 35%;
}

/* Top Frame Label */
#step2 label {
    align-items:center;
    margin-bottom:8px;
    line-height:1.4;
}
#step2 input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]) {
    min-height:44px;
    padding:10px 12px !important;
    box-sizing:border-box;
}

.id-guide-label {
    position: absolute;
    left: 50%;
    top: 12px;
    transform: translateX(-50%);
    background: rgba(24, 42, 22, 0.92);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    color: #ffffff;
    font-size: 11px;
    font-weight: 600;
    padding: 5px 14px;
    border-radius: 999px;
    white-space: nowrap;
    max-width: 92%;
    overflow: hidden;
    text-overflow: ellipsis;
    font-family: 'Poppins', sans-serif;
    letter-spacing: .2px;
    border: 1px solid rgba(198, 212, 140, 0.35);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.25);
    z-index: 3;
}

/* Bottom Frame Orientation */
.id-guide-orientation {
    position: absolute;
    left: 50%;
    bottom: 12px;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    color: #e3ebd8;
    font-size: 10.5px;
    font-weight: 500;
    padding: 4px 12px;
    border-radius: 999px;
    white-space: nowrap;
    font-family: 'Poppins', sans-serif;
    letter-spacing: .2px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    z-index: 3;
}

/* Compact Guidance Row: Good lighting • No glare • Show all edges */
.id-compact-tips-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    margin-top: 14px;
    font-size: 11.5px;
    color: #3f5939;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    flex-wrap: wrap;
}

.id-compact-tips-row span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.id-compact-tips-row i {
    color: #47613b;
    font-size: 12.5px;
}

.id-tip-dot {
    color: #a3b894;
    font-weight: 900;
    font-size: 11px;
}

/* Instruction Text Below Camera */
.id-guide-help {
    font-size: 11.5px;
    color: #63755f;
    margin-top: 8px;
    text-align: center;
    font-weight: 500;
    line-height: 1.45;
    font-family: 'Poppins', sans-serif;
}

/* Photo Tips Subtle Link */
.id-tips-link {
    background: none;
    border: none;
    color: #47613b;
    font-size: 11px;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px;
    text-decoration: underline;
    text-underline-offset: 3px;
    transition: color .2s ease;
}

.id-tips-link:hover {
    color: #24513c;
}

.id-guide-tips {
    list-style: none;
    margin: 8px 0 0;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 7px;
    background: #fbfdf9;
    border: 1px solid #dce8cd;
    border-radius: 14px;
}

.id-guide-tips li {
    font-size: 11px;
    color: #38562d;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
}

.id-guide-tips li::before {
    content: '\2713';
    color: #4b794d;
    font-weight: 900;
    font-size: 11px;
}

.id-quality-hint {
    display: none;
    margin-top: 10px;
    padding: 9px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.45;
}

.id-quality-hint.warn { display: block; background: #fff8e1; border: 1px solid #eecb6f; color: #8a6116; }
.id-quality-hint.bad { display: block; background: #fdecea; border: 1px solid #f5c6c2; color: #a93226; }
.id-quality-hint.good { display: block; background: #eef7ee; border: 1px solid #cfe3cf; color: #2e6b34; }

.id-senior-guidance {
    display: none;
    margin-top: 12px;
    background: #f9fbf8;
    border: 1.5px solid #c6d48c;
    border-left: 4px solid #4b794d;
    border-radius: 14px;
    padding: 12px 14px;
}

.id-senior-title { font-size: 13px; font-weight: 700; color: #24513c; }
.id-senior-sub { font-size: 11px; color: #6c757d; margin-top: 4px; line-height: 1.45; }

/* Action Buttons Hierarchy */
.id-actions-container {
    margin-top: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* Dominant Primary Green Button */
#captureBtn {
    width: 100%;
    min-height: 48px;
    border-radius: 14px;
    border: none;
    background: linear-gradient(135deg, #3d5932, #4f7842);
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
    font-family: 'Poppins', sans-serif;
    box-shadow: 0 4px 14px rgba(61, 89, 50, 0.28);
    cursor: pointer;
    text-align: center;
    line-height: 48px;
    padding: 0 16px;
    transition: all .2s ease;
    touch-action: manipulation;
}

#captureBtn:hover {
    filter: brightness(1.06);
    box-shadow: 0 6px 18px rgba(61, 89, 50, 0.35);
}

#captureBtn:active {
    transform: scale(0.985);
}

/* Clean Secondary Outline Button: Upload from gallery */
#uploadGalleryBtn,
.id-btn-gallery {
    width: 100%;
    min-height: 42px;
    border-radius: 14px;
    border: 1.5px solid #c6d48c;
    background: #ffffff;
    color: #24513c;
    font-size: 13px;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: all .2s ease;
    touch-action: manipulation;
}

#uploadGalleryBtn:hover,
.id-btn-gallery:hover {
    background: #f4f8ef;
    border-color: #47613b;
}

#uploadGalleryBtn:active,
.id-btn-gallery:active {
    transform: scale(0.985);
}

/* Retake Button */
#retakeBtn,
.id-btn-secondary {
    width: 100%;
    min-height: 42px;
    border-radius: 14px;
    border: 1.5px solid #d5e4c6;
    background: #f7f9f5;
    color: #38562d;
    font-size: 13px;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: all .2s ease;
    touch-action: manipulation;
}

#retakeBtn:hover,
.id-btn-secondary:hover {
    background: #edf3e8;
}

/* Fullscreen Mode Overrides */
#cameraView.id-camera-full {
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: #0e0e0e;
    padding: 14px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

#cameraView.id-camera-full #idCameraStage { flex: 1 1 auto; min-height: 260px; border-radius: 18px; }
#cameraView.id-camera-full #cameraPreview { height: 100% !important; object-fit: cover; background: #000; }
#cameraView.id-camera-full .id-stage-fullscreen-btn {
    top: 20px;
    right: 20px;
    background: rgba(0, 0, 0, 0.75);
    border-color: rgba(255, 255, 255, 0.4);
}
#cameraView.id-camera-full .id-guide-help { color: #dfe7d4; }
#cameraView.id-camera-full .id-tips-link,
#cameraView.id-camera-full .id-guide-tips,
#cameraView.id-camera-full .id-compact-tips-row { display: none !important; }
#cameraView.id-camera-full .id-senior-guidance { background: rgba(255, 255, 255, .94); }
#cameraView.id-camera-full #captureBtn { min-height: 50px; font-size: 15px; }
body.id-camera-lock { overflow: hidden; }

/* ==========================================================================
   OCR Confirmation Panel (Review ID Details)
   ========================================================================== */

.ocr-confirm-panel {
    margin-top: 14px;
    background: #ffffff;
    border: 1.5px solid #c6d48c;
    border-radius: 20px;
    padding: 18px 16px;
    box-shadow: 0 6px 20px rgba(71, 97, 59, 0.08);
    box-sizing: border-box;
    width: 100%;
    overflow: hidden;
}

.ocr-status-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #eef7ee;
    border: 1px solid #cce5cc;
    border-radius: 14px;
    padding: 12px 14px;
    margin-bottom: 12px;
}

.ocr-status-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #4b794d;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
    box-shadow: 0 2px 6px rgba(75, 121, 77, 0.25);
}

.ocr-status-text {
    min-width: 0;
    flex: 1;
}

.ocr-confirm-title {
    font-size: 14px;
    font-weight: 700;
    color: #24513c;
    line-height: 1.3;
    display: flex;
    align-items: center;
    gap: 6px;
}

.ocr-status-desc {
    font-size: 11px;
    color: #556c52;
    margin-top: 2px;
    line-height: 1.4;
}

.ocr-confirm-note {
    font-size: 11.5px;
    color: #6c757d;
    margin-bottom: 12px;
    line-height: 1.45;
    padding: 8px 12px;
    border-radius: 10px;
    background: #f8faf6;
    border: 1px solid #e4ecdc;
}

.ocr-confirm-note:empty {
    display: none;
}

.ocr-confirm-note.warn {
    color: #8a6116;
    background: #fff8e1;
    border-color: #eecb6f;
}

.ocr-confirm-note.bad {
    color: #a93226;
    background: #fdecea;
    border-color: #f5c6c2;
}

.ocr-preview-strip {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #fcfbf6;
    border: 1px solid #e9edd8;
    border-radius: 14px;
    padding: 10px 14px;
    margin-bottom: 14px;
    box-sizing: border-box;
    width: 100%;
    overflow: hidden;
}

.ocr-preview-thumbs-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.ocr-preview-thumb {
    position: relative;
    width: 60px;
    height: 42px;
    border-radius: 8px;
    overflow: hidden;
    border: 1.5px solid #d5e2c5;
    background: #eef2e8;
    flex-shrink: 0;
}

.ocr-preview-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.ocr-thumb-tag {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(36, 81, 60, 0.88);
    color: #fff;
    font-size: 8.5px;
    font-weight: 700;
    text-align: center;
    padding: 1px 0;
    text-transform: uppercase;
    letter-spacing: .2px;
}

.ocr-preview-strip-info {
    flex: 1;
    min-width: 0;
}

.ocr-preview-strip-title {
    font-size: 12px;
    font-weight: 700;
    color: #24513c;
    font-family: 'Poppins', sans-serif;
    line-height: 1.3;
}

.ocr-preview-strip-sub {
    font-size: 11px;
    color: #6b7a68;
    font-family: 'Poppins', sans-serif;
    line-height: 1.35;
    margin-top: 2px;
}

.ocr-card-section {
    background: #fbfdf9;
    border: 1.5px solid #eef2e8;
    border-radius: 16px;
    padding: 14px 12px;
    margin-bottom: 12px;
}

.ocr-section-header {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 11px;
    font-weight: 700;
    color: #38562d;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 10px;
    padding-bottom: 6px;
    border-bottom: 1px solid #edf1ea;
}

.ocr-section-header i {
    font-size: 13px;
    color: #47613b;
}

.ocr-confirm-group {
    font-size: 10.5px;
    font-weight: 700;
    color: #6b8266;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-top: 12px;
    margin-bottom: 4px;
}

.ocr-confirm-row {
    margin-top: 10px;
}

.ocr-confirm-row:first-of-type {
    margin-top: 0;
}

.ocr-row-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.ocr-row-header label {
    margin: 0;
    font-size: 11px;
    font-weight: 700;
    color: #38562d;
    letter-spacing: .3px;
    text-transform: uppercase;
}

.ocr-detect-badge {
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    letter-spacing: .2px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}

.ocr-detect-badge.detected {
    background: #eef7ee;
    color: #2e6b34;
    border: 1px solid #cfe3cf;
}

.ocr-detect-badge.missing {
    background: #fff8e1;
    color: #8a6116;
    border: 1px solid #eecb6f;
}

.ocr-confirm-input {
    display: block;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    border: 1.5px solid #d5e4c6;
    border-radius: 12px;
    padding: 8px 12px;
    font-size: 13px;
    font-weight: 500;
    font-family: 'Poppins', sans-serif;
    color: #24513c;
    background: #ffffff;
    outline: none;
    box-sizing: border-box;
    height: 40px;
    transition: border-color .2s, box-shadow .2s;
}

.ocr-confirm-input:focus {
    border-color: #4b794d;
    box-shadow: 0 0 0 3px rgba(75, 121, 77, 0.15);
}

.ocr-confirm-input.is-missing {
    border-color: #e6ce78;
    background: #fffef8;
}

.ocr-confirm-input[type="date"] {
    -webkit-appearance: none;
    appearance: none;
    height: auto;
    min-height: 40px;
    padding: 7px 12px;
    font-size: 12.5px;
    line-height: 1.35;
    text-align: left;
}

.ocr-confirm-input[type="date"]::-webkit-date-and-time-value { text-align: left; margin: 0; }
.ocr-confirm-input[type="date"]::-webkit-calendar-picker-indicator { margin-left: auto; padding: 0; cursor: pointer; }

.ocr-confirm-hint {
    font-size: 11px;
    color: #6b7a68;
    line-height: 1.45;
    margin: 10px 0 14px;
    display: flex;
    gap: 6px;
    align-items: flex-start;
}

.ocr-confirm-hint i {
    color: #83966a;
    font-size: 13px;
    margin-top: 1px;
    flex-shrink: 0;
}

.ocr-confirm-actions {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    margin-top: 14px;
    width: 100%;
    box-sizing: border-box;
}

.ocr-btn {
    width: 100%;
    max-width: 260px;
    height: 38px;
    min-height: 38px;
    padding: 0 16px;
    box-sizing: border-box;
    border-radius: 12px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border: 1.5px solid transparent;
    font-family: 'Poppins', sans-serif;
    transition: all .2s ease;
    touch-action: manipulation;
    white-space: nowrap;
    text-align: center;
}

.ocr-btn i {
    font-size: 14px;
}

.ocr-btn-primary {
    background: linear-gradient(135deg, #47613b, #5d8550);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(71, 97, 59, 0.25);
}

.ocr-btn-primary:hover {
    filter: brightness(1.05);
}

.ocr-btn-primary:active {
    transform: scale(0.98);
}

.ocr-btn-outline {
    background: #ffffff;
    color: #24513c;
    border-color: #c6d48c;
}

.ocr-btn-outline:hover {
    background: #f4f8ef;
    border-color: #47613b;
}

.ocr-btn-outline:active {
    transform: scale(0.98);
}

/* Manual Entry Fallback Button & Divider */
.ocr-manual-divider {
    display: flex;
    align-items: center;
    text-align: center;
    margin: 16px 0 10px;
    color: #8c9e88;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    font-family: 'Poppins', sans-serif;
}

.ocr-manual-divider::before,
.ocr-manual-divider::after {
    content: '';
    flex: 1;
    border-bottom: 1px dashed #d5e2c5;
}

.ocr-manual-divider span {
    padding: 0 10px;
}

.ocr-manual-btn,
.ocr-manual-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    box-sizing: border-box;
    background: #fbfdf9;
    border: 1.5px dashed #c6d48c;
    border-radius: 14px;
    padding: 10px 14px;
    color: #38562d;
    font-size: 12px;
    font-weight: 600;
    font-family: 'Poppins', sans-serif;
    cursor: pointer;
    text-decoration: none;
    transition: all .2s ease;
    box-shadow: 0 1px 4px rgba(71, 97, 59, 0.04);
}

.ocr-manual-btn:hover,
.ocr-manual-link:hover {
    background: #f3f8ee;
    border-color: #47613b;
    border-style: solid;
    color: #1e3818;
    box-shadow: 0 3px 10px rgba(71, 97, 59, 0.1);
    transform: translateY(-1px);
}

.ocr-manual-btn:active,
.ocr-manual-link:active {
    transform: scale(0.985);
}

.ocr-manual-btn-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #eef4e6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #47613b;
    font-size: 12px;
    flex-shrink: 0;
}

.ocr-manual-btn-text {
    line-height: 1.35;
    text-align: center;
}

.ocr-manual-btn-text strong {
    color: #24513c;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.ocr-manual-btn-arrow {
    font-size: 16px;
    color: #5d705a;
    transition: transform .2s ease;
    flex-shrink: 0;
}

.ocr-manual-btn:hover .ocr-manual-btn-arrow {
    transform: translateX(2px);
    color: #24513c;
}

/* Theme-Consistent Back Navigation Buttons */
.account-top-back-btn {
    border: 1px solid #d5e4c6 !important;
    background: #ffffff !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    font-weight: 600 !important;
    font-size: 12.5px !important;
    font-family: 'Poppins', sans-serif !important;
    color: #24513c !important;
    padding: 6px 14px !important;
    border-radius: 999px !important;
    cursor: pointer !important;
    transition: all .2s ease !important;
    box-shadow: 0 1px 3px rgba(71, 97, 59, 0.06) !important;
    text-decoration: none !important;
    outline: none !important;
}

.account-top-back-btn:hover {
    background: #f4f8ef !important;
    border-color: #47613b !important;
    color: #1a3a29 !important;
    transform: translateX(-2px);
    box-shadow: 0 2px 6px rgba(71, 97, 59, 0.12) !important;
}

.account-top-back-btn:active {
    transform: scale(0.97) !important;
}

.account-top-back-btn i,
.account-top-back-btn span {
    color: #24513c !important;
    transition: color .2s ease;
}

.account-top-back-btn:hover i,
.account-top-back-btn:hover span {
    color: #1a3a29 !important;
}

.account-top-back-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    transition: transform .2s ease;
}

.account-top-back-btn:hover .account-top-back-icon {
    transform: translateX(-2px);
}

.btn-step-back {
    border: 1.5px solid #c6d48c !important;
    background: #ffffff !important;
    color: #24513c !important;
    font-weight: 600 !important;
    font-family: 'Poppins', sans-serif !important;
    border-radius: 14px !important;
    padding: 9px 14px !important;
    font-size: 13px !important;
    transition: all .2s ease !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    min-height: 40px !important;
}

.btn-step-back:hover {
    background: #f4f8ef !important;
    border-color: #47613b !important;
    color: #1a3a29 !important;
}

.btn-step-back:active {
    transform: scale(0.985) !important;
}

.btn-step-back i,
.btn-step-back span {
    color: #47613b !important;
}

.btn-step-back:hover i,
.btn-step-back:hover span {
    color: #1a3a29 !important;
}

/* Password Visibility Toggle */
.password-input-wrapper {
    position: relative;
    width: 100%;
}

.password-input-wrapper input {
    padding-right: 42px !important;
}

.password-toggle-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    padding: 6px 8px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #47613b;
    font-size: 16px;
    line-height: 1;
    border-radius: 8px;
    transition: color .2s, background-color .2s;
    outline: none;
    -webkit-tap-highlight-color: transparent;
    z-index: 2;
}

.password-toggle-btn:hover {
    color: #24513c;
    background-color: #f4f8ef;
}

.password-toggle-btn:active {
    transform: translateY(-50%) scale(0.95);
}

.password-toggle-btn:focus-visible {
    box-shadow: 0 0 0 2px rgba(71, 97, 59, 0.3);
}

@media (max-width: 520px) {
    .id-actions-bar {
        flex-direction: column;
    }
}

@media (max-width: 380px) {
    .ocr-preview-strip {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
}

/* Category selection cards */
.category-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    background: #ffffff;
    border: 1.5px solid #d5e4c6;
    border-radius: 12px;
    font-size: 12.5px;
    font-weight: 600;
    color: #24513c;
    font-family: 'Poppins', sans-serif;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
    width: 100%;
}
.category-btn:hover {
    border-color: #4b794d;
    background: #f7faf4;
}
.category-btn.active {
    background: #eef6ea;
    border-color: #4b794d;
    box-shadow: 0 0 0 2px rgba(75, 121, 77, 0.2);
    color: #1e3f1f;
}
.category-btn i {
    font-size: 16px;
    color: #47613b;
}

/* Senior ID choice options */
.senior-id-choice {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: #ffffff;
    border: 1.5px solid #d5e4c6;
    border-radius: 12px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #24513c;
    font-family: 'Poppins', sans-serif;
    transition: all 0.2s ease;
}
.senior-id-choice:hover {
    border-color: #4b794d;
    background: #fbfdf9;
}
.senior-id-choice.active {
    border-color: #4b794d;
    background: #eef6ea;
    box-shadow: 0 0 0 2px rgba(75, 121, 77, 0.15);
}
.senior-id-choice input[type="radio"] {
    accent-color: #4b794d;
    width: 16px;
    height: 16px;
}
</style>
