<div class="modal fade" id="addBusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:12px;border:1px solid #dcdcdc;">

            <div class="modal-header" style="background:#f4f7ed;color:#295738;">
                <h5 class="modal-title">
                    <i class="bi bi-bus-front-fill me-2"></i>Add New Bus
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="addBusForm">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Bus ID</label>
                            <input type="text" name="bus_id" class="form-control" placeholder="BUS-001" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Plate Number</label>
                            <input type="text" name="plate_number" class="form-control" placeholder="ABC-1234" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Bus Type</label>
                            <select name="bus_type" class="form-select">
                                <option value="Coach">Coach</option>
                                <option value="City Bus">City Bus</option>
                                <option value="Mini Bus">Mini Bus</option>
                                <option value="Shuttle">Shuttle</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Seats</label>
                            <input type="number" name="seats" class="form-control" min="1">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Standing</label>
                            <input type="number" name="standing_capacity" class="form-control" min="0">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Accessible Features</label>
                            <select name="accessible_features" class="form-select">
                                <option value="None">None</option>
                                <option value="Priority Seats">Priority Seats</option>
                                <option value="PWD Accessible">PWD Accessible</option>
                                <option value="Wheelchair Ramp">Wheelchair Ramp</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Assign Driver</label>
                            <select name="driver" class="form-select">
                                <option value="">— Select Available Driver —</option>
                <?php foreach ($availableDrivers as $driver): ?>
                  <option value="<?= $driver['driver_id'] ?>">
                    <?= htmlspecialchars($driver['full_name']) ?> (<?= $driver['driver_id'] ?>)
</option>
                <?php endforeach; ?>
              </select>
              </div>

              <div class="col-md-6">
                  <label class="form-label">
                      <i class="bi bi-box-arrow-right me-1"></i>From Terminal
                  </label>
                  <select name="from_terminal_id" class="form-select" required>
                      <option value="">Select Origin</option>
                <?php foreach ($terminals as $t): ?>
                  <option value="<?= $t['id'] ?>">
                    <?= htmlspecialchars($t['terminal_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              </div>

              <div class="col-md-6">
                  <label class="form-label">
                      <i class="bi bi-flag-fill me-1"></i>To Terminal
                  </label>
                  <select name="to_terminal_id" class="form-select" required>
                      <option value="">Select Destination</option>
                <?php foreach ($terminals as $t): ?>
                  <option value="<?= $t['id'] ?>">
                    <?= htmlspecialchars($t['terminal_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              </div>

              <div class="col-md-6">
                  <label class="form-label">Schedule</label>
                  <input type="text" name="schedule" class="form-control" placeholder="06:00 AM – 06:00 PM">
              </div>

              <div class="col-md-6">
                  <label class="form-label">Status</label>
                  <select name="status" class="form-select">
                      <option value="Active" selected>Active</option>
                      <option value="Inactive">Inactive</option>
                      <option value="Pending QR">Pending QR</option>
                  </select>
              </div>

              </div>

              <div class="d-grid mt-4">
                  <button type="submit" class="btn" style="background:#f4f7ed;border:1px solid #b8e405;color:#295738;">
                      <i class="bi bi-save me-1"></i>Save Bus
                  </button>
              </div>
              </form>
              </div>
              </div>
              </div>
              </div>
