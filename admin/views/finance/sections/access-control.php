<h5 class="mt-5 mb-3">Access Control</h5>

<div class="table-responsive">
    <table class="table table-striped text-center" id="accessTable">
        <thead style="background:linear-gradient(90deg,#b8e405 0%,#005a15 100%);color:white;">
            <tr>
                <th>Module</th>
                <th>View</th>
                <th>Add</th>
                <th>Edit</th>
                <th>Delete</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Financial Records</td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
            </tr>
            <tr>
                <td>Payments</td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
            </tr>
            <tr>
                <td>Reports</td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
            </tr>
            <tr>
                <td>Trips (Fare View)</td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
            </tr>
            <tr>
                <td>Settings</td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
                <td><button class="perm-btn active">✔</button></td>
            </tr>
        </tbody>
    </table>

    <div class="mt-3 d-flex gap-2">

        <button id="undoBtn"
            class="btn btn-sm"
            style="background:#fff4c2;color:#a67c00;border:none;border-radius:20px;padding:6px 18px;">
            Undo
        </button>

        <button id="redoBtn"
            class="btn btn-sm"
            style="background:#ffe0b2;color:#c25b00;border:none;border-radius:20px;padding:6px 18px;">
            Redo
        </button>

        <button id="saveBtn"
            class="btn btn-sm"
            style="background:#c8f2c2;color:#2e7d32;border:none;border-radius:20px;padding:6px 18px;">
            Save Permissions
        </button>

    </div>

</div>