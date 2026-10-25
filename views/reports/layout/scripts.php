<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="/public/login/custom-alert.css">

<script>

    let generatedPDFUrl = '';


    document.addEventListener(
    'DOMContentLoaded',
    function () {

        console.clear();

        console.log(
        '✅ REPORT SYSTEM LOADED'
        );


        const reportCards =

        document.querySelectorAll(
        '.report-card'
        );


        reportCards.forEach(card => {

            card.addEventListener(
            'click',
            function () {


                const modalId =

                this.dataset.modal;


                if (!modalId) {

                    console.error(
                    '❌ Missing modal ID'
                    );

                    return;
                }


                document
                .querySelectorAll('.modal.show')
                .forEach(openModal => {

                    const instance =

                    bootstrap.Modal
                    .getInstance(
                    openModal
                    );

                    if (instance) {

                        instance.hide();
                    }
                });


                document
                .querySelectorAll(
                '.modal-backdrop'
                )
                .forEach(backdrop => {

                    backdrop.remove();
                });


                document.body.classList.remove(
                'modal-open'
                );

                document.body.style.overflow = '';

                document.body.style.paddingRight = '';


                setTimeout(() => {


                    const targetModal =

                    document.getElementById(
                    modalId
                    );


                    if (!targetModal) {

                        console.error(
                        '❌ Modal not found:',
                        modalId
                        );

                        return;
                    }


                    const modal =

                    new bootstrap.Modal(
                    targetModal,
                    {
                        backdrop:true,
                        keyboard:true,
                        focus:true
                    }
                    );


                    modal.show();

                }, 250);
            }
            );
        });


        document
        .querySelectorAll('.modal')
        .forEach(modal => {

            modal.addEventListener(
            'hidden.bs.modal',
            function () {


                document
                .querySelectorAll(
                '.modal-backdrop'
                )
                .forEach(backdrop => {

                    backdrop.remove();
                });


                document.body.classList.remove(
                'modal-open'
                );

                document.body.style.overflow = '';

                document.body.style.paddingRight = '';
            }
            );
        });
    });


    function setText(id, value)
    {
        const element =

        document.getElementById(id);

        if (!element) {

            console.warn(
            'Missing element:',
            id
            );

            return;
        }

        element.innerText = value;
    }


    function setHTML(id, value)
    {
        const element =

        document.getElementById(id);

        if (!element) {

            console.warn(
            'Missing element:',
            id
            );

            return;
        }

        element.innerHTML = value;
    }


    function renderReportList(
    elementId,
    items
    ) {

        let html = '';


        if (
        !Array.isArray(items)
        ||
        items.length === 0
        ) {

            html = `

        <li class="list-group-item text-muted"
            style="
                padding:14px 16px;
                font-size:.84rem;
            ">

            No data available.

        </li>
        `;
        }


        else {

            items.forEach(item => {

                html += `

            <li class="list-group-item"
                style="
                    padding:14px 16px;
                    border-color:#ecefed;
                    font-size:.85rem;
                    color:#4b5563;
                    line-height:1.7;
                ">

                ${item}

            </li>
            `;
            });
        }

        setHTML(
        elementId,
        html
        );
    }


    async function generateReport()
    {
        try {


            const loadingElement =

            document.getElementById(
            'reportLoading'
            );

            const resultElement =

            document.getElementById(
            'reportResult'
            );

            const generateButton =

            document.getElementById(
            'generateBtn'
            );

            const reportTypeElement =

            document.getElementById(
            'reportType'
            );


            if (!reportTypeElement) {

                throw new Error(
                'Report type selector not found.'
                );
            }


            if (loadingElement) {

                loadingElement.classList.remove(
                'd-none'
                );
            }

            if (resultElement) {

                resultElement.classList.add(
                'd-none'
                );
            }

            if (generateButton) {

                generateButton.disabled = true;
            }


            const reportType =
            reportTypeElement.value;


            const response = await fetch(

            '/admin/api/reports/GenerateReportAPI.php',

            {
                method:'POST',

                headers: {
                    'Content-Type':'application/json'
                },

                body:JSON.stringify({

                    report_type:reportType
                })
            }
            );


            if (!response.ok) {

                throw new Error(

                'Server returned '
                +
                response.status
                );
            }


            let result = null;

            try {

                result =
                await response.json();
            }

            catch (jsonError) {

                throw new Error(
                'Invalid API JSON response.'
                );
            }


            if (!result.success) {

                throw new Error(

                result.message
                ||
                'Failed to generate report.'
                );
            }


            const ai =
            result.data?.ai_report || {};

            const raw =
            result.data?.raw_report || {};


            generatedPDFUrl =
            result.data?.pdf_url || '';


            setText(

            'reportTitle',

            ai.title
            ||
            'Transportation Report'
            );


            setText(

            'reportCategory',

            ai.category
            ||
            'Transportation'
            );


            setText(

            'reportConfidence',

            (
            ai.confidence
            ||
            'Medium'
            )
            +
            ' Confidence'
            );


            setText(

            'reportRecords',

            (
            raw.total_records
            ||
            0
            )
            +
            ' Records'
            );


            setText(

            'reportSummary',

            ai.summary
            ||
            'No summary available.'
            );


            let analysisHTML = '';

            if (
            Array.isArray(ai.analysis)
            &&
            ai.analysis.length > 0
            ) {

                ai.analysis.forEach(paragraph => {

                    analysisHTML += `

                <div style="
                    background:#fff;
                    border:1px solid #ecefed;
                    border-radius:14px;
                    padding:18px;
                    margin-bottom:12px;
                    line-height:1.8;
                    color:#4b5563;
                    font-size:.88rem;
                ">

                    ${paragraph}

                </div>
                `;
                });
            }


            else {

                analysisHTML = `

            <div style="
                background:#fff;
                border:1px solid #ecefed;
                border-radius:14px;
                padding:18px;
                color:#6b7280;
                font-size:.84rem;
            ">

                No analysis available.

            </div>
            `;
            }


            setHTML(
            'reportAnalysis',
            analysisHTML
            );


            renderReportList(
            'reportInsights',
            ai.insights
            );

            renderReportList(
            'reportRisks',
            ai.risks
            );

            renderReportList(
            'reportRecommendations',
            ai.recommendations
            );


            if (resultElement) {

                resultElement.classList.remove(
                'd-none'
                );
            }


            showAlert('success', 'Report Generated', 'Transportation report generated successfully.');
        }

        catch (error) {

            console.error(error);

            showAlert('error', 'Generation Failed', error.message || 'Failed to generate report.');
        }

        finally {


            const loadingElement =

            document.getElementById(
            'reportLoading'
            );

            const generateButton =

            document.getElementById(
            'generateBtn'
            );

            if (loadingElement) {

                loadingElement.classList.add(
                'd-none'
                );
            }

            if (generateButton) {

                generateButton.disabled = false;
            }
        }
    }


    function downloadGeneratedPDF()
    {
        if (!generatedPDFUrl) {

            alert(
            'No generated PDF found.'
            );

            return;
        }

        window.open(
        generatedPDFUrl,
        '_blank'
        );
    }

    async function archiveReports()
    {
        try {


            const modal =

            document.getElementById(
            'archiveModal'
            );


            const year =

            modal.querySelector(
            '#archiveYear'
            ).value;


            const month =

            modal.querySelector(
            '#archiveMonth'
            ).value;


            console.log({

                selectedYear: year,

                selectedMonth: month
            });


            if (!confirm('Archive Reports? Selected transportation reports will be moved into archive storage.')) {

                return;
            }


            const buttonText =

            document.getElementById(
            'archiveBtnText'
            );

            const buttonLoading =

            document.getElementById(
            'archiveBtnLoading'
            );

            if (buttonText) {

                buttonText.classList.add(
                'd-none'
                );
            }

            if (buttonLoading) {

                buttonLoading.classList.remove(
                'd-none'
                );
            }


            const response = await fetch(

            '/admin/api/reports/ArchiveReportsAPI.php',

            {
                method:'POST',

                headers: {
                    'Content-Type':'application/json'
                },

                body:JSON.stringify({

                    year:year,

                    month:month
                })
            }
            );


            if (!response.ok) {

                throw new Error(

                'Server returned '
                +
                response.status
                );
            }


            const result =
            await response.json();


            console.log(
            'ARCHIVE RESPONSE:',
            result
            );


            if (!result.success) {

                throw new Error(

                result.message
                ||
                'Failed to archive reports.'
                );
            }


            showAlert('success', 'Archived Successfully', 'Transportation reports were archived successfully.');


            await loadArchivedReports();
        }

        catch (error) {

            console.error(error);

            showAlert('error', 'Archive Failed', error.message || 'Failed to archive reports.');
        }

        finally {


            const buttonText =

            document.getElementById(
            'archiveBtnText'
            );

            const buttonLoading =

            document.getElementById(
            'archiveBtnLoading'
            );

            if (buttonText) {

                buttonText.classList.remove(
                'd-none'
                );
            }

            if (buttonLoading) {

                buttonLoading.classList.add(
                'd-none'
                );
            }
        }
    }



    async function loadArchivedReports()
    {
        try {


            const response = await fetch(

            '/admin/api/reports/GetArchivedReportsAPI.php'
            );


            const result =
            await response.json();


            const container =

            document.getElementById(
            'archiveResultsList'
            );


            container.innerHTML = '';


            let totalSize = 0;

            let totalFiles = 0;


            if (
            !result.files
            ||
            result.files.length === 0
            ) {

                container.innerHTML = `

            <div style="
                padding:30px;
                text-align:center;
                color:#9ca3af;
                font-size:.84rem;
            ">

                No archived reports found.

            </div>
            `;


                document.getElementById(
                'archiveTotalFiles'
                ).innerText = '0';

                document.getElementById(
                'archiveTotalSize'
                ).innerText = '0 KB';

                return;
            }


            result.files.forEach(file => {

                totalFiles++;


                const sizeNumber =

                parseFloat(
                file.size
                );

                totalSize += sizeNumber;


                container.innerHTML += `

            <div class="archived-report-row"
                 data-name="${file.name.toLowerCase()}"
                 style="
                    padding:14px 16px;
                    border-bottom:1px solid #f1f3f2;
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    gap:14px;
                 ">

                <div style="flex:1;">

                    <div style="
                        font-size:.84rem;
                        font-weight:600;
                        color:#374151;
                        word-break:break-word;
                    ">

                        ${file.name}

                    </div>

                    <div style="
                        font-size:.74rem;
                        color:#9ca3af;
                        margin-top:4px;
                    ">

                        ${file.size}

                    </div>

                </div>

                <div class="d-flex gap-2">

                    <a href="${file.path}"
                       target="_blank"
                       class="btn btn-sm btn-light border">

                        <i class="fas fa-eye"></i>

                    </a>

                    <button
                        type="button"
                        onclick="restoreArchivedReport('${file.name}')"
                        class="btn btn-sm btn-dark">

                        <i class="fas fa-rotate-left me-1"></i>

                        Restore

                    </button>

                </div>

            </div>
            `;
            });


            document.getElementById(
            'archiveTotalFiles'
            ).innerText = totalFiles;

            document.getElementById(
            'archiveTotalSize'
            ).innerText =

            totalSize.toFixed(2)
            + ' KB';
        }

        catch (error) {

            console.error(error);

            showAlert('error', 'Load Failed', 'Failed to load archived reports.');
        }
    }


    async function restoreArchivedReport(file)
    {
        try {

            if (!confirm('Restore Report? The archived report will be restored back into generated reports.')) {

                return;
            }


            const response = await fetch(

            '/admin/api/reports/RestoreArchivedReportAPI.php',

            {
                method:'POST',

                headers: {
                    'Content-Type':'application/json'
                },

                body:JSON.stringify({

                    file:file
                })
            }
            );


            const result =
            await response.json();


            if (!result.success) {

                throw new Error(
                result.message
                ||
                'Failed to restore report.'
                );
            }


            showAlert('success', 'Restored Successfully', 'Archived report restored successfully.');


            await loadArchivedReports();
        }

        catch (error) {

            console.error(error);

            showAlert('error', 'Restore Failed', error.message || 'Failed to restore report.');
        }
    }


    function filterArchivedReports()
    {
        const search =

        document
        .getElementById('archiveSearch')
        .value
        .toLowerCase();

        document
        .querySelectorAll(
        '.archived-report-row'
        )
        .forEach(row => {

            const name =
            row.dataset.name;

            row.style.display =

            name.includes(search)
            ?
            'flex'
            :
            'none';
        });
    }

    document
    .getElementById('archiveModal')
    .addEventListener(
    'shown.bs.modal',
    function () {

        loadArchivedReports();
    }
    );




    window.allPrintLogs = [];
    window.currentPrintLogsPeriod = 'month';

    window.setPrintLogsPeriod = function (period)
    {
        window.currentPrintLogsPeriod = period;

        document.querySelectorAll('.period-btn').forEach(function (btn) {

            if (btn.dataset.period === period) {

                btn.style.background = '#c4b417';
                btn.style.color = '#ffffff';
                btn.style.borderColor = '#c4b417';

            } else {

                btn.style.background = '#ffffff';
                btn.style.color = '#687168';
                btn.style.borderColor = '#dfe5d1';
            }
        });

        loadPrintLogs();
    };

    function renderPrintLogs(logs, totalCount)
    {
        const tbody =

        document.getElementById(
        'printLogsTableBody'
        );

        if (!tbody) return;

        const total = totalCount != null ? totalCount : (logs ? logs.length : 0);

        const countEl =

        document.getElementById(
        'filterCount'
        );

        if (countEl) {

            const showing = logs ? logs.length : 0;

            countEl.textContent = logs

            ? 'Showing ' + showing + ' of ' + total + ' result' + (total !== 1 ? 's' : '')

            : '';
        }

        tbody.innerHTML = !logs || !logs.length

        ? '<tr><td colspan="5" style="padding:30px;text-align:center;color:#9ca3af;font-size:.84rem;">No logs found</td></tr>'

        : logs.slice(0, 20).map(log => `

            <tr class="print-log-row">

                <td>
                    ${log.report || 'N/A'}
                </td>

                <td>
                    ${log.printed_by || 'Unknown'}
                </td>

                <td>
                    ${log.ip || 'N/A'}
                </td>

                <td>
                    ${log.date || 'Unknown'}
                </td>

                <td>
                    ${log.status || 'N/A'}
                </td>

            </tr>
        `).join('');
    }

    window.loadPrintLogs = async function ()
    {
        try {

            const tbody =

            document.getElementById(
            'printLogsTableBody'
            );

            if (!tbody) return;

            tbody.innerHTML = `

        <tr>

            <td colspan="5"
                style="
                    padding:30px;
                    text-align:center;
                    color:#9ca3af;
                    font-size:.84rem;
                ">

                Loading system activity logs...

            </td>

        </tr>
        `;


            const response = await fetch(

            '/admin/api/reports/GetPrintLogsAPI.php?period=' + window.currentPrintLogsPeriod
            );

            const result =
            await response.json();

            console.log(
            'LOG RESPONSE:',
            result
            );


            if (!result.success) {

                throw new Error(
                result.message
                );
            }


            document.getElementById(
            'securityLogsCount'
            ).innerText =

            result.security_count || 0;

            document.getElementById(
            'loginLogsCount'
            ).innerText =

            result.login_count || 0;

            document.getElementById(
            'sessionLogsCount'
            ).innerText =

            result.session_count || 0;

            document.getElementById(
            'auditLogsCount'
            ).innerText =

            result.audit_count || 0;


            window.allPrintLogs = result.logs || [];


            renderPrintLogs(
            window.allPrintLogs,
            window.allPrintLogs.length
            );
        }

        catch (error) {

            console.error(error);

            showAlert('error', 'Failed', error.message);
        }
    }


    window.printLogsTable = async function ()
    {
        try {




            const response = await fetch(

            '/admin/api/reports/GetPrintLogsAPI.php?generate_pdf=1&period=' + window.currentPrintLogsPeriod
            );

            const result =
            await response.json();

            console.log(
            'LOG RESPONSE:',
            result
            );


            if (!result.success) {

                throw new Error(

                result.message
                ||
                'Failed to generate report.'
                );
            }


            if (!result.pdf) {

                throw new Error(
                'PDF file was not generated.'
                );
            }


            const link =
            document.createElement('a');

            link.href =
            result.pdf;

            link.download =
            'System_Activity_Report.pdf';

            document.body.appendChild(link);

            link.click();

            document.body.removeChild(link);


            showAlert('success', 'Download Started', 'System activity report downloaded successfully.');

        } catch (error) {

            console.error(error);

            showAlert('error', 'Download Failed', error.message || 'Failed to generate report.');
        }
    };


    window.filterPrintLogs = function ()
    {
        const search =

        (
        document
        .getElementById('logsSearch')
        ?.value
        ||
        ''
        )
        .toLowerCase();

        if (!search) {

            renderPrintLogs(
            window.allPrintLogs,
            window.allPrintLogs.length
            );
            return;
        }

        const filtered =
        (window.allPrintLogs || [])
        .filter(log =>

        (log.report || '')
        .toLowerCase().includes(search) ||

        (log.printed_by || '')
        .toLowerCase().includes(search) ||

        (log.ip || '')
        .toLowerCase().includes(search) ||

        (log.status || '')
        .toLowerCase().includes(search)
        );

        renderPrintLogs(
        filtered,
        window.allPrintLogs.length
        );
    }


    function printReportsPage()
    {
        window.print();
    }


    const logsModal =

    document.getElementById(
    'logsModal'
    );

    if (logsModal) {

        logsModal.addEventListener(

        'shown.bs.modal',

        function () {

            navigator.sendBeacon
            ? navigator.sendBeacon(
            '/admin/api/audit/log.php',
            new URLSearchParams({
                module: 'Reports',
                action: 'print_logs',
                description: 'Viewed system activity logs'
            })
            )
            : fetch('/admin/api/audit/log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'module=Reports&action=print_logs&description=Viewed+system+activity+logs'
            });

            if (window.setPrintLogsPeriod) {
                window.setPrintLogsPeriod('month');
            } else {
                loadPrintLogs();
            }
        }
        );
    }
    const searchInput = document.getElementById('categorySearch');

    searchInput.addEventListener('keyup', function () {

        const value = this.value.toLowerCase();

        document.querySelectorAll('.category-item').forEach(item => {

            const name = item.dataset.name;

            if (name.includes(value)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }

        });

    });
</script>

<script src="/public/login/custom-alert.js"></script>

<div id="customAlertOverlay" class="custom-alert-overlay">
    <div class="custom-alert-card">
        <div class="custom-alert-header">
            <div class="custom-alert-icon"></div>
            <div class="custom-alert-title"></div>
        </div>
        <div class="custom-alert-body">
            <div class="custom-alert-message"></div>
            <button class="custom-alert-btn"></button>
        </div>
    </div>
</div>
