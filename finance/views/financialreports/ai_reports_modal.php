<?php if (!defined('APP_LOADED')) exit; ?>

<?php

require_once __DIR__ .

    '/../../controllers/AITripOperationsReportController.php';

use Finance\Controllers\AITripOperationsReportController;

$aiController =
    new AITripOperationsReportController();

$reports =
    $aiController->getReports();

?>





<div
    class="modal fade transport-modal fin-report-modal"
    id="viewAIReportsModal"
    tabindex="-1"
    aria-labelledby="aiReportsLibraryTitle"
    aria-hidden="true"
>

    <div class="
        modal-dialog
        modal-xl
        modal-dialog-scrollable
    ">

        <div class="modal-content">

            

            <div class="modal-header">

                <div>

                    <h4 id="aiReportsLibraryTitle" style="
                        margin:0;
                        font-weight:800;
                        color:#4f7c65;
                    ">

                        AI Analysis Reports

                    </h4>

                    <div style="
                        margin-top:5px;
                        color:#737373;
                        font-size:0.92rem;
                    ">

                        View all generated AI transportation analysis reports

                    </div>

                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close AI report library"
                ></button>

            </div>

            

            <div class="modal-body">
                <div class="fin-modal-callout"><strong>Explore saved transportation analysis</strong><p>Use search and category filters to find an existing AI report. Open an entry to review its findings and reporting context.</p></div>

                

                <div style="
                    margin-bottom:24px;
                ">

                    <div class="row g-3">

                        

                        <div class="col-lg-6">

                            <div style="
                                position:relative;
                            ">

                                <i class="fas fa-search"
                                    style="
                                        position:absolute;
                                        top:50%;
                                        left:18px;
                                        transform:translateY(-50%);
                                        color:#7b8b80;
                                        font-size:14px;
                                        z-index:2;
                                    "
                                ></i>

                                <input
                                    type="text"
                                    id="aiReportSearch"

                                    placeholder="Search reports, summaries, risks..."

                                    onkeyup="filterAIReports()"

                                    style="
                                        width:100%;
                                        height:52px;
                                        border-radius:16px;
                                        border:1px solid #dbe8de;
                                        background:#f9fcfa;
                                        padding:0 18px 0 48px;
                                        font-size:14px;
                                        outline:none;
                                    "
                                >

                            </div>

                        </div>

                        

                        <div class="col-lg-3">

                            <select
                                id="aiCategoryFilter"

                                onchange="filterAIReports()"

                                style="
                                    width:100%;
                                    height:52px;
                                    border-radius:16px;
                                    border:1px solid #dbe8de;
                                    background:#f9fcfa;
                                    padding:0 16px;
                                    font-size:14px;
                                    outline:none;
                                    color:#444;
                                "
                            >

                                <option value="">

                                    All Categories

                                </option>

                                <?php

                                $categories = [];

                                foreach($reports as $report){

                                    if(
                                        !empty(
                                            $report['ai_category']
                                        )
                                    ){

                                        $categories[] =
                                            $report['ai_category'];
                                    }
                                }

                                $categories =
                                    array_unique($categories);

                                sort($categories);

                                ?>

                                <?php foreach($categories as $category): ?>

                                    <option
                                        value="<?= strtolower(
                                            htmlspecialchars($category)
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars($category) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>

                </div>

                

                <div
                    class="row g-4"
                    id="aiReportsGrid"
                >

                    <?php if(empty($reports)): ?>

                        <div class="col-12">

                            <div class="alert alert-warning">

                                No AI analysis reports available.

                            </div>

                        </div>

                    <?php endif; ?>

                    <?php foreach($reports as $report): ?>

                        <?php

                        $insights =

                            json_decode(
                                $report['ai_insights']
                                ?? '[]',
                                true
                            );

                        ?>

                        <div
                            class="col-lg-6 ai-report-card"

                            data-category="<?= strtolower(

                                htmlspecialchars(
                                    $report['ai_category']
                                )

                            ) ?>"

                            data-date="<?= htmlspecialchars(
                                $report['generated_at']
                            ) ?>"

                            data-search="<?= strtolower(

                                htmlspecialchars(

                                    $report['report_title']
                                    .
                                    ' '
                                    .
                                    $report['trip_group_code']
                                    .
                                    ' '
                                    .
                                    $report['ai_category']
                                    .
                                    ' '
                                    .
                                    $report['ai_summary']
                                )
                            ) ?>"
                        >

                            <div class="transport-section-card">

                                

                                <div style="
                                    display:flex;
                                    justify-content:space-between;
                                    align-items:flex-start;
                                    gap:15px;
                                    margin-bottom:18px;
                                ">

                                    <div>

                                        <div style="
                                            font-size:1rem;
                                            font-weight:800;
                                            color:#4f7c65;
                                            margin-bottom:5px;
                                        ">

                                            <?= htmlspecialchars(
                                                $report['report_title']
                                            ) ?>

                                        </div>

                                        <div style="
                                            font-size:13px;
                                            color:#737373;
                                        ">

                                            <?= htmlspecialchars(
                                                $report['trip_group_code']
                                            ) ?>

                                        </div>

                                    </div>

                                    

                                    <div style="
                                        background:#edf5ef;
                                        color:#4f7c65;
                                        padding:6px 12px;
                                        border-radius:999px;
                                        font-size:12px;
                                        font-weight:700;
                                    ">

                                        <?= htmlspecialchars(
                                            $report['ai_category']
                                        ) ?>

                                    </div>

                                </div>

                                

                                <div style="
                                    color:#444;
                                    line-height:1.7;
                                    font-size:14px;
                                    margin-bottom:18px;
                                    min-height:95px;
                                ">

                                    <?= nl2br(htmlspecialchars(

                                        mb_strimwidth(

                                            $report['ai_summary'],

                                            0,

                                            230,

                                            '...'
                                        )
                                    )) ?>

                                </div>

                                

                                <?php if(!empty($insights)): ?>

                                    <div style="
                                        margin-bottom:18px;
                                    ">

                                        <?php foreach(array_slice($insights,0,2) as $item): ?>

                                            <div style="
                                                background:#fcfbf4;
                                                border:1px solid #ece7c8;
                                                border-radius:12px;
                                                padding:10px 12px;
                                                margin-bottom:10px;
                                                font-size:13px;
                                                color:#555;
                                            ">

                                                • <?= htmlspecialchars($item) ?>

                                            </div>

                                        <?php endforeach; ?>

                                    </div>

                                <?php endif; ?>

                                

                                <div style="
                                    display:flex;
                                    justify-content:space-between;
                                    align-items:center;
                                    margin-top:15px;
                                ">

                                    <div style="
                                        font-size:12px;
                                        color:#888;
                                    ">

                                        <?= htmlspecialchars(
                                            $report['generated_at']
                                        ) ?>

                                    </div>

                                    

                                    <div style="
                                        display:flex;
                                        gap:10px;
                                    ">

                                        

                                        <button
                                            type="button"
                                            class="btn btn-sm"

                                            onclick="viewAIReport(this)"

                                            data-report='<?= htmlspecialchars(
                                                json_encode($report),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>'

                                            style="
                                                background:#edf5ef;
                                                border:1px solid #cfe1d4;
                                                color:#4f7c65;
                                                border-radius:12px;
                                                width:42px;
                                                height:42px;
                                                display:flex;
                                                align-items:center;
                                                justify-content:center;
                                                cursor:pointer;
                                            "
                                        >

                                            <i class="fas fa-eye"></i>

                                        </button>

                                    </div>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>

    </div>

</div>





<div
    class="modal fade transport-modal fin-report-modal"
    id="viewSingleAIReportModal"
    tabindex="-1"
    aria-labelledby="singleAIReportTitle"
    aria-hidden="true"
>

    <div class="
        modal-dialog
        modal-xl
        modal-dialog-scrollable
    ">

        <div class="modal-content">

            <div class="modal-header">

                <h4 id="singleAIReportTitle"
                    style="
                        margin:0;
                        font-weight:800;
                        color:#4f7c65;
                    "
                >

                    AI Report

                </h4>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close AI report"
                ></button>

            </div>

            <div class="modal-body">
                <p class="fin-modal-help mb-3">Read this saved analysis alongside its reporting period and source context. Its findings describe the data available when it was generated.</p>

                <div id="singleAIReportContent">

                </div>

            </div>

        </div>

    </div>

</div>





<script>
function filterAIReports() {
    const search = document.getElementById("aiReportSearch").value.toLowerCase();

    const category = document.getElementById("aiCategoryFilter").value.toLowerCase();

    const finFromEl = document.getElementById("finReportsFrom");

    const finToEl = document.getElementById("finReportsTo");

    const rangeFrom = (finFromEl && finFromEl.value) || "";

    const rangeTo = (finToEl && finToEl.value) || "";

    const cards = document.querySelectorAll(".ai-report-card");

    cards.forEach((card) => {
        const text = card.getAttribute("data-search").toLowerCase();

        const cardCategory = (card.getAttribute("data-category") || "").toLowerCase();

        const cardDate = (card.getAttribute("data-date") || "").slice(0, 10);

        const matchSearch = text.includes(search);

        const matchCategory = !category || cardCategory.includes(category);

        const matchDate =
            (!rangeFrom || cardDate >= rangeFrom) && (!rangeTo || cardDate <= rangeTo);

        if (matchSearch && matchCategory && matchDate) {
            card.style.display = "";
        } else {
            card.style.display = "none";
        }
    });
}

function viewAIReport(button) {
    try {
        const report = JSON.parse(button.getAttribute("data-report"));

        let analysis = [];

        let insights = [];

        let risks = [];

        let recommendations = [];

        try {
            analysis = JSON.parse(report.ai_analysis || "[]");
        } catch (e) {}

        try {
            insights = JSON.parse(report.ai_insights || "[]");
        } catch (e) {}

        try {
            risks = JSON.parse(report.ai_risks || "[]");
        } catch (e) {}

        try {
            recommendations = JSON.parse(report.ai_recommendations || "[]");
        } catch (e) {}

        document.getElementById("singleAIReportTitle").innerHTML = report.report_title;

        let html = `

            <div class="transport-section-card">

                <div style="
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    margin-bottom:20px;
                    flex-wrap:wrap;
                    gap:15px;
                ">

                    <div>

                        <div style="
                            font-size:13px;
                            color:#737373;
                        ">

                            ${report.trip_group_code}

                        </div>

                        <div style="
                            font-size:13px;
                            color:#737373;
                            margin-top:5px;
                        ">

                            Generated:
                            ${report.generated_at}

                        </div>

                    </div>

                    <div style="
                        background:#edf5ef;
                        color:#4f7c65;
                        padding:8px 14px;
                        border-radius:999px;
                        font-size:13px;
                        font-weight:700;
                    ">

                        ${report.ai_category}

                    </div>

                </div>


                <div style="
                    margin-bottom:25px;
                ">

                    <div class="transport-section-title">

                        AI Summary

                    </div>

                    <div style="
                        line-height:1.9;
                        color:#444;
                    ">

                        ${report.ai_summary}

                    </div>

                </div>

        `;

        if (analysis.length) {
            html += `

                <div style="margin-bottom:25px;">

                    <div class="transport-section-title">

                        Detailed Analysis

                    </div>

            `;

            analysis.forEach((item) => {
                html += `

                    <div style="
                        margin-bottom:16px;
                        line-height:1.8;
                        color:#444;
                    ">

                        ${item}

                    </div>

                `;
            });

            html += `</div>`;
        }

        if (insights.length) {
            html += `

                <div style="margin-bottom:25px;">

                    <div class="transport-section-title">

                        Insights

                    </div>

                    <ul>
            `;

            insights.forEach((item) => {
                html += `<li>${item}</li>`;
            });

            html += `</ul></div>`;
        }

        if (risks.length) {
            html += `

                <div style="margin-bottom:25px;">

                    <div class="transport-section-title">

                        Risks

                    </div>

                    <ul>
            `;

            risks.forEach((item) => {
                html += `<li>${item}</li>`;
            });

            html += `</ul></div>`;
        }

        if (recommendations.length) {
            html += `

                <div style="margin-bottom:25px;">

                    <div class="transport-section-title">

                        Recommendations

                    </div>

                    <ul>
            `;

            recommendations.forEach((item) => {
                html += `<li>${item}</li>`;
            });

            html += `</ul></div>`;
        }

        html += `

            <div style="
                display:flex;
                gap:12px;
                margin-top:25px;
                flex-wrap:wrap;
            ">

                <a
                    href="/finance/export-ai-report-pdf?id=${report.id}"
                    target="_blank"
                    class="btn"
                    style="
                        background:#fff3f3;
                        border:1px solid #ffd2d2;
                        color:#cc4b37;
                        border-radius:14px;
                        padding:12px 18px;
                    "
                >

                    <i class="fas fa-file-pdf"></i>

                    Export PDF

                </a>

                <a
                    href="/finance/export-ai-report-excel?id=${report.id}"
                    target="_blank"
                    class="btn"
                    style="
                        background:#eef8ef;
                        border:1px solid #cfe8d1;
                        color:#2f7d32;
                        border-radius:14px;
                        padding:12px 18px;
                    "
                >

                    <i class="fas fa-file-excel"></i>

                    Export Excel

                </a>

            </div>

        `;

        html += `</div>`;

        document.getElementById("singleAIReportContent").innerHTML = html;

        const modal = new bootstrap.Modal(document.getElementById("viewSingleAIReportModal"));

        modal.show();
    } catch (error) {
        console.error(error);

        showAlert("error", "View Error", "Unable to load AI report.");
    }
}
</script>
