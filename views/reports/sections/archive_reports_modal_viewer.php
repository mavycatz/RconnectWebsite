
<div class="modal fade"
     id="archivedReportsViewerModal"
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

                                <i class="fa-solid fa-folder-open"
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

Archived Reports Viewer

                            </h2>

                            <p style="
    margin-top:18px;
    font-size:.94rem;
    line-height:1.9;
    color:#70796f;
">

Browse archived transportation reports,
generated PDFs, and historical operational
records in one organized workspace.

                            </p>

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
   ">
                                    </i>

                                </div>

                                <div>

                                    <div style="
    font-size:.9rem;
    font-weight:700;
    color:#2e4836;
">

Historical Access

                                    </div>

                                    <div style="
    font-size:.76rem;
    color:#7d857c;
">

Review and restore archived records

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

Archived Reports

                            </h4>

                            <div style="
    font-size:.84rem;
    color:#7b847b;
    margin-top:4px;
">

Stored transportation archive records

                            </div>

                        </div>

                        <button type="button"
        class="btn-close"
        data-bs-dismiss="modal">
                        </button>

                    </div>

                    <div class="modal-body px-5 py-4">


                        <div class="input-group mb-4">

                            <span class="input-group-text border-end-0"
      style="
        background:#fff;
        border-radius:16px 0 0 16px;
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
       id="archiveSearch"
       class="form-control border-start-0"
       placeholder="Search archived reports..."
       onkeyup="filterArchivedReports()"
       style="
            height:54px;
            border-radius:0 16px 16px 0;
            border-color:#dfe5d1;
            font-size:.84rem;
            box-shadow:none;
       ">

                        </div>


                        <div class="row g-3 mb-4">

                            <div class="col-md-4">

                                <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:18px;
    padding:20px;
    text-align:center;
">

                                    <div id="archiveTotalFiles"
     style="
        font-size:1.4rem;
        font-weight:700;
        color:#2e4836;
     ">

0

                                    </div>

                                    <div style="
    margin-top:4px;
    font-size:.75rem;
    color:#8a9389;
">

Archived PDFs

                                    </div>

                                </div>

                            </div>

                            <div class="col-md-4">

                                <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:18px;
    padding:20px;
    text-align:center;
">

                                    <div id="archiveTotalSize"
     style="
        font-size:1.4rem;
        font-weight:700;
        color:#2e4836;
     ">

0 KB

                                    </div>

                                    <div style="
    margin-top:4px;
    font-size:.75rem;
    color:#8a9389;
">

Storage Used

                                    </div>

                                </div>

                            </div>

                            <div class="col-md-4">

                                <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:18px;
    padding:20px;
    text-align:center;
">

                                    <div id="archiveTotalTables"
     style="
        font-size:1.4rem;
        font-weight:700;
        color:#2e4836;
     ">

4

                                    </div>

                                    <div style="
    margin-top:4px;
    font-size:.75rem;
    color:#8a9389;
">

Archived Tables

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div id="archiveResultsList">

                            <div style="
    background:#ffffff;
    border:1px solid #ebe7d4;
    border-radius:18px;
    padding:40px;
    text-align:center;
    color:#98a097;
    font-size:.84rem;
">

No archived reports found.

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>