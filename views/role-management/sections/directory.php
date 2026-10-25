<section class="rmg" id="roleManagementSection">

    <header class="rmg-head">
        <div>
            <h1>Role Management</h1>
            <p>Every driver, inspector, dispatcher, finance officer/manager, and administrator account in one place.</p>
        </div>
        <div class="rmg-head-actions">
            <span class="rmg-count" id="rmgCount"><i class="bi bi-people"></i> —</span>
            <button type="button" class="rmg-btn rmg-btn-primary" id="rmgAddBtn"><i class="bi bi-person-plus"></i> Add user</button>
            <button type="button" class="rmg-btn" id="rmgRefresh"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        </div>
    </header>

    <div class="rmg-tabs" id="rmgTabs"></div>

    <div class="rmg-filters">
        <div class="rmg-search">
            <i class="bi bi-search"></i>
            <input type="text" id="rmgSearch" placeholder="Search name, ID, email, contact, department...">
        </div>
        <div class="rmg-filter">
            <label for="rmgStatus">Status</label>
            <select id="rmgStatus"><option value="">All statuses</option></select>
        </div>
        <div class="rmg-filter">
            <label for="rmgPerPage">Per page</label>
            <select id="rmgPerPage">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    <div class="rmg-list" id="rmgList">
        <div class="rmg-state"><i class="bi bi-hourglass-split"></i><h6>Loading accounts...</h6>Fetching the role directory.</div>
    </div>

    <div class="rmg-pagination">
        <span class="rmg-pg-info" id="rmgPgInfo"></span>
        <div class="rmg-pager" id="rmgPager"></div>
    </div>

</section>

<div class="modal fade" id="rmgAddModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border:1px solid #e5e0a4;border-radius:20px;background:#fffdf8;">
            <div class="modal-header" style="border-bottom:1px solid #f0ede0;padding:14px 20px;background:#fef8f3;border-radius:18px 18px 0 0;">
                <div>
                    <h5 class="modal-title" style="font-size:.95rem;font-weight:700;color:#295738;"><i class="bi bi-person-plus me-1"></i> Add user</h5>
                    <div style="font-size:.72rem;color:#8a9a8a;">Creates the account in its existing role table — no duplicate records.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:16px 20px;">
                <div class="rmg-form-field" style="margin-bottom:10px;">
                    <label for="rmgAddRole">Role</label>
                    <select id="rmgAddRole"></select>
                </div>
                <div class="rmg-form-grid" id="rmgAddFields"></div>
                <div class="rmg-error" id="rmgAddError" hidden></div>
                <div class="rmg-note" id="rmgAddNote" hidden></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0ede0;padding:12px 20px;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="rmgAddSave"><i class="bi bi-check2"></i> Create user</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rmgEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border:1px solid #e5e0a4;border-radius:20px;background:#fffdf8;">
            <div class="modal-header" style="border-bottom:1px solid #f0ede0;padding:14px 20px;background:#fef8f3;border-radius:18px 18px 0 0;">
                <div>
                    <h5 class="modal-title" id="rmgEditTitle" style="font-size:.95rem;font-weight:700;color:#295738;">Edit profile</h5>
                    <div id="rmgEditSub" style="font-size:.72rem;color:#8a9a8a;">—</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:16px 20px;">
                <div class="rmg-form-grid" id="rmgEditFields"></div>
                <div class="rmg-error" id="rmgEditError" hidden></div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0ede0;padding:12px 20px;display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="rmgEditSave"><i class="bi bi-check2"></i> Save changes</button>
            </div>
        </div>
    </div>
</div>
