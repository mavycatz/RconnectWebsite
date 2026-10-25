
<div class="modal fade"
     id="logsModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content border-0 overflow-hidden"
     style="
        border-radius:24px;
        background:#fef8f3;
        box-shadow:0 18px 50px rgba(0,0,0,.08);
     ">

            <div class="row g-0">


                <div class="col-lg-5"
     style="
        background:#f9f4da;
        border-right:1px solid #e7e1c9;
        min-height:680px;
     ">

                    <div class="p-5 h-100 d-flex flex-column justify-content-between">


                        <div>

                            <div style="
    width:72px;
    height:72px;
    border-radius:22px;
    background:#ffffff;
    border:1px solid #ebe5ca;
    display:flex;
    align-items:center;
    justify-content:center;
">

                                <i class="fa-solid fa-shield-halved"
   style="
      color:#c4b417;
      font-size:1.5rem;
   ">
                                </i>

                            </div>

                            <h2 style="
    margin-top:28px;
    font-size:2rem;
    font-weight:700;
    line-height:1.3;
    color:#2e4836;
">

System Activity Monitoring

                            </h2>

                            <p style="
    margin-top:18px;
    font-size:.94rem;
    line-height:1.9;
    color:#70796f;
">

Monitor transportation system activity,
authentication logs, security events,
sessions, and operational monitoring
through one centralized workspace.

                            </p>

                        </div>


                        <div class="d-grid gap-3">

                            <div style="
    background:#ffffff;
    border:1px solid #ebe5ca;
    border-radius:18px;
    padding:18px;
">

                                <div class="d-flex align-items-center">

                                    <div style="
    width:46px;
    height:46px;
    border-radius:14px;
    background:#f9f4da;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-right:14px;
">

                                        <i class="fa-solid fa-user-shield"
   style="
      color:#c4b417;
      font-size:1rem;
   ">
                                        </i>

                                    </div>

                                    <div>

                                        <div style="
    font-size:.9rem;
    font-weight:700;
    color:#2e4836;
">

Security Monitoring

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7d857c;
">

Realtime authentication activity

                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div style="
    background:#ffffff;
    border:1px solid #ebe5ca;
    border-radius:18px;
    padding:18px;
">

                                <div class="d-flex align-items-center">

                                    <div style="
    width:46px;
    height:46px;
    border-radius:14px;
    background:#f9f4da;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-right:14px;
">

                                        <i class="fa-solid fa-chart-line"
   style="
      color:#c4b417;
      font-size:1rem;
   ">
                                        </i>

                                    </div>

                                    <div>

                                        <div style="
    font-size:.9rem;
    font-weight:700;
    color:#2e4836;
">

Activity Insights

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7d857c;
">

Track system usage and sessions

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-lg-7">


                    <div class="modal-header border-0 px-5 pt-5 pb-0">

                        <div>

                            <h4 style="
    margin:0;
    color:#2e4836;
    font-weight:700;
">

System Activity Logs

                            </h4>

                            <div style="
    font-size:.84rem;
    color:#7b847b;
    margin-top:4px;
">

Security monitoring and transportation system logs

                            </div>

                        </div>

                        <button type="button"
        class="btn-close"
        data-bs-dismiss="modal">
                        </button>

                    </div>


                    <div class="modal-body px-5 py-4">


                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

                            <div style="
    font-size:.84rem;
    color:#737b73;
">

Realtime transportation management system monitoring logs.

                            </div>

                            <div class="d-flex align-items-center gap-2 flex-wrap">


                                <div style="
    width:260px;
">

                                    <div class="input-group">

                                        <span class="input-group-text border-end-0"
      style="
        background:#fff;
        border-radius:14px 0 0 14px;
        border-color:#dfe5d1;
      ">

                                            <i class="fa-solid fa-search"
   style="
      color:#9aa39a;
      font-size:.8rem;
   ">
                                            </i>

                                        </span>

                                        <input type="text"
       id="logsSearch"
       class="form-control border-start-0"
       placeholder="Search activity..."
       onkeyup="window.filterPrintLogs()"
       style="
            height:48px;
            border-radius:0 14px 14px 0;
            border-color:#dfe5d1;
            font-size:.82rem;
            box-shadow:none;
       ">

                                    </div>

                                </div>


                                <button onclick="window.loadPrintLogs()"
        class="btn"
        style="
            height:48px;
            padding:0 18px;
            border-radius:14px;
            background:#ffffff;
            border:1px solid #dfe5d1;
            color:#687168;
            font-size:.82rem;
            font-weight:700;
        ">

                                    <i class="fa-solid fa-rotate-right me-2"></i>

    Refresh

                                </button>

                            </div>

                        </div>


                        <div class="d-flex gap-2 mb-4 flex-wrap">

                            <button class="period-btn"
        data-period="today"
        onclick="setPrintLogsPeriod('today')"
        style="
            height:40px;
            padding:0 18px;
            border-radius:12px;
            border:1px solid #dfe5d1;
            background:#ffffff;
            color:#687168;
            font-size:.82rem;
            font-weight:700;
            cursor:pointer;
            transition:all .2s ease;
        ">

    Today

                            </button>

                            <button class="period-btn"
        data-period="week"
        onclick="setPrintLogsPeriod('week')"
        style="
            height:40px;
            padding:0 18px;
            border-radius:12px;
            border:1px solid #dfe5d1;
            background:#ffffff;
            color:#687168;
            font-size:.82rem;
            font-weight:700;
            cursor:pointer;
            transition:all .2s ease;
        ">

    This Week

                            </button>

                            <button class="period-btn active"
        data-period="month"
        onclick="setPrintLogsPeriod('month')"
        style="
            height:40px;
            padding:0 18px;
            border-radius:12px;
            border:1px solid #c4b417;
            background:#c4b417;
            color:#ffffff;
            font-size:.82rem;
            font-weight:700;
            cursor:pointer;
            transition:all .2s ease;
        ">

    This Month

                            </button>

                            <button class="period-btn"
        data-period="6months"
        onclick="setPrintLogsPeriod('6months')"
        style="
            height:40px;
            padding:0 18px;
            border-radius:12px;
            border:1px solid #dfe5d1;
            background:#ffffff;
            color:#687168;
            font-size:.82rem;
            font-weight:700;
            cursor:pointer;
            transition:all .2s ease;
        ">

    Last 6 Months

                            </button>

                        </div>


                        <div class="row g-3 mb-4">


                            <div class="col-lg-3">

                                <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:20px;
    padding:22px;
">

                                    <div style="
    font-size:.76rem;
    color:#8a9389;
    margin-bottom:8px;
">

Security Events

                                    </div>

                                    <div id="securityLogsCount"
     style="
        font-size:1.7rem;
        font-weight:700;
        color:#2e4836;
     ">

0

                                    </div>

                                </div>

                            </div>


                            <div class="col-lg-3">

                                <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:20px;
    padding:22px;
">

                                    <div style="
    font-size:.76rem;
    color:#8a9389;
    margin-bottom:8px;
">

Login Attempts

                                    </div>

                                    <div id="loginLogsCount"
     style="
        font-size:1.7rem;
        font-weight:700;
        color:#2e4836;
     ">

0

                                    </div>

                                </div>

                            </div>


                            <div class="col-lg-3">

                                <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:20px;
    padding:22px;
">

                                    <div style="
    font-size:.76rem;
    color:#8a9389;
    margin-bottom:8px;
">

Active Sessions

                                    </div>

                                    <div id="sessionLogsCount"
     style="
        font-size:1.7rem;
        font-weight:700;
        color:#2e4836;
     ">

0

                                    </div>

                                </div>

                            </div>


                            <div class="col-lg-3">

                                <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:20px;
    padding:22px;
">

                                    <div style="
    font-size:.76rem;
    color:#8a9389;
    margin-bottom:8px;
">

Audit Trail

                                    </div>

                                    <div id="auditLogsCount"
     style="
        font-size:1.7rem;
        font-weight:700;
        color:#2e4836;
     ">

0

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:22px;
    overflow:hidden;
">


                            <div style="
    padding:18px 22px;
    border-bottom:1px solid #f1eee2;
    background:#fcfbf6;
">

                                <div style="
    font-size:.9rem;
    font-weight:700;
    color:#2e4836;
">

System Logs

                                </div>

                                <div style="
    font-size:.74rem;
    color:#8a9389;
    margin-top:3px;
">

Transportation authentication and monitoring activity

                                </div>

                            </div>


                            <div id="filterCount" style="
    font-size:.75rem;
    color:#8a9389;
    padding:8px 22px;
    border-bottom:1px solid #f1eee2;
    background:#fcfbf6;
">
                            </div>


                            <div class="table-responsive">

                                <table class="table align-middle mb-0">

                                    <thead style="
    background:#fffdfa;
">

                                        <tr>

                                            <th style="
    padding:16px 18px;
    font-size:.8rem;
    font-weight:700;
    color:#2e4836;
    border-bottom:1px solid #f1eee2;
">

Activity

                                            </th>

                                            <th style="
    padding:16px 18px;
    font-size:.8rem;
    font-weight:700;
    color:#2e4836;
    border-bottom:1px solid #f1eee2;
">

User

                                            </th>

                                            <th style="
    padding:16px 18px;
    font-size:.8rem;
    font-weight:700;
    color:#2e4836;
    border-bottom:1px solid #f1eee2;
">

IP Address

                                            </th>

                                            <th style="
    padding:16px 18px;
    font-size:.8rem;
    font-weight:700;
    color:#2e4836;
    border-bottom:1px solid #f1eee2;
">

Date

                                            </th>

                                            <th style="
    padding:16px 18px;
    font-size:.8rem;
    font-weight:700;
    color:#2e4836;
    border-bottom:1px solid #f1eee2;
    text-align:center;
">

Status

                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody id="printLogsTableBody">

                                        <tr>

                                            <td colspan="5"
    style="
        padding:40px;
        text-align:center;
        color:#98a097;
        font-size:.84rem;
    ">

Loading system activity logs...

                                            </td>

                                        </tr>

                                    </tbody>

                                </table>

                            </div>

                        </div>


                        <div class="mt-4 d-flex justify-content-end gap-3 flex-wrap">

                            <button type="button"
        class="btn"
        data-bs-dismiss="modal"
        style="
            height:50px;
            padding:0 24px;
            border-radius:14px;
            background:#ffffff;
            border:1px solid #dfe5d1;
            color:#687168;
            font-weight:600;
        ">

    Close

                            </button>

                            <button onclick="window.loadPrintLogs()"
        class="btn"
        style="
            height:50px;
            padding:0 24px;
            border-radius:14px;
            background:#ffffff;
            border:1px solid #dfe5d1;
            color:#687168;
            font-weight:700;
        ">

                                <i class="fa-solid fa-rotate-right me-2"></i>

    Refresh Logs

                            </button>

                            <button onclick="printLogsTable()"
        class="btn"
        style="
            height:50px;
            padding:0 26px;
            border:none;
            border-radius:14px;
            background:#c4b417;
            color:#ffffff;
            font-weight:700;
        ">

                                <i class="fa-solid fa-file-pdf me-2"></i>

    Generate Summary Report

                            </button>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

