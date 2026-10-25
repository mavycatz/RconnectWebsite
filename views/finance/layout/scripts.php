<script>
    document.addEventListener("DOMContentLoaded", function () {

        const accessTable = document.getElementById("accessTable");

        if (!accessTable) return;

        let undoStack = [];
        let redoStack = [];

        accessTable.addEventListener("click", function (e) {
            if (e.target.classList.contains("perm-btn")) {
                const btn = e.target;
                const prev = btn.classList.contains("active");
                undoStack.push({ btn, prev });
                redoStack = [];
                btn.classList.toggle("active");
                btn.textContent = btn.classList.contains("active") ? "✔" : "✖";
            }
        });

        const undoBtn = document.getElementById("undoBtn");
        if (undoBtn) {
            undoBtn.addEventListener("click", () => {
                if (undoStack.length) {
                    const last = undoStack.pop();
                    last.btn.classList.toggle("active", last.prev);
                    last.btn.textContent = last.prev ? "✔" : "✖";
                    redoStack.push(last);
                }
            });
        }

        const redoBtn = document.getElementById("redoBtn");
        if (redoBtn) {
            redoBtn.addEventListener("click", () => {
                if (redoStack.length) {
                    const last = redoStack.pop();
                    last.btn.classList.toggle("active", !last.prev);
                    last.btn.textContent = last.btn.classList.contains("active") ? "✔" : "✖";
                    undoStack.push(last);
                }
            });
        }

        const saveBtn = document.getElementById("saveBtn");
        if (saveBtn) {
            saveBtn.addEventListener("click", () => {
                const permissions = {};
                accessTable.querySelectorAll("tr").forEach(row => {
                    const module = row.cells[0].textContent;
                    permissions[module] = {};
                    ["view", "add", "edit", "delete"].forEach((perm, i) => {
                        const btn = row.cells[i+1].querySelector("button");
                        permissions[module][perm] = btn.classList.contains("active");
                    });
                });
                console.log("Saved Permissions:", permissions);
                alert("Permissions saved.");
            });
        }

    });
</script>

</body>
</html>