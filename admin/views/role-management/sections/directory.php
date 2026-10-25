<section class="rmg" id="roleManagementSection">

    <header class="rmg-head">
        <div>
            <h1 id="rmgDirectoryTitle">Role Management</h1>
            <p>Every driver, inspector, dispatcher, finance officer/manager, and administrator account in one place.</p>
        </div>
        <div class="rmg-head-actions">
            <span class="rmg-count" id="rmgCount"><i class="bi bi-people"></i> —</span>
            <button type="button" class="rmg-btn" id="rmgSecurityBtn"><i class="bi bi-shield-lock"></i> Security <span class="rmg-sec-chip" id="rmgSecurityChip" hidden></span></button>
            <button type="button" class="rmg-btn rmg-btn-primary" id="rmgAddBtn"><i class="bi bi-person-plus"></i> Add user</button>
            <button type="button" class="rmg-btn" id="rmgRefresh"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            <button type="button" class="rmg-btn" id="rmgArchivedUsers"><i class="bi bi-archive"></i> Archived Users</button>
            <button type="button" class="rmg-btn" id="rmgExport"><i class="bi bi-filetype-csv"></i> Export CSV</button>
        </div>
    </header>

    <div class="rmg-summary" id="rmgSummary"></div>

    <div class="rmg-filters">
        <div class="rmg-search">
            <i class="bi bi-search"></i>
            <input type="text" id="rmgSearch" placeholder="Search name, ID, email, contact, department...">
        </div>
        <div class="rmg-filter">
            <label for="rmgRole">Role</label>
            <select id="rmgRole"><option value="all">All roles</option></select>
        </div>
        <div class="rmg-filter">
            <label for="rmgStatus">Status</label>
            <select id="rmgStatus"><option value="">All statuses</option></select>
        </div>
        <div class="rmg-filter">
            <label for="rmgDepartment">Department</label>
            <select id="rmgDepartment"><option value="">All departments</option></select>
        </div>
        <div class="rmg-filter">
            <label for="rmgEmployment">Employment</label>
            <select id="rmgEmployment"><option value="">All employment statuses</option></select>
        </div>
        <div class="rmg-filter rmg-date-filter">
            <label>Created</label>
            <div class="rmg-date-range">
                <span class="rmg-date-wrap">
                    <input type="date" id="rmgCreatedFrom" aria-label="Created from date">
                </span>
                <span class="rmg-date-sep" aria-hidden="true">&ndash;</span>
                <span class="rmg-date-wrap">
                    <input type="date" id="rmgCreatedTo" aria-label="Created to date">
                </span>
            </div>
        </div>
        <div class="rmg-filter">
            <label for="rmgSort">Sort</label>
            <select id="rmgSort">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="name_asc">Name A–Z</option>
                <option value="name_desc">Name Z–A</option>
            </select>
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
        <button type="button" class="rmg-btn rmg-btn-sm rmg-btn-clear" id="rmgClearFilters"><i class="bi bi-x-circle"></i> Clear filters</button>
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
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-icon"><i class="bi bi-person-plus"></i></span>
                    <div>
                        <div class="rmg-form-kicker" id="rmgAddKicker">New account</div>
                        <h5 class="modal-title rmg-form-title">Add user</h5>
                        <div class="rmg-form-sub">Creates the account in its existing role table — no duplicate records.</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <section class="rmg-detail-section">
                    <div class="rmg-section-head">
                        <span class="rmg-section-icon tone-role"><i class="bi bi-person-badge"></i></span>
                        <div class="rmg-section-title">Role</div>
                    </div>
                    <div class="rmg-form-grid">
                        <div class="rmg-form-field">
                            <label for="rmgAddRole">Account role</label>
                            <select id="rmgAddRole"></select>
                        </div>
                        <div class="rmg-form-field">
                            <label for="rmgAddUserId">User ID (auto-generated)</label>
                            <div class="rmg-id-field">
                                <input type="text" id="rmgAddUserId" readonly aria-readonly="true" value="Generating...">
                                <span class="rmg-id-state" id="rmgAddUserIdState" aria-hidden="true"><i class="bi bi-arrow-repeat"></i></span>
                            </div>
                            <div class="rmg-field-hint" id="rmgAddUserIdHint">A preview is shown here; the final ID is assigned securely when the account is saved.</div>
                        </div>
                    </div>
                </section>
                <div class="rmg-steps" id="rmgAddSteps" role="tablist" aria-label="Form steps"></div>
                <div class="rmg-form-grid" id="rmgAddFields"></div>
                <div class="rmg-review" id="rmgAddReview" hidden></div>
                <div class="rmg-duplicate-warning" id="rmgAddDuplicates" hidden></div>
                <div class="rmg-error" id="rmgAddError" hidden></div>
                <div class="rmg-note" id="rmgAddNote" hidden></div>
            </div>
            <div class="modal-footer rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn" id="rmgAddBack"><i class="bi bi-arrow-left"></i> Back</button>
                <button type="button" class="rmg-btn" id="rmgAddNext">Next <i class="bi bi-arrow-right"></i></button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="rmgAddSave"><i class="bi bi-check2"></i> Create user</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rmgEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-avatar" id="rmgEditAvatar">—</span>
                    <div>
                        <div class="rmg-form-kicker" id="rmgEditKicker">Edit account</div>
                        <h5 class="modal-title rmg-form-title" id="rmgEditTitle">Edit profile</h5>
                        <div class="rmg-form-sub" id="rmgEditSub">—</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <div class="rmg-steps" id="rmgEditSteps" role="tablist" aria-label="Form steps"></div>
                <div class="rmg-form-grid" id="rmgEditFields"></div>
                <div class="rmg-review" id="rmgEditReview" hidden></div>
                <div class="rmg-error" id="rmgEditError" hidden></div>
            </div>
            <div class="modal-footer rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn" id="rmgEditBack"><i class="bi bi-arrow-left"></i> Back</button>
                <button type="button" class="rmg-btn" id="rmgEditNext">Next <i class="bi bi-arrow-right"></i></button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="rmgEditSave"><i class="bi bi-check2"></i> Save changes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rmgDetailModal" tabindex="-1" aria-labelledby="rmgDetailName" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content rmg-detail-modal">
            <div class="modal-header rmg-detail-header">
                <div class="rmg-detail-identity">
                    <div class="rmg-detail-avatar-wrap" id="rmgDetailAvatarWrap">
                        <div class="rmg-avatar rmg-detail-avatar" id="rmgDetailAvatar">—</div>
                        <span class="rmg-detail-dot" aria-hidden="true"></span>
                    </div>
                    <div class="rmg-detail-who">
                        <div class="rmg-detail-kicker" id="rmgDetailKicker">Account</div>
                        <h5 class="modal-title" id="rmgDetailName">Account</h5>
                        <div id="rmgDetailChips" class="rmg-detail-chips"></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-detail-body" id="rmgDetailBody"></div>
            <div class="modal-footer rmg-detail-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Close</button>
                <div id="rmgDetailActions" class="rmg-detail-actions"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rmgSecurityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rmg-sec-modal">
            <div class="modal-header rmg-sec-header">
                <div class="rmg-sec-head">
                    <span class="rmg-sec-icon"><i class="bi bi-shield-lock"></i></span>
                    <div>
                        <h5 class="modal-title" id="rmgSecurityTitle">Security authorization</h5>
                        <div class="rmg-sec-sub" id="rmgSecuritySummary">—</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="rmg-sec-label" for="rmgSecurityCode">Security Authorization Code</label>
                <input type="password" class="rmg-sec-input" id="rmgSecurityCode" autocomplete="off" placeholder="Enter the code set by the Super Admin">
                <div class="rmg-error" id="rmgSecurityError" hidden></div>
                <div class="rmg-sec-hint"><i class="bi bi-info-circle"></i> The code is single-use and bound to this exact change. It expires after 5 minutes and a new code is needed for every action.</div>
            </div>
            <div class="modal-footer rmg-sec-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="rmgSecurityConfirm"><i class="bi bi-shield-check"></i> Verify &amp; continue</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rmgSecuritySettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content rmg-sec-modal">
            <div class="modal-header rmg-sec-header">
                <div class="rmg-sec-head">
                    <span class="rmg-sec-icon"><i class="bi bi-shield-check"></i></span>
                    <div>
                        <h5 class="modal-title">Security settings</h5>
                        <div class="rmg-sec-sub">Super Admin only · protects every account change</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="rmg-sec-state" id="rmgSecSettingsState">—</div>
                <div class="rmg-sec-meta" id="rmgSecSettingsMeta">—</div>
                <div class="rmg-form-grid">
                    <div class="rmg-form-field full" id="rmgSecCurrentWrap">
                        <label for="rmgSecCurrentCode">Current code *</label>
                        <input type="password" id="rmgSecCurrentCode" autocomplete="off">
                    </div>
                    <div class="rmg-form-field">
                        <label for="rmgSecNewCode">New code *</label>
                        <input type="password" id="rmgSecNewCode" autocomplete="off">
                    </div>
                    <div class="rmg-form-field">
                        <label for="rmgSecConfirmCode">Confirm new code *</label>
                        <input type="password" id="rmgSecConfirmCode" autocomplete="off">
                    </div>
                    <div class="rmg-form-field full">
                        <label for="rmgSecPassword">Your account password *</label>
                        <input type="password" id="rmgSecPassword" autocomplete="off">
                    </div>
                </div>
                <div class="rmg-error" id="rmgSecSettingsError" hidden></div>
                <div class="rmg-sec-hint"><i class="bi bi-exclamation-triangle"></i> Rotating the code immediately cancels every outstanding verification. Share the new code only with the admins who need it.</div>
                <div class="rmg-sec-activity-block">
                    <div class="rmg-block-title"><i class="bi bi-clock-history"></i> Recent security activity</div>
                    <div class="rmg-sec-activity" id="rmgSecActivity"></div>
                </div>
            </div>
            <div class="modal-footer rmg-sec-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Close</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="rmgSecSettingsSave"><i class="bi bi-shield-check"></i> Save security code</button>
            </div>
        </div>
    </div>
</div>
