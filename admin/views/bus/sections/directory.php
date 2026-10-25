<section class="rmg" id="busManagementSection">

    <header class="rmg-head">
        <div>
            <h1>Bus Management</h1>
            <p>Every registered bus, its assignments, compliance dates, and QR identity in one place.</p>
        </div>
        <div class="rmg-head-actions">
            <span class="rmg-count" id="busCount"><i class="bi bi-bus-front"></i> —</span>
            <button type="button" class="rmg-btn" id="busSecurityBtn"><i class="bi bi-shield-lock"></i> Security <span class="rmg-sec-chip" id="busSecurityChip" hidden></span></button>
            <button type="button" class="rmg-btn rmg-btn-primary" id="busAddBtn"><i class="bi bi-plus-circle"></i> Add bus</button>
            <button type="button" class="rmg-btn" id="busBulkQr"><i class="bi bi-qr-code-scan"></i> Bulk QR</button>
            <button type="button" class="rmg-btn" id="busRefresh"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
            <button type="button" class="rmg-btn" id="busExport"><i class="bi bi-filetype-csv"></i> Export CSV</button>
        </div>
    </header>

    <div class="rmg-summary" id="busSummary"></div>

    <div class="rmg-filters">
        <div class="rmg-search">
            <i class="bi bi-search"></i>
            <input type="text" id="busSearch" placeholder="Search bus ID, plate, unit number, driver, route...">
        </div>
        <div class="rmg-filter">
            <label for="busStatus">Status</label>
            <select id="busStatus"><option value="">All statuses</option></select>
        </div>
        <div class="rmg-filter">
            <label for="busType">Type</label>
            <select id="busType"><option value="">All types</option></select>
        </div>
        <div class="rmg-filter">
            <label for="busRoute">Route</label>
            <select id="busRoute"><option value="">All routes</option></select>
        </div>
        <div class="rmg-filter">
            <label for="busDriver">Driver</label>
            <select id="busDriver"><option value="">All drivers</option></select>
        </div>
        <div class="rmg-filter rmg-date-filter">
            <label>Registered</label>
            <div class="rmg-date-range">
                <span class="rmg-date-wrap">
                    <input type="date" id="busCreatedFrom" aria-label="Registered from date">
                </span>
                <span class="rmg-date-sep" aria-hidden="true">&ndash;</span>
                <span class="rmg-date-wrap">
                    <input type="date" id="busCreatedTo" aria-label="Registered to date">
                </span>
            </div>
        </div>
        <div class="rmg-filter">
            <label for="busSort">Sort</label>
            <select id="busSort">
                <option value="bus_asc">Bus ID A–Z</option>
                <option value="bus_desc">Bus ID Z–A</option>
                <option value="plate_asc">Plate A–Z</option>
                <option value="plate_desc">Plate Z–A</option>
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
            </select>
        </div>
        <div class="rmg-filter">
            <label for="busPerPage">Per page</label>
            <select id="busPerPage">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
        <button type="button" class="rmg-btn rmg-btn-sm rmg-btn-clear" id="busClearFilters"><i class="bi bi-x-circle"></i> Clear filters</button>
        <button type="button" class="rmg-btn rmg-btn-sm" id="busArchivedBtn"><i class="bi bi-archive"></i> Archived</button>
    </div>

    <div class="rmg-list" id="busList">
        <div class="rmg-state"><i class="bi bi-hourglass-split"></i><h6>Loading buses...</h6>Fetching the bus directory.</div>
    </div>

    <div class="rmg-pagination">
        <span class="rmg-pg-info" id="busPgInfo"></span>
        <div class="rmg-pager" id="busPager"></div>
    </div>

</section>

<div class="modal fade" id="busAddModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-icon"><i class="bi bi-bus-front"></i></span>
                    <div>
                        <div class="rmg-form-kicker" id="busAddKicker">New bus</div>
                        <h5 class="modal-title rmg-form-title">Add bus</h5>
                        <div class="rmg-form-sub">The Bus ID is generated automatically and the QR code is created when the bus is saved.</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <section class="rmg-detail-section">
                    <div class="rmg-section-head">
                        <span class="rmg-section-icon tone-role"><i class="bi bi-hash"></i></span>
                        <div class="rmg-section-title">Bus ID</div>
                    </div>
                    <div class="rmg-form-field">
                        <label for="busAddUserId">Bus ID (auto-generated)</label>
                        <div class="rmg-id-field">
                            <input type="text" id="busAddUserId" readonly aria-readonly="true" value="Generating...">
                            <span class="rmg-id-state" id="busAddUserIdState" aria-hidden="true"><i class="bi bi-arrow-repeat"></i></span>
                        </div>
                        <div class="rmg-field-hint" id="busAddUserIdHint">A preview is shown here; the final ID is allocated securely when the bus is saved.</div>
                    </div>
                </section>
                <div class="rmg-steps" id="busAddSteps" role="tablist" aria-label="Form steps"></div>
                <div class="rmg-form-grid" id="busAddFields"></div>
                <div class="rmg-review" id="busAddReview" hidden></div>
                <div class="rmg-duplicate-warning" id="busAddDuplicates" hidden></div>
                <div class="rmg-error" id="busAddError" hidden></div>
            </div>
            <div class="modal-footer rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn" id="busAddBack"><i class="bi bi-arrow-left"></i> Back</button>
                <button type="button" class="rmg-btn" id="busAddNext">Next <i class="bi bi-arrow-right"></i></button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="busAddSave"><i class="bi bi-check2"></i> Register bus</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="busEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-avatar" id="busEditAvatar">—</span>
                    <div>
                        <div class="rmg-form-kicker" id="busEditKicker">Edit bus</div>
                        <h5 class="modal-title rmg-form-title" id="busEditTitle">Edit bus</h5>
                        <div class="rmg-form-sub" id="busEditSub">—</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <div class="rmg-steps" id="busEditSteps" role="tablist" aria-label="Form steps"></div>
                <div class="rmg-form-grid" id="busEditFields"></div>
                <div class="rmg-review" id="busEditReview" hidden></div>
                <div class="rmg-error" id="busEditError" hidden></div>
            </div>
            <div class="modal-footer rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn" id="busEditBack"><i class="bi bi-arrow-left"></i> Back</button>
                <button type="button" class="rmg-btn" id="busEditNext">Next <i class="bi bi-arrow-right"></i></button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="busEditSave"><i class="bi bi-check2"></i> Save changes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="busRestoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-avatar" id="busRestoreAvatar">—</span>
                    <div>
                        <div class="rmg-form-kicker" id="busRestoreKicker">Restore bus</div>
                        <h5 class="modal-title rmg-form-title" id="busRestoreTitle">Restore bus</h5>
                        <div class="rmg-form-sub" id="busRestoreSub">—</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <div class="rmg-note"><i class="bi bi-info-circle"></i> Enter the bus details to keep for this bus. Fields you do not change keep their stored values, and the bus keeps its original ID, QR code, and records.</div>
                <div class="rmg-steps" id="busRestoreSteps" role="tablist" aria-label="Restore form steps"></div>
                <div class="rmg-form-grid" id="busRestoreFields"></div>
                <div class="rmg-review" id="busRestoreReview" hidden></div>
                <div class="rmg-error" id="busRestoreError" hidden></div>
            </div>
            <div class="modal-footer rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn" id="busRestoreBack"><i class="bi bi-arrow-left"></i> Back</button>
                <button type="button" class="rmg-btn" id="busRestoreNext">Next <i class="bi bi-arrow-right"></i></button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="busRestoreSave"><i class="bi bi-arrow-counterclockwise"></i> Save details &amp; restore</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="busDetailModal" tabindex="-1" aria-labelledby="busDetailName" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content rmg-detail-modal">
            <div class="modal-header rmg-detail-header">
                <div class="rmg-detail-identity">
                    <div class="rmg-detail-avatar-wrap" id="busDetailAvatarWrap">
                        <div class="rmg-avatar rmg-detail-avatar" id="busDetailAvatar"><i class="bi bi-bus-front"></i></div>
                        <span class="rmg-detail-dot" aria-hidden="true"></span>
                    </div>
                    <div class="rmg-detail-who">
                        <div class="rmg-detail-kicker" id="busDetailKicker">Bus</div>
                        <h5 class="modal-title" id="busDetailName">Bus</h5>
                        <div id="busDetailChips" class="rmg-detail-chips"></div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-detail-body" id="busDetailBody"></div>
            <div class="modal-footer rmg-detail-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Close</button>
                <div id="busDetailActions" class="rmg-detail-actions"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="busArchivedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-icon"><i class="bi bi-archive"></i></span>
                    <div>
                        <div class="rmg-form-kicker">Archived buses</div>
                        <h5 class="modal-title rmg-form-title">Archived buses</h5>
                        <div class="rmg-form-sub">Archived buses keep their Bus ID, QR history, and records.</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <div id="busArchivedList"><div class="rmg-state"><i class="bi bi-hourglass-split"></i><h6>Loading archived buses...</h6></div></div>
            </div>
            <div class="modal-footer rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="busBulkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-icon"><i class="bi bi-qr-code-scan"></i></span>
                    <div>
                        <div class="rmg-form-kicker">Bulk QR preparation</div>
                        <h5 class="modal-title rmg-form-title">Prepare QR codes</h5>
                        <div class="rmg-form-sub">Existing buses without a QR receive one; valid codes are never replaced.</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <div class="rmg-note" id="busBulkSummary">Checking buses without a QR code...</div>
                <div id="busBulkList"></div>
                <div class="rmg-block-title" style="margin-top:12px;"><i class="bi bi-printer"></i> Printable QR sheet</div>
                <div class="rmg-field-hint">One QR per bus with its Bus ID and Unit Number below, ready to print or save as PDF.</div>
            </div>
            <div class="modal-footer rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Close</button>
                <button type="button" class="rmg-btn" id="busBulkPdf"><i class="bi bi-file-earmark-pdf"></i> Generate QR PDF</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="busBulkPrepare"><i class="bi bi-magic"></i> Prepare missing QR codes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="busSecurityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rmg-sec-modal">
            <div class="modal-header rmg-sec-header">
                <div class="rmg-sec-head">
                    <span class="rmg-sec-icon"><i class="bi bi-shield-lock"></i></span>
                    <div>
                        <h5 class="modal-title" id="busSecurityTitle">Security authorization</h5>
                        <div class="rmg-sec-sub" id="busSecuritySummary">—</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="rmg-sec-label" for="busSecurityCode">Security Authorization Code</label>
                <input type="password" class="rmg-sec-input" id="busSecurityCode" autocomplete="off" placeholder="Enter the code set by the Super Admin">
                <div class="rmg-error" id="busSecurityError" hidden></div>
                <div class="rmg-sec-hint"><i class="bi bi-info-circle"></i> The code is single-use and bound to this exact change. It expires after 5 minutes and a new code is needed for every action.</div>
            </div>
            <div class="modal-footer rmg-sec-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="busSecurityConfirm"><i class="bi bi-shield-check"></i> Verify &amp; continue</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="busSecSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content rmg-sec-modal">
            <div class="modal-header rmg-sec-header">
                <div class="rmg-sec-head">
                    <span class="rmg-sec-icon"><i class="bi bi-shield-check"></i></span>
                    <div>
                        <h5 class="modal-title">Security settings</h5>
                        <div class="rmg-sec-sub">Super Admin only · protects every bus and account change</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="rmg-sec-state" id="busSecSettingsState">—</div>
                <div class="rmg-sec-meta" id="busSecSettingsMeta">—</div>
                <div class="rmg-form-grid">
                    <div class="rmg-form-field full" id="busSecCurrentWrap">
                        <label for="busSecCurrentCode">Current code *</label>
                        <input type="password" id="busSecCurrentCode" autocomplete="off">
                    </div>
                    <div class="rmg-form-field">
                        <label for="busSecNewCode">New code *</label>
                        <input type="password" id="busSecNewCode" autocomplete="off">
                    </div>
                    <div class="rmg-form-field">
                        <label for="busSecConfirmCode">Confirm new code *</label>
                        <input type="password" id="busSecConfirmCode" autocomplete="off">
                    </div>
                    <div class="rmg-form-field full">
                        <label for="busSecPassword">Your account password *</label>
                        <input type="password" id="busSecPassword" autocomplete="off">
                    </div>
                </div>
                <div class="rmg-error" id="busSecSettingsError" hidden></div>
                <div class="rmg-sec-hint"><i class="bi bi-exclamation-triangle"></i> Rotating the code immediately cancels every outstanding verification.</div>
                <div class="rmg-sec-activity-block">
                    <div class="rmg-block-title"><i class="bi bi-clock-history"></i> Recent security activity</div>
                    <div class="rmg-sec-activity" id="busSecActivity"></div>
                </div>
            </div>
            <div class="modal-footer rmg-sec-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Close</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="busSecSettingsSave"><i class="bi bi-shield-check"></i> Save security code</button>
            </div>
        </div>
    </div>
</div>
