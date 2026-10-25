<div style="padding:32px 28px 28px;font-family:'Poppins',sans-serif;text-align:center;position:relative;">

    <div style="width:64px;height:64px;border-radius:18px;background:#e8f5e9;border:2px solid #a5d6a7;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
        <i class="bi bi-shield-fill-check" style="color:#2e7d32;font-size:28px;"></i>
    </div>

    <button type="button" onclick="document.getElementById('otpModal').style.display='none'" style="position:absolute;top:16px;right:16px;border:none;background:none;font-size:26px;cursor:pointer;color:#999;line-height:1;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:background .2s;" onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='transparent'">&times;</button>

    <h4 style="margin:0 0 6px;font-weight:700;color:#1b3a2a;font-size:20px;">Verify OTP</h4>

    <p style="font-size:13px;color:#6b7a6b;margin:0 0 16px;line-height:1.6;max-width:320px;margin-left:auto;margin-right:auto;">An OTP has been sent to your email address. Enter or paste the 6-digit code below to complete registration.</p>

    <div style="margin-bottom:14px;">
        <button type="button" id="pasteOtpBtn" style="background:#f4f8f3;border:1.5px dashed #a5d6a7;color:#2e7d32;font-size:12px;font-weight:600;padding:6px 16px;border-radius:20px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .2s;" onmouseover="this.style.background='#e8f5e9'" onmouseout="this.style.background='#f4f8f3'">
            <i class="bi bi-clipboard-check"></i> Paste 6-Digit Code
        </button>
    </div>

    <form id="otpVerifyForm" autocomplete="one-time-code" novalidate>

        <div id="regOtpContainer" style="display:flex;justify-content:center;gap:8px;margin-bottom:8px;">
            <?php for ($i = 1; $i <= 6; $i++): ?>
            <input type="text" name="otp_digit_<?= $i ?>" id="regOtp<?= $i ?>" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" required
                   style="width:44px;height:52px;text-align:center;font-size:20px;font-weight:700;color:#24513c;
                          border:2px solid #cdd9b5;border-radius:12px;background:#fff;outline:none;
                          transition:border-color .2s,box-shadow .2s;">
            <?php endfor; ?>
        </div>
        <div style="font-size:11px;color:#8a9a7a;margin-bottom:16px;">You can paste (Ctrl+V) the code anywhere or into any box</div>
        <input type="hidden" name="otp" id="regOtpCombined">

        <button type="submit" id="verifyOtpSubmitBtn" style="width:100%;margin-top:6px;height:50px;border:none;border-radius:14px;background:linear-gradient(135deg,#47613b,#2e7d32);color:#fff;font-size:15px;font-weight:700;cursor:pointer;font-family:'Poppins',sans-serif;transition:opacity .2s,transform .1s;box-shadow:0 4px 14px rgba(46,125,50,.35);" onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'" onmousedown="this.style.transform='scale(.97)'" onmouseup="this.style.transform='scale(1)'">Verify OTP</button>

    </form>

    <div id="otpResponse" style="margin-top:16px;font-size:13px;text-align:center;font-weight:500;"></div>

    <div id="otpResendArea" style="text-align:center;margin-top:22px;padding-top:18px;border-top:1px solid #e8e0c8;">

        <button type="button" id="resendOtpBtn" style="background:transparent;border:2px solid #c4b417;color:#47613b;font-size:13px;font-weight:700;cursor:pointer;border-radius:50px;padding:12px 36px;transition:all .2s;font-family:'Poppins',sans-serif;" onmouseover="this.style.background='#fefcf5'" onmouseout="this.style.background='transparent'">Resend OTP</button>

        <div id="resendTimer" style="font-size:12px;color:#8a9a7a;margin-top:10px;font-weight:500;"></div>

    </div>

</div>
