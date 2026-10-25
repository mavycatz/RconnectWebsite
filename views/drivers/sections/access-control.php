<style>
    @media(max-width:768px) {
        .access-section { padding: 14px !important; margin: 1rem 0 !important; }
        .access-section h5 { font-size: 1rem !important; }
        .access-section table { font-size: 0.7rem !important; }
        .access-section th, .access-section td { padding: 4px 2px !important; }
        .access-section .perm-btn { font-size: 0.7rem !important; padding: 2px 6px !important; min-width: 28px; }
    }
</style>
<div class="container my-5 p-4 access-section" style="background:#faf9e5; border-radius:1rem; box-shadow:0 4px 12px rgba(0,0,0,0.1);">

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
                    <td><button class="perm-btn active">✔</button></td>
                    <td><button class="perm-btn">✖</button></td>
                    <td><button class="perm-btn">✖</button></td>
                    <td><button class="perm-btn">✖</button></td>
                </tr>

                <tr>
                    <td>Trip Schedule</td>
                    <td><button class="perm-btn active">✔</button></td>
                    <td><button class="perm-btn">✖</button></td>
                    <td><button class="perm-btn">✖</button></td>
                    <td><button class="perm-btn">✖</button></td>
                </tr>

                <tr>
                    <td>Trip Logs</td>
                    <td><button class="perm-btn active">✔</button></td>
                    <td><button class="perm-btn active">✔</button></td>
                    <td><button class="perm-btn active">✔</button></td>
                    <td><button class="perm-btn">✖</button></td>
                </tr>

                <tr>
                    <td>Vehicle Status</td>
                    <td><button class="perm-btn active">✔</button></td>
                    <td><button class="perm-btn active">✔</button></td>
                    <td><button class="perm-btn active">✔</button></td>
                    <td><button class="perm-btn">✖</button></td>
                </tr>

                <tr>
                    <td>Reports</td>
                    <td><button class="perm-btn active">✔</button></td>
                    <td><button class="perm-btn">✖</button></td>
                    <td><button class="perm-btn">✖</button></td>
                    <td><button class="perm-btn">✖</button></td>
                </tr>

                <tr>
                    <td>User Management</td>
                    <td><button class="perm-btn">✖</button></td>
                    <td><button class="perm-btn">✖</button></td>
                    <td><button class="perm-btn">✖</button></td>
                    <td><button class="perm-btn">✖</button></td>
                </tr>

                <tr>
                    <td>System Settings</td>
                    <td><button class="perm-btn">✖</button></td>
                    <td><button class="perm-btn">✖</button></td>
                    <td><button class="perm-btn">✖</button></td>
                    <td><button class="perm-btn">✖</button></td>
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

<style>
    .perm-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: none;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.2s;
        background: #f0f0f0;
        color: #333;
    }

    .perm-btn.active {
        background: linear-gradient(90deg, #b8e405 0%, #005a15 100%);
        color: white;
    }

    .perm-btn:hover {
        transform: scale(1.1);
    }

    .table td, .table th {
        vertical-align: middle;
    }
</style>