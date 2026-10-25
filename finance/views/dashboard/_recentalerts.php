<div class="card shadow-sm flex-grow-1 h-100" style="background:linear-gradient(180deg,#ffffff 0%,#faf6ed 100%);border:1px solid #e7e3af;border-radius:14px;">
  
  <div class="card-header d-flex align-items-center justify-content-between gap-2"
       style="background:transparent;border-bottom:1px solid #e7e3af;border-radius:14px 14px 0 0;padding:14px 18px;">

      <div class="d-flex align-items-center gap-2">
          <i class="bi bi-megaphone-fill text-success" style="font-size:1.1rem;"></i>
          <span class="fw-semibold" style="font-size:0.85rem; color:#2f4f2f;">Announcements</span>
      </div>

      <button class="btn btn-sm fw-semibold" style="font-size:0.75rem; color:#295738; border:1px solid #dee4ba; border-radius:8px;"
              data-bs-toggle="modal"
              data-bs-target="#announcementModal">
          See More
      </button>

  </div>

  <div class="card-body p-3" id="alertsContainer">
      <div class="text-center text-muted py-3" style="font-size:0.85rem;">Loading...</div>
  </div>

</div>



<div class="modal fade" id="announcementModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content" style="border:1px solid #e7e3af;border-radius:14px;">

      <div class="modal-header"
           style="background:linear-gradient(180deg,#ffffff 0%,#faf6ed 100%);border-bottom:1px solid #e7e3af;border-radius:14px 14px 0 0;padding:14px 18px;">

          <h5 class="modal-title fw-semibold" style="font-size:0.9rem; color:#2f4f2f;">
              <i class="bi bi-megaphone-fill text-success me-2"></i>
              Announcements
          </h5>

          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body p-3" id="allAnnouncements"></div>

    </div>
  </div>
</div>



<script>
function loadAnnouncements(query = "") {
    const container = document.getElementById("alertsContainer");
    const modalContainer = document.getElementById("allAnnouncements");

    fetch("/admin/api/dashboard/announcements.php?" + query)
        .then((res) => res.json())
        .then((response) => {
            if (!response.success) {
                container.innerHTML = `<div class="text-danger text-center py-3">Failed to load announcements</div>`;
                return;
            }

            const announcements = response.data || [];
            window.__setAiPageData && window.__setAiPageData("announcements", announcements);

            container.innerHTML = "";
            modalContainer.innerHTML = "";

            if (announcements.length === 0) {
                container.innerHTML = `<div style="text-align:center;padding:22px 14px;color:#a3ae9f;font-size:0.78rem;font-weight:500;">No announcements for the selected period.</div>`;
                return;
            }

            announcements.slice(0, 3).forEach((a) => {
                const div = document.createElement("div");

                div.style.padding = "10px 0";
                div.style.borderBottom = "1px solid #f0ede0";
                div.style.cursor = "pointer";
                div.style.transition = "background 0.2s";

                div.onmouseover = () => (div.style.background = "rgba(0,0,0,0.03)");
                div.onmouseout = () => (div.style.background = "transparent");

                div.innerHTML = `
                <div class="d-flex flex-column" style="padding:0 4px;">
                    <span style="font-weight:600; font-size:0.85rem; color:#2f4f2f;">
                        <i class="bi bi-megaphone text-success me-1" style="font-size:0.8rem;"></i> ${a.title}
                    </span>
                    <small style="color:#94a3b8; font-size:0.7rem;">${a.date} | ${a.time}</small>
                    <small style="color:#64748b; font-size:0.8rem;">${a.message}</small>
                </div>
            `;

                container.appendChild(div);
            });

            announcements.forEach((a) => {
                const div = document.createElement("div");

                div.style.padding = "14px 0";
                div.style.borderBottom = "1px solid #f0ede0";

                div.innerHTML = `
                <div class="d-flex flex-column">
                    <span style="font-weight:600; font-size:0.85rem; color:#2f4f2f;">
                        <i class="bi bi-megaphone text-success me-1" style="font-size:0.8rem;"></i> ${a.title}
                    </span>
                    <small style="color:#94a3b8; font-size:0.7rem;">${a.date} | ${a.time}</small>
                    <small style="color:#64748b; font-size:0.8rem;">${a.message}</small>
                </div>
            `;

                modalContainer.appendChild(div);
            });
        });
}

document.addEventListener("DOMContentLoaded", () => {
    loadAnnouncements();

    setInterval(() => {
        loadAnnouncements(window.__financeFilterQuery || "");
    }, 30000);
});
</script>