<?php if (!defined('APP_LOADED')) exit; ?>

<style>
.financial-tabs {
    display: flex;
    gap: 20px;
    padding: 12px 20px;
    border: 2px solid #e7e3af;
    border-radius: 14px;
    background: transparent;
    width: 100%;
    overflow-x: auto;
    align-items: center;
}

.financial-tab-btn {
    background: transparent;
    border: none;
    padding: 10px 18px;
    font-weight: 400;
    color: #05590c;
    cursor: pointer;
    border-radius: 10px;
    transition: all 0.25s ease;
    white-space: nowrap;
    font-size: 14px;
}

.financial-tab-btn:hover {
    background: rgba(231, 227, 175, 0.4);
}

.financial-tab-btn.active {
    font-weight: 700;
    background: #faf9e5;
    border: 2px solid #e7e3af;
}

.financial-section {
    display: none;
    animation: fadeIn 0.25s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>


<div
    class="financial-tabs mb-4"
    id="financialTabs"
>
    Loading AI reports...
</div>

<script>
document.addEventListener("DOMContentLoaded", async function () {
    await loadAIReportTabs();
});

async function loadAIReportTabs() {
    const tabs = document.getElementById("financialTabs");

    if (!tabs) {
        console.error("financialTabs element not found");

        return;
    }

    tabs.innerHTML = `

        <div style="
            color:#295738;
            padding:10px;
            font-weight:600;
        ">

            Loading AI reports...

        </div>

    `;

    try {
        const response = await fetch(
            window.location.origin + "/finance/api/get_ai_reports.php",

            {
                method: "GET",

                headers: {
                    Accept: "application/json",
                },
            },
        );

        console.log("RAW RESPONSE:", response);

        if (!response.ok) {
            const rawText = await response.text();

            console.error("HTTP ERROR RESPONSE:", rawText);

            throw new Error("HTTP ERROR " + response.status + " : " + rawText);
        }

        const raw = await response.text();

        console.log("RAW API TEXT:", raw);

        if (!raw) {
            throw new Error("Empty API response.");
        }

        let data = null;

        try {
            data = JSON.parse(raw);
        } catch (jsonError) {
            console.error("JSON PARSE ERROR:", jsonError);

            console.error("INVALID JSON:", raw);

            throw new Error("Invalid JSON response. " + raw);
        }

        console.log("AI REPORTS:", data);

        if (!data.success) {
            tabs.innerHTML = `

                <div style="
                    color:#d62828;
                    padding:12px;
                    border:2px solid #ffb3b3;
                    border-radius:10px;
                    background:#fff5f5;
                    font-weight:600;
                ">

                    Failed to load reports

                    <br><br>

                    <small>

                        ${data.message || "Unknown server error"}

                    </small>

                </div>

            `;

            return;
        }

        if (!Array.isArray(data.reports) || data.reports.length === 0) {
            tabs.innerHTML = `

                <div style="
                    color:#777;
                    padding:12px;
                    border:2px dashed #ddd;
                    border-radius:10px;
                ">

                    No AI reports available

                </div>

            `;

            return;
        }

        tabs.innerHTML = "";

        data.reports.forEach((report, index) => {
            const button = document.createElement("button");

            button.type = "button";

            button.className = "financial-tab-btn";

            if (index === 0) {
                button.classList.add("active");
            }

            button.innerText = report.report_title || "Untitled Report";

            button.dataset.id = report.id;

            button.addEventListener("click", function () {
                document.querySelectorAll("#financialTabs .financial-tab-btn").forEach((btn) => {
                    btn.classList.remove("active");
                });

                this.classList.add("active");

                loadReportContent(report);
            });

            tabs.appendChild(button);

            if (index === 0) {
                loadReportContent(report);
            }
        });
    } catch (error) {
        console.error("AI REPORT ERROR:", error);

        tabs.innerHTML = `

            <div style="
                color:#d62828;
                padding:14px;
                border:2px solid #ffb3b3;
                border-radius:12px;
                background:#fff5f5;
                line-height:1.6;
            ">

                <div style="
                    font-weight:700;
                    margin-bottom:8px;
                ">

                    AI Reports Unavailable

                </div>

                <div style="
                    font-size:13px;
                    white-space:pre-wrap;
                    word-break:break-word;
                ">

                    ${error.message}

                </div>

            </div>

        `;
    }
}
</script>