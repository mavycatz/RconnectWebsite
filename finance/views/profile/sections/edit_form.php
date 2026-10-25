<div style="background:#fff;border:1.5px solid #d0e6d4;border-radius:20px;box-shadow:0 4px 16px rgba(0,0,0,0.04);padding:24px;">

    <h5 style="font-weight:700;color:#2f4f2f;margin-bottom:18px;font-size:1rem;">
        <i class="bi bi-pencil-square me-2" style="color:#198754;"></i>Update Profile
    </h5>

    <form method="POST" action="/finance/api/update_profile.php?token=<?= htmlspecialchars($_GET['token'] ?? '') ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;">

            <div>
                <label style="font-size:0.78rem;font-weight:600;color:#2f4f2f;margin-bottom:4px;display:block;">Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($profileUser['username'] ?? ''); ?>" required
                       style="width:100%;padding:9px 12px;border-radius:10px;border:1.5px solid #d0e6d4;font-size:0.85rem;outline:none;background:#fafefa;">
            </div>

            <div>
                <label style="font-size:0.78rem;font-weight:600;color:#2f4f2f;margin-bottom:4px;display:block;">Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($profileUser['email'] ?? ''); ?>" required
                       style="width:100%;padding:9px 12px;border-radius:10px;border:1.5px solid #d0e6d4;font-size:0.85rem;outline:none;background:#fafefa;">
            </div>

            <div>
                <label style="font-size:0.78rem;font-weight:600;color:#2f4f2f;margin-bottom:4px;display:block;">Department</label>
                <input type="text" name="department" value="<?= htmlspecialchars($profileUser['department'] ?? ''); ?>"
                       style="width:100%;padding:9px 12px;border-radius:10px;border:1.5px solid #d0e6d4;font-size:0.85rem;outline:none;background:#fafefa;">
            </div>

            <div>
                <label style="font-size:0.78rem;font-weight:600;color:#2f4f2f;margin-bottom:4px;display:block;">First Name</label>
                <input type="text" name="first_name" value="<?= htmlspecialchars($profileUser['first_name'] ?? ''); ?>"
                       style="width:100%;padding:9px 12px;border-radius:10px;border:1.5px solid #d0e6d4;font-size:0.85rem;outline:none;background:#fafefa;">
            </div>

            <div>
                <label style="font-size:0.78rem;font-weight:600;color:#2f4f2f;margin-bottom:4px;display:block;">Last Name</label>
                <input type="text" name="last_name" value="<?= htmlspecialchars($profileUser['last_name'] ?? ''); ?>"
                       style="width:100%;padding:9px 12px;border-radius:10px;border:1.5px solid #d0e6d4;font-size:0.85rem;outline:none;background:#fafefa;">
            </div>

            <div>
                <label style="font-size:0.78rem;font-weight:600;color:#2f4f2f;margin-bottom:4px;display:block;">Phone</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($profileUser['phone'] ?? ''); ?>"
                       style="width:100%;padding:9px 12px;border-radius:10px;border:1.5px solid #d0e6d4;font-size:0.85rem;outline:none;background:#fafefa;">
            </div>

            <div>
                <label style="font-size:0.78rem;font-weight:600;color:#2f4f2f;margin-bottom:4px;display:block;">Address</label>
                <input type="text" name="address" value="<?= htmlspecialchars($profileUser['address'] ?? ''); ?>"
                       style="width:100%;padding:9px 12px;border-radius:10px;border:1.5px solid #d0e6d4;font-size:0.85rem;outline:none;background:#fafefa;">
            </div>

            <div>
                <label style="font-size:0.78rem;font-weight:600;color:#2f4f2f;margin-bottom:4px;display:block;">Bio</label>
                <textarea name="bio" rows="1" style="width:100%;padding:9px 12px;border-radius:10px;border:1.5px solid #d0e6d4;font-size:0.85rem;outline:none;background:#fafefa;resize:vertical;"><?= htmlspecialchars($profileUser['bio'] ?? ''); ?></textarea>
            </div>

            <div>
                <label style="font-size:0.78rem;font-weight:600;color:#2f4f2f;margin-bottom:4px;display:block;">New Password</label>
                <input type="password" name="password" placeholder="Optional"
                       style="width:100%;padding:9px 12px;border-radius:10px;border:1.5px solid #d0e6d4;font-size:0.85rem;outline:none;background:#fafefa;">
            </div>

        </div>

        <div style="margin-top:16px;padding:14px;background:#fafefa;border:1.5px dashed #d0e6d4;border-radius:12px;">
            <label style="font-size:0.78rem;font-weight:600;color:#2f4f2f;margin-bottom:6px;display:block;">
                <i class="bi bi-camera me-1" style="color:#198754;"></i>Profile Photo
            </label>
            <div class="d-flex align-items-center gap-3">
                <div style="width:56px;height:56px;border-radius:50%;border:2px solid #d0e6d4;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:#fafefa;">
                    <?php if (!empty($profileUser['profile_photo'])): ?>
                        <img src="/uploads/<?= htmlspecialchars($profileUser['profile_photo']) ?>?t=<?= time() ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <i class="bi bi-person" style="font-size:1.3rem;color:#94a3b8;"></i>
                    <?php endif; ?>
                </div>
                <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/gif,image/webp"
                       style="font-size:0.8rem;color:#555;">
            </div>
            <small style="color:#94a3b8;font-size:0.7rem;">JPG, PNG, GIF, WebP · Max 2MB</small>
        </div>

        <div style="text-align:right;margin-top:16px;">
            <button type="submit"
                    style="background:#198754;border:none;color:white;padding:9px 28px;border-radius:12px;font-size:0.85rem;font-weight:600;cursor:pointer;transition:opacity .2s;"
                    onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                <i class="bi bi-check-lg me-1"></i> Save Changes
            </button>
        </div>

    </form>

</div>