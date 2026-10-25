<form method="POST" action="/passenger/register" enctype="multipart/form-data">
<?php if (function_exists("passenger_csrf_token")): ?>
<input type="hidden" name="_csrf" value="<?= htmlspecialchars(passenger_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
    <button type="button" onclick="history.back()" style="border:none;background:none;display:flex;align-items:center;gap:5px;font-weight:600;">
        <i class="bi bi-arrow-left"></i> Back
    </button>

    <select id="languageSelect" class="form-select" style="width:75px;font-size:12px;padding:2px 6px;">
        <option value="en">EN</option>
        <option value="tl">TL</option>
        <option value="ilo">ILO</option>
    </select>
</div>

<div style="display:flex; align-items:center; margin-bottom:15px;">
    <div id="circle1" style="width:28px;height:28px;border-radius:50%;background:#4b794d;color:#fff;display:flex;align-items:center;justify-content:center;">
        <i class="bi bi-person-fill"></i>
    </div>

    <div style="flex:1;height:3px;background:#ccc;margin:0 6px;">
        <div id="progressLine" style="height:100%;width:0%;background:#4b794d;"></div>
    </div>

    <div id="circle2" style="width:28px;height:28px;border-radius:50%;background:#ccc;color:#fff;display:flex;align-items:center;justify-content:center;">
        <i class="bi bi-shield-fill-check"></i>
    </div>
</div>

<div id="step1" style="display:flex; flex-direction:column; gap:10px;">

<div>
<label style="display:flex;gap:6px;">
<i class="bi bi-person-fill" style="color:#47613b;"></i>
<span data-en="Full Name" data-tl="Buong Pangalan" data-ilo="Naanay a Nagan">Full Name</span>
</label>
<input type="text" name="full_name" class="form-control form-control-sm" placeholder="Maria Santos" minlength="2" maxlength="100" autocomplete="name" required>
</div>

<div>
<label style="display:flex;gap:6px;">
<i class="bi bi-envelope-fill" style="color:#47613b;"></i>
<span>Email Address</span>
</label>
<input type="email" name="email" class="form-control form-control-sm" placeholder="maria@gmail.com" maxlength="100" autocomplete="email" required>
</div>

<div>
<label style="display:flex;gap:6px;">
<i class="bi bi-telephone-fill" style="color:#47613b;"></i>
<span>Phone Number</span>
</label>
<input type="tel" name="phone" class="form-control form-control-sm" placeholder="09123456789" maxlength="14" inputmode="tel" autocomplete="tel" required>
</div>

<div>
<label style="display:flex;gap:6px;">
<i class="bi bi-lock-fill" style="color:#47613b;"></i>
<span data-lang="password">Password</span>
</label>
<div class="password-input-wrapper">
<input type="password" name="password" id="pass" class="form-control form-control-sm" minlength="8" maxlength="72" required style="padding-right:42px;">
<button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('pass', 'passEyeIcon')" aria-label="Show password" title="Show password">
    <i id="passEyeIcon" class="bi bi-eye-slash"></i>
</button>
</div>
</div>

<div>
<label style="display:flex;gap:6px;">
<i class="bi bi-shield-fill-check" style="color:#47613b;"></i>
<span data-lang="confirm_password">Confirm Password</span>
</label>
<div class="password-input-wrapper">
<input type="password" name="confirm_password" id="confirm" class="form-control form-control-sm" minlength="8" maxlength="72" required style="padding-right:42px;">
<button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('confirm', 'confirmEyeIcon')" aria-label="Show password" title="Show password">
    <i id="confirmEyeIcon" class="bi bi-eye-slash"></i>
</button>
</div>
</div>

<button type="button" class="btn btn-success w-100 btn-sm" onclick="nextStep()">Continue</button>

</div>

<style>
.discount-card {
    border:1.5px solid #c6d48c;
    border-radius:14px;
    padding:12px 8px;
    text-align:center;
    background:#fcf5dd;
    cursor:pointer;
    transition:0.2s;
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
    border-radius:10px;
    background:#fff;
    border:1.5px solid #c6d48c;
    color:#47613b;
    font-size:18px;
}
.discount-card span {
    font-size:12px;
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
</style>

<div id="step2" style="display:none;">

<div style="margin-bottom:12px;">
<label style="font-weight:600;">
<i class="bi bi-tag-fill" style="color:#47613b;"></i> Discount Type
</label>

<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:8px;">

<label class="discount-card active">
<input type="radio" name="discount" value="Regular" hidden checked>
<i class="bi bi-person"></i>
<span>Regular</span>
</label>

<label class="discount-card">
<input type="radio" name="discount" value="Student" hidden>
<i class="bi bi-mortarboard-fill"></i>
<span>Student</span>
</label>

<label class="discount-card">
<input type="radio" name="discount" value="Senior" hidden>
<i class="bi bi-person-badge-fill"></i>
<span>Senior / PWD</span>
</label>

<label class="discount-card">
<input type="radio" name="discount" value="Special" hidden>
<i class="bi bi-heart-pulse-fill"></i>
<span>Teacher / Nurse</span>
</label>

</div>
</div>

<div id="studentField" style="display:none; margin-bottom:10px;">
<input type="text" name="student_id" class="form-control form-control-sm" placeholder="Student ID Number">
</div>

<div id="uploadID" style="display:none; margin-bottom:10px;">
<div style="border:1.5px solid #d2c27e;border-radius:14px;padding:12px;background:#fff;">

<div style="display:flex; gap:10px; align-items:center;">
<div style="width:40px;height:40px;border-radius:10px;border:1.5px solid #d2c27e;display:flex;align-items:center;justify-content:center;background:#fff;">
<i class="bi bi-card-image" style="color:#47613b;"></i>
</div>

<div>
<div style="font-weight:600;font-size:12px;">Upload ID</div>
<div style="font-size:11px;color:#6c757d;">Take or upload a clear ID photo</div>
</div>
</div>

<input type="file" name="id_image" class="form-control form-control-sm mt-2" accept="image/*">
</div>
</div>

<div style="border:1.5px solid #d2c27e;border-radius:14px;padding:12px;background:#f9fbf8;">

<div style="display:flex; gap:10px;">
<div style="width:40px;height:40px;border-radius:10px;border:1.5px solid #d2c27e;display:flex;align-items:center;justify-content:center;background:#fff;">
<i class="bi bi-geo-alt-fill" style="color:#47613b;"></i>
</div>

<div>
<div style="font-weight:600;font-size:12px;">Location Access</div>
<div style="font-size:11px;color:#6c757d;">We need your location for routes and stops.</div>
</div>
</div>

<button type="button" class="btn btn-success btn-sm w-100 mt-2" onclick="requestLocation()">Allow Location</button>

<input type="hidden" name="latitude" id="lat">
<input type="hidden" name="longitude" id="lng">

</div>

<div style="display:flex;gap:6px;margin-top:12px;">
<button type="button" class="btn btn-secondary btn-sm w-100" onclick="prevStep()">Back</button>
<button type="submit" class="btn btn-success btn-sm w-100">Create</button>
</div>

</div>
</form>
</div>
</div>
