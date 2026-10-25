<style>
body.modal-open{ overflow:hidden; }

.modal-overlay{
    position:fixed;
    inset:0;
    z-index:9999;
    background:rgba(46,72,54,0.35);
    -webkit-backdrop-filter:blur(3px);
    backdrop-filter:blur(3px);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:16px;
    overflow-y:auto;
}

.modal-box{
    position:relative;
    width:100%;
    max-width:400px;
    margin:auto;
    background:#fef8f3;
    border:1.5px solid #c6d48c;
    border-radius:24px;
    box-shadow:0 20px 50px rgba(36,81,60,0.30);
    overflow:hidden;
    font-family:'Poppins',sans-serif;
}

.modal-close{
    position:absolute;
    top:12px;
    right:12px;
    width:34px;
    height:34px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:none;
    border-radius:50%;
    background:rgba(255,255,255,0.75);
    color:#2e4836;
    font-size:20px;
    line-height:1;
    cursor:pointer;
    z-index:2;
    transition:background .2s;
}

.modal-close:hover{ background:#fffdf7; }

/* Header: cream band with the light-green icon tile used across the app */
.fp-header{
    position:relative;
    padding:26px 22px 50px;
    text-align:center;
    background:linear-gradient(160deg,#fefaf3 0%,#fbf3dc 100%);
    border-bottom:1px solid #e6dfbe;
}

.fp-icon{
    width:64px;
    height:64px;
    margin:0 auto 14px;
    border-radius:20px;
    background:#e8f5e9;
    border:1.5px solid #a5d6a7;
    box-shadow:0 6px 14px rgba(46,125,50,0.12);
    display:flex;
    align-items:center;
    justify-content:center;
}

.fp-icon i{
    color:#2e7d32;
    font-size:30px;
}

.modal-title{
    font-size:20px;
    font-weight:700;
    color:#24513c;
}

.modal-sub{
    margin-top:5px;
    font-size:12.5px;
    line-height:1.5;
    color:#7b8a74;
}

/* Step progress pill overlapping the header */
.fp-steps{
    position:absolute;
    left:18px;
    right:18px;
    bottom:-26px;
    display:flex;
    justify-content:space-between;
    gap:6px;
    padding:10px 14px;
    background:#fffdf7;
    border:1px solid #e9e2c6;
    border-radius:16px;
    box-shadow:0 6px 16px rgba(120,110,60,0.10);
}

.fp-step{
    flex:1;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:3px;
}

.fp-step span{
    width:22px;
    height:22px;
    border-radius:50%;
    background:#eee9d8;
    color:#a09c7d;
    font-size:11px;
    font-weight:700;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:background .2s,color .2s;
}

.fp-step em{
    font-style:normal;
    font-size:10px;
    font-weight:600;
    letter-spacing:.2px;
    color:#a8a68e;
}

.fp-step.active span{
    background:#e3ebbc;
    color:#2e4836;
    box-shadow:0 3px 8px rgba(140,150,70,0.25);
}

.fp-step.active em{ color:#2e4836; }

.fp-step.done span{
    background:#dff0d8;
    color:#2e7d32;
}

.fp-step.done em{ color:#2e7d32; }

.fp-body{
    padding:44px 22px 26px;
}

.hidden{ display:none; }

/* Inputs */
.input-field{
    width:100%;
    box-sizing:border-box;
    padding:13px 14px;
    border:2px solid #e2dab7;
    border-radius:14px;
    background:#fdfaf2;
    font-family:'Poppins',sans-serif;
    font-size:14px;
    color:#24513c;
    outline:none;
    transition:border-color .2s, box-shadow .2s;
    margin-bottom:12px;
}

.input-field::placeholder{ color:#a8a68e; }

.input-field:focus{
    border-color:#8fb27a;
    box-shadow:0 0 0 4px rgba(140,180,110,0.18);
}

.fp-input{
    position:relative;
    margin-bottom:12px;
}

.fp-input > i{
    position:absolute;
    left:15px;
    top:50%;
    transform:translateY(-50%);
    font-size:15px;
    color:#a8a68e;
    pointer-events:none;
    transition:color .2s;
}

.fp-input .input-field{
    margin-bottom:0;
    padding-left:42px;
}

.fp-input:focus-within > i{ color:#5d8a4a; }

/* Buttons */
.btn{
    width:100%;
    height:50px;
    border:none;
    border-radius:14px;
    background:linear-gradient(90deg,#306449,#b3be5b);
    color:#fff;
    font-family:'Poppins',sans-serif;
    font-size:15px;
    font-weight:700;
    letter-spacing:.2px;
    cursor:pointer;
    box-shadow:0 6px 16px rgba(120,140,70,0.28);
    transition:opacity .2s, transform .1s;
}

.btn:hover{ opacity:.92; }
.btn:active{ transform:scale(.98); }

.btn:disabled{
    opacity:0.6;
    cursor:not-allowed;
}

/* OTP boxes (same as the Create Account verification design) */
.otp-row{
    display:flex;
    justify-content:center;
    gap:8px;
    margin-bottom:10px;
}

.otp-digit{
    width:44px;
    height:52px;
    text-align:center;
    font-family:'Poppins',sans-serif;
    font-size:20px;
    font-weight:700;
    color:#24513c;
    border:2px solid #dcd3ae;
    border-radius:12px;
    background:#fdfaf2;
    outline:none;
    transition:border-color .2s, box-shadow .2s, background .2s;
}

.otp-digit:focus{
    border-color:#8fb27a;
    box-shadow:0 0 0 4px rgba(140,180,110,0.18);
    background:#fff;
}

.otp-hint{
    font-size:11px;
    color:#a8a68e;
    text-align:center;
    margin-bottom:14px;
}

/* Password fields with visibility toggle */
.pw-field{
    position:relative;
    margin-bottom:12px;
}

.pw-field .input-field{
    margin-bottom:0;
    padding-left:42px;
    padding-right:46px;
}

.pw-lead{
    position:absolute;
    left:15px;
    top:50%;
    transform:translateY(-50%);
    font-size:15px;
    color:#a8a68e;
    pointer-events:none;
    transition:color .2s;
}

.pw-field:focus-within .pw-lead{ color:#5d8a4a; }

.pw-toggle{
    position:absolute;
    top:2px;
    right:2px;
    bottom:2px;
    width:40px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:none;
    background:transparent;
    color:#2e4836;
    cursor:pointer;
    border-radius:12px;
    padding:0;
    transition:background .2s;
}

.pw-toggle:hover{ background:rgba(46,72,54,0.08); }

.pw-toggle i{ font-size:16px; }

/* Live password requirement checklist */
.pw-reqs{
    list-style:none;
    margin:0 0 12px;
    padding:12px 14px;
    background:#f8f8ea;
    border:1px solid #e5e3c6;
    border-radius:14px;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:6px 10px;
}

.pw-reqs li{
    display:flex;
    align-items:center;
    gap:6px;
    font-size:11px;
    line-height:1.4;
    color:#a8a68e;
}

.pw-reqs li::before{
    content:'○';
    font-weight:700;
    font-size:10px;
}

.pw-reqs li.met{ color:#2e7d32; }
.pw-reqs li.met::before{ content:'✓'; }
.pw-reqs li.unmet{ color:#b02a37; }

/* Live confirm-password match hint */
.pw-match{
    display:flex;
    align-items:center;
    gap:6px;
    min-height:18px;
    margin:0 0 12px;
    padding:0 4px;
    font-size:11.5px;
}

.pw-match.ok{ color:#2e7d32; }
.pw-match.ok::before{ content:'✓'; font-weight:700; }
.pw-match.bad{ color:#b02a37; }
.pw-match.bad::before{ content:'!'; font-weight:700; }

@media (max-width:420px){
    .fp-steps{ left:12px; right:12px; }
    .otp-digit{ width:40px; height:48px; font-size:18px; }
    .pw-reqs{ grid-template-columns:1fr; }
}
</style>

<div class="modal-overlay" id="forgotModal" onclick="closeForgotModal(event)">
<div class="modal-box" onclick="event.stopPropagation()">

    <div class="fp-header">
        <button type="button" class="modal-close" onclick="closeForgotModal()" aria-label="Close">&times;</button>

        <div class="fp-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <div class="modal-title"><?= htmlspecialchars(passenger_t('profile.reset_password', 'Reset Password'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="modal-sub" id="stepText"><?= htmlspecialchars(passenger_t('profile.enter_email', 'Enter your email'), ENT_QUOTES, 'UTF-8') ?></div>

        <div class="fp-steps" id="fpSteps">
            <div class="fp-step active" data-step="1"><span>1</span><em><?= htmlspecialchars(passenger_t('profile.step_email', 'Email'), ENT_QUOTES, 'UTF-8') ?></em></div>
            <div class="fp-step" data-step="2"><span>2</span><em><?= htmlspecialchars(passenger_t('profile.step_verify', 'Verify'), ENT_QUOTES, 'UTF-8') ?></em></div>
            <div class="fp-step" data-step="3"><span>3</span><em><?= htmlspecialchars(passenger_t('profile.step_reset', 'Reset'), ENT_QUOTES, 'UTF-8') ?></em></div>
        </div>
    </div>

    <div class="fp-body">

        <div id="step1">
            <div class="fp-input">
                <i class="bi bi-envelope-fill"></i>
                <input type="email" id="email" class="input-field" placeholder="<?= htmlspecialchars(passenger_t('profile.email', 'Email'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="email">
            </div>
            <button type="button" class="btn" id="btnSend" onclick="sendCode()"><?= htmlspecialchars(passenger_t('profile.send_code', 'Send Code'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>

        <div id="step2" class="hidden">
            <div class="otp-row">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                <input type="text" id="modalOtp<?= $i ?>" class="otp-digit" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" required>
                <?php endfor; ?>
            </div>
            <div class="otp-hint"><?= htmlspecialchars(passenger_t('profile.otp_hint', 'Enter or paste the 6-digit code sent to your email'), ENT_QUOTES, 'UTF-8') ?></div>
            <input type="hidden" id="modalOtpCombined">
            <button type="button" class="btn" id="btnVerifyOtp" onclick="verifyCode()"><?= htmlspecialchars(passenger_t('profile.verify_code', 'Verify Code'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>

        <div id="step3" class="hidden">
            <div class="pw-field">
                <i class="bi bi-shield-lock-fill pw-lead"></i>
                <input type="password" id="currentPass" class="input-field" placeholder="<?= htmlspecialchars(passenger_t('profile.current_password', 'Current Password'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="current-password">
                <button type="button" class="pw-toggle" onclick="togglePasswordVisibility('currentPass', this)" aria-label="<?= htmlspecialchars(passenger_t('profile.show_password', 'Show password'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-eye-slash"></i></button>
            </div>
            <div class="pw-field">
                <i class="bi bi-lock-fill pw-lead"></i>
                <input type="password" id="pass" class="input-field" placeholder="<?= htmlspecialchars(passenger_t('profile.new_password', 'New Password'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="new-password">
                <button type="button" class="pw-toggle" onclick="togglePasswordVisibility('pass', this)" aria-label="<?= htmlspecialchars(passenger_t('profile.show_password', 'Show password'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-eye-slash"></i></button>
            </div>
            <ul class="pw-reqs" id="pwReqs">
                <li data-req="length"><?= htmlspecialchars(passenger_t('profile.req_length', 'At least 8 characters'), ENT_QUOTES, 'UTF-8') ?></li>
                <li data-req="max"><?= htmlspecialchars(passenger_t('profile.req_max', 'No more than 72 characters'), ENT_QUOTES, 'UTF-8') ?></li>
                <li data-req="letter"><?= htmlspecialchars(passenger_t('profile.req_letter', 'Contains a letter'), ENT_QUOTES, 'UTF-8') ?></li>
                <li data-req="number"><?= htmlspecialchars(passenger_t('profile.req_number', 'Contains a number'), ENT_QUOTES, 'UTF-8') ?></li>
            </ul>
            <div class="pw-field">
                <i class="bi bi-check2-circle pw-lead"></i>
                <input type="password" id="confirm" class="input-field" placeholder="<?= htmlspecialchars(passenger_t('profile.confirm_password', 'Confirm Password'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="new-password">
                <button type="button" class="pw-toggle" onclick="togglePasswordVisibility('confirm', this)" aria-label="<?= htmlspecialchars(passenger_t('profile.show_password', 'Show password'), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-eye-slash"></i></button>
            </div>
            <div class="pw-match" id="pwMatch" aria-live="polite"></div>
            <button type="button" class="btn" id="btnUpdatePassword" onclick="resetPassword()"><?= htmlspecialchars(passenger_t('profile.update_password', 'Update Password'), ENT_QUOTES, 'UTF-8') ?></button>
        </div>

    </div>

</div>
</div>
