<?php

if (!isset($_SESSION["passenger_id"])) {
    echo "<div style='color:red;'>Session expired</div>";
    exit();
}

require_once $_SERVER["DOCUMENT_ROOT"] . "/app/Core/Database.php";
use App\Core\Database;

$conn = Database::get();

$stmt = $conn->prepare("SELECT * FROM passengers WHERE id = ?");
$stmt->execute([$_SESSION["passenger_id"]]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$name = htmlspecialchars($user["full_name"] ?? "");
$email = htmlspecialchars($user["email"] ?? "");
$phone = htmlspecialchars($user["phone"] ?? "");
$image = !empty($user["id_image"]) ? "/uploads/" . $user["id_image"] : "/assets/images/passenger/profile.png";

$discountStatus = $user["discount_status"] ?? "";
$isApproved = in_array(strtolower(trim((string) $discountStatus)), ['verified', 'approved'], true);

$approvedType = "";
$discountType = trim($user["discount_type"] ?? "");
if ($isApproved && $discountType !== "" && strtolower($discountType) !== "regular") {
    $typeLabels = [
        "Student" => "Student ID",
        "Senior" => "Senior Citizen ID",
        "PWD" => "PWD ID",
        "Nurse" => "Nurse ID",
        "Teacher" => "Teacher ID",
    ];
    $approvedType = htmlspecialchars($typeLabels[$discountType] ?? $discountType . " ID");
}

$discountFile = "";
if ($isApproved && !empty($user["discount_id"])) {
    $raw = $user["discount_id"];
    $discountFile = strpos($raw, "/uploads/") === 0 ? $raw : "/uploads/" . $raw;
}
?>
<style>
.modal-overlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.4);
    display:flex;
    justify-content:center;
    align-items:center;
    padding:10px;
    z-index:9999999;
}
.modal-container{
    width:100%;
    max-width:460px;
    max-height:95vh;
    overflow-y:auto;
    background:#fef8f3;
    border-radius:20px;
    box-shadow:0 20px 50px rgba(0,0,0,.18);
    animation:fadeIn .25s ease;
}
.modal-container .modal-header{
    position:sticky;
    top:0;
    background:#fcf5dd;
    border-bottom:1px solid #e8e0d4;
    z-index:2;
}
@keyframes fadeIn{
    from{opacity:0;transform:scale(.95)}
    to{opacity:1;transform:scale(1)}
}

.modal-header{
    padding:16px 18px 14px;
    position:relative;
    display:flex;
    align-items:center;
    gap:12px;
}
.modal-header-icon{
    width:36px;height:36px;border-radius:11px;
    background:linear-gradient(135deg,#f9f4da,#d4e0b0);
    display:flex;align-items:center;justify-content:center;
    color:#47613b;font-size:16px;flex-shrink:0;
    box-shadow:0 2px 6px rgba(198,212,140,.35);
}
.modal-header-text{
    min-width:0;
    padding-right:32px;
}
.modal-header-text h2{
    margin:0;font-size:16px;font-weight:700;color:#24513c;font-family:'Poppins',sans-serif;
}
.modal-header-text p{
    margin:2px 0 0;font-size:11px;color:#9a8f84;font-family:'Poppins',sans-serif;
}
.close-btn{
    position:absolute;top:13px;right:14px;
    width:28px;height:28px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;background:rgba(0,0,0,.04);color:#8a7e72;
    font-size:14px;transition:.2s;border:none;
}
.close-btn:hover{background:rgba(0,0,0,.08)}
.modal-body{padding:0 18px 20px;background:#fef8f3;}
.modal-body::-webkit-scrollbar{width:4px}
.modal-body::-webkit-scrollbar-thumb{background:#d9d0c4;border-radius:999px}

.profile-row{
    display:flex;align-items:center;gap:14px;
    padding:18px 0 16px;border-bottom:1px solid #eee4d6;margin-bottom:18px;
}
.profile-upload{
    position:relative;cursor:pointer;flex-shrink:0;
}
.profile-upload img{
    width:56px;height:56px;border-radius:50%;object-fit:cover;
    border:3px solid #fff;box-shadow:0 3px 12px rgba(198,212,140,.45);transition:.3s;
    background:#fff;
}
.profile-upload:hover img{box-shadow:0 4px 16px rgba(198,212,140,.55)}
.profile-upload input{display:none}
.edit-overlay{
    position:absolute;right:-1px;bottom:1px;
    width:22px;height:22px;border-radius:50%;
    background:linear-gradient(135deg,#c6d48c,#d4e0b0);color:#47613b;
    display:flex;align-items:center;justify-content:center;
    border:2px solid #fef8f3;font-size:10px;transition:.2s;
}
.profile-upload:hover .edit-overlay{transform:scale(1.1)}
.profile-info{flex:1;min-width:0}
.profile-info .name{font-size:15px;font-weight:700;color:#24513c;font-family:'Poppins',sans-serif;}
.profile-info .sub{font-size:11px;color:#9a8f84;font-family:'Poppins',sans-serif;margin-top:2px;overflow-wrap:anywhere;}

.section-caption{
    font-size:11px;font-weight:700;color:#47613b;
    margin:0 0 12px;font-family:'Poppins',sans-serif;
    letter-spacing:.5px;text-transform:uppercase;
    display:flex;align-items:center;gap:8px;
}
.section-caption::after{
    content:"";flex:1;height:1px;background:#eee4d6;
}

.input-group{margin-bottom:14px}
.input-label{
    font-size:11px;font-weight:600;color:#8a7e72;
    margin-bottom:6px;font-family:'Poppins',sans-serif;letter-spacing:.3px;text-transform:uppercase;
}
.input-wrap{
    display:flex;align-items:center;width:100%;box-sizing:border-box;
    border:1.5px solid #e3dcc8;border-radius:10px;background:#fffdf8;
    min-height:44px;transition:border-color .2s,box-shadow .2s;
}
.input-wrap:focus-within{border-color:#c6d48c;box-shadow:0 0 0 3px rgba(198,212,140,.28)}
.input-icon{
    width:40px;min-width:40px;display:flex;align-items:center;justify-content:center;
    color:#d4c9b8;font-size:14px;transition:color .2s;
}
.input-wrap:focus-within .input-icon{color:#c6d48c}
.input-wrap input{
    flex:1;min-width:0;border:none;outline:none;font-size:14px;font-family:'Poppins',sans-serif;
    font-weight:600;color:#24372b;background:transparent;padding:0 12px 0 0;min-height:44px;
}
.input-wrap input::placeholder{color:#d4c9b8;font-weight:400}

.upload-card{
    background:#fffdf8;border:1.5px solid #e3dcc8;border-radius:12px;
    padding:16px;transition:border-color .2s;
}
.upload-card:focus-within{border-color:#c6d48c}
.upload-title{font-size:13px;font-weight:700;color:#24513c;font-family:'Poppins',sans-serif;}
.upload-title i{color:#7fa354;margin-right:6px}
.upload-desc{font-size:11px;color:#9a8f84;line-height:1.45;margin-top:5px;font-family:'Poppins',sans-serif;}

.verify-status{margin-top:14px}
.status-pill{
    display:inline-flex;align-items:center;gap:5px;
    padding:5px 14px;border-radius:999px;
    font-size:11px;font-weight:700;font-family:'Poppins',sans-serif;
}
.verify-helper{
    display:flex;align-items:center;gap:6px;
    font-size:11px;color:#3d6b3d;font-weight:500;
    margin-top:8px;font-family:'Poppins',sans-serif;
}

.verify-meta{
    display:flex;align-items:center;justify-content:space-between;
    flex-wrap:wrap;gap:6px 10px;
    margin-top:12px;padding:10px 12px;
    background:#faf7ec;border:1px dashed #d8d3b8;border-radius:10px;
}
.verify-meta-label{
    font-size:10px;font-weight:700;color:#8a7e72;
    letter-spacing:.5px;text-transform:uppercase;font-family:'Poppins',sans-serif;
}
.verify-meta-value{
    display:inline-flex;align-items:center;gap:6px;
    padding:3px 12px;border-radius:999px;
    background:#e2f0da;color:#2f6b3a;
    font-size:11px;font-weight:700;font-family:'Poppins',sans-serif;
}

.view-id-btn{
    display:flex;align-items:center;justify-content:center;gap:7px;
    width:100%;box-sizing:border-box;margin-top:12px;
    padding:10px 14px;border-radius:10px;
    border:1.5px solid #cdd9b5;background:#fff;color:#47613b;
    font-size:12px;font-weight:600;font-family:'Poppins',sans-serif;
    text-decoration:none;transition:.2s;
}
.view-id-btn:hover{background:#f4f7e8;border-color:#b9c98f}
.view-id-btn i{color:#7fa354}

.upload-card input[type="file"]{
    margin-top:12px;font-size:12px;font-family:'Poppins',sans-serif;width:100%;max-width:100%;box-sizing:border-box;color:#9a8f84;
}
.upload-card input[type="file"]::file-selector-button{
    padding:8px 14px;border:1.5px solid #cdd9b5;border-radius:8px;max-width:100%;
    background:linear-gradient(135deg,#eef3da,#e3eabb);color:#47613b;font-weight:600;font-size:11px;cursor:pointer;margin-right:10px;transition:.2s;font-family:'Poppins',sans-serif;
}
.upload-card input[type="file"]::file-selector-button:hover{background:linear-gradient(135deg,#e3eabb,#eef3da)}

.save-btn{
    width:100%;height:48px;border:none;border-radius:12px;
    background:linear-gradient(135deg,#c6d48c,#a9c170);
    color:#24513c;font-size:15px;font-weight:700;font-family:'Poppins',sans-serif;
    margin-top:8px;cursor:pointer;transition:.25s;letter-spacing:.3px;
    box-shadow:0 6px 18px rgba(140,170,96,.35);
}
.save-btn:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(140,170,96,.45)}
.save-btn:active{transform:scale(.98)}
@media (max-width:480px){
    .modal-body{padding:0 14px 16px}
    .profile-row{padding:14px 0 12px;gap:12px}
    .profile-upload img{width:48px;height:48px}
    .profile-info .name{font-size:14px}
    .input-wrap{min-height:42px}
    .input-wrap input{font-size:16px;min-height:42px}
    .input-icon{width:36px;min-width:36px;font-size:13px}
    .modal-header{padding:13px 14px 11px}
    .modal-header-text h2{font-size:15px}
    .upload-card{padding:14px}
    .save-btn{height:46px}
}
@media (max-width:360px){
    .modal-body{padding:0 12px 14px}
    .modal-header{padding:12px 12px 10px}
    .close-btn{top:12px;right:12px}
    .upload-card{padding:12px}
    .profile-row{margin-bottom:14px}
    .input-group{margin-bottom:12px}
}
</style>
<div class="modal-overlay" id="editProfileModal" onclick="if(event.target===this)confirmCloseModal()">

    <div class="modal-container">

        <div class="modal-header">
            <div class="modal-header-icon">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="modal-header-text">
                <h2><?= htmlspecialchars(passenger_t('profile.edit_title', 'Edit Profile'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars(passenger_t('profile.edit_subtitle', 'Update your personal information'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <button class="close-btn" onclick="closeEditProfileModal()">✕</button>
        </div>

        <div class="modal-body">

            <form method="POST" action="/passenger/profile/update" enctype="multipart/form-data">
<?php if (function_exists("passenger_csrf_token")): ?>
<input type="hidden" name="_csrf" value="<?= htmlspecialchars(passenger_csrf_token(), ENT_QUOTES, "UTF-8") ?>">
<?php endif; ?>

                <div class="profile-row">
                    <label class="profile-upload">
                        <img id="profilePreview" src="<?php echo $image; ?>">
                        <input type="file" name="profile_image" id="profileInput" accept="image/*">
                        <div class="edit-overlay">
                            <i class="bi bi-camera-fill"></i>
                        </div>
                    </label>
                    <div class="profile-info">
                        <div class="name"><?php echo $name; ?></div>
                        <div class="sub"><?php echo $email; ?></div>
                    </div>
                </div>

                <div class="section-caption"><?= htmlspecialchars(passenger_t('profile.section_personal', 'Personal Information'), ENT_QUOTES, 'UTF-8') ?></div>

                <div class="input-group">
                    <div class="input-label"><?= htmlspecialchars(passenger_t('profile.full_name', 'Full Name'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="input-wrap">
                        <div class="input-icon"><i class="bi bi-person-fill"></i></div>
                        <input type="text" name="full_name" value="<?php echo $name; ?>" minlength="2" maxlength="100" autocomplete="name" required>
                    </div>
                </div>

                <div class="input-group">
                    <div class="input-label"><?= htmlspecialchars(passenger_t('profile.email_address', 'Email Address'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="input-wrap">
                        <div class="input-icon"><i class="bi bi-envelope-fill"></i></div>
                        <input type="email" name="email" value="<?php echo $email; ?>" maxlength="100" autocomplete="email" required>
                    </div>
                </div>

                <div class="input-group">
                    <div class="input-label"><?= htmlspecialchars(passenger_t('profile.mobile_number', 'Mobile Number'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="input-wrap">
                        <div class="input-icon"><i class="bi bi-phone-fill"></i></div>
                        <input type="tel" name="phone" value="<?php echo $phone; ?>" maxlength="14" autocomplete="tel" inputmode="tel" required>
                    </div>
                </div>

                <div class="section-caption"><?= htmlspecialchars(passenger_t('profile.section_verification', 'Verification'), ENT_QUOTES, 'UTF-8') ?></div>

                <div class="upload-card">
                    <div class="upload-title"><i class="bi bi-file-earmark-text"></i> <?= htmlspecialchars(passenger_t('profile.valid_id', 'Valid ID'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="upload-desc"><?= htmlspecialchars(passenger_t('profile.upload_desc', 'Upload a school ID, senior citizen ID, PWD ID, or other supporting document.'), ENT_QUOTES, 'UTF-8') ?></div>

                    <div class="verify-status">
                        <span class="status-pill" style="background:<?php echo $isApproved
                            ? "#e2f0da"
                            : "#f7f0d6"; ?>;color:<?php echo $isApproved ? "#2f6b3a" : "#8a7a3a"; ?>;">
                            <?php echo $isApproved
                                ? '<i class="bi bi-check-circle-fill"></i> ' . htmlspecialchars(passenger_t('profile.approved', 'Approved'), ENT_QUOTES, 'UTF-8')
                                : '<i class="bi bi-clock-fill"></i> Pending Review'; ?>
                        </span>
                        <?php if ($isApproved): ?>
                        <div class="verify-helper">
                            <i class="bi bi-check2-circle"></i> <?= htmlspecialchars(passenger_t('profile.id_verified', 'Your ID has been verified.'), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($isApproved && $approvedType !== ""): ?>
                    <div class="verify-meta">
                        <span class="verify-meta-label"><?= htmlspecialchars(passenger_t('profile.approved_id_type', 'Approved ID Type'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="verify-meta-value"><?php echo $approvedType; ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($isApproved && $discountFile !== ""): ?>
                    <a class="view-id-btn" href="<?php echo $discountFile; ?>" target="_blank" rel="noopener">
                        <i class="bi bi-eye-fill"></i> <?= htmlspecialchars(passenger_t('profile.view_id', 'View ID'), ENT_QUOTES, 'UTF-8') ?> <i class="bi bi-arrow-right"></i>
                    </a>
                    <?php endif; ?>

                    <input type="file" name="discount_id" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf">
                </div>

                <button type="submit" class="save-btn"><?= htmlspecialchars(passenger_t('profile.save_changes', 'Save Changes'), ENT_QUOTES, 'UTF-8') ?></button>

            </form>

        </div>

    </div>

</div>
