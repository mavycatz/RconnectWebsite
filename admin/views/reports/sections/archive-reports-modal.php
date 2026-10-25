
<div class="modal fade"
     id="archiveModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-xl">
    
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

                                <i class="fa-solid fa-box-archive"
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

Archive Management Center

                            </h2>

                            <p style="
    margin-top:18px;
    font-size:.94rem;
    line-height:1.9;
    color:#70796f;
">

Manage archived transportation reports,
generated PDFs, operational records,
and historical analytics through a
clean organized archive workspace.

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

                                        <i class="fa-solid fa-folder-tree"
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

Smart Archive Storage

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7d857c;
">

Organized transportation records

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

                                        <i class="fa-solid fa-clock-rotate-left"
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

Historical Records

                                        </div>

                                        <div style="
    font-size:.76rem;
    color:#7d857c;
">

Maintain archived operational data

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

Archive Reports

                            </h4>

                            <div style="
    font-size:.84rem;
    color:#7b847b;
    margin-top:4px;
">

Transportation archive management system

                            </div>

                        </div>

                        <button type="button"
        class="btn-close"
        data-bs-dismiss="modal">
                        </button>

                    </div>


                    <div class="modal-body px-5 py-4">


                        <div id="archiveAlert"
     class="alert d-none mb-4"
     style="
        border-radius:16px;
        font-size:.84rem;
        border:none;
     ">
                        </div>


                        <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:22px;
    padding:28px;
">

                            <div style="
    font-size:.92rem;
    font-weight:700;
    color:#2e4836;
    margin-bottom:20px;
">

Archive Filters

                            </div>

                            <div class="row g-3">


                                <div class="col-md-6">

                                    <label style="
    display:block;
    margin-bottom:8px;
    font-size:.8rem;
    font-weight:600;
    color:#687168;
">

Select Year

                                    </label>

                                    <select id="archiveYear"
        class="form-select"
        style="
            height:54px;
            border-radius:16px;
            border:1px solid #dce3ce;
            box-shadow:none;
            font-size:.88rem;
            color:#2e4836;
        ">

                                        <option value="2026">2026</option>
                                        <option value="2025">2025</option>
                                        <option value="2024">2024</option>
                                        <option value="2023">2023</option>
                                        <option value="2022">2022</option>

                                    </select>

                                </div>


                                <div class="col-md-6">

                                    <label style="
    display:block;
    margin-bottom:8px;
    font-size:.8rem;
    font-weight:600;
    color:#687168;
">

Select Month

                                    </label>

                                    <select id="archiveMonth"
        class="form-select"
        style="
            height:54px;
            border-radius:16px;
            border:1px solid #dce3ce;
            box-shadow:none;
            font-size:.88rem;
            color:#2e4836;
        ">

                                        <option value="01">January</option>
                                        <option value="02">February</option>
                                        <option value="03">March</option>
                                        <option value="04">April</option>
                                        <option value="05">May</option>
                                        <option value="06">June</option>
                                        <option value="07">July</option>
                                        <option value="08">August</option>
                                        <option value="09">September</option>
                                        <option value="10">October</option>
                                        <option value="11">November</option>
                                        <option value="12">December</option>

                                    </select>

                                </div>

                            </div>


                            <div class="mt-4 d-flex justify-content-end">

                                <button type="button"
        id="archiveReportsBtn"
        onclick="archiveReports()"
        class="btn"
        style="
            height:54px;
            padding:0 24px;
            border:none;
            border-radius:16px;
            background:#c4b417;
            color:#ffffff;
            font-size:.9rem;
            font-weight:700;
        ">

                                    <span id="archiveBtnText">

                                        <i class="fa-solid fa-box-archive me-2"></i>

Archive Selected Reports

                                    </span>

                                    <span id="archiveBtnLoading"
      class="d-none">

                                        <span class="spinner-border spinner-border-sm me-2"></span>

Archiving...

                                    </span>

                                </button>

                            </div>

                        </div>


                        <div style="
    margin-top:24px;
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:22px;
    overflow:hidden;
">


                            <div style="
    padding:20px 24px;
    border-bottom:1px solid #f1eee2;
    background:#fcfbf6;
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

                                        <i class="fa-solid fa-circle-info"
   style="
      color:#c4b417;
   ">
                                        </i>

                                    </div>

                                    <div>

                                        <div style="
    font-size:.92rem;
    font-weight:700;
    color:#2e4836;
">

Archive Suggestions

                                        </div>

                                        <div style="
    font-size:.75rem;
    color:#8a9389;
    margin-top:3px;
">

Helpful archive recommendations

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <div class="p-4">

                                <div class="d-grid gap-3">

                                    <div style="
    background:#fefcf6;
    border:1px solid #efe7c6;
    border-radius:16px;
    padding:16px;
">

                                        <div class="d-flex">

                                            <i class="fa-solid fa-calendar-days me-3"
   style="
      color:#c4b417;
      margin-top:3px;
   ">
                                            </i>

                                            <div>

                                                <div style="
    font-size:.84rem;
    font-weight:700;
    color:#2e4836;
">

Archive older reports

                                                </div>

                                                <div style="
    margin-top:4px;
    font-size:.77rem;
    line-height:1.8;
    color:#737b73;
">

Reports older than 6 to 12 months are recommended for archive storage.

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                    <div style="
    background:#fefcf6;
    border:1px solid #efe7c6;
    border-radius:16px;
    padding:16px;
">

                                        <div class="d-flex">

                                            <i class="fa-solid fa-file-pdf me-3"
   style="
      color:#c4b417;
      margin-top:3px;
   ">
                                            </i>

                                            <div>

                                                <div style="
    font-size:.84rem;
    font-weight:700;
    color:#2e4836;
">

Move unused PDFs

                                                </div>

                                                <div style="
    margin-top:4px;
    font-size:.77rem;
    line-height:1.8;
    color:#737b73;
">

Store inactive generated reports to optimize dashboard performance.

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <div class="mt-4">

                                <button type="button"
        class="btn w-100"
        data-bs-toggle="modal"
        data-bs-target="#archivedReportsViewerModal"
        style="
            height:56px;
            border:none;
            border-radius:18px;
            background:#ffffff;
            border:1px solid #e6e0c7;
            color:#2e4836;
            font-size:.9rem;
            font-weight:700;
        ">

                                    <i class="fa-solid fa-folder-open me-2"
       style="color:#c4b417;">
                                    </i>

    View Archived Reports

                                </button>

                            </div>

                        </div>

                    </div>


                    <div class="modal-footer border-0 px-5 pb-5 pt-0">

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

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>