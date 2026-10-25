<div class="container my-5 p-4" style="background:#faf9e5;border-radius:1rem;box-shadow:0 4px 12px rgba(0,0,0,0.1);">

    <h5 class="mb-4">Access Control</h5>

    <div class="table-responsive">
        <table class="table table-bordered text-center align-middle" style="border-color:#e5e0a4;">
            <thead style="background-color:#f5f4e6;">
                <tr>
                    <th>Module</th>
                    <th>View</th>
                    <th>Add</th>
                    <th>Edit</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody id="accessTable">
                <tr>
                    <td>Dashboard</td>
                    <td><button class="perm-btn active" data-module="Dashboard" data-perm="view">✔</button></td>
                    <td><button class="perm-btn" data-module="Dashboard" data-perm="add">✖</button></td>
                    <td><button class="perm-btn" data-module="Dashboard" data-perm="edit">✖</button></td>
                    <td><button class="perm-btn" data-module="Dashboard" data-perm="delete">✖</button></td>
                </tr>
                <tr>
                    <td>Trip Schedule</td>
                    <td><button class="perm-btn active" data-module="Trip Schedule" data-perm="view">✔</button></td>
                    <td><button class="perm-btn" data-module="Trip Schedule" data-perm="add">✖</button></td>
                    <td><button class="perm-btn" data-module="Trip Schedule" data-perm="edit">✖</button></td>
                    <td><button class="perm-btn" data-module="Trip Schedule" data-perm="delete">✖</button></td>
                </tr>
                <tr>
                    <td>Trip Logs</td>
                    <td><button class="perm-btn active" data-module="Trip Logs" data-perm="view">✔</button></td>
                    <td><button class="perm-btn active" data-module="Trip Logs" data-perm="add">✔</button></td>
                    <td><button class="perm-btn active" data-module="Trip Logs" data-perm="edit">✔</button></td>
                    <td><button class="perm-btn" data-module="Trip Logs" data-perm="delete">✖</button></td>
                </tr>
                <tr>
                    <td>Vehicle Status</td>
                    <td><button class="perm-btn active" data-module="Vehicle Status" data-perm="view">✔</button></td>
                    <td><button class="perm-btn active" data-module="Vehicle Status" data-perm="add">✔</button></td>
                    <td><button class="perm-btn active" data-module="Vehicle Status" data-perm="edit">✔</button></td>
                    <td><button class="perm-btn" data-module="Vehicle Status" data-perm="delete">✖</button></td>
                </tr>
                <tr>
                    <td>Reports</td>
                    <td><button class="perm-btn active" data-module="Reports" data-perm="view">✔</button></td>
                    <td><button class="perm-btn" data-module="Reports" data-perm="add">✖</button></td>
                    <td><button class="perm-btn" data-module="Reports" data-perm="edit">✖</button></td>
                    <td><button class="perm-btn" data-module="Reports" data-perm="delete">✖</button></td>
                </tr>
                <tr>
                    <td>User Management</td>
                    <td><button class="perm-btn" data-module="User Management" data-perm="view">✖</button></td>
                    <td><button class="perm-btn" data-module="User Management" data-perm="add">✖</button></td>
                    <td><button class="perm-btn" data-module="User Management" data-perm="edit">✖</button></td>
                    <td><button class="perm-btn" data-module="User Management" data-perm="delete">✖</button></td>
                </tr>
                <tr>
                    <td>System Settings</td>
                    <td><button class="perm-btn" data-module="System Settings" data-perm="view">✖</button></td>
                    <td><button class="perm-btn" data-module="System Settings" data-perm="add">✖</button></td>
                    <td><button class="perm-btn" data-module="System Settings" data-perm="edit">✖</button></td>
                    <td><button class="perm-btn" data-module="System Settings" data-perm="delete">✖</button></td>
                </tr>
            </tbody>
        </table>
    </div>

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