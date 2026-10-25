<div class="card"
     style="
        background:linear-gradient(145deg,#ffffff,#f6f3d9);
        border:1px solid #e5e0a4;
        border-radius:22px;
        box-shadow:0 10px 25px rgba(0,0,0,0.07);
        padding:25px;
     ">

    <h5 style="font-weight:700;color:#295738;margin-bottom:18px;">
        Update Profile
    </h5>

    <form method="POST" action="/admin/Controllers/update_profile.php" enctype="multipart/form-data">

        <div style="
            display:grid;
            grid-template-columns: repeat(3, 1fr);
            gap:15px;
        ">

            <div>
                <label style="font-size:0.8rem;font-weight:600;color:#295738;margin-bottom:4px;display:block;">
                    Username
                </label>
                <input type="text" name="username"
                       value="<?= htmlspecialchars($profileUser['username'] ?? ''); ?>"
                       required
                       style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #e5e0a4;font-size:0.85rem;">
</div>

<div>
    <label style="font-size:0.8rem;font-weight:600;color:#295738;margin-bottom:4px;display:block;">
                    Email
    </label>
    <input type="email" name="email"
                       value="<?= htmlspecialchars($profileUser['email'] ?? ''); ?>"
                       required
                       style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #e5e0a4;font-size:0.85rem;">
</div>

<div>
    <label style="font-size:0.8rem;font-weight:600;color:#295738;margin-bottom:4px;display:block;">
                    Department
    </label>
    <input type="text" name="department"
                       value="<?= htmlspecialchars($profileUser['department'] ?? ''); ?>"
                       style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #e5e0a4;font-size:0.85rem;">
</div>

<div>
    <label style="font-size:0.8rem;font-weight:600;color:#295738;margin-bottom:4px;display:block;">
                    First Name
    </label>
    <input type="text" name="first_name"
                       value="<?= htmlspecialchars($profileUser['first_name'] ?? ''); ?>"
                       style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #e5e0a4;font-size:0.85rem;">
</div>

<div>
    <label style="font-size:0.8rem;font-weight:600;color:#295738;margin-bottom:4px;display:block;">
                    Last Name
    </label>
    <input type="text" name="last_name"
                       value="<?= htmlspecialchars($profileUser['last_name'] ?? ''); ?>"
                       style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #e5e0a4;font-size:0.85rem;">
</div>

<div>
    <label style="font-size:0.8rem;font-weight:600;color:#295738;margin-bottom:4px;display:block;">
                    Phone
    </label>
    <input type="text" name="phone"
                       value="<?= htmlspecialchars($profileUser['phone'] ?? ''); ?>"
                       style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #e5e0a4;font-size:0.85rem;">
</div>

<div>
    <label style="font-size:0.8rem;font-weight:600;color:#295738;margin-bottom:4px;display:block;">
                    Address
    </label>
    <input type="text" name="address"
                       value="<?= htmlspecialchars($profileUser['address'] ?? ''); ?>"
                       style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #e5e0a4;font-size:0.85rem;">
</div>

<div>
    <label style="font-size:0.8rem;font-weight:600;color:#295738;margin-bottom:4px;display:block;">
                    Bio
    </label>
    <textarea name="bio" rows="1"
                          style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #e5e0a4;font-size:0.85rem;"><?= htmlspecialchars($profileUser['bio'] ?? ''); ?></textarea>
</div>

<div>
    <label style="font-size:0.8rem;font-weight:600;color:#295738;margin-bottom:4px;display:block;">
                    New Password
    </label>
    <input type="password" name="password"
                       placeholder="Optional"
                       style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #e5e0a4;font-size:0.85rem;">
</div>

</div>

<div style="grid-column:span 3;">
    <label style="font-size:0.8rem;font-weight:600;color:#295738;margin-bottom:4px;display:block;">
                    Profile Photo
    </label>
    <div class="d-flex align-items-center gap-3">
        <div style="width:60px;height:60px;border-radius:50%;background:#f9f4da;border:2px solid #cdd9b5;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                        <?php if (!empty($profileUser['profile_photo'])): ?>
                            <img src="/uploads/<?= htmlspecialchars($profileUser['profile_photo']) ?>?t=<?= time() ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <i class="bi bi-person" style="font-size:1.5rem;color:#94a3b8;"></i>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/gif,image/webp"
                           style="font-size:0.8rem;">
                    </div>
                    <small style="color:#94a3b8;font-size:0.7rem;">JPG, PNG, GIF, WebP · Max 2MB</small>
                    </div>

                    </div>

                    <div style="text-align:right;margin-top:15px;">
                        <button type="submit"
                    style="
                        background:linear-gradient(135deg,#295738,#1e3d28);
                        border:none;
                        color:white;
                        padding:8px 22px;
                        border-radius:20px;
                        font-size:0.85rem;
                        font-weight:600;
                        box-shadow:0 6px 14px rgba(0,0,0,0.12);
                        cursor:pointer;
                        transition:opacity .2s;
                    "
                    onmouseover="this.style.opacity='0.85'"
                    onmouseout="this.style.opacity='1'">
                Save
                        </button>
                    </div>

                    </form>

                    </div>