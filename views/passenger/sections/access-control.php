<div class="container my-5 p-4" style="background:#faf9e5;border-radius:1rem;box-shadow:0 4px 12px rgba(0,0,0,0.1);">

    <h5 class="mb-4">Access Control</h5>

    <div class="table-responsive">
        <table class="table table-bordered text-center align-middle" style="border-color:#e5e0a4;" id="accessTable">
            <thead style="background-color:#f5f4e6;">
                <tr>
                    <th>Module</th>
                    <th>View</th>
                    <th>Add</th>
                    <th>Edit</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Passenger List</td><td><button class="perm-btn active">✔</button></td><td><button class="perm-btn active">✔</button></td><td><button class="perm-btn">✖</button></td><td><button class="perm-btn">✖</button></td></tr>
                <tr><td>Trip History</td><td><button class="perm-btn active">✔</button></td><td><button class="perm-btn">✖</button></td><td><button class="perm-btn">✖</button></td><td><button class="perm-btn">✖</button></td></tr>
                <tr><td>Passenger Details</td><td><button class="perm-btn active">✔</button></td><td><button class="perm-btn">✖</button></td><td><button class="perm-btn active">✔</button></td><td><button class="perm-btn">✖</button></td></tr>
                <tr><td>Reports</td><td><button class="perm-btn active">✔</button></td><td><button class="perm-btn">✖</button></td><td><button class="perm-btn">✖</button></td><td><button class="perm-btn">✖</button></td></tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex gap-2 justify-content-end mt-3">
        <button id="undoBtn" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Undo</button>
        <button id="redoBtn" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i>Redo</button>
        <button id="saveBtn" class="btn btn-sm text-white" style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);border:none;"><i class="bi bi-floppy me-1"></i>Save</button>
    </div>

</div>
