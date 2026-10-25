<style>
    @media(max-width:768px) {
        #tripAnalyticsContainer { padding: 16px !important; border-radius: 12px !important; }
        #tripAnalyticsContainer input[type="date"] { width: 100% !important; }
        #tripAnalyticsContainer .d-flex.gap-2.flex-wrap { width: 100%; }
        #tripAnalyticsContainer .card canvas { max-height: 220px !important; }
    }

    .trip-ai-loader {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        border: 2px solid #dce8b2;
        border-top: 2px solid #7da453;
        animation: spinTripAI 0.8s linear infinite;
        flex-shrink: 0;
    }

    @keyframes spinTripAI {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    @keyframes fadeTripAI {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .trip-ai-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        animation: fadeTripAI .3s ease;
    }

    .trip-ai-card {
        background: #fafaf5;
        border: 1px solid #ece7c0;
        border-radius: 10px;
        padding: 10px 12px;
    }

    .trip-ai-head {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        color: #2f4f2f;
        margin-bottom: 5px;
    }

    .trip-ai-head i {
        color: #6b7d3a;
        font-size: 0.8rem;
    }

    .trip-ai-body {
        font-size: 0.78rem;
        line-height: 1.55;
        color: #555;
    }

    @media(max-width:768px) {
        .trip-ai-grid {
            grid-template-columns: 1fr;
        }
    }

    .chart-loading {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .tc-skel {
        position: relative;
        overflow: hidden;
        background: #ece7cf;
        border-radius: 10px;
    }

    .tc-skel::after {
        content: "";
        position: absolute;
        top: 0; left: 0; height: 100%; width: 40%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.65), transparent);
        animation: tcShimmer 1.3s infinite;
    }

    @keyframes tcShimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(260%); }
    }

    .tc-loader {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        border: 2px solid #dce8b2;
        border-top-color: #198754;
        animation: tcSpin 0.9s linear infinite;
        flex-shrink: 0;
    }

    @keyframes tcSpin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div class="container mb-5" id="tripAnalyticsContainer"
     style="
        background:linear-gradient(180deg,#ffffff 0%,#faf6ed 100%);
        border:1px solid #e7e3af;
        border-radius:14px;
        padding:24px;
        box-shadow:0 8px 24px rgba(0,0,0,0.05);
">

    <div class="mb-3">
        <h4 class="fw-bold mb-1" style="color:#295738; font-size:1.15rem;">
            <i class="bi bi-bar-chart-line-fill text-success me-2"></i>Trip Analytics Dashboard
        </h4>
        <small style="color:#94a3b8; font-size:0.8rem;">
        Customizable operational analysis
        </small>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3"
     style="
        background:#fffdf8;
        border:1px solid #e7e3af;
        border-radius:10px;
        padding:10px 16px;
">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-funnel text-success" style="font-size:0.85rem;"></i>
            <span style="font-weight:600; font-size:0.8rem; color:#295738;">Date Range</span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <input type="date" id="fromDate" class="form-control form-control-sm" style="width:150px;">
            <span class="text-muted small">to</span>
            <input type="date" id="toDate" class="form-control form-control-sm" style="width:150px;">
            <button id="applyRange" class="btn btn-success btn-sm px-3" style="font-size:0.75rem;">Apply</button>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-3 align-items-stretch">

        <div style="flex:7 1 0; min-width:300px; max-width:100%; height:400px;">
            <div class="card shadow-sm p-3 h-100"
             style="background:#fffdf2;border:1px solid #e7e3af;border-radius:14px;display:flex;flex-direction:column;">
                <h5 class="fw-semibold mb-2" style="font-size:0.85rem; color:#2f4f2f;">
                    <i class="bi bi-bar-chart-fill text-success me-2"></i>Trip Volume
                </h5>
                <div id="tripChartLoading" class="chart-loading" style="flex:1 1 auto;min-height:0;">
                    <div class="d-inline-flex align-items-center gap-2" style="font-size:0.8rem;font-weight:600;color:#198754;">
                        <div class="tc-loader"></div> Loading trip data...
                    </div>
                    <div class="tc-skel" style="flex:1 1 auto;"></div>
                </div>
                <canvas id="tripChart" style="width:100%;flex:1 1 auto;min-height:0;display:none;"></canvas>
            </div>
        </div>

        <div style="flex:3 1 0; min-width:300px; max-width:100%; height:400px;">
            <div class="card shadow-sm p-3 h-100"
             style="background:#fffdf8;border:1px solid #e7e3af;border-radius:14px;display:flex;flex-direction:column;">
                <h5 class="fw-semibold mb-2" style="font-size:0.85rem; color:#2f4f2f;">
                    <i class="bi bi-cpu-fill text-success me-2"></i>AI Trip Analytics
                </h5>
                <div id="tripAIAnalysis" style="flex:1 1 auto;min-height:0;overflow-y:auto;"></div>
            </div>
        </div>

    </div>

    <div class="card shadow-sm p-4 mt-4"
     style="background:#fffdf2;border:1px solid #e7e3af;border-radius:14px;">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-semibold mb-0" style="font-size:0.85rem; color:#2f4f2f;">
                <i class="bi bi-people-fill text-success me-2"></i>Passenger Distribution
            </h5>
            <small style="color:#94a3b8; font-size:0.75rem;">Passenger Categories</small>
        </div>

        <div id="passengerChartLoading" class="chart-loading" style="margin-top:6px;">
            <div class="d-inline-flex align-items-center gap-2" style="font-size:0.8rem;font-weight:600;color:#198754;">
                <div class="tc-loader"></div> Loading passenger data...
            </div>
            <div class="tc-skel" style="height:280px;max-width:100%;"></div>
        </div>

        <canvas id="passengerChart" style="max-height:320px;display:none;"></canvas>

    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

    document.addEventListener("DOMContentLoaded", function () {

        let tripChart;
        let passengerChart;

        let passengerRawData = null;
        let level = "payment";



        tripChart = new Chart(
        document.getElementById("tripChart"),
        {
            type: "bar",
            data: {
                labels: [],
                datasets: [{
                    label: "Trips",
                    data: [],
                    backgroundColor: [],
                    borderColor: [],
                    borderWidth:1,
                    borderRadius:6,
                    minBarLength:5
                }]
            },
            options: {
                responsive:true,
                maintainAspectRatio:false,
                animation: {
                    duration:1000,
                    easing:'easeOutQuart',
                    delay:(ctx) => (ctx.dataIndex||0)*60
                },
                plugins: { legend: { display:false } },
                scales: {
                    y: {
                        beginAtZero:true,
                        ticks: {
                            precision:0,
                            callback:(value) => Number.isInteger(value) ? value : null
                        }
                    }
                } } });


        passengerChart = new Chart(
        document.getElementById("passengerChart"),
        {
            type:"bar",
            data: {
                labels:[],
                datasets:[{
                    data:[],
                    backgroundColor:"#dee4ba",
                    borderColor:"#2f4f2f",
                    borderWidth:1.5,
                    borderRadius:8,
                    minBarLength:5
                }]
            },
            options: {
                indexAxis:"y",
                responsive:true,
                animation: {
                    duration:1000,
                    easing:'easeOutQuart',
                    delay:(ctx) => (ctx.dataIndex||0)*60
                },
                plugins: {
                    legend: { display:false },
                    title: {
                        display:true,
                        text:"Passenger Overview",
                        color:"#2f4f2f",
                        font: { size:14, weight:'600' }
                    }
                },
                scales: { x: { beginAtZero:true } }
            }
        });



        function getPassengerData() {

            if (!passengerRawData) {
                return { labels:[], data:[], title:"No Data" };
            }

            const n = (v) => Number(v || 0);

            switch (level) {

                case "payment":
                return {
                    title:"Payment Methods",
                    labels:["Cash", "GCash", "Hybrid"],
                    data:[n(passengerRawData.cash), n(passengerRawData.gcash), n(passengerRawData.hybrid)]
                };

                case "type":
                return {
                    title:"Passenger Type",
                    labels:["Regular", "Special"],
                    data:[n(passengerRawData.regular), n(passengerRawData.special)]
                };

                case "trip_type":
                return {
                    title:"Trip Type",
                    labels:["Regular Trips", "Special Trips"],
                    data:[n(passengerRawData.regular_trips), n(passengerRawData.special_trips)]
                };

                case "special":
                return {
                    title:"Special Breakdown",
                    labels:["Student", "Senior", "PWD", "Nurse", "Teacher"],
                    data:[
                    n(passengerRawData.students),
                    n(passengerRawData.seniors),
                    n(passengerRawData.pwd),
                    n(passengerRawData.nurses),
                    n(passengerRawData.teachers)
                    ]
                };

                default:
                return { labels:[], data:[], title:"No Data" };
            }
        }



        function renderPassengerChart() {

            const d = getPassengerData();

            passengerChart.data.labels = d.labels;
            passengerChart.data.datasets[0].data = d.data;
            passengerChart.options.plugins.title.text = d.title;

            passengerChart.update();

            const hasPassengerData = (d.data || []).some(v => Number(v) > 0);
            if (hasPassengerData) {
                if (window.__hideChartNoData) window.__hideChartNoData("passengerChart");
            } else {
                if (window.__showChartNoData) window.__showChartNoData("passengerChart", "No data available for the selected period.");
            }

        }



        document.getElementById("passengerChart").onclick = function (evt) {

            const points = passengerChart.getElementsAtEventForMode(
            evt, 'nearest', { intersect:true }, true
            );

            if (!points.length) return;

            const label = passengerChart.data.labels[points[0].index];

            switch (level) {

                case "payment":
                level = "type";
                break;

                case "type":
                level = (label === "Special") ? "special" : "trip_type";
                break;

                case "special":
                level = "trip_type";
                break;

                default:
                level = "payment";
                break;

            }

            renderPassengerChart();

        };



        function showAILoading() {
            const container =
            document.getElementById("tripAIAnalysis");
            if (!container) return;

            container.innerHTML = `

<div class="chart-loading">

<div class="d-inline-flex align-items-center gap-2" style="font-size:0.8rem;font-weight:600;color:#198754;padding:2px 0;">
<div class="tc-loader"></div> Analyzing trip data...
</div>

<div class="trip-ai-grid">
<div class="trip-ai-card">
<div class="tc-skel" style="width:45%;height:11px;margin-bottom:8px;"></div>
<div class="tc-skel" style="width:95%;height:9px;margin-bottom:5px;"></div>
<div class="tc-skel" style="width:80%;height:9px;margin-bottom:5px;"></div>
<div class="tc-skel" style="width:88%;height:9px;"></div>
</div>

<div class="trip-ai-card">
<div class="tc-skel" style="width:40%;height:11px;margin-bottom:8px;"></div>
<div class="tc-skel" style="width:92%;height:9px;margin-bottom:5px;"></div>
<div class="tc-skel" style="width:75%;height:9px;margin-bottom:5px;"></div>
<div class="tc-skel" style="width:85%;height:9px;"></div>
</div>

<div class="trip-ai-card">
<div class="tc-skel" style="width:48%;height:11px;margin-bottom:8px;"></div>
<div class="tc-skel" style="width:90%;height:9px;margin-bottom:5px;"></div>
<div class="tc-skel" style="width:78%;height:9px;margin-bottom:5px;"></div>
<div class="tc-skel" style="width:84%;height:9px;"></div>
</div>

<div class="trip-ai-card">
<div class="tc-skel" style="width:52%;height:11px;margin-bottom:8px;"></div>
<div class="tc-skel" style="width:94%;height:9px;margin-bottom:5px;"></div>
<div class="tc-skel" style="width:82%;height:9px;margin-bottom:5px;"></div>
<div class="tc-skel" style="width:86%;height:9px;"></div>
</div>
</div>

</div>

`;
        }

        function showChartLoading() {
            const tripLoad =
            document.getElementById("tripChartLoading");
            if (tripLoad) tripLoad.style.display = "";
            const tripCanvas =
            document.getElementById("tripChart");
            if (tripCanvas) tripCanvas.style.display = "none";

            const passLoad =
            document.getElementById("passengerChartLoading");
            if (passLoad) passLoad.style.display = "";
            const passCanvas =
            document.getElementById("passengerChart");
            if (passCanvas) passCanvas.style.display = "none";

            showAILoading();
        }

        function hideChartLoading() {
            const tripLoad =
            document.getElementById("tripChartLoading");
            if (tripLoad) tripLoad.style.display = "none";
            const tripCanvas =
            document.getElementById("tripChart");
            if (tripCanvas) tripCanvas.style.display = "";

            const passLoad =
            document.getElementById("passengerChartLoading");
            if (passLoad) passLoad.style.display = "none";
            const passCanvas =
            document.getElementById("passengerChart");
            if (passCanvas) passCanvas.style.display = "";

            if (tripChart) {
                setTimeout(() => { tripChart.resize(); }, 60);
            }
            if (passengerChart) {
                setTimeout(() => { passengerChart.resize(); }, 60);
            }
        }

        function loadTripAnalytics(query = "") {

            window.__trReq = (window.__trReq || 0) + 1;
            const req = window.__trReq;

            showChartLoading();

            if (window.__dashLoadBegin) window.__dashLoadBegin();
            fetch("/admin/api/dashboard/trip-analytics.php?"+query, { cache:"no-store" })
            .then(res =>res.json())
            .then(data => {

                if (req !== window.__trReq) return;

                if (!data.success) {
                    hideChartLoading();
                    return;
                }

                hideChartLoading();

                const trips = data.trips.map(t =>({
                    date: t.trip_date,
                    trips: Number(t.trips),
                    predicted: t.is_predicted
                }));

                let chartTrips = trips;

                if (!query) {

                    const cutoff = new Date();
                    cutoff.setDate(cutoff.getDate() - 30);
                    const cutoffStr = cutoff.toISOString().slice(0, 10);

                    chartTrips =
                    trips.filter(
                    d => d.date >= cutoffStr
                    );
                }

                const realTrips = chartTrips.filter(t => !t.predicted);

                const labels = chartTrips.map(d =>d.date);
                const values = chartTrips.map(d =>d.trips);

                const bgColors = chartTrips.map(d =>
                d.predicted
                ? "rgba(255, 222, 89, 0.3)"
                : "#ffde59"
                );

                const borderColors = chartTrips.map(d =>
                d.predicted
                ? "rgba(255, 222, 89, 1)"
                : "#d4c94f"
                );

                const hasTripData = values.some(v => Number(v) > 0);
                if (hasTripData) {
                    tripChart.data.labels = labels;
                    tripChart.data.datasets[0].data = values;
                    tripChart.data.datasets[0].backgroundColor = bgColors;
                    tripChart.data.datasets[0].borderColor = borderColors;
                    tripChart.update();
                    if (window.__hideChartNoData) window.__hideChartNoData("tripChart");
                } else {
                    tripChart.data.labels = [];
                    tripChart.data.datasets[0].data = [];
                    tripChart.data.datasets[0].backgroundColor = [];
                    tripChart.data.datasets[0].borderColor = [];
                    tripChart.update();
                    if (window.__showChartNoData) window.__showChartNoData("tripChart", "No data available for the selected period.");
                }

                generateAIAnalysis(realTrips, data.passenger_summary);

                passengerRawData = data.passenger_summary || {};
                level = "payment";
                renderPassengerChart();
            })
            .finally(() => { if (window.__dashLoadEnd) window.__dashLoadEnd(); });

        }

        window.loadTripAnalytics = loadTripAnalytics;


        function escapeHtml(str) {

            return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");

        }

        function parseAISections(text) {

            const labels =
            [
            "Summary",
            "Strengths",
            "Watch Outs",
            "Recommendation"
            ];

            const re =
            new RegExp(
            "(?:\\*{0,2}\\s*)?("
            + labels.join("|")
            + ")\\s*\\*{0,2}\\s*:\\s*"
            + "([\\s\\S]*?)"
            + "(?=(?:\\*{0,2}\\s*)?(?:"
            + labels.join("|")
            + ")\\s*\\*{0,2}\\s*:|$)",
            "g"
            );

            const sections = [];

            let m;

            while ((m = re.exec(text))) {

                sections.push({
                    label: m[1],
                    text: m[2].trim()
                });

            }

            return sections;

        }

        function renderTripAIGrid(text) {

            const container =
            document.getElementById(
            "tripAIAnalysis"
            );

            if (!container) return;

            const sections =
            parseAISections(text);

            if (!sections.length) {

                container.innerHTML = `

<div style="
font-size:0.85rem;
line-height:1.7;
color:#444;
white-space:pre-wrap;
">

${escapeHtml(text)}

</div>

`;

                return;
            }

            const icons = {
                "Summary": "bi-lightbulb-fill",
                "Strengths": "bi-graph-up-arrow",
                "Watch Outs": "bi-exclamation-triangle-fill",
                "Recommendation": "bi-check-circle-fill"
            };

            container.innerHTML = `

<div class="trip-ai-grid">

${sections.map(s=>`

<div class="trip-ai-card">

<div class="trip-ai-head">

<i class="bi ${icons[s.label] || 'bi-lightbulb-fill'}"></i>

<span>${s.label}</span>

</div>

<div class="trip-ai-body">

${escapeHtml(s.text)}

</div>

</div>

`).join('')}

</div>

`;

        }


        function generateAIAnalysis(data, summary) {

            const ps = summary || {};

            const container =
            document.getElementById(
            "tripAIAnalysis"
            );

            if (data.length === 0) {

                container.innerHTML = `
<div style="
color:#777;
font-size:14px;
">
No trip data available.
</div>
`;

                return;
            }


            const trips =
            data.map(d => Number(d.trips || 0));

            const maxTrips =
            Math.max(...trips);

            const minTrips =
            Math.min(...trips);

            const avgTrips =
            Math.round(
            trips.reduce((a, b) =>a+b, 0)
            /
            trips.length
            );

            let trend = 0;

            for (let i = 1; i<trips.length; i++) {

                trend +=
                (trips[i] - trips[i-1]);

            }

            trend =
            trend / (trips.length-1 || 1);

            let growthRate = 0;

            if (
            trips.length > 1
            && trips[0] !== 0
            ) {

                growthRate =
                (
                (
                trips[
                trips.length-1
                ]
                -
                trips[0]
                )
                /
                trips[0]
                )
                * 100;

                growthRate =
                Math.round(growthRate);
            }

            let trendStatus = "stable";

            if (trend > 1)
            trendStatus = "increasing";

            if (trend < -1)
            trendStatus = "decreasing";


            showAILoading();


            if (window.__dashLoadBegin) window.__dashLoadBegin();
            fetch(
            '/admin/api/dashboard/ai-trip-analysis.php',
            {

                method:'POST',

                headers: {
                    'Content-Type':'application/json'
                },

                body:JSON.stringify({

                    max_trips:maxTrips,

                    min_trips:minTrips,

                    average_trips:avgTrips,

                    growth_rate:growthRate,

                    trend_status:trendStatus,

                    trend_value:trend,

                    total_passengers:Number(ps.cash||0)+Number(ps.gcash||0)+Number(ps.hybrid||0),

                    cash:Number(ps.cash||0),

                    gcash:Number(ps.gcash||0),

                    hybrid:Number(ps.hybrid||0),

                    regular:Number(ps.regular||0),

                    special:Number(ps.special||0),

                    students:Number(ps.students||0),

                    seniors:Number(ps.seniors||0),

                    pwd:Number(ps.pwd||0),

                    nurses:Number(ps.nurses||0),

                    teachers:Number(ps.teachers||0),

                    window_days:data.length,

                    total_trips:trips.reduce((a, b) =>a+b, 0),

                    daily_data:data

                })

            })

            .then(async r => {

                const text = await r.text();

                try {

                    return JSON.parse(text);

                } catch (e) {

                    console.error(
                    "Invalid AI JSON:",
                    text
                    );

                    return {
                        success:false
                    };

                }

            })

            .then(ai => {

                if (!ai.success) {

                    const target =
                    document.getElementById(
                    "tripAIAnalysis"
                    );

                    if (!target) return;

                    target.innerHTML = `

<div style="
color:#dc3545;
font-weight:600;
font-size:0.85rem;
">

${ai.error ||
'Unable to generate AI analysis.'}

</div>

`;

                    return;
                }

                renderTripAIGrid(ai.analysis);

            })

            .catch (err => {

                console.error(err);

                const target =
                document.getElementById(
                "tripAIAnalysis"
                );

                if (target) {

                    target.innerHTML = `

<div style="
color:#dc3545;
font-weight:600;
font-size:0.85rem;
">

AI analysis unavailable.

</div>

`;

                }

            })
            .finally(() => { if (window.__dashLoadEnd) window.__dashLoadEnd(); });

        }


        function applyDateFilter() {

            const fromDate = document.getElementById("fromDate").value;
            const toDate = document.getElementById("toDate").value;

            const params = new URLSearchParams();

            if (fromDate) params.append("fromDate", fromDate);
            if (toDate) params.append("toDate", toDate);

            loadTripAnalytics(params.toString());

        }

        document.getElementById("applyRange")
        .addEventListener("click", applyDateFilter);


        loadTripAnalytics();

    });

</script>