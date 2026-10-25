<style>
    @media(max-width:768px) {
        .modal-dialog.modal-lg { margin: 0.5rem !important; max-width: calc(100vw - 1rem) !important; }
    }
</style>

<div class="modal fade" id="discountsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 overflow-hidden"
     style="border-radius:24px; background:#fef8f3; box-shadow:0 18px 50px rgba(0,0,0,.08);">

            <div class="row g-0">

                <div class="col-lg-4 d-none d-lg-block p-4"
     style="background:#f9f4da; border-right:1px solid #e7e1c9; min-height:400px;">

                    <div class="h-100 d-flex flex-column justify-content-center text-center">
                        <div style="width:72px;height:72px;border-radius:50%;background:#ffffff;border:1px solid #cdd9b5;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                            <i class="bi bi-tags-fill" style="color:#57694d; font-size:1.8rem;"></i>
                        </div>
                        <h2 style="font-size:1.5rem;font-weight:700;color:#2e4836;margin-bottom:8px;">Assign Discount</h2>
                        <p style="font-size:.85rem;color:#57694d;line-height:1.7;margin-bottom:0;padding:0 10px;">
    Select a passenger and assign a discount type or voucher. Changes take effect immediately.
                        </p>
                    </div>

                </div>

                <div class="col-lg-8 col-12">

                    <div class="modal-header border-0 px-4 pt-4 pb-0">
                        <h4 style="margin:0;color:#2e4836;font-weight:700;font-size:1.1rem;">
                            <i class="bi bi-tags-fill me-2" style="color:#57694d;"></i>Assign Discount / Voucher
                        </h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body px-4 py-3">

                        <div id="discountForm">
                            <div class="mb-3">
                                <label style="display:block;margin-bottom:6px;font-size:.85rem;font-weight:600;color:#57694d;">Select Passenger</label>
                                <select id="discPassenger" class="form-control" style="height:50px;border-radius:14px;border:1.5px solid #cdd9b5;font-size:.9rem;color:#2e4836;">
                                    <option value="">Loading passengers...</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label style="display:block;margin-bottom:6px;font-size:.85rem;font-weight:600;color:#57694d;">Discount / Voucher Type</label>
                                <div style="display:flex;flex-wrap:wrap;gap:8px;" id="discTypeOptions">
                                    <label style="flex:1;min-width:100px;cursor:pointer;">
                                        <input type="radio" name="discount_type" value="Senior" checked>
                                        <span style="display:block;padding:10px;text-align:center;border:2px solid #cdd9b5;border-radius:12px;font-size:.82rem;font-weight:600;color:#57694d;background:#fff;">Senior</span>
                                    </label>
                                    <label style="flex:1;min-width:100px;cursor:pointer;">
                                        <input type="radio" name="discount_type" value="PWD">
                                        <span style="display:block;padding:10px;text-align:center;border:2px solid #cdd9b5;border-radius:12px;font-size:.82rem;font-weight:600;color:#57694d;background:#fff;">PWD</span>
                                    </label>
                                    <label style="flex:1;min-width:100px;cursor:pointer;">
                                        <input type="radio" name="discount_type" value="Student">
                                        <span style="display:block;padding:10px;text-align:center;border:2px solid #cdd9b5;border-radius:12px;font-size:.82rem;font-weight:600;color:#57694d;background:#fff;">Student</span>
                                    </label>
                                    <label style="flex:1;min-width:100px;cursor:pointer;">
                                        <input type="radio" name="discount_type" value="Voucher">
                                        <span style="display:block;padding:10px;text-align:center;border:2px solid #cdd9b5;border-radius:12px;font-size:.82rem;font-weight:600;color:#57694d;background:#fff;">Voucher</span>
                                    </label>
                                    <label style="flex:1;min-width:100px;cursor:pointer;">
                                        <input type="radio" name="discount_type" value="Custom">
                                        <span style="display:block;padding:10px;text-align:center;border:2px solid #cdd9b5;border-radius:12px;font-size:.82rem;font-weight:600;color:#57694d;background:#fff;">Custom %</span>
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label style="display:block;margin-bottom:6px;font-size:.85rem;font-weight:600;color:#57694d;">Discount Percentage</label>
                                <div class="input-group">
                                    <input type="number" id="discPercent" class="form-control" min="1" max="100" value="20" style="height:50px;border-radius:14px 0 0 14px;border:1.5px solid #cdd9b5;font-size:.9rem;color:#2e4836;">
                                    <span style="display:flex;align-items:center;padding:0 16px;background:#f9f4da;border:1.5px solid #cdd9b5;border-left:none;border-radius:0 14px 14px 0;font-weight:700;color:#57694d;">%</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label style="display:block;margin-bottom:6px;font-size:.85rem;font-weight:600;color:#57694d;">Voucher Description</label>
                                <select id="voucherSelect" class="form-control" onchange="toggleVoucherCustom()" style="height:50px;border-radius:14px;border:1.5px solid #cdd9b5;font-size:.9rem;color:#2e4836;margin-bottom:8px;">
                                    <option value="">— None —</option>
                                    <option value="Welcome Discount — First ride promo">Welcome Discount — First ride promo</option>
                                    <option value="Referral Bonus — Invite a friend">Referral Bonus — Invite a friend</option>
                                    <option value="Holiday Promo — Special rate">Holiday Promo — Special rate</option>
                                    <option value="Birthday Voucher — Free ride">Birthday Voucher — Free ride</option>
                                    <option value="Loyalty Reward — Thank you">Loyalty Reward — Thank you</option>
                                    <option value="Student Special — Back to school">Student Special — Back to school</option>
                                    <option value="Weekend Getaway — Discounted fare">Weekend Getaway — Discounted fare</option>
                                    <option value="__custom__">Other (type below)</option>
                                </select>
                                <input type="text" id="voucherCustom" class="form-control" placeholder="Type custom voucher description..." style="display:none;height:50px;border-radius:14px;border:1.5px solid #cdd9b5;font-size:.9rem;color:#2e4836;">
                            </div>

                            <div class="mb-3">
                                <label style="display:block;margin-bottom:6px;font-size:.85rem;font-weight:600;color:#57694d;">Status</label>
                                <select id="discStatus" class="form-control" style="height:50px;border-radius:14px;border:1.5px solid #cdd9b5;font-size:.9rem;color:#2e4836;">
                                    <option value="Verified">Verified — Approved</option>
                                    <option value="Pending">Pending — Awaiting verification</option>
                                    <option value="Rejected">Rejected — Not approved</option>
                                </select>
                            </div>
                        </div>

                    </div>

                    <div class="modal-footer border-0 px-4 pb-4 pt-0">
                        <button type="button" class="btn" data-bs-dismiss="modal"
            style="height:48px;border-radius:14px;background:#ffffff;border:1.5px solid #cdd9b5;color:#57694d;font-weight:600;flex:1;">Cancel</button>
                            <button type="button" id="assignDiscBtn" class="btn text-white"
            style="height:48px;border-radius:14px;border:none;background:#57694d;font-weight:600;flex:1;font-size:.9rem;">
                                <i class="bi bi-check2 me-1"></i>Assign Discount
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <script>
            function openDiscounts() {
                fetch('/admin/api/Passenger/PassengerApi.php?action=get_passengers')
                .then(function (r) { return r.json() })
                .then(function (r) {
                    var sel = document.getElementById('discPassenger');
                    if (!r.success || !r.data) { sel.innerHTML = '<option value="">No passengers available</option>'; return; }
                    var html = '<option value="">— Select Passenger —</option>';
                    r.data.forEach(function (p) { html += '<option value="'+p.id+'">'+ (p.full_name||'Unknown') + ' (ID: '+p.id+')' + '</option>'; });
                    sel.innerHTML = html;
                })
                .catch (function () { document.getElementById('discPassenger').innerHTML = '<option value="">Failed to load</option>'; });
                new bootstrap.Modal(document.getElementById('discountsModal')).show();
            }

            document.getElementById('assignDiscBtn')?.addEventListener('click', function () {
                var pid = document.getElementById('discPassenger').value;
                if (!pid) { psgrAlert('error', 'No Passenger', 'Please select a passenger.'); return; }
                var type = document.querySelector('input[name="discount_type"]:checked');
                var discountType = type ? type.value : '';
                var discountPct = document.getElementById('discPercent').value || '';
                var sel = document.getElementById('voucherSelect');
                var voucherDesc = sel.value === '__custom__' ? document.getElementById('voucherCustom').value : sel.value;
                var status = document.getElementById('discStatus').value;

                if (discountType === 'Custom' && (!discountPct || discountPct < 1)) {
                    psgrAlert('error', 'Invalid', 'Please enter a valid discount percentage.'); btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Assign Discount'; return;
                }
                if (discountType === 'Custom') { discountType = 'Custom - ' + discountPct + '%'; }

                var fd = new FormData();
                fd.append('id', pid.replace(/[^0-9]/g, ''));
                fd.append('discount_type', discountType);
                fd.append('discount_status', status);
                fd.append('discount_percent', discountPct);
                fd.append('voucher_description', voucherDesc);

                fetch('/admin/api/Passenger/PassengerApi.php?action=update_discount', { method:'POST', body:fd })
                .then(function (r) { return r.text() })
                .then(function (t) {
                    try {
                        var r = JSON.parse(t);
                        if (r.success) {
                            bootstrap.Modal.getInstance(document.getElementById('discountsModal')).hide();
                            document.cookie = 'show_discount_celebration=1; path=/';
                            psgrAlert('success', 'Assigned!', 'Discount assigned successfully.');
                            if (typeof loadPassengers === 'function') loadPassengers();
                        } else { psgrAlert('error', 'Failed', r.message); }
                    } catch (e) { psgrAlert('error', 'Invalid', t.substring(0, 200)); }
                })
                .catch (function (e) { psgrAlert('error', 'Error', e.message); })
                .finally(function () { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Assign Discount'; });
            });

            document.querySelectorAll('#discTypeOptions input[type="radio"]').forEach(function (r) {
                r.addEventListener('change', function () {
                    document.querySelectorAll('#discTypeOptions span').forEach(function (s) { s.style.borderColor = '#cdd9b5'; s.style.background = '#fff'; });
                    if (this.checked) { this.nextElementSibling.style.borderColor = '#57694d'; this.nextElementSibling.style.background = '#f9f4da'; }
                });
            });

            function toggleVoucherCustom() {
                var s = document.getElementById('voucherSelect');
                var c = document.getElementById('voucherCustom');
                c.style.display = s.value === '__custom__' ? 'block' : 'none';
            }
        </script>

