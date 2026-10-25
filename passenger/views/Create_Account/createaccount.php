

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="/assets/images/logo/logoR.png">
<title>Create Passenger Account</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php include __DIR__ . "/../layout/csrf.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="/public/login/custom-alert.css">
<?php require __DIR__ . "/assets/styles.php"; ?>
</head>

<body>

<div id="privacySection" class="privacy-overlay">
    <div class="privacy-overlay-inner">
    <div class="privacy-overlay-header">
        <h3 data-lang="privacy_title">Passenger Privacy Policy</h3>
        <select id="privacyLangSelect" class="form-select">
            <option value="en">EN</option>
            <option value="tl">TL</option>
            <option value="ilo">ILO</option>
        </select>
    </div>
    <div class="privacy-overlay-body">
        <div class="privacy-effective-date" data-lang="privacy_effective_date"><strong>Effective Date:</strong> July 8, 2026</div>
        <p data-lang="privacy_intro">R Connect Transportation Management System is committed to protecting the privacy and personal information of all passengers. We value your trust and are dedicated to ensuring that your personal data is collected, processed, and protected in accordance with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong> and the guidelines of the <strong>National Privacy Commission (NPC)</strong>.</p>

        <h4 data-lang="privacy_info_collect_heading">Information We Collect</h4>
        <p data-lang="privacy_info_collect_desc">To provide safe and efficient public transportation services, we may collect only the information necessary for your travel, including:</p>
        <ul>
            <li data-lang="privacy_collect_name">Full Name</li>
            <li data-lang="privacy_collect_type">Passenger Type (Regular, Student, Senior Citizen, Person with Disability (PWD), Cooperative Member, etc.)</li>
            <li data-lang="privacy_collect_ids">Student ID, Senior Citizen ID, PWD ID, or Cooperative ID Number (when applicable for fare discounts)</li>
            <li data-lang="privacy_collect_board">Boarding Point</li>
            <li data-lang="privacy_collect_dest">Destination</li>
            <li data-lang="privacy_collect_fare">Fare Information</li>
            <li data-lang="privacy_collect_date">Date and Time of Travel</li>
            <li data-lang="privacy_collect_qr">QR Code Transaction Information</li>
        </ul>
        <p data-lang="privacy_collect_note">Only information necessary for transportation services is collected.</p>

        <h4 data-lang="privacy_purpose_heading">Purpose of Collecting Your Information</h4>
        <p data-lang="privacy_purpose_desc">Your personal information is collected <strong>solely for transportation-related purposes</strong>, including:</p>
        <ul>
            <li data-lang="privacy_purpose_id">Passenger identification</li>
            <li data-lang="privacy_purpose_fare">Fare computation and discount verification</li>
            <li data-lang="privacy_purpose_trip">Recording passenger trips</li>
            <li data-lang="privacy_purpose_count">Passenger counting and occupancy monitoring</li>
            <li data-lang="privacy_purpose_report">Generating transportation and financial reports</li>
            <li data-lang="privacy_purpose_ops">Improving transportation operations</li>
            <li data-lang="privacy_purpose_emergency">Responding to emergencies or transportation-related incidents</li>
            <li data-lang="privacy_purpose_comply">Complying with applicable Philippine laws and transportation regulations</li>
        </ul>
        <p data-lang="privacy_purpose_note">Your information <strong>will not be used</strong> for advertising, marketing, commercial promotions, profiling, or any purpose unrelated to transportation services.</p>

        <h4 data-lang="privacy_protect_heading">Protection of Your Personal Data</h4>
        <p data-lang="privacy_protect_desc">R Connect implements appropriate administrative, technical, and organizational security measures to protect your personal information against:</p>
        <ul>
            <li data-lang="privacy_protect_access">Unauthorized access</li>
            <li data-lang="privacy_protect_disclose">Unauthorized disclosure</li>
            <li data-lang="privacy_protect_loss">Loss or theft</li>
            <li data-lang="privacy_protect_alter">Alteration</li>
            <li data-lang="privacy_protect_misuse">Misuse</li>
            <li data-lang="privacy_protect_destroy">Destruction</li>
        </ul>
        <p data-lang="privacy_protect_note">Only authorized personnel with legitimate transportation-related responsibilities are allowed access to passenger information.</p>

        <h4 data-lang="privacy_sharing_heading">Data Sharing</h4>
        <p data-lang="privacy_sharing_desc">Passenger information is kept confidential and <strong>will not be sold, rented, or shared with third parties</strong> except when:</p>
        <ul>
            <li data-lang="privacy_sharing_law">Required by Philippine law;</li>
            <li data-lang="privacy_sharing_order">Requested through a lawful order issued by a competent authority;</li>
            <li data-lang="privacy_sharing_safety">Necessary to protect public safety or investigate transportation-related incidents.</li>
        </ul>

        <h4 data-lang="privacy_retention_heading">Data Retention</h4>
        <p data-lang="privacy_retention_desc">Passenger information is retained only for as long as necessary to fulfill transportation, operational, legal, and auditing requirements. After the required retention period, records are securely archived or permanently disposed of in accordance with applicable laws and company policies.</p>

        <h4 data-lang="privacy_rights_heading">Your Rights</h4>
        <p data-lang="privacy_rights_desc">Under the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>, passengers have the right to:</p>
        <ul>
            <li data-lang="privacy_rights_informed">Be informed about how their personal information is collected and used.</li>
            <li data-lang="privacy_rights_access">Access their personal information.</li>
            <li data-lang="privacy_rights_correct">Request correction of inaccurate or incomplete information.</li>
            <li data-lang="privacy_rights_delete">Request deletion of personal information when permitted by law.</li>
            <li data-lang="privacy_rights_object">Object to unlawful processing of personal information.</li>
            <li data-lang="privacy_rights_complaint">File a complaint with the <strong>National Privacy Commission (NPC)</strong> if they believe their privacy rights have been violated.</li>
        </ul>

        <h4 data-lang="privacy_consent_heading">Passenger Consent</h4>
        <p data-lang="privacy_consent_desc">By registering as a passenger, scanning your QR code, or using the R Connect Transportation Management System, you acknowledge that you have read and understood this Privacy Policy and consent to the collection and processing of your personal information <strong>solely for transportation purposes</strong>.</p>

        <h4 data-lang="privacy_commit_heading">Commitment to Privacy</h4>
        <p data-lang="privacy_commit_desc">R Connect respects every passenger's right to privacy. We collect only the information necessary to provide safe, efficient, and reliable transportation services.</p>
        <p data-lang="privacy_commit_note">Passenger information shall be used exclusively for transportation-related operations and shall never be used for personal gain, marketing, advertising, commercial purposes, or any activity unrelated to the delivery and management of public transportation services.</p>
        <p data-lang="privacy_commit_compliance">All passenger information is handled confidentially and protected in accordance with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong> and the standards established by the <strong>National Privacy Commission (NPC)</strong> of the Philippines.</p>

    </div>
    <div class="privacy-overlay-footer">
        <div id="privacyScrollHint" class="privacy-scroll-hint" data-lang="privacy_scroll_hint">Scroll to the end of the policy to enable the agreement.</div>
        <label>
            <input type="checkbox" id="privacyCheckbox" disabled onchange="document.getElementById('privacyAcceptBtn').disabled=!this.checked;">
            <span data-lang="privacy_agree_label">I have read and agree to the Privacy Policy</span>
        </label>
        <button id="privacyAcceptBtn" class="btn privacy-accept-btn" disabled data-lang="privacy_accept_btn" onclick="if(!this.disabled){document.getElementById('privacy_accepted').value='1';var ps=document.getElementById('privacySection');ps.classList.add('fade-out');setTimeout(function(){ps.style.display='none';},400);}">I Accept</button>
    </div>
</div>
</div>

<script>
(function(){
    var body = document.querySelector('#privacySection .privacy-overlay-body');
    var checkbox = document.getElementById('privacyCheckbox');
    var hint = document.getElementById('privacyScrollHint');
    if(!body || !checkbox){ return; }

    function enableAgreement(){
        checkbox.disabled = false;
        if(hint){ hint.style.display = 'none'; }
    }

    function checkScrollEnd(){
        if(body.scrollTop + body.clientHeight >= body.scrollHeight - 8){
            enableAgreement();
        }
    }

    checkbox.disabled = true;
    body.addEventListener('scroll', checkScrollEnd, {passive:true});

    // Content shorter than the viewport: nothing to scroll.
    setTimeout(function(){
        if(body.scrollHeight <= body.clientHeight + 8){
            enableAgreement();
        } else {
            checkScrollEnd();
        }
    }, 80);
})();
</script>

<?php include __DIR__ . "/../layout/header.php"; ?>

<div style="position: relative; width: 100%; margin: 0; padding: 0;">

    <img src="/assets/images/passenger/header-banner.png" style="width:100%; display:block; object-fit:cover;">

    <div style="
        position: absolute;
        top: 130%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 95%;
        max-width: 800px;
        background: #fef8f3;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        padding: 25px 20px;
    ">
<h2 
style="margin:0; font-family:'Poppins'; font-weight:700; font-size:20px; color:#24513c;"
data-lang="create_account">
Create Your Account!
</h2>

<p 
style="margin-top:5px; font-size:12px; color:#6c757d;"
data-lang="getting_started">
Let's get you started!
</p>

    </div>

    <div style="
        position: absolute;
        top: 160%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 55%;
        max-width: 450px;
        background: #fcf5dd;
        border: 1px solid #c6d48c;
        border-radius: 15px;
        padding: 5px 10px;
        display: flex;
        align-items: center;
        gap: 15px;
        z-index: 2;
    ">

<div style="text-align:center;">
<h3 
style="margin:0; font-size:13px; font-weight:600;"
data-lang="fill_information">
Please fill out your information
</h3>
</div>
    </div>

    <div style="
        position: absolute;
        top: 160%;
        left: 50%;
        transform: translateX(-50%);
        width: 95%;
        background: #e3eabb;
        padding: 30px 0;
        z-index: 1;
    "></div>

</div>

<div style="
    position: relative;
    width: 95%;
    max-width: 900px;
    margin: 120px auto 0 auto;
    display: flex;
    justify-content: center;
    background: #fef8f3;
    border: 2px solid #c6d48c;
    border-radius: 20px;
    padding: 10px 0;
    z-index: 10;
">
<div style="
    width: 95%;
    max-width: 650px;
    background: #fef8f3;
    border-radius: 20px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    padding: 10px 20px 20px 20px;
    margin-top: 0;
">

<form
id="createAccountForm"
method="POST"
action="/passenger/register"
enctype="multipart/form-data">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">

<button 
type="button"
onclick="history.back()"
class="account-top-back-btn"
aria-label="Go back">
    <span class="account-top-back-icon">
        <i class="bi bi-arrow-left"></i>
    </span>
    <span data-lang="back">Back</span>
</button>

<select id="languageSelect" class="form-select" style="width:75px;font-size:12px;padding:3px 8px;border-radius:999px;border:1px solid #d5e4c6;color:#24513c;font-weight:600;background-color:#ffffff;outline:none;">
<option value="en">EN</option>
<option value="tl">TL</option>
<option value="ilo">ILO</option>
</select>

</div>

<div style="display:flex;align-items:center;margin-bottom:15px;">

<div id="circle1" style="width:30px;height:30px;border-radius:50%;background:#4b794d;color:#fff;display:flex;align-items:center;justify-content:center;">
<i class="bi bi-person-fill"></i>
</div>

<div style="flex:1;height:3px;background:#d8d8d8;margin:0 6px;border-radius:10px;overflow:hidden;">
<div id="progressLine" style="height:100%;width:0%;background:#4b794d;transition:.3s;"></div>
</div>

<div id="circle2" style="width:30px;height:30px;border-radius:50%;background:#ccc;color:#fff;display:flex;align-items:center;justify-content:center;">
<i class="bi bi-shield-fill-check"></i>
</div>

</div>

<div id="step1" style="display:flex;flex-direction:column;gap:10px;width:100%;">

<!-- Passenger Category Selection -->
<div id="passengerCategorySection" style="margin-bottom:12px;">
    <label style="display:flex;align-items:center;gap:7px;font-weight:600;font-size:12.5px;color:#24513c;margin-bottom:8px;font-family:'Poppins',sans-serif;">
        <i class="bi bi-person-badge-fill" style="color:#47613b;font-size:15px;"></i>
        <span>Passenger Type / Category</span>
    </label>
    <div class="passenger-type-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">
        <button type="button" class="category-btn active" data-category="Regular" onclick="selectPassengerType('Regular', true)">
            <i class="bi bi-person"></i>
            <span>Regular</span>
        </button>
        <button type="button" class="category-btn" data-category="Student" onclick="selectPassengerType('Student', true)">
            <i class="bi bi-mortarboard-fill"></i>
            <span>Student</span>
        </button>
        <button type="button" class="category-btn" data-category="Senior Citizen" onclick="selectPassengerType('Senior Citizen', true)">
            <i class="bi bi-person-heart"></i>
            <span>Senior Citizen</span>
        </button>
        <button type="button" class="category-btn" data-category="PWD" onclick="selectPassengerType('PWD', true)">
            <i class="bi bi-person-wheelchair"></i>
            <span>PWD</span>
        </button>
        <button type="button" class="category-btn" data-category="Teacher" onclick="selectPassengerType('Teacher', true)">
            <i class="bi bi-book-half"></i>
            <span>Teacher</span>
        </button>
        <button type="button" class="category-btn" data-category="Nurse" onclick="selectPassengerType('Nurse', true)">
            <i class="bi bi-hospital"></i>
            <span>Nurse</span>
        </button>
        <button type="button" class="category-btn" data-category="RPMVFMPC Member" onclick="selectPassengerType('RPMVFMPC Member', true)" style="grid-column: span 2;">
            <i class="bi bi-people-fill"></i>
            <span>RPMVFMPC Member (Cooperative)</span>
        </button>
    </div>
    <input type="hidden" name="discount" id="passenger_type_input" value="Regular">
    <input type="hidden" name="id_type" id="id_type_input" value="">
    <input type="hidden" name="ocr_verification" id="ocr_verification" value="">
</div>

<!-- ID Verification Section (context-dependent: hidden for Regular, locked/selectable for discounts) -->
<div id="idVerificationSection" style="display:none;margin-bottom:12px;">

    <!-- Locked ID box for categories with exactly one required ID -->
    <div id="fixedIdBox" style="background:#f4f8ef;border:1.5px solid #c6d48c;border-radius:14px;padding:12px 14px;margin-bottom:12px;">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#24513c;">
                <i class="bi bi-lock-fill" style="color:#47613b;"></i> Required Document
            </span>
            <span class="badge bg-success" style="font-weight:600;font-size:11px;padding:4px 8px;border-radius:8px;">
                <i class="bi bi-shield-check"></i> Locked
            </span>
        </div>
        <div id="fixedIdTitle" style="font-size:14px;font-weight:700;color:#24513c;margin-top:4px;">Student ID</div>
        <div id="fixedIdHelper" style="font-size:11.5px;color:#556b50;margin-top:2px;">Upload your valid Student ID to verify discount eligibility.</div>
    </div>

    <!-- Senior Citizen dual choice box (Senior Citizen ID or PhilSys / National ID) -->
    <div id="seniorIdChoiceBox" style="background:#f4f8ef;border:1.5px solid #c6d48c;border-radius:14px;padding:12px 14px;margin-bottom:12px;display:none;">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#24513c;margin-bottom:8px;">
            <i class="bi bi-card-checklist" style="color:#47613b;"></i> Select Senior Verification ID
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <label class="senior-id-choice active" id="seniorChoiceOsca" onclick="setSeniorIdType('senior-citizen-id')">
                <input type="radio" name="senior_id_radio" value="senior-citizen-id" checked>
                <span>Senior Citizen ID (OSCA)</span>
            </label>
            <label class="senior-id-choice" id="seniorChoicePhilsys" onclick="setSeniorIdType('philsys-id')">
                <input type="radio" name="senior_id_radio" value="philsys-id">
                <span>PhilSys / National ID</span>
            </label>
        </div>
        <div style="font-size:11.5px;color:#556b50;margin-top:6px;">Senior citizens can verify using an OSCA Senior Citizen ID or PhilSys / National ID.</div>
    </div>

    <!-- ID Number Field -->
    <div id="studentField" style="margin-bottom:12px;">
        <label id="idNumberLabel" for="student_id" style="display:flex;gap:6px;font-weight:600;font-size:12px;color:#24513c;margin-bottom:4px;">
            <i class="bi bi-card-text" style="color:#47613b;"></i>
            <span id="idNumberLabelText">ID Number</span>
        </label>
        <input type="text" name="student_id" id="student_id" class="form-control form-control-sm" placeholder="Enter ID Number" maxlength="50" autocomplete="off" style="border:1px solid #c4b417;border-radius:14px;padding:8px 12px;font-size:13px;height:40px;">
        <div id="student_id_hint" style="font-size:11px;color:#6c757d;margin-top:3px;">Required to verify discount eligibility.</div>
    </div>

<div id="uploadID">

    <div class="id-card-header">
        <div class="id-card-header-main">
            <div class="id-header-icon-circle">
                <i class="bi bi-person-bounding-box"></i>
            </div>
            <div>
                <h2 class="id-header-title" data-lang="scan_upload">Scan ID</h2>
                <p class="id-header-subtitle" data-lang="autofill_details">Take a clear photo of your ID to verify and autofill details</p>
            </div>
        </div>
        <span class="id-step-pill">Step 1 of 2</span>
    </div>

    <div id="cameraView">
        <div id="idCameraStage">
            <video id="cameraPreview" autoplay playsinline></video>

            <button type="button" id="fullscreenCameraBtn" class="id-stage-fullscreen-btn" title="Full screen">
                <i class="bi bi-arrows-fullscreen"></i> <span id="fullscreenCameraLabel">Full screen</span>
            </button>

            <div id="idGuideOverlay" class="id-guide-overlay" aria-hidden="true">
                <div class="id-guide-frame landscape">
                    <span class="id-guide-corner tl"></span>
                    <span class="id-guide-corner tr"></span>
                    <span class="id-guide-corner bl"></span>
                    <span class="id-guide-corner br"></span>

                    <div class="id-scan-line"></div>
                    <div class="id-card-watermark">
                        <div class="id-card-watermark-row"></div>
                        <div class="id-card-watermark-row short"></div>
                    </div>

                    <span class="id-guide-label">Place ID inside the frame</span>
                </div>
                <div class="id-guide-orientation">Landscape ID &bull; hold the ID sideways</div>
            </div>

            <div id="noCameraMsg" style="display:none;position:absolute;inset:0;background:#f8faf6;flex-direction:column;align-items:center;justify-content:center;color:#47613b;font-size:13px;text-align:center;padding:20px;">
                <i class="bi bi-camera-video-off" style="font-size:36px;color:#8ba57c;margin-bottom:8px;"></i>
                <span data-lang="no_camera" style="font-weight:600;">Camera not available</span>
                <span style="font-size:11px;color:#6b7a68;margin-top:4px;">Please allow camera permissions to scan your ID.</span>
            </div>
        </div>

        <div class="id-compact-tips-row">
            <span><i class="bi bi-brightness-high"></i> Good lighting</span>
            <span class="id-tip-dot">&bull;</span>
            <span><i class="bi bi-sun"></i> No glare</span>
            <span class="id-tip-dot">&bull;</span>
            <span><i class="bi bi-bounding-box"></i> Show all edges</span>
        </div>

        <div class="id-guide-help">Place the ID inside the frame. Keep it flat, avoid glare, and show all corners.</div>

        <div style="text-align:center;margin-top:6px;">
            <button type="button" class="id-tips-link" id="idTipsToggle" aria-expanded="false">
                <i class="bi bi-question-circle"></i> Photo tips
            </button>
        </div>
        <ul class="id-guide-tips" id="idGuideTips" style="display:none;">
            <li>Keep the ID flat on a plain, contrasting surface</li>
            <li>Avoid direct glare or reflections</li>
            <li>Make sure all four corners are visible</li>
            <li>Use bright, even lighting</li>
            <li>Keep the text sharp and in focus</li>
        </ul>

        <div id="scanQualityHint" class="id-quality-hint" style="display:none;"></div>

        <div id="seniorGuidance" class="id-senior-guidance" style="display:none;">
            <div class="id-senior-title" id="seniorGuidanceTitle">Position your Senior Citizen ID inside the frame.</div>
            <div class="id-senior-sub" id="seniorGuidanceSub">Show the ID number, full name, and date of birth (if available).</div>
        </div>

        <div class="id-actions-container">
            <button type="button" id="captureBtn" class="id-btn-dominant" data-lang="capture_photo">
                Capture Front
            </button>
            <button type="button" id="retakeBtn" class="id-btn-secondary" style="display:none;" data-lang="retake">
                <i class="bi bi-arrow-counterclockwise"></i> Retake
            </button>
        </div>
        <input type="file" name="id_front" id="id_front" style="display:none;" accept="image/*">
        <input type="file" name="id_back" id="id_back" style="display:none;" accept="image/*">
        <canvas id="cameraCanvas" style="display:none;"></canvas>
    </div>

    <div id="previewContainer" style="display:none;margin-top:14px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div style="background:#fcfbf6;border:1.5px solid #dce8cd;border-radius:14px;padding:8px;text-align:center;">
                <div style="font-size:11px;font-weight:700;color:#24513c;margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px;" data-lang="front_label">Front of ID</div>
                <img id="previewFront" style="width:100%;height:130px;object-fit:cover;border-radius:10px;border:1px solid #d5e2c5;display:block;">
            </div>
            <div style="background:#fcfbf6;border:1.5px solid #dce8cd;border-radius:14px;padding:8px;text-align:center;">
                <div style="font-size:11px;font-weight:700;color:#24513c;margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px;" data-lang="back_label">Back of ID</div>
                <img id="previewBack" style="width:100%;height:130px;object-fit:cover;border-radius:10px;border:1px solid #d5e2c5;display:block;">
            </div>
        </div>
        <div id="scanStatus" style="margin-top:10px;font-size:12.5px;color:#24513c;font-weight:600;text-align:center;padding:9px 12px;background:#f4f8ef;border-radius:12px;border:1px solid #dce8cd;" data-lang="waiting_upload">Waiting for uploads...</div>
        <button type="button" id="retakeScanBtn" style="display:none;margin-top:10px;border:1.5px solid #c6d48c;border-radius:14px;padding:9px;font-size:12.5px;font-weight:600;color:#24513c;background:#fff;cursor:pointer;width:100%;box-sizing:border-box;font-family:'Poppins',sans-serif;" onclick="resetCapture()">
            <i class="bi bi-camera"></i> Retake Photos
        </button>

        <div id="ocrConfirmPanel" class="ocr-confirm-panel" style="display:none;">
            <div class="ocr-status-banner">
                <div class="ocr-status-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div class="ocr-status-text">
                    <div class="ocr-confirm-title" style="display:flex;align-items:center;gap:8px;">
                        <span>Review ID Details</span>
                        <span id="ocrStatusBadge" class="badge bg-warning text-dark" style="font-size:10px;padding:3px 7px;border-radius:6px;font-weight:600;letter-spacing:0.3px;">PENDING REVIEW</span>
                    </div>
                    <div class="ocr-status-desc" id="ocrStatusDesc">
                        Details were read from your ID. Please review or edit them below. An administrator verifies your discount document before discounted fares apply.
                    </div>
                </div>
            </div>

            <div class="ocr-preview-strip">
                <div class="ocr-preview-thumbs-wrap">
                    <div class="ocr-preview-thumb">
                        <img id="ocrThumbFront" src="" alt="Front of ID">
                        <span class="ocr-thumb-tag">Front</span>
                    </div>
                    <div class="ocr-preview-thumb">
                        <img id="ocrThumbBack" src="" alt="Back of ID">
                        <span class="ocr-thumb-tag">Back</span>
                    </div>
                </div>
                <div class="ocr-preview-strip-info">
                    <div class="ocr-preview-strip-title">Scanned ID Photos</div>
                    <div class="ocr-preview-strip-sub">Clear photos ensure accurate verification</div>
                </div>
            </div>

            <div id="ocrQualityNote" class="ocr-confirm-note"></div>

            <div class="ocr-card-section">
                <div class="ocr-section-header">
                    <i class="bi bi-person-vcard"></i>
                    <span>Identity Details</span>
                </div>

                <div class="ocr-confirm-row">
                    <div class="ocr-row-header">
                        <label for="ocrFullName">Full Name</label>
                        <span class="ocr-detect-badge detected" id="badge_ocrFullName"><i class="bi bi-check-circle-fill"></i> Detected</span>
                    </div>
                    <input type="text" id="ocrFullName" class="ocr-confirm-input" placeholder="Not detected">
                </div>

                <div class="ocr-confirm-row">
                    <div class="ocr-row-header">
                        <label for="ocrIdNumber">ID Number</label>
                        <span class="ocr-detect-badge detected" id="badge_ocrIdNumber"><i class="bi bi-check-circle-fill"></i> Detected</span>
                    </div>
                    <input type="text" id="ocrIdNumber" class="ocr-confirm-input" placeholder="Not detected">
                </div>

                <div class="ocr-confirm-row">
                    <div class="ocr-row-header">
                        <label for="ocrDateOfBirth">Date of Birth</label>
                        <span class="ocr-detect-badge detected" id="badge_ocrDateOfBirth"><i class="bi bi-check-circle-fill"></i> Detected</span>
                    </div>
                    <input type="date" id="ocrDateOfBirth" class="ocr-confirm-input">
                </div>
            </div>

            <div class="ocr-card-section" id="ocrDocumentSection" style="display:none;">
                <div class="ocr-section-header">
                    <i class="bi bi-mortarboard"></i>
                    <span id="ocrDocumentSectionTitle">Document Details</span>
                </div>

                <div class="ocr-confirm-row" id="ocrSchoolRow" style="display:none;">
                    <div class="ocr-row-header">
                        <label for="ocrSchool">School Name</label>
                        <span class="ocr-detect-badge detected" id="badge_ocrSchool"><i class="bi bi-check-circle-fill"></i> Detected</span>
                    </div>
                    <input type="text" id="ocrSchool" class="ocr-confirm-input" placeholder="Not detected">
                </div>

                <div class="ocr-confirm-row" id="ocrStudentNumberRow" style="display:none;">
                    <div class="ocr-row-header">
                        <label for="ocrStudentNumber">Student Number</label>
                        <span class="ocr-detect-badge detected" id="badge_ocrStudentNumber"><i class="bi bi-check-circle-fill"></i> Detected</span>
                    </div>
                    <input type="text" id="ocrStudentNumber" class="ocr-confirm-input" placeholder="Not detected">
                </div>

                <div class="ocr-confirm-row" id="ocrSchoolYearRow" style="display:none;">
                    <div class="ocr-row-header">
                        <label for="ocrSchoolYear">School Year</label>
                        <span class="ocr-detect-badge detected" id="badge_ocrSchoolYear"><i class="bi bi-check-circle-fill"></i> Detected</span>
                    </div>
                    <input type="text" id="ocrSchoolYear" class="ocr-confirm-input" placeholder="Not detected">
                </div>

                <div class="ocr-confirm-row" id="ocrValidityRow" style="display:none;">
                    <div class="ocr-row-header">
                        <label for="ocrValidity">ID Validity / Expiry</label>
                        <span class="ocr-detect-badge detected" id="badge_ocrValidity"><i class="bi bi-check-circle-fill"></i> Detected</span>
                    </div>
                    <input type="text" id="ocrValidity" class="ocr-confirm-input" placeholder="No expiry printed on this ID">
                </div>
            </div>

            <div class="ocr-card-section" id="ocrReviewSection" style="display:none;">
                <div class="ocr-section-header">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>For Administrator Review</span>
                </div>
                <ul id="ocrReviewFlags" style="margin:0;padding-left:18px;font-size:12px;color:#7a5c1e;line-height:1.7;"></ul>
                <div class="ocr-confirm-hint">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Scanning reads your document. When the scanned details match the ones you entered, the discount is verified automatically and discounted fares apply right away. Anything unreadable or mismatched goes to an administrator for review.</span>
                </div>
            </div>

            <div class="ocr-card-section">
                <div class="ocr-section-header">
                    <i class="bi bi-geo-alt"></i>
                    <span>Address &amp; Contact Details</span>
                </div>

                <div class="ocr-confirm-row" id="ocrAddressRow">
                    <div class="ocr-row-header">
                        <label for="ocrAddress">Address</label>
                        <span class="ocr-detect-badge detected" id="badge_ocrAddress"><i class="bi bi-check-circle-fill"></i> Detected</span>
                    </div>
                    <input type="text" id="ocrAddress" class="ocr-confirm-input" placeholder="Not detected">
                </div>

                <div class="ocr-confirm-row">
                    <div class="ocr-row-header">
                        <label for="ocrEmail">Email Address</label>
                        <span class="ocr-detect-badge detected" id="badge_ocrEmail"><i class="bi bi-check-circle-fill"></i> Detected</span>
                    </div>
                    <input type="email" id="ocrEmail" class="ocr-confirm-input" placeholder="Not detected">
                </div>

                <div class="ocr-confirm-row">
                    <div class="ocr-row-header">
                        <label for="ocrPhone">Phone Number</label>
                        <span class="ocr-detect-badge detected" id="badge_ocrPhone"><i class="bi bi-check-circle-fill"></i> Detected</span>
                    </div>
                    <input type="text" id="ocrPhone" class="ocr-confirm-input" placeholder="Phone number not detected">
                </div>

                <div class="ocr-confirm-hint">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>Contact information is filled from the ID if printed. Please double check that this is your active email and phone number.</span>
                </div>
            </div>

            <div class="ocr-confirm-actions">
                <button type="button" id="ocrApplyBtn" class="ocr-btn ocr-btn-primary"><i class="bi bi-check2-circle"></i> Use these details</button>
                <button type="button" id="ocrRetakeBtn" class="ocr-btn ocr-btn-outline"><i class="bi bi-camera"></i> Retake ID</button>
            </div>

            <div class="ocr-manual-divider">
                <span>Or enter manually</span>
            </div>
            <button type="button" id="ocrManualBtn" class="ocr-manual-btn">
                <span class="ocr-manual-btn-icon">
                    <i class="bi bi-pencil-square"></i>
                </span>
                <span class="ocr-manual-btn-text">
                    Unable to read the ID clearly? <strong>Enter details manually</strong>
                </span>
                <i class="bi bi-arrow-right-short ocr-manual-btn-arrow"></i>
            </button>
        </div>

        <div style="font-size:11px;color:#6c757d;text-align:center;margin-top:8px;" data-lang="autofill_hint">Auto-fill your details using your ID</div>
    </div>

</div>
</div><!-- /#idVerificationSection -->

<div style="width:100%;display:flex;flex-direction:column;">
<label style="display:flex;gap:6px;font-weight:600;">
<i class="bi bi-person-fill" style="color:#47613b;"></i>
<span data-lang="full_name">Full Name</span>
</label>

<input 
type="text"
id="full_name"
name="full_name"
placeholder="Maria Santos"
minlength="2"
maxlength="100"
autocomplete="name"
required
style="
display:block;
width:100%;
max-width:100%;
min-width:0;
border:1px solid #c4b417;
border-radius:14px;
padding:8px 12px;
font-size:13px;
outline:none;
background:#fff;
height:40px;
box-sizing:border-box;
">
</div>

<div style="width:100%;display:flex;flex-direction:column;">
<label style="display:flex;gap:6px;font-weight:600;">
<i class="bi bi-envelope-fill" style="color:#47613b;"></i>
<span data-lang="email">Email Address</span>
</label>

<input 
type="email"
id="email"
name="email"
placeholder="maria@gmail.com"
maxlength="100"
autocomplete="email"
required
style="
display:block;
width:100%;
max-width:100%;
min-width:0;
border:1px solid #c4b417;
border-radius:14px;
padding:8px 12px;
font-size:13px;
outline:none;
background:#fff;
height:40px;
box-sizing:border-box;
">
</div>

<div style="width:100%;display:flex;flex-direction:column;">
<label style="display:flex;gap:6px;font-weight:600;">
<i class="bi bi-telephone-fill" style="color:#47613b;"></i>
<span data-lang="phone">Phone Number</span>
</label>

<input 
type="tel"
id="phone"
name="phone"
placeholder="09123456789"
maxlength="14"
inputmode="tel"
autocomplete="tel"
required
style="
display:block;
width:100%;
max-width:100%;
min-width:0;
border:1px solid #c4b417;
border-radius:14px;
padding:8px 12px;
font-size:13px;
outline:none;
background:#fff;
height:40px;
box-sizing:border-box;
">
</div>

<div style="width:100%;display:flex;flex-direction:column;">
<label style="display:flex;gap:6px;font-weight:600;">
<i class="bi bi-calendar-date-fill" style="color:#47613b;"></i>
<span>Date of Birth</span>
</label>

<input
type="text"
id="date_of_birth"
name="date_of_birth"
placeholder="YYYY-MM-DD"
maxlength="10"
inputmode="numeric"
pattern="\d{4}-\d{2}-\d{2}"
title="Enter your date of birth as YYYY-MM-DD"
required
style="
display:block;
width:100%;
border:1px solid #c4b417;
border-radius:14px;
padding:8px 12px;
font-size:13px;
outline:none;
background:#fff;
height:40px;
box-sizing:border-box;
">
<div id="date_of_birth_error" style="display:none;color:#c62828;font-size:11.5px;margin-top:4px;font-weight:600;"></div>
</div>

<div id="addressField" style="width:100%;display:none;flex-direction:column;">
<label style="display:flex;gap:6px;font-weight:600;">
<i class="bi bi-geo-alt-fill" style="color:#47613b;"></i>
<span data-lang="address">Address</span>
</label>
<input
type="text"
id="address"
name="address"
maxlength="255"
placeholder="123 Barangay, City"
style="display:block;width:100%;max-width:100%;min-width:0;border:1px solid #c4b417;border-radius:14px;padding:8px 12px;font-size:13px;outline:none;background:#fff;height:40px;box-sizing:border-box;">
</div>

<div style="width:100%;display:flex;flex-direction:column;">
<label style="display:flex;gap:6px;font-weight:600;">
<i class="bi bi-lock-fill" style="color:#47613b;"></i>
<span data-lang="password">Password</span>
</label>

<div class="password-input-wrapper">
    <input 
    type="password"
    name="password"
    id="pass"
    required
    minlength="8"
    maxlength="72"
    placeholder="At least 8 characters"
    style="
    display:block;
    width:100%;
    max-width:100%;
    min-width:0;
    border:1px solid #c4b417;
    border-radius:14px;
    padding:8px 42px 8px 12px;
    font-size:13px;
    outline:none;
    background:#fff;
    height:40px;
    box-sizing:border-box;
    ">
    <button 
    type="button" 
    class="password-toggle-btn"
    onclick="togglePasswordVisibility('pass', 'passEyeIcon')"
    aria-label="Show password"
    title="Show password">
        <i id="passEyeIcon" class="bi bi-eye-slash"></i>
    </button>
</div>
</div>

<div style="width:100%;display:flex;flex-direction:column;">
<label style="display:flex;gap:6px;font-weight:600;">
<i class="bi bi-shield-fill-check" style="color:#47613b;"></i>
<span data-lang="confirm_password">Confirm Password</span>
</label>

<div class="password-input-wrapper">
    <input 
    type="password"
    name="confirm_password"
    id="confirm"
    required
    minlength="8"
    maxlength="72"
    placeholder="Re-enter password"
    style="
    display:block;
    width:100%;
    max-width:100%;
    min-width:0;
    border:1px solid #c4b417;
    border-radius:14px;
    padding:8px 42px 8px 12px;
    font-size:13px;
    outline:none;
    background:#fff;
    height:40px;
    box-sizing:border-box;
    ">
    <button 
    type="button" 
    class="password-toggle-btn"
    onclick="togglePasswordVisibility('confirm', 'confirmEyeIcon')"
    aria-label="Show password"
    title="Show password">
        <i id="confirmEyeIcon" class="bi bi-eye-slash"></i>
    </button>
</div>
</div>

<button 
type="button"
class="btn btn-success w-100 btn-sm"
onclick="nextStep()"
data-lang="continue">
Continue
</button>

</div>

<div id="step2" style="display:none;">

<div style="background:#f4f8ef;border:1.5px solid #c6d48c;border-radius:16px;padding:16px;margin-bottom:14px;">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#24513c;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
        <i class="bi bi-person-check-fill" style="color:#47613b;font-size:14px;"></i>
        <span>Account & Verification Summary</span>
    </div>
    <div style="display:grid;grid-template-columns:100px minmax(0,1fr);gap:16px;align-items:start;padding:10px 0;border-bottom:1px solid #e1ebda;">
        <span style="font-size:12px;color:#6c757d;">Passenger Category</span>
        <span id="summaryCategoryText" style="font-size:13px;font-weight:700;color:#24513c;text-align:right;overflow-wrap:anywhere;">Regular</span>
    </div>
    <div id="summaryIdTypeRow" style="display:none;justify-content:space-between;gap:16px;align-items:flex-start;padding:10px 0;border-bottom:1px solid #e1ebda;">
        <span style="font-size:12px;color:#6c757d;">Verification Document</span>
        <span id="summaryIdTypeText" style="font-size:13px;font-weight:600;color:#24513c;min-width:0;text-align:right;overflow-wrap:anywhere;"></span>
    </div>
    <div id="summaryIdNumberRow" style="display:none;justify-content:space-between;gap:16px;align-items:flex-start;padding:10px 0;border-bottom:1px solid #e1ebda;">
        <span id="summaryIdNumberLabel" style="font-size:12px;color:#6c757d;">ID Number</span>
        <span id="summaryIdNumberValue" style="font-size:13px;font-weight:700;color:#24513c;min-width:0;text-align:right;overflow-wrap:anywhere;"></span>
    </div>
    <div style="display:grid;grid-template-columns:100px minmax(0,1fr);gap:16px;align-items:start;padding:10px 0;border-bottom:1px solid #e1ebda;">
        <span style="font-size:12px;color:#6c757d;">Full Name</span>
        <span id="summaryNameText" style="font-size:13px;font-weight:600;color:#24513c;text-align:right;overflow-wrap:anywhere;"></span>
    </div>
    <div style="display:grid;grid-template-columns:100px minmax(0,1fr);gap:16px;align-items:start;padding:10px 0;">
        <span style="font-size:12px;color:#6c757d;">Contact</span>
        <span id="summaryContactText" style="font-size:12.5px;color:#24513c;text-align:right;min-width:0;overflow-wrap:anywhere;white-space:pre-line;line-height:1.6;"></span>
    </div>
</div>

<div style="border:1.5px solid #d2c27e;border-radius:16px;padding:14px;background:#f9fbf8;">

<div style="display:flex;gap:10px;">

<div style="width:45px;height:45px;border-radius:12px;border:1.5px solid #d2c27e;display:flex;align-items:center;justify-content:center;background:#fff;">
<i class="bi bi-geo-alt-fill" style="color:#47613b;"></i>
</div>

<div>
<div 
style="font-weight:700;font-size:13px;"
data-lang="location_access">
Location Access
</div>

<div 
style="font-size:11px;color:#6c757d;"
data-lang="location_description">
We need your location for routes and stops.
</div>
</div>

</div>

<button 
type="button"
class="btn btn-success btn-sm w-100 mt-3"
onclick="requestLocation()">
Allow Location
</button>

<input type="hidden" name="latitude" id="lat">
<input type="hidden" name="longitude" id="lng">
<input type="hidden" name="privacy_accepted" id="privacy_accepted" value="0">

</div>

<div style="display:flex;gap:8px;margin-top:14px;">

<button 
type="button"
class="btn btn-step-back btn-sm w-100"
onclick="prevStep()"
data-lang="back">
<i class="bi bi-arrow-left"></i> Back
</button>

<button 
type="submit"
class="btn btn-success btn-sm w-100"
style="min-height:40px;border-radius:14px;background:linear-gradient(135deg,#3d5932,#4f7842);border:none;font-weight:700;font-family:'Poppins',sans-serif;"
data-lang="create">
Create
</button>

</div>

</div>
</form>

<div
id="otpModal"
style="
display:none;
position:fixed;
inset:0;
background:rgba(0,0,0,.5);
z-index:99999;
align-items:center;
justify-content:center;
padding:16px;
overflow-y:auto;
">

    <div
    style="
    width:100%;
    max-width:500px;
    background:#fff;
    border-radius:20px;
    margin:auto;
    overflow:hidden;
    ">

        <div
        id="otpModalContent">

        </div>

    </div>

</div>

</div>
</div>

<div id="customAlertOverlay" class="custom-alert-overlay">
    <div class="custom-alert-card">
        <div class="custom-alert-header">
            <div class="custom-alert-icon"></div>
            <h3 class="custom-alert-title"></h3>
        </div>
        <div class="custom-alert-body">
            <p class="custom-alert-message"></p>
            <div class="custom-alert-actions">
                <button class="custom-alert-btn"></button>
                <button class="custom-alert-btn-cancel"></button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . "/../layout/otp-input.php"; ?>
<?php require __DIR__ . "/assets/scripts.php"; ?>

<script>
(function() {
    var previewFront = document.getElementById('previewFront');
    var previewBack = document.getElementById('previewBack');
    var ocrThumbFront = document.getElementById('ocrThumbFront');
    var ocrThumbBack = document.getElementById('ocrThumbBack');

    function syncThumbnails() {
        if (previewFront && ocrThumbFront && previewFront.src) {
            ocrThumbFront.src = previewFront.src;
        }
        if (previewBack && ocrThumbBack && previewBack.src) {
            ocrThumbBack.src = previewBack.src;
        }
    }
    if (previewFront) previewFront.addEventListener('load', syncThumbnails);
    if (previewBack) previewBack.addEventListener('load', syncThumbnails);

    var fieldPairs = [
        { id: 'ocrFullName', badge: 'badge_ocrFullName' },
        { id: 'ocrIdNumber', badge: 'badge_ocrIdNumber' },
        { id: 'ocrDateOfBirth', badge: 'badge_ocrDateOfBirth' },
        { id: 'ocrAddress', badge: 'badge_ocrAddress' },
        { id: 'ocrEmail', badge: 'badge_ocrEmail' },
        { id: 'ocrPhone', badge: 'badge_ocrPhone' }
    ];

    function updateBadges() {
        fieldPairs.forEach(function(item) {
            var input = document.getElementById(item.id);
            var badge = document.getElementById(item.badge);
            if (!input || !badge) return;
            var val = (input.value || '').trim();
            var isDetected = val.length > 0 && !input.classList.contains('is-missing');
            if (isDetected) {
                badge.className = 'ocr-detect-badge detected';
                badge.innerHTML = '<i class="bi bi-check-circle-fill"></i> Detected';
            } else {
                badge.className = 'ocr-detect-badge missing';
                badge.innerHTML = '<i class="bi bi-exclamation-circle"></i> Not detected';
            }
        });
    }

    fieldPairs.forEach(function(item) {
        var input = document.getElementById(item.id);
        if (input) {
            input.addEventListener('input', updateBadges);
            input.addEventListener('change', updateBadges);
        }
    });

    var panel = document.getElementById('ocrConfirmPanel');
    if (panel && window.MutationObserver) {
        var observer = new MutationObserver(function() {
            syncThumbnails();
            updateBadges();
        });
        observer.observe(panel, { attributes: true, attributeFilter: ['style', 'class'] });
    }

    var origResetCapture = window.resetCapture;
    window.resetCapture = function() {
        if (typeof origResetCapture === 'function') {
            origResetCapture();
        }
        if (ocrThumbFront) ocrThumbFront.src = '';
        if (ocrThumbBack) ocrThumbBack.src = '';
        updateBadges();
    };
})();
</script>

</body>
</html>
