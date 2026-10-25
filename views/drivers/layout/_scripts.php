
<script>
    const accessTable = document.getElementById("accessTable");
    let undoStack = [];
    let redoStack = [];

    accessTable?.addEventListener("click", function (e) {
        if (e.target.classList.contains("perm-btn")) {
            const btn = e.target;
            const prev = btn.classList.contains("active");

            undoStack.push({ btn, prev });
            redoStack = [];

            btn.classList.toggle("active");
            btn.textContent = btn.classList.contains("active") ? "✔" : "✖";
        }
    });

    document.getElementById("undoBtn")?.addEventListener("click", () => {
        if (undoStack.length) {
            const last = undoStack.pop();
            last.btn.classList.toggle("active", last.prev);
            last.btn.textContent = last.prev ? "✔" : "✖";
            redoStack.push(last);
        }
    });

    document.getElementById("redoBtn")?.addEventListener("click", () => {
        if (redoStack.length) {
            const last = redoStack.pop();
            last.btn.classList.toggle("active", !last.prev);
            last.btn.textContent = last.btn.classList.contains("active") ? "✔" : "✖";
            undoStack.push(last);
        }
    });

    document.getElementById("saveBtn")?.addEventListener("click", () => {
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
        alert("Permissions saved! Check console for details.");
    });


    const TIMEOUT = 900000;
    let logoutTimer;

    function resetTimer() {
        clearTimeout(logoutTimer);
        logoutTimer = setTimeout(() => {
            window.location.href = "/public/login/index.php?expired=1";
        }, TIMEOUT);
    }

    document.addEventListener('DOMContentLoaded', resetTimer);
    document.onmousemove = resetTimer;
    document.onkeydown = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;
</script>