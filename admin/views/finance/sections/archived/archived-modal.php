<style>
    @media(max-width:768px) {
        #archivedEmployeesModal .modal-dialog.modal-xl { margin: 0.5rem !important; max-width: calc(100vw - 1rem) !important; }
        #archivedEmployeesModal .modal-content .container { padding: 12px !important; }
        #archivedEmployeesModal .modal-content .row > div { width: 100% !important; }
        #archivedEmployeesModal .modal-content h4, #archivedEmployeesModal .modal-content h5 { font-size: 1rem !important; }
        #archivedEmployeesModal .modal-content input, #archivedEmployeesModal .modal-content select, #archivedEmployeesModal .modal-content textarea { font-size: 0.8rem !important; padding: 6px 10px !important; }
        #archivedEmployeesModal .modal-content label { font-size: 0.75rem !important; }
        #archivedEmployeesModal .modal-content .btn { font-size: 0.75rem !important; padding: 6px 12px !important; }
    }
</style>

<div class="modal fade" id="archivedEmployeesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 overflow-hidden"
     style="border-radius:24px; background:#fef8f3; box-shadow:0 18px 50px rgba(0,0,0,.08);">

            <div class="row g-0">

                <div class="col-lg-4 d-none d-lg-block"
     style="background:#f9f4da; border-right:1px solid #e7e1c9; min-height:600px;">

                    <div class="p-5 h-100 d-flex flex-column justify-content-between">

                        <div>

                            <div style="width:72px;height:72px;border-radius:22px;background:#ffffff;border:1px solid #ebe5ca;display:flex;align-items:center;justify-content:center;">
                                <i class="fa-solid fa-box-archive" style="color:#c4b417; font-size:1.6rem;"></i>
                            </div>

                            <h2 style="margin-top:28px;font-size:2rem;font-weight:700;line-height:1.3;color:#2e4836;">
    Archived Employees
                            </h2>

                            <p style="margin-top:18px;font-size:.94rem;line-height:1.9;color:#70796f;">
    View all archived employees that have been
    removed from active rotation. You can
    restore them to bring them back.
                            </p>

                        </div>

                        <div class="d-grid gap-3">

                            <div style="background:#ffffff;border:1px solid #ebe5ca;border-radius:18px;padding:18px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:46px;height:46px;border-radius:14px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:14px;">
                                        <i class="fa-solid fa-eye-slash" style="color:#c4b417; font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.9rem;font-weight:700;color:#2e4836;">Removed from Rotation</div>
                                        <div style="font-size:.76rem;color:#7d857c;">Employees are hidden from active list</div>
                                    </div>
                                </div>
                            </div>

                            <div style="background:#ffffff;border:1px solid #ebe5ca;border-radius:18px;padding:18px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:46px;height:46px;border-radius:14px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:14px;">
                                        <i class="fa-solid fa-rotate-left" style="color:#c4b417; font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.9rem;font-weight:700;color:#2e4836;">Restorable</div>
                                        <div style="font-size:.76rem;color:#7d857c;">Archived employees can be restored anytime</div>
                                    </div>
                                </div>
                            </div>

                            <div style="background:#ffffff;border:1px solid #ebe5ca;border-radius:18px;padding:18px;">
                                <div class="d-flex align-items-center">
                                    <div style="width:46px;height:46px;border-radius:14px;background:#f9f4da;display:flex;align-items:center;justify-content:center;margin-right:14px;">
                                        <i class="fa-solid fa-clock" style="color:#c4b417; font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:.9rem;font-weight:700;color:#2e4836;">Records Preserved</div>
                                        <div style="font-size:.76rem;color:#7d857c;">All employee records are kept</div>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-lg-8 col-12">

                    <div class="modal-header border-0 px-5 pt-5 pb-0">

                        <div>
                            <h4 style="margin:0;color:#2e4836;font-weight:700;">
                                <i class="fa-solid fa-box-archive me-2" style="color:#8d6e00;"></i>Archived Employees
                            </h4>
                            <div style="font-size:.84rem;color:#7b847b;margin-top:4px;">
                                <span id="archivedEmployeeCount">Loading archived records...</span>
                            </div>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body px-5 py-4">

                        <div id="archivedEmployeesBody" style="background:#ffffff;border:1px solid #ebe7d4;border-radius:22px;padding:24px;min-height:300px;">

                            <div style="text-align:center;padding:60px 20px;color:#70796f;">
                                <i class="fa-solid fa-spinner fa-spin" style="font-size:2rem;color:#c4b417;"></i>
                                <p style="margin-top:16px;font-size:.9rem;">Loading archived employees...</p>
                            </div>

                        </div>

                    </div>

                    <div class="modal-footer border-0 px-5 pb-5 pt-0">

                        <button type="button" class="btn" data-bs-dismiss="modal"
        style="height:50px;padding:0 24px;border-radius:14px;background:#ffffff;border:1px solid #dfe5d1;color:#687168;font-weight:600;">
    Close
                        </button>

                    </div>

                </div>

            </div>

        </div>
    </div>
</div>

<script>
    function openArchivedModal() {
        const el = document.getElementById('archivedEmployeesModal');
        if (el) {
            let modal = bootstrap.Modal.getInstance(el);
            if (!modal) modal = new bootstrap.Modal(el);
            modal.show();
        }
    }

    document.getElementById('archivedEmployeesModal').addEventListener('hidden.bs.modal', function () {
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
        document.body.style.removeProperty('overflow');
    });

    document.getElementById('archivedEmployeesModal').addEventListener('show.bs.modal', async function () {
        const body = document.getElementById('archivedEmployeesBody');
        const countEl = document.getElementById('archivedEmployeeCount');

        try {
            const res = await fetch('/admin/api/Finance/getFinance.php?action=getArchivedEmployees');
            const result = await res.json();

            if (!result.success) {
                body.innerHTML = '<div style="text-align:center;padding:40px;color:#721c24;">Error loading archived employees.</div>';
                return;
            }

            if (!result.data || result.data.length === 0) {
                countEl.textContent = 'No archived records';
                body.innerHTML = '<div style="text-align:center;padding:60px 20px;color:#70796f;"><i class="fa-solid fa-box-open" style="font-size:2rem;color:#c4b417;"></i><p style="margin-top:16px;font-size:.9rem;">No archived employees found.</p></div>';
                return;
            }

            countEl.textContent = result.data.length + ' archived employee(s)';

            let rows = '';
            result.data.forEach(function (e) {
                var empId = e.employee_id || 'ID-' + e.id || '-';
                var name = e.full_name || e.username || e.email || '-';
                var area = e.assigned_area || e.department || '-';
                rows += '<tr style="border-bottom:1px solid #f0ede0;">';
                rows += '<td style="padding:14px 16px;color:#2e4836;font-weight:600;font-size:0.85rem;">' + empId + '</td>';
                rows += '<td style="padding:14px 16px;color:#475569;font-size:0.85rem;">' + name + '</td>';
                rows += '<td style="padding:14px 16px;color:#475569;font-size:0.85rem;">' + (e.role || '-') + '</td>';
                rows += '<td style="padding:14px 16px;color:#475569;font-size:0.85rem;">' + area + '</td>';
                rows += '<td style="padding:14px 16px;"><span style="background:#f8d7da;color:#721c24;padding:4px 14px;border-radius:20px;font-size:0.75rem;font-weight:600;display:inline-block;">Archived</span></td>';
                rows += '<td style="padding:14px 16px;"><button class="restore-emp-btn" data-id="' + e.id + '" style="background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:8px;padding:6px 14px;font-size:0.75rem;font-weight:600;"><i class="fa-solid fa-rotate-left me-1"></i>Restore</button></td>';
                rows += '</tr>';
            });

            body.innerHTML = '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">' +
            '<thead><tr style="background:linear-gradient(135deg,#f9f4da 0%,#f5edd0 100%);">' +
            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Employee ID</th>' +
            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Name</th>' +
            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Role</th>' +
            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Area</th>' +
            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Status</th>' +
            '<th style="padding:14px 16px;text-align:left;color:#2e4836;font-weight:700;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.5px;">Action</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table></div>';

            document.querySelectorAll('.restore-emp-btn').forEach(function (btn) {
                btn.addEventListener('click', async function () {
                    const id = this.getAttribute('data-id');
                    showConfirm('Restore Employee?', 'Set this employee back to Active?', async function () {

                        try {
                            const fd = new FormData();
                            fd.append('id', id);
                            const res = await fetch('/admin/api/Finance/getFinance.php?action=restoreEmployee', { method: 'POST', body: fd });
                            const d = await res.json();
                            if (d.success) {
                                showAlert('success', 'Restored', 'Employee restored successfully.');
                                location.reload();
                            } else {
                                showAlert('error', 'Failed', d.message);
                            }
                        } catch (err) {
                            showAlert('error', 'Error', err.message);
                        }
                    });
                });
            });

        } catch (err) {
            body.innerHTML = '<div style="text-align:center;padding:40px;color:#721c24;">Failed to load data.</div>';
        }
    });
</script>
