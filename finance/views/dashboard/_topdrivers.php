<style>
.td-loader {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid #dce8b2;
    border-top-color: #198754;
    animation: td-spin 0.9s linear infinite;
    flex-shrink: 0;
}

@keyframes td-spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.td-sk {
    position: relative;
    overflow: hidden;
    background: #ece7cf;
    border-radius: 6px;
}

.td-sk::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 40%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.65), transparent);
    animation: td-shimmer 1.3s infinite;
}

@keyframes td-shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(260%);
    }
}

.td-avatar {
    width: 14px;
    height: 14px;
}
.td-bar {
    height: 10px;
}
</style>
<div class="card shadow-sm flex-grow-1 h-100" style="background:linear-gradient(180deg,#ffffff 0%,#faf6ed 100%);border:1px solid #e7e3af;border-radius:14px;">

    <div class="card-header d-flex align-items-center gap-2"
         style="background:transparent;border-bottom:1px solid #e7e3af;border-radius:14px 14px 0 0;padding:14px 18px;">

        <i class="bi bi-trophy-fill text-success" style="font-size:1.1rem;"></i>

        <span class="fw-semibold" style="font-size:0.85rem; color:#2f4f2f;">
            Top Performing Drivers
        </span>

    </div>

    <div class="card-body p-3" id="topDriversContainer">

        <div class="d-inline-flex align-items-center gap-2 mb-2" style="font-size:0.8rem;font-weight:600;color:#198754;">
            <div class="td-loader"></div> Loading top drivers...
        </div>

        <div class="d-flex align-items-center gap-3 py-2">
            <div class="td-sk td-avatar"></div>
            <div class="flex-grow-1"><div class="td-sk td-bar mb-2" style="width:60%;"></div><div class="td-sk td-bar" style="width:35%;"></div></div>
        </div>
        <div class="d-flex align-items-center gap-3 py-2">
            <div class="td-sk td-avatar"></div>
            <div class="flex-grow-1"><div class="td-sk td-bar mb-2" style="width:55%;"></div><div class="td-sk td-bar" style="width:40%;"></div></div>
        </div>
        <div class="d-flex align-items-center gap-3 py-2">
            <div class="td-sk td-avatar"></div>
            <div class="flex-grow-1"><div class="td-sk td-bar mb-2" style="width:48%;"></div><div class="td-sk td-bar" style="width:30%;"></div></div>
        </div>

    </div>

</div>



<script>
function renderTopDriversLoading(container) {
    container.innerHTML = `
        <div class="d-inline-flex align-items-center gap-2 mb-2" style="font-size:0.8rem;font-weight:600;color:#198754;">
            <div class="td-loader"></div> Loading top drivers...
        </div>

        <div class="d-flex align-items-center gap-3 py-2">
            <div class="td-sk td-avatar"></div>
            <div class="flex-grow-1"><div class="td-sk td-bar mb-2" style="width:60%;"></div><div class="td-sk td-bar" style="width:35%;"></div></div>
        </div>
        <div class="d-flex align-items-center gap-3 py-2">
            <div class="td-sk td-avatar"></div>
            <div class="flex-grow-1"><div class="td-sk td-bar mb-2" style="width:55%;"></div><div class="td-sk td-bar" style="width:40%;"></div></div>
        </div>
        <div class="d-flex align-items-center gap-3 py-2">
            <div class="td-sk td-avatar"></div>
            <div class="flex-grow-1"><div class="td-sk td-bar mb-2" style="width:48%;"></div><div class="td-sk td-bar" style="width:30%;"></div></div>
        </div>
    `;
}

function loadDriverPerformance(query = "") {
    const container = document.getElementById("topDriversContainer");
    window.__tdReq = (window.__tdReq || 0) + 1;
    const req = window.__tdReq;

    renderTopDriversLoading(container);

    fetch("/admin/api/dashboard/top-drivers.php?" + query, {
        cache: "no-store",
    })
        .then((res) => res.json())

        .then((response) => {
            if (req !== window.__tdReq) return;

            if (!response.success) {
                container.innerHTML = `
                <div class="text-center text-danger py-3">
                    Failed to load drivers
                </div>
            `;
                return;
            }

            const drivers = response.data || [];
            console.log("top-drivers response:", response);
            window.__setAiPageData && window.__setAiPageData("top_drivers", drivers);

            container.innerHTML = "";

            if (drivers.length === 0) {
                container.innerHTML = `
                <div style="text-align:center;padding:22px 14px;color:#a3ae9f;font-size:0.78rem;font-weight:500;">No driver data for the selected period.</div>
            `;
                return;
            }

            const medals = [
                '<i class="bi bi-trophy-fill" style="color:#ffc107;"></i>',
                '<i class="bi bi-trophy" style="color:#adb5bd;"></i>',
                '<i class="bi bi-award" style="color:#cd7f32;"></i>',
            ];

            drivers.forEach((driver, index) => {
                if (!driver) return;

                const medal = index < 3 ? medals[index] + " " : "";

                const div = document.createElement("div");

                div.style.borderBottom = "1px solid #f0ede0";
                div.style.padding = "10px 0";
                div.style.display = "flex";
                div.style.justifyContent = "space-between";
                div.style.alignItems = "center";
                div.style.transition = "background 0.2s";
                div.style.cursor = "pointer";

                div.onmouseover = () => (div.style.background = "rgba(0,0,0,0.03)");
                div.onmouseout = () => (div.style.background = "transparent");

                let perfVal = 0;
                try {
                    const inc = parseFloat(driver.income) || parseFloat(driver.earnings) || 0;
                    const trips = parseInt(driver.trips, 10) || 1;
                    perfVal = inc / trips;
                } catch (e) {
                    perfVal = 0;
                }

                div.innerHTML = `
                <div style="display:flex; align-items:center; gap:8px;">

                    <span style="font-weight:600; color:#2f4f2f; font-size:0.85rem;">
                        ${index + 1}.
                    </span>

                    <span style="font-size:0.85rem; color:#475569;">
                        ${medal}${driver.name}
                    </span>

                </div>

                <div style="text-align:right;">

                    <div style="color:#2f4f2f; font-size:0.8rem; font-weight:500;">
                        ${parseInt(driver.trips, 10) || 0} trips
                    </div>

                    <div style="color:#198754; font-size:0.7rem; font-weight:600;">
                        ₱${perfVal.toFixed(2)} / trip
                    </div>

                </div>
            `;

                container.appendChild(div);
            });
        })

        .catch((error) => {
            if (req !== window.__tdReq) return;

            console.error(error);

            container.innerHTML = `
            <div class="text-center text-danger py-3">
                Error loading drivers
            </div>
        `;
        });
}

document.addEventListener("DOMContentLoaded", function () {
    loadDriverPerformance();
});
</script>