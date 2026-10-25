<section class="rmg" id="dispatchQueueSection">

    <header class="rmg-head">
        <div>
            <h1>Bus Dispatch Queue</h1>
            <p>Assign each bus and driver to a terminal, arrange the official order, and publish what the Dispatcher follows. A bus is dispatched from its current terminal and joins the receiving terminal's queue after its arrival is confirmed.</p>
        </div>
        <div class="rmg-head-actions">
            <span class="rmg-count" id="dqCount"><i class="bi bi-list-ol"></i> &mdash;</span>
            <span class="dq-chip" id="dqStateChip" hidden></span>
            <button type="button" class="rmg-btn rmg-btn-primary" id="dqPublishBtn"><i class="bi bi-megaphone"></i> Publish queue</button>
            <button type="button" class="rmg-btn" id="dqSaveBtn"><i class="bi bi-save"></i> Save draft</button>
            <button type="button" class="rmg-btn" id="dqHistoryBtn"><i class="bi bi-clock-history"></i> History</button>
            <button type="button" class="rmg-btn" id="dqPrintBtn"><i class="bi bi-printer"></i> Print</button>
            <button type="button" class="rmg-btn" id="dqRefresh"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        </div>
    </header>

    <div class="rmg-summary" id="dqSummary"></div>

    <div class="rmg-filters">
        <div class="rmg-filter rmg-date-filter">
            <label for="dqDate">Operating date</label>
            <span class="rmg-date-wrap">
                <input type="date" id="dqDate" aria-label="Operating date">
            </span>
            <span class="dq-date-status" id="dqDateStatus" aria-live="polite"></span>
        </div>
        <div class="rmg-filter">
            <label for="dqTerminal">Terminal</label>
            <select id="dqTerminal">
                <option value="">All terminals</option>
            </select>
        </div>
        <div class="rmg-search">
            <i class="bi bi-search"></i>
            <input type="text" id="dqSearch" placeholder="Search bus number, plate, or driver...">
        </div>
        <button type="button" class="rmg-btn rmg-btn-sm rmg-btn-clear" id="dqClearFilters"><i class="bi bi-x-circle"></i> Clear filters</button>
    </div>

    <div class="dq-history-notice" id="dqHistoryNotice" aria-live="polite" hidden></div>

    <div class="rmg-list" id="dqList">
        <div class="rmg-state"><i class="bi bi-hourglass-split"></i><h6>Loading dispatch queue...</h6>Fetching buses, drivers, and today's trips.</div>
    </div>

    <p class="dq-hint">
        <i class="bi bi-info-circle"></i>
        This is the master order for the operating date. Assign each bus and driver to its current terminal before dispatch: a bus is queued and dispatched from where it actually is, and after a confirmed arrival it automatically joins the receiving terminal's queue. Drag the rows to arrange the official order, then publish.
    </p>

</section>

<div class="modal fade" id="dqAssignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-icon"><i class="bi bi-person-badge"></i></span>
                    <div>
                        <div class="rmg-form-kicker">Dispatch queue</div>
                        <h5 class="modal-title rmg-form-title">Assign driver</h5>
                        <div class="rmg-form-sub" id="dqAssignBus">—</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <div class="rmg-form-grid">
                    <div class="rmg-form-field full">
                        <label for="dqAssignDriver">Driver</label>
                        <select id="dqAssignDriver"></select>
                        <div class="rmg-field-hint" id="dqAssignHint"></div>
                    </div>
                </div>
                <div class="rmg-error" id="dqAssignError" hidden></div>
            </div>
            <div class="rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="dqAssignSave"><i class="bi bi-check2-circle"></i> Assign driver</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dqAvailabilityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-icon"><i class="bi bi-slash-circle"></i></span>
                    <div>
                        <div class="rmg-form-kicker">Dispatch queue</div>
                        <h5 class="modal-title rmg-form-title">Bus availability</h5>
                        <div class="rmg-form-sub" id="dqAvailabilityBus">&mdash;</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <div class="rmg-form-grid">
                    <div class="rmg-form-field full">
                        <label for="dqAvailabilityStatus">Availability</label>
                        <select id="dqAvailabilityStatus">
                            <option value="Available">Available</option>
                            <option value="Under Maintenance">Under Maintenance</option>
                            <option value="Out of Service">Out of Service</option>
                        </select>
                        <div class="rmg-field-hint" id="dqAvailabilityHint"></div>
                    </div>
                    <div class="rmg-form-field full">
                        <label for="dqAvailabilityReason">Reason for this change *</label>
                        <textarea id="dqAvailabilityReason" rows="2" placeholder="Why is this bus being marked unavailable or available? (recorded with your username)"></textarea>
                    </div>
                </div>
                <div class="rmg-error" id="dqAvailabilityError" hidden></div>
            </div>
            <div class="rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="dqAvailabilitySave"><i class="bi bi-check2-circle"></i> Save availability</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dqTerminalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-icon"><i class="bi bi-geo-alt"></i></span>
                    <div>
                        <div class="rmg-form-kicker">Dispatch queue</div>
                        <h5 class="modal-title rmg-form-title">Assign terminal</h5>
                        <div class="rmg-form-sub" id="dqTerminalBus">&mdash;</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <div class="rmg-form-grid">
                    <div class="rmg-form-field full">
                        <label for="dqTerminalSelect">Bus terminal</label>
                        <select id="dqTerminalSelect"></select>
                        <div class="rmg-field-hint" id="dqTerminalBusHint"></div>
                    </div>
                </div>
                <div class="rmg-error" id="dqTerminalError" hidden></div>
            </div>
            <div class="rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="dqTerminalSave"><i class="bi bi-check2-circle"></i> Save terminal</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dqHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-icon"><i class="bi bi-clock-history"></i></span>
                    <div>
                        <div class="rmg-form-kicker">Dispatch queue</div>
                        <h5 class="modal-title rmg-form-title">Queue history</h5>
                        <div class="rmg-form-sub" id="dqHistoryDate">&mdash;</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body" id="dqHistoryBody">
                <div class="rmg-state"><i class="bi bi-hourglass-split"></i><h6>Loading history...</h6>Fetching the audit trail.</div>
            </div>
            <div class="rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dqOverrideModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-icon"><i class="bi bi-arrows-move"></i></span>
                    <div>
                        <div class="rmg-form-kicker">Dispatch queue</div>
                        <h5 class="modal-title rmg-form-title">Set queue position</h5>
                        <div class="rmg-form-sub" id="dqOverrideBus">—</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <div class="rmg-form-grid">
                    <div class="rmg-form-field">
                        <label for="dqOverridePosition">Position</label>
                        <input type="number" id="dqOverridePosition" min="1" step="1" value="1">
                    </div>
                    <div class="rmg-form-field full">
                        <label for="dqOverrideReason">Reason for this override *</label>
                        <textarea id="dqOverrideReason" rows="2" placeholder="Why does this bus need an explicit position? (recorded with your username)"></textarea>
                    </div>
                </div>
                <div class="rmg-error" id="dqOverrideError" hidden></div>
            </div>
            <div class="rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="dqOverrideSave"><i class="bi bi-check2-circle"></i> Apply override</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dqPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content rmg-form-modal">
            <div class="modal-header rmg-form-header">
                <div class="rmg-form-head">
                    <span class="rmg-form-icon"><i class="bi bi-megaphone"></i></span>
                    <div>
                        <div class="rmg-form-kicker">Dispatch queue</div>
                        <h5 class="modal-title rmg-form-title">Preview before publishing</h5>
                        <div class="rmg-form-sub" id="dqPreviewSub">&mdash;</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body rmg-form-body">
                <div class="dq-preview-note" id="dqPreviewNote" hidden></div>
                <div class="dq-preview-list" id="dqPreviewBody">
                    <div class="rmg-state"><i class="bi bi-hourglass-split"></i><h6>Preparing preview...</h6>Assembling the official order.</div>
                </div>
            </div>
            <div class="rmg-form-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="dqPreviewConfirm"><i class="bi bi-shield-check"></i> Confirm &amp; publish</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dqSecurityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rmg-sec-modal">
            <div class="modal-header rmg-sec-header">
                <div class="rmg-sec-head">
                    <span class="rmg-sec-icon"><i class="bi bi-shield-lock"></i></span>
                    <div>
                        <h5 class="modal-title">Security authorization</h5>
                        <div class="rmg-sec-sub" id="dqSecuritySummary">&mdash;</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="rmg-sec-label" for="dqSecurityCode">Security Authorization Code</label>
                <input type="password" class="rmg-sec-input" id="dqSecurityCode" autocomplete="off" placeholder="Enter the code set by the Super Admin">
                <div class="rmg-error" id="dqSecurityError" hidden></div>
                <div class="rmg-sec-hint"><i class="bi bi-info-circle"></i> Publishing the official order is protected. The code is single-use and bound to this exact date. It expires after 5 minutes and a new code is needed for every publish.</div>
            </div>
            <div class="modal-footer rmg-sec-footer">
                <button type="button" class="rmg-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="rmg-btn rmg-btn-primary" id="dqSecurityConfirm"><i class="bi bi-shield-check"></i> Verify &amp; publish</button>
            </div>
        </div>
    </div>
</div>
