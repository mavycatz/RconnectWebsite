<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<div class="d-flex justify-content-between align-items-center mb-3">
    

    <div>
        <h1 style="font-weight:800; font-size:1.4rem; color:#295738; margin:0 0 2px;">
        Reports Center
        </h1>
        <p style="font-weight:500; font-size:0.82rem; color:#8a9a8a; margin:0;">
        Generate, view, and export operational and financial reports.
        </p>
    </div>

</div>


<?php
$tools = [

["Generate New Report", "Create printable report", "bi-file-earmark-plus", "globalTransportGenerateReportsModal"],
[
"Download All Reports",
"Export all generated reports",
"bi-download",
"globalTransportDownloadReportsModal"
],

[
"Archive Reports",
"Move old reports to archive",
"bi-archive",
"archiveModal"
],

[
"Print Logs",
"View and print report logs",
"bi-printer",
"logsModal"
]

];
?>


<div class="row g-4">

<?php foreach ($tools as $tool): ?>

<div class="col-lg-3 col-md-6">

    <div class="report-card h-100"

     data-modal="<?= $tool[3]; ?>"

     style="
        cursor:pointer;
        padding:20px;
        background:#faf9e5;
        border:1px solid #e5e0a4;
        border-radius:14px;
        transition:all .2s ease;
     ">

<div class="d-flex flex-column">


    <div style="
    font-size:1.6rem;
    color:#295738;
    margin-bottom:12px;
">

        <i class="bi <?= $tool[2]; ?>"></i>

</div>


<div style="
    font-weight:700;
    color:#295738;
    font-size:1rem;
    margin-bottom:6px;
">

<?= $tool[0]; ?>

</div>


<div style="
    font-size:.85rem;
    color:#5b6d60;
    line-height:1.4;
">

<?= $tool[1]; ?>

</div>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

<script>

    function openReportModal(modalId) {

        const modalElement = document.getElementById(modalId);

        if (!modalElement) {
            console.error("Modal not found:", modalId);
            return;
        }

        const modal = new bootstrap.Modal(modalElement);

        modal.show();

    }

</script>