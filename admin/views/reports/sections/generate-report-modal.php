<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<div class="modal fade"
     id="globalTransportGenerateReportsModal"
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
        border-right:1px solid #e6e1c8;
        min-height:680px;
     ">

                    <div class="p-5 h-100 d-flex flex-column justify-content-between">


                        <div>

                            <div style="
    width:74px;
    height:74px;
    border-radius:22px;
    background:#ffffff;
    border:1px solid #e8e3c9;
    display:flex;
    align-items:center;
    justify-content:center;
">

                                <i class="fas fa-chart-line"
   style="
      color:#c4b417;
      font-size:1.6rem;
   ">
                                </i>

                            </div>

                            <h2 style="
    margin-top:28px;
    font-size:2.1rem;
    font-weight:700;
    line-height:1.3;
    color:#2e4836;
">

Transportation Analytics Platform

                            </h2>

                            <p style="
    margin-top:18px;
    font-size:.95rem;
    line-height:1.9;
    color:#6d766d;
">

Generate AI-powered transportation reports,
passenger analytics, financial summaries,
and operational insights through a clean
professional reporting workspace.

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

                                        <i class="fas fa-robot"
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

AI Smart Reports

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7c847c;
">

Automated transportation insights

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

                                        <i class="fas fa-database"
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

Operational Data

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7c847c;
">

Passenger and route analytics

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
    color:#2e4836;
    font-weight:700;
    margin-bottom:5px;
">

Generate Report

                            </h4>

                            <div style="
    font-size:.84rem;
    color:#7d867c;
">

Create transportation intelligence reports

                            </div>

                        </div>

                        <button type="button"
        class="btn-close"
        data-bs-dismiss="modal">
                        </button>

                    </div>

                    <div class="modal-body px-5 py-4">


                        <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:22px;
    padding:30px;
">


                            <label style="
    display:block;
    margin-bottom:10px;
    font-size:.82rem;
    font-weight:700;
    color:#2e4836;
">

Select Report Type

                            </label>


                            <select class="form-select"
        id="reportType"
        style="
            height:54px;
            border-radius:16px;
            border:1px solid #dce3ce;
            background:#fff;
            font-size:.9rem;
            color:#2e4836;
            box-shadow:none;
        ">

                                <optgroup label="Driver Management">
                                    <option value="drivers">Driver Masterlist</option>
                                    <option value="driver_accounts">Driver Accounts</option>
                                    <option value="driver_bus_assignments">Driver Bus Assignments</option>
                                </optgroup>

                                <optgroup label="Passenger Management">
                                    <option value="passengers">Passenger Directory</option>
                                    <option value="passenger_trips">Passenger Trips</option>
                                    <option value="passenger_trip_history">Passenger Trip History</option>
                                </optgroup>

                                <optgroup label="Transportation Operations">
                                    <option value="trip_operations_reports">Trip Operations</option>
                                    <option value="trip_operation_evidences">Trip Evidences</option>
                                    <option value="ai_trip_operations_reports">AI Operations Report</option>
                                    <option value="route_stops">Route Stops</option>
                                </optgroup>

                                <optgroup label="Financial Reports">
                                    <option value="fares">Fare Matrix</option>
                                    <option value="remittance_logs">Remittance Logs</option>
                                </optgroup>

                                <optgroup label="Inspector Reports">
                                    <option value="inspectors">Inspector Activity</option>
                                </optgroup>

                            </select>


                            <div style="
    margin-top:22px;
    background:#f9f4da;
    border:1px solid #ece2ae;
    border-radius:18px;
    padding:18px;
">

                                <div class="d-flex">

                                    <div style="
    width:42px;
    height:42px;
    border-radius:12px;
    background:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-right:14px;
">

                                        <i class="fas fa-sparkles"
   style="
      color:#c4b417;
   ">
                                        </i>

                                    </div>

                                    <div>

                                        <div style="
    font-size:.86rem;
    font-weight:700;
    color:#2e4836;
">

AI Report Intelligence

                                        </div>

                                        <div style="
    margin-top:4px;
    font-size:.78rem;
    line-height:1.8;
    color:#737b73;
">

The system analyzes operational records,
trip activities, passenger movement,
and transportation performance metrics.

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <button class="btn w-100 mt-4"
        id="generateBtn"
        onclick="generateReport()"
        style="
            height:54px;
            border:none;
            border-radius:16px;
            background:#c4b417;
            color:#ffffff;
            font-size:.92rem;
            font-weight:700;
            letter-spacing:.2px;
        ">

                                <i class="fas fa-chart-bar me-2"></i>

    Generate AI Report

                            </button>

                        </div>


                        <div id="reportLoading"
     class="text-center py-5 d-none">

                            <div class="spinner-border"
     style="
        color:#c4b417;
        width:3rem;
        height:3rem;
     ">
                            </div>

                            <div style="
    margin-top:18px;
    font-size:1rem;
    font-weight:700;
    color:#2e4836;
">

Generating report...

                            </div>

                            <div style="
    margin-top:8px;
    font-size:.82rem;
    color:#7b847a;
">

Analyzing transportation records and generating insights

                            </div>

                        </div>


                        <div id="reportResult"
     class="d-none mt-4">


                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>