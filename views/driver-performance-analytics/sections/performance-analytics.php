<?php
declare(strict_types = 1);
?>
 <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">


 <style>
     .analytics-section,
     .analytics-section * {
         font-family: 'Poppins', sans-serif;
     }
     .analytics-section {
         max-width: 1440px;
         margin: 0 auto;
         padding: 2px;
         color: #2e4836;
     }

     .sec-head {
         display: flex;
         align-items: center;
         justify-content: space-between;
         gap: 14px;
         margin: 0 0 14px;
     }
     .sec-head h2 {
         font-size: .8rem;
         font-weight: 700;
         text-transform: uppercase;
         letter-spacing: .5px;
         color: #295738;
         margin: 0;
     }
     .sec-sub { font-size: .78rem; color: #8a9a8a; }

     .dpa-header {
         display: flex;
         justify-content: space-between;
         align-items: flex-end;
         gap: 12px;
         flex-wrap: wrap;
         margin-bottom: 14px;
     }
     .dpa-title h1 {
         font-size: 1.4rem;
         font-weight: 800;
         color: #295738;
         margin: 0 0 2px;
         line-height: 1.2;
     }
      .dpa-title p { margin: 0; color: #8a9a8a; font-size: .82rem; font-weight: 500; }
      .dpa-filter-summary {
          display: inline-flex;
          align-items: center;
          flex-wrap: wrap;
          gap: 6px;
          margin-top: 7px;
          background: #fff;
          border: 1px solid #e5e0a4;
          border-radius: 20px;
          padding: 4px 12px;
          font-size: .72rem;
          font-weight: 600;
          color: #475569;
      }
      .dpa-filter-summary i { color: #295738; }
      .dpa-filter-summary .dfs-sep { color: #cdd9b5; }
     .dpa-period {
         display: flex;
         align-items: center;
         gap: 8px;
         flex-wrap: wrap;
         justify-content: flex-end;
     }

     .dash-filters-label {
         font-size: .72rem;
         font-weight: 700;
         letter-spacing: .06em;
         text-transform: uppercase;
         color: #2e4836;
     }
     .dash-seg {
         display: inline-flex;
         background: #f9f4da;
         border: 1px solid #cdd9b5;
         border-radius: 999px;
         padding: 3px;
         gap: 2px;
     }
     .dash-seg-btn {
         border: none;
         background: transparent;
         border-radius: 999px;
         padding: 5px 14px;
         font-size: .74rem;
         font-weight: 600;
         color: #295738;
         cursor: pointer;
         transition: background .15s ease, color .15s ease;
         line-height: 1.2;
         height: auto;
     }
     .dash-seg-btn:hover { background: #efe9c9; }
     .dash-seg-btn.active { background: #295738; color: #fef8f3; }

     .date-controls { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
     .date-controls .dc-label { font-weight: 600; color: #295738; font-size: .78rem; white-space: nowrap; }
     .date-controls input[type="date"], .cf-date-input {
         padding: 6px 10px;
         background: #fff;
         border: 1px solid #cdd9b5;
         border-radius: 10px;
         outline: none;
         color: #2e4836;
         font-size: .78rem;
     }
     .btn-go {
         background: #295738;
         border: none;
         border-radius: 10px;
         color: #fff;
         font-weight: 600;
         font-size: .8rem;
         padding: 7px 16px;
         height: auto;
     }
     .btn-go:hover { background: #2e4836; }

     .btn-view {
         background: #fff;
         border: 1px solid #cdd9b5;
         color: #295738;
         border-radius: 10px;
         font-weight: 600;
         font-size: .78rem;
         padding: 7px 14px;
         height: auto;
         transition: all .15s ease;
     }
     .btn-view:hover { background: #f9f4da; border-color: #c4b417; }
     .btn-view:disabled { opacity: .5; cursor: not-allowed; background: #fff; border-color: #e5e7eb; color: #9ca3af; }

     .btn-generate {
         background: #295738;
         border: none;
         border-radius: 12px;
         color: #fff;
         font-weight: 600;
         font-size: .88rem;
         padding: 10px 20px;
         box-shadow: 0 2px 6px rgba(41, 87, 56, .2);
         white-space: nowrap;
         height: auto;
         align-self: center;
     }
     .btn-generate:hover { background: #2e4836; }
     .btn-generate:disabled { background: #cfd8cf; box-shadow: none; cursor: not-allowed; }

     .stat-card-group {
         display: grid;
         grid-template-columns: repeat(4, minmax(0, 1fr));
         gap: 16px;
         width: 100%;
         max-width: none;
         margin-bottom: 24px;
     }
     .stat-item {
         width: 100%;
         min-width: 0;
         min-height: 124px;
         background: #fff;
         border: 1.5px solid #cdd9b5;
         border-radius: 18px;
         padding: 18px 20px;
         display: flex;
         align-items: center;
         gap: 16px;
         transition: .2s;
         cursor: default;
         box-shadow: 0 1px 4px rgba(46, 72, 54, .05);
     }
     .stat-item:hover {
         transform: translateY(-2px);
         box-shadow: 0 6px 16px rgba(46, 72, 54, .09);
     }
     .stat-icon-box {
         width: 52px;
         height: 52px;
         min-width: 52px;
         border-radius: 14px;
         display: flex;
         align-items: center;
         justify-content: center;
         flex-shrink: 0;
         font-size: 24px;
     }
     .stat-info {
         flex: 1;
         min-width: 0;
         display: flex;
         flex-direction: column;
         justify-content: center;
     }
     .stat-label {
         font-size: 11px;
         font-weight: 600;
         color: #6f7d6f;
         text-transform: uppercase;
         letter-spacing: .5px;
         margin-bottom: 5px;
         line-height: 1.2;
     }
     .stat-value {
         font-size: clamp(15px, 1.6vw, 23px);
         font-weight: 700;
         color: #2e4836;
         line-height: 1.2;
         font-variant-numeric: tabular-nums;
         white-space: normal;
         overflow-wrap: anywhere;
     }
     .stat-desc {
         font-size: 11px;
         color: #8a9389;
         margin-top: 4px;
         line-height: 1.2;
     }
     .pager {
         display: flex;
         align-items: center;
         gap: 10px;
     }
     .pg-count {
         font-size: .84rem;
         font-weight: 600;
         color: #295738;
         white-space: nowrap;
         font-variant-numeric: tabular-nums;
     }
     @media(max-width:1100px) {
         .stat-card-group { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
         .stat-item { min-height: 110px; padding: 16px; }
     }
     @media(max-width:575px) {
         .stat-card-group { grid-template-columns: 1fr; gap: 12px; }
         .stat-item { min-height: 100px; padding: 14px 16px; border-radius: 14px; gap: 12px; }
         .stat-icon-box { width: 44px; height: 44px; min-width: 44px; border-radius: 12px; font-size: 19px; }
         .dash-seg-btn { padding: 5px 10px; }
     }

     .overview-block { margin-bottom: 30px; }
     .overview-grid {
         display: grid;
         grid-template-columns: minmax(0, 1fr) 330px;
         gap: 18px;
         align-items: stretch;
     }
     .chart-card {
         background: #fffdf8;
         border: 1px solid #e5e0a4;
         border-radius: 16px;
         padding: 18px 18px 12px;
         min-width: 0;
     }
     .chart-box { position: relative; height: 300px; }
     .chart-empty {
         position: absolute;
         inset: 0;
         display: flex;
         flex-direction: column;
         align-items: center;
         justify-content: center;
         gap: 4px;
         text-align: center;
         padding: 20px;
         background: linear-gradient(180deg, #fffdf8, #f7f8ee);
         border: 1px dashed #e5e0a4;
         border-radius: 12px;
     }
     .chart-empty-icon {
         width: 54px;
         height: 54px;
         border-radius: 50%;
         display: flex;
         align-items: center;
         justify-content: center;
         background: #eef4e6;
         border: 1px dashed #c6d48c;
         color: #5d8550;
         font-size: 22px;
         margin-bottom: 6px;
     }
     .chart-empty-title {
         font-family: 'Poppins', sans-serif;
         font-weight: 700;
         color: #2e4836;
         font-size: .95rem;
     }
     .chart-empty-sub {
         color: #8a9a8a;
         font-size: .78rem;
         line-height: 1.5;
         max-width: 320px;
     }

     .insights-card {
         background: #fffdf8;
         border: 1px solid #e5e0a4;
         border-radius: 16px;
         padding: 18px 20px;
         min-width: 0;
     }
     .insights-card h3 {
         font-size: .72rem;
         font-weight: 700;
         text-transform: uppercase;
         letter-spacing: .5px;
         color: #8a9a8a;
         margin: 0 0 8px;
     }
     .insight-row {
         display: flex;
         gap: 11px;
         padding: 12px 0;
         border-bottom: 1px solid #f0ede0;
     }
     .insight-row:last-child { border-bottom: none; }
     .insight-row .ins-icon {
         width: 36px;
         height: 36px;
         flex-shrink: 0;
         border-radius: 10px;
         background: #eef3e6;
         color: #295738;
         display: flex;
         align-items: center;
         justify-content: center;
         font-size: .95rem;
     }
     .insight-row .ins-label {
         display: block;
         font-size: .66rem;
         font-weight: 600;
         text-transform: uppercase;
         letter-spacing: .05em;
         color: #9aa89a;
         margin-bottom: 1px;
     }
     .insight-row .ins-value {
         display: block;
         font-size: .9rem;
         font-weight: 600;
         color: #2e4836;
         line-height: 1.35;
         overflow-wrap: break-word;
     }
     .insight-row .ins-sub { display: block; font-size: .74rem; color: #9aa89a; margin-top: 2px; }
     .insight-row .ins-sub .flag-danger { color: #b94a48; font-weight: 600; }
     @media(max-width:1199.98px) {
         .overview-grid { grid-template-columns: 1fr; }
         .chart-box { height: 260px; }
     }

     .table-block { margin-bottom: 30px; }
     .table-scroll {
         overflow-x: auto;
         -webkit-overflow-scrolling: touch;
         background: #fffdf8;
         border: 1px solid #e5e0a4;
         border-radius: 16px;
     }
     .dpa-table { width: 100%; border-collapse: collapse; min-width: 860px; margin: 0; }
     .dpa-table th {
         text-align: left;
         padding: 12px 16px;
         font-size: .7rem;
         font-weight: 600;
         text-transform: uppercase;
         letter-spacing: .4px;
         color: #2e4836;
         background: #f6f8ec;
         border-bottom: 1px solid #e5e0a4;
         white-space: nowrap;
     }
     .dpa-table td {
         padding: 13px 16px;
         font-size: .88rem;
         color: #2e4836;
         border-bottom: 1px solid #f0ede0;
         vertical-align: middle;
         font-variant-numeric: tabular-nums;
     }
     .dpa-table tbody tr:last-child td { border-bottom: none; }
     .dpa-table tbody tr:hover td { background: #f9fbee; }
     .dpa-table th.num, .dpa-table td.num { text-align: right; white-space: nowrap; }
     .dpa-table td { overflow-wrap: anywhere; }
     .dpa-table .col-driver { min-width: 240px; }
     .dpa-table .col-action { text-align: right; width: 1%; white-space: nowrap; }
     .driver-cell { display: flex; align-items: center; gap: 12px; }
     .driver-cell .thumb {
         width: 40px;
         height: 40px;
         border-radius: 50%;
         object-fit: cover;
         border: 2px solid #e5e0a4;
         background: #eef3e6;
         flex-shrink: 0;
     }
     .driver-cell .dc-name { font-weight: 600; line-height: 1.25; color: #2e4836; max-width: 210px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
     .driver-cell .dc-id { font-size: .74rem; color: #9aa89a; max-width: 210px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
     .att-pct { display: block; font-weight: 700; color: #295738; font-size: .92rem; line-height: 1.2; font-variant-numeric: tabular-nums; }
     .att-sub { display: block; font-size: .72rem; color: #9aa89a; }
     .net-val { font-weight: 700; color: #295738; white-space: nowrap; }
     .net-val.neg { color: #b94a48; }
     .status-badge {
         display: inline-block;
         padding: 3px 11px;
         border-radius: 999px;
         font-size: .74rem;
         font-weight: 600;
         white-space: nowrap;
         border: 1px solid;
     }
     .btn-ai {
         display: inline-flex;
         align-items: center;
         gap: 6px;
         border: 1px solid #e5e0a4;
         background: #fffdf8;
         color: #295738;
         font-weight: 600;
         font-size: .78rem;
         padding: 6px 12px;
         border-radius: 9px;
         transition: all .15s ease;
     }
     .btn-ai:hover { background: #295738; color: #fff; border-color: #295738; }
     .table-foot {
         display: flex;
         align-items: center;
         justify-content: space-between;
         gap: 12px;
         padding: 12px 2px 0;
     }
     .table-foot .pg-info { font-size: .86rem; color: #8a9a8a; font-weight: 500; }

     .fin-block { margin-bottom: 30px; }
     .fin-grid {
         display: grid;
         grid-template-columns: repeat(4, minmax(0, 1fr));
         gap: 14px;
     }
     .fin-cell {
         background: #fffdf8;
         border: 1px solid #e5e0a4;
         border-radius: 16px;
         padding: 16px 18px;
         display: flex;
         flex-direction: column;
         gap: 3px;
         min-width: 0;
     }
     .fin-cell .f-label { font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; color: #8a9a8a; }
     .fin-cell .f-value { font-size: 1.25rem; font-weight: 700; color: #2e4836; font-variant-numeric: tabular-nums; letter-spacing: -.4px; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
     .fin-cell .f-sub { font-size: .72rem; color: #9aa89a; }
     .fin-cell.net {
         background: #eef6ee;
         border: 1px solid #cfe3cf !important;
     }
     .fin-cell.net .f-value { color: #295738; font-size: 1.6rem; }
     @media(max-width:860px) {
         .fin-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
     }
     @media(max-width:520px) {
         .fin-grid { grid-template-columns: 1fr; }
     }

     .closing-block { margin-top: 6px; }
     .closing-bar {
         display: flex;
         align-items: center;
         flex-wrap: wrap;
         gap: 16px;
         background: #fffdf8;
         border: 1px solid #e5e0a4;
         border-radius: 16px;
         padding: 14px 20px;
     }
     .cb-item { display: flex; align-items: center; gap: 12px; min-width: 0; }
     .cb-eyebrow { font-size: .64rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #9aa89a; margin-bottom: 3px; }
     .cb-badge {
         display: inline-flex;
         align-items: center;
         gap: 6px;
         font-weight: 600;
         font-size: .82rem;
         padding: 4px 12px;
         border-radius: 999px;
         border: 1px solid #e5e0a4;
         background: #f6f8ec;
         color: #295738;
     }
     .cb-sep { width: 1px; height: 32px; background: #f0ede0; flex-shrink: 0; }
     .cb-msg .cb-msg-text { font-size: .86rem; font-weight: 600; color: #6b786d; }
     .cb-spacer { flex: 1 1 auto; min-width: 12px; }
     @media(max-width:980px) {
         .cb-spacer { flex-basis: 100%; order: 5; }
         .closing-bar .btn-generate { margin-left: 0; width: 100%; }
     }

     .finalized-panel {
         margin-top: 16px;
         background: #fffdf8;
         border: 1px solid #e5e0a4;
         border-radius: 16px;
         padding: 20px 22px;
     }
     .finalized-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 16px; }
     .finalized-head h3 { font-size: .95rem; font-weight: 700; color: #295738; margin: 0 0 3px; }
     .finalized-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 16px; }
     .kpi { background: #f6f8ec; border-radius: 12px; padding: 12px 15px; min-width: 0; }
     .kpi .k-label { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #8a9a8a; }
     .kpi .k-value { display: block; font-size: 1.1rem; font-weight: 700; color: #295738; margin-top: 3px; font-variant-numeric: tabular-nums; }
     .kpi .k-sub { font-size: .72rem; color: #9aa89a; margin-top: 2px; }
     .finalized-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
     .finalized-table { width: 100%; border-collapse: collapse; min-width: 920px; }
     .finalized-table th {
         text-align: left;
         padding: 11px 13px;
         font-size: .7rem;
         font-weight: 600;
         text-transform: uppercase;
         letter-spacing: .4px;
         color: #2e4836;
         background: #f6f8ec;
         border-bottom: 1px solid #e5e0a4;
         white-space: nowrap;
     }
     .finalized-table td { padding: 11px 13px; font-size: .88rem; color: #2e4836; border-bottom: 1px solid #f0ede0; font-variant-numeric: tabular-nums; white-space: nowrap; }
     .finalized-table th.num, .finalized-table td.num { text-align: right; }
     .finalized-table tbody tr:hover td { background: #f9fbee; }

     .ai-report-body { max-height: 72vh; overflow-y: auto; }
     .ai-sec { margin-bottom: 18px; }
     .ai-sec:last-child { margin-bottom: 0; }
     .ai-sec-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #295738; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #ece6cf; }
     .ai-overview-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
     @media(max-width:575.98px) { .ai-overview-grid { grid-template-columns: repeat(2, 1fr); } }
     .ai-ov { background: #f6f8ec; border: 1px solid #e5e0a4; border-radius: 12px; padding: 10px 12px; }
     .ai-ov .ov-label { font-size: .68rem; text-transform: uppercase; letter-spacing: .4px; color: #8a9a8a; }
     .ai-ov .ov-value { font-size: 1.12rem; font-weight: 700; color: #295738; line-height: 1.2; margin-top: 2px; }
     .ai-ov .ov-sub { font-size: .72rem; color: #9aa89a; }
     .ai-fin-row { display: flex; justify-content: space-between; align-items: baseline; padding: 5px 0; border-bottom: 1px dashed #ece6cf; font-size: .9rem; }
     .ai-fin-row .fin-label { color: #3a4a3c; }
     .ai-fin-row .fin-value { font-weight: 600; color: #2e4836; font-variant-numeric: tabular-nums; }
     .ai-fin-total { font-weight: 700; border-bottom: none; border-top: 2px solid #d8d2b0; margin-top: 2px; }
     .ai-fin-total .fin-label, .ai-fin-total .fin-value { color: #295738; font-size: 1rem; }
     .ai-fin-net { background: #eef6ee; border: 1px solid #cfe3cf; border-radius: 10px; padding: 8px 10px; margin-top: 4px; }
     .ai-fin-net .fin-label, .ai-fin-net .fin-value { color: #295738; font-weight: 700; font-size: 1.05rem; }
     .ai-cap-pct { font-size: 1.05rem; font-weight: 700; color: #295738; }
     .ai-cap-bar { height: 10px; background: #ece6cf; border-radius: 999px; overflow: hidden; }
     .ai-cap-fill { height: 100%; background: linear-gradient(90deg, #a5d6a7, #295738); border-radius: 999px; }
     .ai-analysis-block { margin-bottom: 12px; }
     .ai-analysis-block:last-child { margin-bottom: 0; }
     .ai-analysis-block .ab-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #7a6f4f; margin-bottom: 3px; }
     .ai-analysis-block .ab-body { font-size: .9rem; color: #3a4a3c; line-height: 1.6; }
     .ai-analysis-block.summary { background: #f6f8ec; border-left: 3px solid #c4b417; border-radius: 8px; padding: 8px 12px; }

     .ai-fin-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
     .ai-fin-table td { padding: 6px 2px; border-bottom: 1px dashed #ece6cf; }
     .ai-fin-table td.num { text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }
     .ai-fin-table tr.total td { border-bottom: none; border-top: 2px solid #d8d2b0; font-weight: 700; color: #295738; }
     .ai-fin-table tr.net td { background: #eef6ee; font-weight: 700; color: #295738; }
     .ai-chart-box { position: relative; height: 240px; }
     .ai-detail-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #ece6cf; border-radius: 12px; }
     .ai-detail-table { width: 100%; border-collapse: collapse; font-size: .8rem; min-width: 420px; }
     .ai-detail-table th { text-align: left; padding: 8px 10px; background: #f6f8ec; color: #2e4836; font-size: .68rem; text-transform: uppercase; letter-spacing: .4px; border-bottom: 1px solid #e5e0a4; white-space: nowrap; }
     .ai-detail-table td { padding: 7px 10px; border-bottom: 1px solid #f0ede0; color: #3a4a3c; font-variant-numeric: tabular-nums; }
     .ai-detail-table th.num, .ai-detail-table td.num { text-align: right; }

     .dpa-alert { border-radius: 12px; font-size: .88rem; }

     /* Screen-only controls are removed from any browser print: navigation,
        filters, pagination and action buttons never appear in the report. */
     @media print {
         .sidebar, .sidebar-overlay, .header, .navbar, #sidebar, .ann-fab-wrap,
         .dpa-period, .dpa-filter-summary, .table-foot, .btn, .btn-ai, .closing-bar,
         .modal-footer, .modal-backdrop, .app-footer, .dpa-alert { display: none !important; }
         .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
         body { background: #fff !important; }
         .analytics-section { max-width: 100% !important; padding: 0 !important; }
         .table-scroll, .finalized-table-scroll, .ai-report-body { overflow: visible !important; max-height: none !important; }
         .dpa-table, .finalized-table { min-width: 0 !important; width: 100% !important; font-size: 11px !important; }
         .dpa-table th, .dpa-table td { padding: 6px 8px !important; }
         thead { display: table-header-group; }
         tr, .stat-item, .fin-cell, .kpi, .chart-card, .insights-card, .finalized-panel, .ai-sec { break-inside: avoid; }
         .stat-card-group { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; }
     }
 </style>

 <section class="analytics-section" id="performanceAnalyticsSection">

     <header class="dpa-header">
         <div class="dpa-title">
             <h1>Driver Performance Analytics</h1>
             <p>Fleet-wide performance overview</p>
             <div class="dpa-filter-summary" id="dpaFilterSummary"></div>
         </div>
         <div class="dpa-period">
             <span class="dash-filters-label">Period</span>
             <div class="dash-seg" id="analyticsPeriodBar">
                 <button type="button" class="dash-seg-btn" data-period="annually" onclick="setAnalyticsPeriod('annually')">Annually</button>
                 <button type="button" class="dash-seg-btn" data-period="semi_annually" onclick="setAnalyticsPeriod('semi_annually')">Semi-Annually</button>
                 <button type="button" class="dash-seg-btn" data-period="quarterly" onclick="setAnalyticsPeriod('quarterly')">Quarterly</button>
                 <button type="button" class="dash-seg-btn active" data-period="monthly" onclick="setAnalyticsPeriod('monthly')">Monthly</button>
                 <button type="button" class="dash-seg-btn" data-period="custom" onclick="setAnalyticsPeriod('custom')">Custom</button>
             </div>
             <div class="date-controls" id="dateControlsWrap" style="display:none;">
                 <span class="dc-label">From</span>
                 <input type="date" id="analyticsStart" aria-label="From date">
                 <span class="dc-label">to</span>
                 <input type="date" id="analyticsEnd" aria-label="To date">
                 <button class="btn btn-go" type="button" id="analyticsApplyBtn" onclick="applyAnalyticsRange()">Apply</button>
             </div>
         </div>
     </header>

     <div id="analyticsLiveWrap">

         <div class="stat-card-group" id="analyticsKpis">
             <div class="stat-item">
                 <div class="stat-icon-box" style="background:#f9f4da;color:#295738;">
                     <i class="bi bi-signpost-2"></i>
                 </div>
                 <div class="stat-info">
                     <div class="stat-label">Trips</div>
                     <div class="stat-value" id="kpiTrips">—</div>
                     <div class="stat-desc" id="kpiTripsSub"></div>
                 </div>
             </div>
             <div class="stat-item">
                 <div class="stat-icon-box" style="background:#cdd9b5;color:#2e4836;">
                     <i class="bi bi-people"></i>
                 </div>
                 <div class="stat-info">
                     <div class="stat-label">Passengers</div>
                     <div class="stat-value" id="kpiPassengers">—</div>
                     <div class="stat-desc" id="kpiPassengersSub"></div>
                 </div>
             </div>
             <div class="stat-item">
                 <div class="stat-icon-box" style="background:#e8f4ec;color:#295738;">
                     <i class="bi bi-cash-coin"></i>
                 </div>
                 <div class="stat-info">
                     <div class="stat-label">Gross Income</div>
                     <div class="stat-value" id="kpiFare">—</div>
                     <div class="stat-desc" id="kpiFareSub"></div>
                 </div>
             </div>
             <div class="stat-item">
                 <div class="stat-icon-box" style="background:#fef8f3;color:#c4b417;border:1px solid #cdd9b5;">
                     <i class="bi bi-percent"></i>
                 </div>
                 <div class="stat-info">
                     <div class="stat-label">Remittance Rate</div>
                     <div class="stat-value" id="kpiRemit">—</div>
                     <div class="stat-desc" id="kpiRemitSub"></div>
                 </div>
             </div>
         </div>

         <section class="overview-block">
             <div class="sec-head">
                 <h2>Performance Overview</h2>
                 <span class="sec-sub" id="analyticsRangeLabel">Period: Today</span>
             </div>
             <div class="overview-grid">
                 <div class="chart-card">
                     <div class="chart-box"><canvas id="overviewChart"></canvas></div>
                 </div>
                 <aside class="insights-card">
                     <h3>Driver Insights</h3>
                     <div id="driverInsightsBody">
                         <div class="small text-muted" style="padding:8px 2px;">Loading insights…</div>
                     </div>
                 </aside>
             </div>
         </section>

         <section class="table-block">
             <div class="sec-head">
                 <h2>Per-Driver Performance</h2>
                 <span class="sec-sub" id="driverCountLabel"></span>
             </div>
             <div class="table-scroll">
                 <table class="dpa-table" id="analyticsTable">
                     <thead id="analyticsTableHead"></thead>
                     <tbody id="analyticsTableBody">
                         <tr><td colspan="8" class="text-center py-5 text-muted">Loading analytics…</td></tr>
                     </tbody>
                 </table>
             </div>
             <div class="table-foot">
                 <span class="pg-info" id="pgInfo"></span>
                 <div class="pager">
                     <button type="button" class="btn btn-view" id="prevPageBtn"><i class="bi bi-chevron-left"></i>&nbsp;Prev</button>
                     <span class="pg-count" id="pgCount"></span>
                     <button type="button" class="btn btn-view" id="nextPageBtn">Next&nbsp;<i class="bi bi-chevron-right"></i></button>
                 </div>
             </div>
         </section>

         <section class="fin-block">
             <div class="sec-head">
                 <h2>Financial Summary</h2>
                 <span class="sec-sub">Income &amp; expenses for the selected period</span>
             </div>
             <div class="fin-grid" id="financialSummaryBody">
                 <div class="fin-cell"><span class="f-label">Gross Income</span><span class="f-value">—</span></div>
                 <div class="fin-cell"><span class="f-label">Fuel Cost</span><span class="f-value">—</span></div>
                 <div class="fin-cell"><span class="f-label">Fixed Costs</span><span class="f-value">—</span></div>
                 <div class="fin-cell net"><span class="f-label">Net Income</span><span class="f-value">—</span></div>
             </div>
         </section>

     </div>

     <section class="closing-block">
         <div class="sec-head">
             <h2>Daily Closing</h2>
             <span class="sec-sub">Generate the daily closing report</span>
         </div>
         <div class="closing-bar" id="closingFooter">
             <div class="cb-item cb-status">
                 <i class="bi bi-flag-fill" style="font-size:1.3rem;color:#295738;"></i>
                 <div>
                     <div class="cb-eyebrow">Ready to Close</div>
                     <div class="cb-badge" id="cfStatusBadge"><i class="bi bi-hourglass-split"></i><span id="cfStatusLabel">Checking…</span></div>
                 </div>
             </div>
             <span class="cb-sep"></span>
             <div class="cb-item">
                 <div>
                     <div class="cb-eyebrow">Report Date</div>
                     <input type="date" id="closingReportDate" class="cf-date-input">
                 </div>
             </div>
             <span class="cb-sep"></span>
             <div class="cb-item cb-msg">
                 <div>
                     <div class="cb-eyebrow">Completion</div>
                     <div class="cb-msg-text" id="cfMsg"><span class="spinner-border spinner-border-sm me-1"></span>Checking…</div>
                 </div>
             </div>
             <div class="cb-spacer"></div>
             <button type="button" class="btn btn-generate" id="generateReportBtn">
                 <i class="bi bi-flag-fill me-2"></i>Generate Closing Report
             </button>
         </div>

         <div id="finalizedReport" style="display:none;" class="finalized-panel">
             <div class="finalized-head">
                 <div>
                     <h3><i class="bi bi-check-circle-fill" style="color:#2e7d32;"></i> Report Status: Finalized</h3>
                     <div class="small text-muted" id="finalizedMeta"></div>
                 </div>
                 <div class="d-flex align-items-center gap-2 flex-wrap">
                     <button type="button" class="btn btn-view" id="dailyPrintBtn"><i class="bi bi-printer me-1"></i>Print</button>
                     <button type="button" class="btn btn-view" id="dailyExportPdfBtn"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</button>
                 </div>
             </div>
             <div class="finalized-kpis" id="finalizedKpis"></div>
             <div class="finalized-table-scroll">
                 <table class="finalized-table" id="finalizedTable">
                     <thead>
                         <tr>
                             <th>Driver</th><th>Att.</th><th class="num">Trips</th><th class="num">Pax</th><th class="num">Other</th>
                             <th class="num">Gross Income</th><th class="num">Remittance</th><th class="num">Fuel</th><th class="num">Expenses</th><th class="num">Net Income</th><th>Remit Status</th>
                         </tr>
                     </thead>
                     <tbody id="finalizedTableBody">
                         <tr><td colspan="8" class="text-center py-4 text-muted">Select a date to load the report.</td></tr>
                     </tbody>
                 </table>
             </div>
         </div>
     </section>

 </section>

 <div class="modal fade" id="aiDriverReportModal" tabindex="-1" aria-hidden="true">
     <div class="modal-dialog modal-dialog-centered modal-xl">
         <div class="modal-content border-0" style="border-radius:22px;background:#fffdf8;box-shadow:0 18px 50px rgba(0,0,0,.1);">
             <div class="modal-header border-0 px-4 pt-4 pb-2">
                 <div>
                     <h5 class="mb-1 fw-bold" style="color:#295738;"><i class="bi bi-robot me-2" style="color:#295738;"></i><span id="aiReportTitle">Performance Report</span></h5>
                     <div class="small text-muted" id="aiReportDriver">—</div>
                     <div class="small text-muted" id="aiReportPeriod">—</div>
                     <div class="small text-muted" id="aiReportMeta">—</div>
                 </div>
                 <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
             </div>
             <div class="modal-body px-4 py-3 ai-report-body">
                 <div id="aiReportLoading" class="text-center py-5">
                     <i class="fa-solid fa-spinner fa-spin" style="font-size:1.8rem;color:#c4b417;"></i>
                     <p class="mt-3 text-muted mb-0">Generating AI analysis…</p>
                 </div>
                 <div id="aiReportContent" style="display:none;">
                     <section class="ai-sec">
                         <h6 class="ai-sec-title">Performance Overview</h6>
                         <div class="ai-overview-grid" id="aiOverview"></div>
                     </section>
                     <section class="ai-sec">
                         <h6 class="ai-sec-title">Attendance Summary</h6>
                         <div class="ai-overview-grid" id="aiAttendance"></div>
                     </section>
                     <section class="ai-sec">
                         <h6 class="ai-sec-title">Financial Performance</h6>
                         <div class="ai-fin" id="aiFinancial"></div>
                     </section>
                     <section class="ai-sec">
                         <h6 class="ai-sec-title">Capacity</h6>
                         <div id="aiCapacity"></div>
                     </section>
                     <section class="ai-sec">
                         <h6 class="ai-sec-title">Activity Over Time</h6>
                         <div class="ai-chart-box"><canvas id="aiReportChart"></canvas></div>
                     </section>
                     <section class="ai-sec">
                         <h6 class="ai-sec-title">Detailed Daily Data</h6>
                         <div class="ai-detail-scroll">
                             <table class="ai-detail-table" id="aiDetailTable">
                                 <thead><tr><th>Date</th><th class="num">Trips</th><th class="num">Passengers</th><th class="num">Fare</th></tr></thead>
                                 <tbody id="aiDetailTableBody"><tr><td colspan="4" class="text-center text-muted">No daily data.</td></tr></tbody>
                             </table>
                         </div>
                     </section>
                     <section class="ai-sec">
                         <h6 class="ai-sec-title">AI Performance Analysis</h6>
                         <div id="aiAnalysis"></div>
                     </section>
                 </div>
                 <div id="aiReportError" class="alert alert-danger" style="display:none;"></div>
             </div>
             <div class="modal-footer border-0 px-4 pb-4 pt-2 d-flex justify-content-end gap-2 flex-wrap">
                 <button type="button" class="btn" data-bs-dismiss="modal" style="height:44px;padding:0 22px;border-radius:12px;background:#fff;border:1px solid #dfe5d1;color:#687168;font-weight:600;">Close</button>
                 <button type="button" class="btn" id="aiPrintBtn" style="height:44px;padding:0 20px;border-radius:12px;border:1px solid #c9d8c9;background:#fff;color:#295738;font-weight:600;"><i class="bi bi-printer me-1"></i>Print Report</button>
                 <button type="button" class="btn text-white" id="aiExportPdfBtn" style="height:44px;padding:0 20px;border-radius:12px;border:none;background:#295738;font-weight:600;"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</button>
                 <button type="button" class="btn" id="aiExportExcelBtn" style="height:44px;padding:0 20px;border-radius:12px;border:1px solid #c9d8c9;background:#fff;color:#295738;font-weight:600;"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</button>
             </div>
         </div>
     </div>
 </div>

 <script>
     window.ANALYTICS_PERIOD = { period: 'monthly', start: '', end: '' };
     const PERIOD_LABELS = { today: 'Today', daily: 'Daily', weekly: 'Weekly', monthly: 'Monthly', quarterly: 'Quarterly', semi_annually: 'Semi-Annually', yearly: 'Yearly', annually: 'Annually', custom: 'Custom' };
     let ANALYTICS_DATA = null;
     let OV_CHART = null;
     let CLOSING_DATE = null;
     let CLOSING_STATUS = null;
     const PAGE_SIZE = 5;
     let ANALYTICS_DRIVERS = [];
     let ANALYTICS_PAGE = 1;
     let ANALYTICS_REQ = null;
     let ANALYTICS_SEQ = 0;
     let CLOSING_REQ = null;
     let CLOSING_SEQ = 0;
     let AI_REPORT_REQ = null;

     function localDateStr(d) {
         const y = d.getFullYear();
         const m = String(d.getMonth() + 1).padStart(2, '0');
         const day = String(d.getDate()).padStart(2, '0');
         return y + '-' + m + '-' + day;
     }

     function updatePeriodControls(period) {
         const wrapEl = document.getElementById('dateControlsWrap');
         if (wrapEl) wrapEl.style.display = (period === 'custom') ? 'flex' : 'none';
     }

     function setPeriodActive(period) {
         document.querySelectorAll('#analyticsPeriodBar .dash-seg-btn').forEach(b => b.classList.toggle('active', b.dataset.period === period));
     }

     function setAnalyticsPeriod(period) {
         window.ANALYTICS_PERIOD.period = period;
         if (period !== 'custom') { window.ANALYTICS_PERIOD.start = ''; window.ANALYTICS_PERIOD.end = ''; }
         updatePeriodControls(period);
         setPeriodActive(period);

         if (period === 'custom') {
             // Show From/To and prefill from the loaded range; wait for Apply so an
             // empty range is never requested.
             const s = document.getElementById('analyticsStart');
             const e = document.getElementById('analyticsEnd');
             const r = (ANALYTICS_DATA && ANALYTICS_DATA.range) || null;
             if (r && s && !s.value) s.value = String(r.start || '').slice(0, 10);
             if (r && e && !e.value) e.value = String(r.end || '').slice(0, 10);
             return;
         }

         loadDriverAnalytics();
     }

     function applyAnalyticsRange() {
         const period = window.ANALYTICS_PERIOD.period;
         if (period !== 'custom') return;

         const startEl = document.getElementById('analyticsStart');
         const endEl = document.getElementById('analyticsEnd');
         let s = startEl ? startEl.value : '';
         let e = endEl ? endEl.value : '';
         if (!s || !e) { showAlert('error', 'Dates required', 'Please choose both start and end dates.'); return; }
         if (s > e) {
             const swap = s;
             s = e;
             e = swap;
             if (startEl) startEl.value = s;
             if (endEl) endEl.value = e;
         }
         window.ANALYTICS_PERIOD.start = s;
         window.ANALYTICS_PERIOD.end = e;

         loadDriverAnalytics();
     }

     function analyticsQuery() {
         const p = window.ANALYTICS_PERIOD;
         if (p.period === 'custom') return 'period=custom&start=' + encodeURIComponent(p.start) + '&end=' + encodeURIComponent(p.end);
         return 'period=' + encodeURIComponent(p.period);
     }

     async function loadDriverAnalytics() {
         /* Cancel any in-flight request so a stale response can never
            overwrite the newest filter selection (no flicker, no duplicates). */
         if (ANALYTICS_REQ) {
             try { ANALYTICS_REQ.abort(); } catch (e) { /* already settled */ }
         }
         const controller = new AbortController();
         ANALYTICS_REQ = controller;
         const seq = ++ANALYTICS_SEQ;

         if (window.__dashLoadBegin) window.__dashLoadBegin();

         try {
             const res = await fetch('/admin/api/Driver/driver-performance-analytics.php?' + analyticsQuery(), { signal: controller.signal });
             const json = await res.json();
             if (seq !== ANALYTICS_SEQ) {
                 return; // superseded by a newer request
             }
             if (!json.success) {
                 throw new Error(json.error || json.message || 'Failed to load analytics.');
             }
             ANALYTICS_DATA = json;
             ANALYTICS_DRIVERS = json.drivers || [];
             ANALYTICS_PAGE = 1;
             renderAnalyticsKpis(json);
             renderOverviewChart(json);
             renderDriverPage();
             renderDriverInsights(json);
             renderFinancialSummary(json);
             document.getElementById('analyticsRangeLabel').textContent = 'Period: ' + periodLabel(json);
             renderHeaderFilters(json);
             syncClosingDateFromRange(json);
         } catch (err) {
             if (err && (err.name === 'AbortError' || controller.signal.aborted)) {
                 return; // replaced by a newer request; that request owns the overlay
             }
             console.error(err);
             const body = document.getElementById('analyticsTableBody');
             if (body) {
                 body.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-danger">Failed to load analytics. Please try again.</td></tr>';
             }
             showAlert('error', 'Loading failed', (err && err.message) ? err.message : 'Something went wrong while loading analytics.');
         } finally {
             if (seq === ANALYTICS_SEQ) {
                 ANALYTICS_REQ = null;
             }
             if (window.__dashLoadEnd) window.__dashLoadEnd();
         }
     }

     function periodLabel(json) {
         const p = json.period;
         const r = json.range || {};
         const label = PERIOD_LABELS[p] || (p.charAt(0).toUpperCase() + p.slice(1));
         const s = fmtDate(String(r.start || '').slice(0, 10));
         const e = fmtDate(String(r.end || '').slice(0, 10));
         if (!s || s === '—') return label;
         return label + ' · ' + s + (e && e !== s ? ' – ' + e : '');
     }

     function renderHeaderFilters(json) {
         const el = document.getElementById('dpaFilterSummary');
         if (!el) return;
         const r = json.range || {};
         const s = fmtDate(String(r.start || '').slice(0, 10));
         const e = fmtDate(String(r.end || '').slice(0, 10));
         const pLabel = PERIOD_LABELS[json.period] || json.period;
         el.innerHTML = '<i class="bi bi-funnel-fill"></i> Filter: ' + esc(pLabel) +
         (s && s !== '—' ? ' · ' + esc(s) + (e && e !== s ? ' – ' + esc(e) : '') : '');
     }

     function renderAnalyticsKpis(json) {
         const drivers = json.drivers || [];
         let trips = 0, passengers = 0, fare = 0, activeDrivers = 0;
         drivers.forEach(d => {
             const driverTrips = Number(d.trips && d.trips.total) || 0;
             trips += driverTrips;
             passengers += Number(d.passengers && d.passengers.total) || 0;
             fare += Number(d.gross_income !== undefined ? d.gross_income : (d.fare && d.fare.total)) || 0;
             if (driverTrips > 0) activeDrivers++;
         });
         document.getElementById('kpiTrips').textContent = fmtNum(trips);
         document.getElementById('kpiTripsSub').textContent = activeDrivers + ' active of ' + drivers.length + ' drivers';
         document.getElementById('kpiPassengers').textContent = fmtNum(passengers);
         document.getElementById('kpiPassengersSub').textContent = 'people served';
         document.getElementById('kpiFare').textContent = fmtPeso(fare);
         document.getElementById('kpiFareSub').textContent = 'income for the selected period';

         const remit = json.remittance || {};
         const expectedAmt = Number(remit.paid_expected) || 0;
         const remittedAmt = Number(remit.remitted_amount) || 0;
         const remitRate = (remit.remittance_rate !== undefined && remit.remittance_rate !== null) ? Number(remit.remittance_rate) : null;
         document.getElementById('kpiRemit').textContent = (expectedAmt > 0 && remitRate !== null) ? pct(remitRate) : '—';
         document.getElementById('kpiRemitSub').textContent = expectedAmt > 0
         ? '₱' + fmtNum2(remittedAmt) + ' of ₱' + fmtNum2(expectedAmt) + ' expected remitted'
         : 'No remittable income in this period';
     }

     function fmtDay(d) {
         if (!d) return '';
         const dt = new Date(String(d).indexOf('T') > -1 ? d : d + 'T00:00:00');
         if (isNaN(dt.getTime())) return String(d);
         return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
     }

     function renderOverviewChart(json) {
         const box = document.querySelector('.chart-box');
         const canvas = document.getElementById('overviewChart');
         if (!box || !canvas) return;
         const trips = (json.charts && json.charts.trips_over_time) ? json.charts.trips_over_time : {};
         const fare = (json.charts && json.charts.fare_over_time) ? json.charts.fare_over_time : {};
         const allDays = Array.from(new Set(Object.keys(trips).concat(Object.keys(fare)))).sort();
         const driversWithTrips = (json.drivers || []).some(d => Number(d.trips && d.trips.total) > 0);
         const noData = allDays.length === 0;
         const emptyExisting = box.querySelector('.chart-empty');

         if (typeof Chart === 'undefined' || noData) {
             if (OV_CHART) { OV_CHART.destroy(); OV_CHART = null; }
             const noChartLib = (typeof Chart === 'undefined');
             const title = noChartLib
             ? 'Chart unavailable'
             : (driversWithTrips ? 'Chart data unavailable' : 'No activity in this period');
             const subtitle = noChartLib
             ? 'The chart library could not be loaded. Please refresh the page.'
             : (driversWithTrips
                 ? 'Trip data exists for the selected period but could not be charted. Please refresh.'
                 : 'Trips and income will appear here once trips are recorded in the selected period.');
             let emptyEl = emptyExisting;
             if (!emptyEl) {
                 emptyEl = document.createElement('div');
                 emptyEl.className = 'chart-empty';
                 box.appendChild(emptyEl);
             }
             emptyEl.innerHTML =
             '<div class="chart-empty-icon"><i class="bi ' + (noChartLib ? 'bi-plug' : 'bi-bar-chart-line') + '"></i></div>' +
             '<div class="chart-empty-title">' + title + '</div>' +
             '<div class="chart-empty-sub">' + subtitle + '</div>';
             return;
         }

         if (emptyExisting) emptyExisting.remove();

         const labels = allDays.map(fmtDay);
         const tripsData = allDays.map(d => trips[d] || 0);
         const fareData = allDays.map(d => fare[d] || 0);

         if (OV_CHART) OV_CHART.destroy();
         OV_CHART = new Chart(canvas, {
             type: 'line',
             data: {
                 labels: labels,
                 datasets: [
                 { label: 'Trips', data: tripsData, borderColor: '#295738', backgroundColor: 'rgba(41,87,56,.10)', fill: true, tension: .35, borderWidth: 2, pointRadius: allDays.length <= 12 ? 3 : 0, pointHoverRadius: 4, pointBackgroundColor: '#295738', yAxisID: 'y', order: 2 },
                 { label: 'Gross Income', data: fareData, borderColor: '#c4b417', backgroundColor: 'rgba(196,180,23,0)', borderDash: [5, 4], tension: .3, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, yAxisID: 'y1', order: 1 }
                 ]
             },
             options: {
                 responsive: true,
                 maintainAspectRatio: false,
                 interaction: { mode: 'index', intersect: false },
                 plugins: {
                     legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11, family: 'Poppins' } } },
                     tooltip: {
                         callbacks: {
                             label: ctx => ctx.dataset.label + ': ' + (ctx.dataset.yAxisID === 'y' ? fmtNum(ctx.parsed.y) : fmtPeso(ctx.parsed.y))
                         }
                     }
                 },
                 scales: {
                     x: {
                         grid: { display: false },
                         ticks: { maxTicksLimit: 10, maxRotation: 0, autoSkip: true, font: { size: 10, family: 'Poppins' }, color: '#8a9a8a' }
                     },
                     y: {
                         beginAtZero: true,
                         grid: { color: '#f0efe2' },
                         ticks: { precision: 0, font: { size: 10, family: 'Poppins' }, color: '#8a9a8a' }
                     },
                     y1: {
                         beginAtZero: true,
                         position: 'right',
                         grid: { drawOnChartArea: false },
                         ticks: { callback: v => fmtShort(v), font: { size: 10, family: 'Poppins' }, color: '#c4b417' }
                     }
                 }
             }
         });
     }

     function renderTableHead() {
         document.getElementById('analyticsTableHead').innerHTML =
         '<tr>' +
         '<th class="col-driver">Driver</th>' +
         '<th>Attendance</th>' +
         '<th class="num" title="Absent scheduled days">Absent</th>' +
         '<th class="num">Trips</th>' +
         '<th class="num">Passengers</th>' +
         '<th class="num">Net Income</th>' +
         '<th>Remittance</th>' +
         '<th class="col-action"></th>' +
         '</tr>';
     }

     function remitBadge(status, variance) {
         const s = String(status || 'No Remit');
         let label = s, color = '#295738', bg = '#eef3e6';
         if (s === 'Completed' || s === 'Matched' || s === 'Paid') { label = 'Remitted'; color = '#2e7d32'; bg = '#eaf6ea'; }
         else if (s === 'Remitted') { label = 'Remitted'; color = '#2f6db0'; bg = '#e6f0f7'; }
         else if (s === 'History') { label = 'History'; color = '#7a6f4f'; bg = '#f3eee2'; }
         else if (s === 'Discrepancy' || s === 'Under Remitted') { label = 'Under Remitted'; color = '#b59a00'; bg = '#fdf6dc'; }
         else if (s === 'Pending') { label = 'Pending'; color = '#b59a00'; bg = '#fdf6dc'; }
         else if (s === 'No Remit') { label = 'No Remit'; color = '#b94a48'; bg = '#fbe9e7'; }
         else if (s === 'Absent') { label = 'Absent'; color = '#6b7280'; bg = '#e9ebee'; }
         else if (s === 'No Activity') { label = 'No Activity'; color = '#8a9a8a'; bg = '#f0f0ea'; }
         return '<span class="status-badge" style="color:' + color + ';background:' + bg + ';border-color:' + color + ';">' + label +
         (Number(variance) > 0 ? ' <span title="Variance">₱' + Number(variance).toLocaleString('en-US', { minimumFractionDigits:2 }) + '</span>' : '') + '</span>';
     }

     function displayRemitStatus(d) {
         return d.remittance_status || (d.remittance !== undefined ? (Number(d.remittance) > 0 ? 'Completed' : 'No Remit') : 'No Remit');
     }

     function esc(s) {
         return String(s === null || s === undefined ? '' : s)
         .replace(/&/g, '&amp;')
         .replace(/</g, '&lt;')
         .replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;')
         .replace(/'/g, '&#039;');
     }

     function renderAnalyticsTable(drivers) {
         const body = document.getElementById('analyticsTableBody');
         if (!drivers.length) {
             body.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted">No driver data for this period.</td></tr>';
             return;
         }
         body.innerHTML = '';
         drivers.forEach(d => {
             const photo = (d.photo && d.photo !== 'null') ? d.photo : '/assets/images/admin/driversinfo/drivericon.png';
             const photoSrc = photo.startsWith('/') ? photo : ('/uploads/drivers/photos/' + photo);
             const trips = Number(d.trips && d.trips.total) || 0;
             const presentDays = Number(d.attendance && d.attendance.present) || 0;
             const scheduledDays = Number(d.attendance && d.attendance.scheduled) || 0;
             const hasActivity = (d.no_activity === true) ? false : (trips > 0 || presentDays > 0);
             const status = hasActivity ? displayRemitStatus(d) : 'No Activity';
             const net = Number(d.net_income) || 0;
             const att = d.attendance || {};
             const attendanceCell = hasActivity
             ? '<span class="att-pct">' + presentDays + ' / ' + scheduledDays + '</span><span class="att-sub">' + pct(att.rate) + ' · expected ' + scheduledDays + '</span>'
             : '<span class="att-pct">No Activity</span><span class="att-sub">0 / ' + scheduledDays + ' · expected ' + scheduledDays + '</span>';
             const tr = document.createElement('tr');
             tr.innerHTML =
             '<td><div class="driver-cell">' +
             '<img src="' + esc(photoSrc) + '" class="thumb" alt="" onerror="this.onerror=null;this.src=\'/assets/images/admin/driversinfo/drivericon.png\';">' +
             '<div><div class="dc-name" title="' + esc(d.name || 'Unknown') + '">' + esc(d.name || 'Unknown') + '</div><div class="dc-id">' + esc(d.driver_id || '') + '</div></div>' +
             '</div></td>' +
             '<td>' + attendanceCell + '</td>' +
             '<td class="num">' + fmtNum(Number(att.absent) || 0) + '</td>' +
             '<td class="num">' + fmtNum(trips) + '</td>' +
             '<td class="num">' + fmtNum(Number(d.passengers && d.passengers.total) || 0) + '</td>' +
             '<td class="num"><span class="net-val' + (net < 0 ? ' neg' : '') + '">' + fmtPeso(net) + '</span></td>' +
             '<td>' + remitBadge(status, d.remitted_variance) + '</td>' +
             '<td class="col-action"><button type="button" class="btn-ai" onclick="openAiReport(\'' + esc(d.driver_id) + '\')" title="Run AI Report"><i class="bi bi-robot"></i><span>Report</span></button></td>';
             body.appendChild(tr);
         });
     }

     function pageCount() {
         return Math.max(1, Math.ceil(ANALYTICS_DRIVERS.length / PAGE_SIZE));
     }

     function goPrevPage() {
         if (ANALYTICS_PAGE > 1) { ANALYTICS_PAGE--; renderDriverPage(); }
     }

     function goNextPage() {
         if (ANALYTICS_PAGE < pageCount()) { ANALYTICS_PAGE++; renderDriverPage(); }
     }

     function renderDriverPage() {
         const total = ANALYTICS_DRIVERS.length;
         const pages = pageCount();
         if (ANALYTICS_PAGE > pages) ANALYTICS_PAGE = pages;
         const start = (ANALYTICS_PAGE - 1) * PAGE_SIZE;
         const slice = ANALYTICS_DRIVERS.slice(start, start + PAGE_SIZE);
         renderTableHead();
         renderAnalyticsTable(slice);

         const info = document.getElementById('pgInfo');
         const count = document.getElementById('driverCountLabel');
         if (count) count.textContent = total + (total === 1 ? ' driver' : ' drivers');
         if (info) {
             info.textContent = total
             ? 'Showing ' + (start + 1) + '–' + Math.min(start + PAGE_SIZE, total) + ' of ' + total + ' drivers'
             : 'No drivers';
         }
         const pg = document.getElementById('pgCount');
         if (pg) pg.textContent = total ? (ANALYTICS_PAGE + ' / ' + pages) : '';
         const prev = document.getElementById('prevPageBtn');
         const next = document.getElementById('nextPageBtn');
         if (prev) prev.disabled = (ANALYTICS_PAGE <= 1);
         if (next) next.disabled = (ANALYTICS_PAGE >= pages);
     }

     function insRow(icon, label, primary, sub) {
         return '<div class="insight-row"><div class="ins-icon"><i class="bi ' + icon + '"></i></div>' +
         '<div style="min-width:0;"><span class="ins-label">' + label + '</span>' +
         '<span class="ins-value">' + primary + '</span>' +
         (sub ? '<span class="ins-sub">' + sub + '</span>' : '') + '</div></div>';
     }

     function computeInsights(json) {
         const drivers = json.drivers || [];
         const rows = [];

         const rank = (d) => Number(d.net_income !== undefined ? d.net_income : d.gross_income) || 0;
         const earners = drivers.filter(d => d.net_income !== undefined ? d.net_income > 0 : d.gross_income > 0).sort((a, b) => rank(b) - rank(a));
         const top = earners[0];
         rows.push(top
         ? insRow('bi-trophy', 'Top Performer', top.name || 'Unknown', fmtPeso(top.net_income !== undefined ? top.net_income : top.gross_income) + ' net income')
         : insRow('bi-trophy', 'Top Performer', '—', 'No income recorded this period'));

         const flagStatuses = ['No Remit', 'Under Remitted', 'Discrepancy', 'Absent'];
         const flagged = drivers.find(d => flagStatuses.includes(String(d.remittance_status)))
         || drivers.find(d => d.attendance.scheduled > 0 && d.attendance.present === 0);
         if (flagged) {
             const reason = flagStatuses.includes(String(flagged.remittance_status))
             ? 'Remittance: <span class="flag-danger">' + flagged.remittance_status + '</span>'
             : 'No attendance recorded this period';
             rows.push(insRow('bi-exclamation-triangle', 'Needs Attention', flagged.name || 'Unknown', reason));
         } else {
             rows.push(insRow('bi-check-circle', 'Needs Attention', 'All clear', 'No flagged drivers this period'));
         }

         const n = drivers.length || 1;
         const avgNet = drivers.reduce((s, d) => s + Number(d.net_income !== undefined ? d.net_income : 0), 0) / n;
         const avgAtt = drivers.reduce((s, d) => s + Number(d.attendance.rate || 0), 0) / n;
         rows.push(insRow('bi-bar-chart', 'Fleet Average', fmtPeso(avgNet), pct(avgAtt.toFixed(1)) + ' average attendance'));

         return rows;
     }

     function renderDriverInsights(json) {
         document.getElementById('driverInsightsBody').innerHTML = computeInsights(json).join('');
     }

     function finCell(label, value, sub, cls) {
         return '<div class="fin-cell' + (cls ? ' ' + cls : '') + '"><span class="f-label">' + label + '</span>' +
         '<span class="f-value">' + value + '</span>' +
         (sub ? '<span class="f-sub">' + sub + '</span>' : '') + '</div>';
     }

     function renderFinancialSummary(json) {
         const fin = json.financial || { gross_income: 0, fuel: 0, other_expenses: 0, net_income: 0 };
         const fuelLiters = (json.drivers || []).reduce((s, d) => s + Number(d.gas ? d.gas.liters : 0), 0);
         document.getElementById('financialSummaryBody').innerHTML =
         finCell('Gross Income', fmtPeso(fin.gross_income), 'income for the selected period') +
         finCell('Fuel Cost', fmtPeso(fin.fuel), fuelLiters ? fmtLiters(fuelLiters) + ' for the period' : '') +
         finCell('Fixed Costs', fmtPeso(fin.other_expenses), 'amortization · insurance · GPS · OPTR') +
         finCell('Net Income', fmtPeso(fin.net_income), 'after fuel & fixed costs', 'net');
     }

     function closingDateDefault() {
         const inp = document.getElementById('closingReportDate');
         if (!inp.value) {
             const d = new Date();
             d.setDate(d.getDate() - 1);
             inp.value = localDateStr(d);
         }
         return inp.value;
     }

     /* Keep the daily closing report aligned with the analytics period: the report
        date follows the END of the selected range (today/daily/weekly/monthly/
        yearly/custom all resolve to a concrete end date server-side). */
     function syncClosingDateFromRange(json) {
         const inp = document.getElementById('closingReportDate');
         if (!inp || !json || !json.range) return;
         const end = String(json.range.end || '').slice(0, 10);
         if (!end || inp.value === end) return;
         inp.value = end;
         loadClosingStatus();
     }

     async function loadClosingStatus() {
         const date = closingDateDefault();
         if (CLOSING_REQ) {
             try { CLOSING_REQ.abort(); } catch (e) { /* already settled */ }
         }
         const controller = new AbortController();
         CLOSING_REQ = controller;
         const seq = ++CLOSING_SEQ;
         try {
             const res = await fetch('/admin/api/Driver/driver-daily-report.php?report_date=' + encodeURIComponent(date) + '&check=1', { signal: controller.signal });
             const json = await res.json();
             if (seq !== CLOSING_SEQ) return;
             if (!json.success) throw new Error(json.error || json.message || 'Failed');
             renderClosingStatus(json.data);
             renderReportDetails(json.data);
             renderValidation(json.data);
             if (json.data.status === 'Finalized') renderFinalized(json.data);
         } catch (err) {
             if (err && err.name === 'AbortError') return;
             if (seq !== CLOSING_SEQ) return;
             console.error(err);
             const lbl = document.getElementById('cfStatusLabel');
             const msg = document.getElementById('cfMsg');
             if (lbl) { lbl.textContent = 'Unavailable'; }
             if (msg) { msg.textContent = 'Could not load closing status.'; }
         } finally {
             if (seq === CLOSING_SEQ) CLOSING_REQ = null;
         }
     }

     function renderClosingStatus(snap) {
         const status = snap.status;
         CLOSING_STATUS = status;
         const msg = document.getElementById('cfMsg');
         const missing = snap.missing || {};
         const missingCount = ((missing.unremitted_trips || []).length + (missing.drivers_without_log || []).length);

         if (msg) {
             if (status === 'Finalized') {
                 msg.innerHTML = '<i class="bi bi-patch-check-fill me-1" style="color:#2e7d32;"></i>Report generated and locked';
             } else if (status === 'Ready') {
                 msg.innerHTML = '<i class="bi bi-check-circle-fill me-1" style="color:#2e7d32;"></i>All data complete — ready to generate';
             } else {
                 msg.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1" style="color:#c4b417;"></i>' +
                 (missingCount === 1 ? '1 item incomplete' : missingCount + ' items incomplete');
             }
         }

         const genBtn = document.getElementById('generateReportBtn');
         const isFinal = (status === 'Finalized');
         if (genBtn) {
             genBtn.disabled = isFinal;
             genBtn.innerHTML = isFinal
             ? '<i class="bi bi-check2-circle me-2"></i>Already Finalized'
             : '<i class="bi bi-flag-fill me-2"></i>Generate Closing Report';
         }
     }

     function renderReportDetails(snap) {
         const badge = document.getElementById('cfStatusBadge');
         const lbl = document.getElementById('cfStatusLabel');
         if (!badge || !lbl) return;
         const missing = snap.missing || {};
         const missingCount = ((missing.unremitted_trips || []).length + (missing.drivers_without_log || []).length);
         let text, color, bg, icon;
         if (snap.status === 'Finalized') {
             text = 'Finalized'; color = '#2e7d32'; bg = '#eaf6ea'; icon = 'bi-patch-check-fill';
         } else if (snap.status === 'Ready') {
             text = 'Ready to Close'; color = '#2e7d32'; bg = '#eaf6ea'; icon = 'bi-check-circle-fill';
         } else {
             text = 'Pending Closing'; color = '#b59a00'; bg = '#fdf6dc'; icon = 'bi-hourglass-split';
         }
         lbl.textContent = text;
         badge.style.color = color;
         badge.style.background = bg;
         badge.style.borderColor = color;
         const bi = badge.querySelector('i');
         if (bi) bi.className = 'bi ' + icon;
     }

     function renderValidation(snap) {
         const msg = document.getElementById('cfMsg');
         if (!msg) return;
         const val = snap.validation;
         if (snap.status === 'Finalized') {
             msg.innerHTML = '<i class="bi bi-patch-check-fill me-1" style="color:#2e7d32;"></i>Finalized';
             return;
         }
         if (val && val.ready) {
             msg.innerHTML = '<i class="bi bi-check-circle-fill me-1" style="color:#2e7d32;"></i>All data complete';
             return;
         }
         const missing = snap.missing || {};
         const missingCount = ((missing.unremitted_trips || []).length + (missing.drivers_without_log || []).length);
         msg.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1" style="color:#c4b417;"></i>' +
         (missingCount === 1 ? '1 item incomplete' : missingCount + ' items incomplete');
     }

     async function generateClosingReport() {
         const date = closingDateDefault();
         const btn = document.getElementById('generateReportBtn');
         const orig = btn.innerHTML;
         btn.disabled = true;
         btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating…';
         try {
             const res = await fetch('/admin/api/Driver/driver-daily-report.php?report_date=' + encodeURIComponent(date) + '&generate=1');
             const text = await res.text();
             let json;
             try {
                 json = JSON.parse(text);
             } catch (e) {
                 console.error('Non-JSON response:', text);
                 showAlert('error', 'Generate failed', 'The server returned an unexpected response (' + (res.status || 'error') + '). Check the browser console for details.');
                 return;
             }
             if (!json.success) {
                 const detail = (json.error || json.message || 'Unable to generate report.') +
                 (json.file ? ' [' + json.file.split('/').pop() + ':' + json.line + ']' : '');
                 console.error('Generate error response:', json);
                 showAlert('error', 'Cannot generate', detail);
                 return;
             }
             renderClosingStatus(json.data);
             renderReportDetails(json.data);
             renderValidation(json.data);
             if (json.data && json.data.status === 'Finalized') {
                 renderFinalized(json.data);
                 showAlert('success', 'Report generated', 'Daily closing report for ' + fmtDate(date) + ' has been finalized.');
             } else {
                 document.getElementById('finalizedReport').style.display = 'none';
                 const pendingCount = ((json.data && json.data.missing && (json.data.missing.unremitted_trips || []).length) || 0) +
                 ((json.data && json.data.missing && (json.data.missing.drivers_without_log || []).length) || 0);
                 showAlert('error', 'Cannot generate', 'Daily closing report for ' + fmtDate(date) + ' is incomplete (' +
                 (pendingCount === 1 ? '1 item pending' : pendingCount + ' items pending') + '). Complete the missing items first.');
             }
         } catch (err) {
             console.error(err);
             const detail = (err && err.message) ? err.message : 'Something went wrong.';
             showAlert('error', 'Generate failed', detail);
         } finally {
             btn.disabled = false;
             btn.innerHTML = orig;
         }
     }

     function renderFinalized(snap) {
         document.getElementById('finalizedReport').style.display = 'block';
         const meta = document.getElementById('finalizedMeta');
         meta.textContent = 'Report For: ' + fmtDate(snap.report_date) +
             (snap.generated_at ? '  ·  Generated: ' + fmtDateTime(snap.generated_at) : '') +
             (snap.generated_by ? ' by ' + snap.generated_by : '');

         const totals = snap.totals || {};
         const kpiWrap = document.getElementById('finalizedKpis');
         const cards = [
         ['Total Trips', fmtNum(totals.trips), (totals.active_drivers || 0) + ' active drivers'],
         ['Gross Income', fmtPeso(totals.gross_income), 'remitted only'],
         ['Total Remittance', fmtPeso(totals.remittance), 'variance ₱' + fmtNum2(totals.variance)],
         ['Fuel Expenses', fmtPeso(totals.fuel), 'from fuel_logs'],
         ['Net Income', fmtPeso(totals.net_income), 'after fuel & fixed costs'],
         ['Attendance', (totals.attendance_present || 0) + ' / ' + (totals.attendance_scheduled || 0), 'present days'],
         ];
         kpiWrap.innerHTML = cards.map(c => '<div class="kpi"><div class="k-label">' + c[0] + '</div><div class="k-value">' + c[1] + '</div><div class="k-sub">' + c[2] + '</div></div>').join('');

         const body = document.getElementById('finalizedTableBody');
         const drivers = snap.drivers || [];
         if (!drivers.length) {
             body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No driver data for this date.</td></tr>';
             return;
         }
         body.innerHTML = '';
         drivers.forEach(d => {
             const att = d.attendance || {};
             const tr = document.createElement('tr');
             tr.innerHTML =
             '<td><div style="font-weight:600;">' + (d.name || 'Unknown') + '</div><div class="small text-muted">' + (d.driver_id || '') + '</div></td>' +
             '<td>' + (att.present || 0) + '/' + (att.scheduled || 0) + '</td>' +
             '<td class="num">' + fmtNum(d.trips) + '</td>' +
             '<td class="num">' + fmtNum(d.passengers) + '</td>' +
             '<td class="num">' + fmtNum(d.other) + '</td>' +
             '<td class="num">' + fmtPeso(d.gross_income) + '</td>' +
             '<td class="num">' + fmtPeso(d.remittance) + '</td>' +
             '<td class="num">' + fmtPeso(d.fuel_cost) + '</td>' +
             '<td class="num">' + fmtPeso(d.expenses) + '</td>' +
             '<td class="num">' + fmtPeso(d.net_income) + '</td>' +
             '<td>' + remitBadge(d.remittance_status, d.variance) + '</td>';
             body.appendChild(tr);
         });
     }

     async function downloadClosingReportPdf() {
         const date = closingDateDefault();
         const btn = document.getElementById('dailyExportPdfBtn');
         if (btn) btn.disabled = true;
         try {
             const res = await fetch('/admin/api/Driver/driver-daily-report-export.php?report_date=' + encodeURIComponent(date));
             const json = await res.json();
             if (!json.success) { showAlert('error', 'Export failed', json.message || json.error || 'Unable to generate PDF.'); return; }
             triggerDownload(json.url);
         } catch (err) {
             console.error(err);
             showAlert('error', 'Export failed', 'Something went wrong.');
         } finally {
             if (btn) btn.disabled = false;
         }
     }

     function triggerDownload(url) {
         if (!url) return;
         const a = document.createElement('a');
         a.href = url;
         a.target = '_blank';
         a.rel = 'noopener';
         a.download = '';
         document.body.appendChild(a);
         a.click();
         a.remove();
     }

     function openPrintFrame(html) {
         let frame = document.getElementById('dpaPrintFrame');
         if (!frame) {
             frame = document.createElement('iframe');
             frame.id = 'dpaPrintFrame';
             frame.setAttribute('aria-hidden', 'true');
             frame.style.position = 'fixed';
             frame.style.right = '0';
             frame.style.bottom = '0';
             frame.style.width = '0';
             frame.style.height = '0';
             frame.style.border = '0';
             document.body.appendChild(frame);
         }
         frame.onload = function () {
             try {
                 frame.contentWindow.focus();
                 frame.contentWindow.print();
             } catch (e) {
                 console.error(e);
                 showAlert('error', 'Print failed', 'The report could not be printed. Please try again.');
             }
         };
         frame.srcdoc = html;
     }

     let aiReportDriverId = null;

     async function openAiReport(driverId) {
         aiReportDriverId = driverId;
         if (AI_REPORT_REQ) {
             try { AI_REPORT_REQ.abort(); } catch (e) { /* already settled */ }
         }
         const controller = new AbortController();
         AI_REPORT_REQ = controller;
         document.getElementById('aiReportLoading').style.display = 'block';
         document.getElementById('aiReportContent').style.display = 'none';
         document.getElementById('aiReportError').style.display = 'none';
         const known = (ANALYTICS_DRIVERS || []).find(d => String(d.driver_id) === String(driverId));
         const knownName = known ? (known.name || driverId) : driverId;
         document.getElementById('aiReportTitle').textContent = knownName + ' – Performance Report';
         document.getElementById('aiReportDriver').textContent = 'Driver: ' + knownName + ' (' + driverId + ')';
         document.getElementById('aiReportPeriod').textContent = 'Period: ' + periodLabel(ANALYTICS_DATA || {});
         document.getElementById('aiReportMeta').textContent = 'Generating report…';

         new bootstrap.Modal(document.getElementById('aiDriverReportModal')).show();
         try {
             const res = await fetch('/admin/api/Driver/ai-driver-report.php?driver_id=' + encodeURIComponent(driverId) + '&' + analyticsQuery(), { signal: controller.signal });
             const json = await res.json();
             if (AI_REPORT_REQ !== controller) return;
             if (!json.success) {
                 document.getElementById('aiReportLoading').style.display = 'none';
                 const err = document.getElementById('aiReportError');
                 err.style.display = 'block';
                 err.textContent = json.message || json.error || 'Failed to generate report.';
                 return;
             }
             renderAiReport(json);
         } catch (err) {
             if (err && err.name === 'AbortError') return;
             if (AI_REPORT_REQ !== controller) return;
             console.error(err);
             document.getElementById('aiReportLoading').style.display = 'none';
             const e = document.getElementById('aiReportError');
             e.style.display = 'block';
             e.textContent = 'Something went wrong while generating the report.';
         } finally {
             if (AI_REPORT_REQ === controller) AI_REPORT_REQ = null;
         }
     }

     function ovCell(label, value, sub) {
         return '<div class="ai-ov"><div class="ov-label">' + label + '</div><div class="ov-value">' + value + '</div>' + (sub ? '<div class="ov-sub">' + sub + '</div>' : '') + '</div>';
     }
     function finTd(label, value) {
         return '<tr><td>' + label + '</td><td class="num">' + value + '</td></tr>';
     }

     function renderAiReport(json) {
         document.getElementById('aiReportLoading').style.display = 'none';
         document.getElementById('aiReportContent').style.display = 'block';
         const m = json.metrics || {};
         const a = json.analysis || {};
         const fc = m.fixed_costs || { amortization: 0, insurance: 0, gps: 0, optr: 0, total: 0 };
         const gross = (m.gross_income !== undefined ? m.gross_income : (m.fare ? m.fare.total : 0));
         const net = (m.net_income !== undefined ? m.net_income : 0);
         const gas = m.gas || { liters: 0, amount: 0 };
         document.getElementById('aiReportTitle').textContent = (m.name || 'Driver') + ' – Performance Report';
         document.getElementById('aiReportDriver').textContent = 'Driver: ' + (m.name || 'Unknown') + (m.driver_id ? ' (' + m.driver_id + ')' : '');
         document.getElementById('aiReportPeriod').textContent = 'Period: ' + periodLabel(json);
         const genBy = String(json.generated_by || '');
         const genRole = String(json.generated_by_role || '');
         const genAt = json.generated_at ? fmtDateTime(json.generated_at) : '';
         const sysName = String(json.system || 'R-Connect Transport Management System');
         document.getElementById('aiReportMeta').textContent =
         (genBy ? 'Generated by ' + genBy + (genRole ? ' (' + genRole + ')' : '') + (genAt ? ' on ' + genAt : '') : '') +
         (genBy && sysName ? '  ·  ' : '') + sysName;
         const att = m.attendance || { present: 0, scheduled: 0, rate: '0%' };
         const tr = m.trips || { total: 0 };
         const pa = m.passengers || { total: 0 };
         const ot = m.other || { records: 0 };
         document.getElementById('aiOverview').innerHTML =
         ovCell('Attendance', att.present + ' / ' + att.scheduled, pct(att.rate)) +
         ovCell('Trips', fmtNum(tr.total), '') +
         ovCell('Passengers', fmtNum(pa.total), '') +
         ovCell('Other Items', fmtNum(ot.records), '');
         document.getElementById('aiAttendance').innerHTML =
         ovCell('Present', att.present + ' / ' + att.scheduled, pct(att.rate) + ' attendance rate') +
         ovCell('Absent', fmtNum(att.absent || 0), 'scheduled days with no recorded check-in') +
         ovCell('Expected Workdays', fmtNum(att.scheduled || 0), 'scheduled days excluding rest days');
         let fin = '<table class="ai-fin-table"><tbody>';
         fin += finTd('Gross Income', fmtPeso(gross));
         fin += finTd('Amortization', fmtPeso(fc.amortization));
         fin += finTd('Insurance', fmtPeso(fc.insurance));
         fin += finTd('GPS', fmtPeso(fc.gps));
         fin += finTd('OPTR', fmtPeso(fc.optr));
         fin += finTd('Gas', fmtLiters(gas.liters) + ' &middot; ' + fmtPeso(gas.amount));
         fin += '<tr class="total"><td>Total Expenses</td><td class="num">' + fmtPeso(fc.total + gas.amount) + '</td></tr>';
         fin += '<tr class="net"><td>Net Income</td><td class="num">' + fmtPeso(net) + '</td></tr>';
         fin += '</tbody></table>';
         document.getElementById('aiFinancial').innerHTML = fin;
         const occ = (m.capacity && m.capacity.occupancy != null) ? m.capacity.occupancy : null;
         const occVal = (occ == null) ? 'N/A' : pct(occ);
         const occNum = (occ == null) ? 0 : Math.min(100, parseFloat(occ));
         document.getElementById('aiCapacity').innerHTML =
         '<div class="d-flex align-items-center gap-2 mb-1"><span class="ai-cap-pct">' + occVal + '</span><span class="small text-muted">occupancy</span></div>' +
         '<div class="ai-cap-bar"><div class="ai-cap-fill" style="width:' + occNum + '%"></div></div>';
         renderAiReportChart(m);
         renderAiDetailTable(m);
         const blocks = [
         ['summary', 'Summary', a.summary],
         ['strengths', 'Strengths', a.strengths],
         ['watch_outs', 'Watch Outs', a.watch_outs],
         ['key_findings', 'Key Findings', a.key_findings],
         ['recommendation', 'Recommendation', a.recommendation],
         ];
         document.getElementById('aiAnalysis').innerHTML = blocks.map(b =>
         '<div class="ai-analysis-block ' + b[0] + '"><div class="ab-title">' + b[1] + '</div><div class="ab-body">' + (b[2] || '—') + '</div></div>').join('');
     }

     let AI_REPORT_CHART = null;

     function renderAiReportChart(m) {
         const canvas = document.getElementById('aiReportChart');
         if (!canvas || typeof Chart === 'undefined') return;
         const daily = (m.daily_performance || []).slice().sort((a, b) => String(a.day).localeCompare(String(b.day)));
         if (AI_REPORT_CHART) { AI_REPORT_CHART.destroy(); AI_REPORT_CHART = null; }
         if (!daily.length) return;
         const labels = daily.map(r => fmtDay(r.day));
         const trips = daily.map(r => Number(r.trips) || 0);
         const fare = daily.map(r => Number(r.fare) || 0);
         AI_REPORT_CHART = new Chart(canvas, {
             type: 'bar',
             data: {
                 labels: labels,
                 datasets: [
                 { label: 'Trips', data: trips, backgroundColor: '#295738', borderRadius: 4, yAxisID: 'y', order: 2 },
                 { label: 'Fare', data: fare, type: 'line', borderColor: '#c4b417', backgroundColor: 'rgba(196,180,23,.15)', tension: .3, borderWidth: 2, pointRadius: 0, yAxisID: 'y1', order: 1 }
                 ]
             },
             options: {
                 responsive: true,
                 maintainAspectRatio: false,
                 plugins: {
                     legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11, family: 'Poppins' } } }
                 },
                 scales: {
                     x: { grid: { display: false }, ticks: { maxTicksLimit: 10, maxRotation: 0, autoSkip: true, font: { size: 10, family: 'Poppins' }, color: '#8a9a8a' } },
                     y: { beginAtZero: true, grid: { color: '#f0efe2' }, ticks: { precision: 0, font: { size: 10, family: 'Poppins' }, color: '#8a9a8a' } },
                     y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => fmtShort(v), font: { size: 10, family: 'Poppins' }, color: '#c4b417' } }
                 }
             }
         });
     }

     function renderAiDetailTable(m) {
         const tbody = document.getElementById('aiDetailTableBody');
         if (!tbody) return;
         const daily = (m.daily_performance || []).slice().sort((a, b) => String(a.day).localeCompare(String(b.day)));
         if (!daily.length) {
             tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No daily data for this period.</td></tr>';
             return;
         }
         tbody.innerHTML = daily.map(r =>
         '<tr><td>' + esc(fmtDate(String(r.day || '').slice(0, 10))) + '</td>' +
         '<td class="num">' + fmtNum(r.trips) + '</td>' +
         '<td class="num">' + fmtNum(r.passengers) + '</td>' +
         '<td class="num">' + fmtPeso(r.fare) + '</td></tr>').join('');
     }

     function exportAiReport(format) {
         if (!aiReportDriverId) return;
         fetch(aiExportUrl(format)).then(r => r.json()).then(j => {
             if (j.success && j.url) triggerDownload(j.url);
             else showAlert('error', 'Export failed', j.message || j.error || 'Failed to export.');
         }).catch(() => showAlert('error', 'Export failed', 'Something went wrong.'));
     }

     function printAiReport(mode) {
         if (!aiReportDriverId) return;
         const src = document.getElementById('aiReportContent');
         if (!src) return;

         /* Capture the chart before building the printable layout so it is
            included in the print / Save-as-PDF output. */
         let bodyHtml = src.innerHTML;
         const canvas = document.getElementById('aiReportChart');
         if (canvas && typeof canvas.toDataURL === 'function') {
             try {
                 if (AI_REPORT_CHART && typeof AI_REPORT_CHART.update === 'function') {
                     AI_REPORT_CHART.update('none');
                 }
                 const imgTag = '<img src="' + canvas.toDataURL('image/png') + '" style="width:100%;">';
                 bodyHtml = bodyHtml.replace(/<canvas\b[^>]*\bid="aiReportChart"[^>]*>[\s\S]*?<\/canvas>/, imgTag);
             } catch (e) {
                 console.error(e);
             }
         }

         /* Structured header: system, title, driver on the left; period and
            generation details aligned on the right. */
         const title = document.getElementById('aiReportTitle').textContent;
         const driverLine = document.getElementById('aiReportDriver').textContent.replace(/^Driver:\s*/, '');
         const periodLine = document.getElementById('aiReportPeriod').textContent.replace(/^Period:\s*/, '');
         const metaEl = document.getElementById('aiReportMeta');
         const metaText = (metaEl && metaEl.textContent) ? metaEl.textContent.trim() : '';
         let generatedLine = '';
         let systemLine = 'R-Connect Transport Management System';
         if (metaText && metaText !== '—' && !/generating/i.test(metaText)) {
             const parts = metaText.split(/\s+·\s+/);
             generatedLine = parts[0] || '';
             if (parts.length > 1) systemLine = parts[parts.length - 1];
         }
         const hint = (mode === 'pdf')
         ? '<div class="pr-hint">In the print dialog, choose <b>Save as PDF</b> as the destination to export this report as a PDF file.</div>'
         : '';
         const headHtml =
         '<div class="report-head">' +
         '<div class="rh-left">' +
         '<div class="rh-system">' + esc(systemLine) + '</div>' +
         '<h1 class="rh-title">' + esc(title) + '</h1>' +
         '<div class="rh-driver">' + esc(driverLine) + '</div>' +
         '</div>' +
         '<div class="rh-right">' +
         (periodLine ? '<div class="rh-row"><span class="rh-label">Period</span><span class="rh-value">' + esc(periodLine) + '</span></div>' : '') +
         (generatedLine ? '<div class="rh-row"><span class="rh-label">Generated</span><span class="rh-value">' + esc(generatedLine) + '</span></div>' : '') +
         '</div>' +
         '</div>';

         const html = '<!doctype html><html><head><title>' + esc(title) + '</title><style>' +
         '@page{size:A4 portrait;margin:16mm 14mm;}' +
         'body{font-family:Poppins,Arial,sans-serif;color:#2e4836;padding:0;margin:0;line-height:1.55;font-size:12px;}' +
         '.report-head{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;border-bottom:2px solid #295738;padding-bottom:14px;margin-bottom:20px;}' +
         '.rh-system{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#8a9a8a;margin-bottom:4px;}' +
         '.rh-title{font-size:22px;font-weight:800;color:#295738;margin:0 0 4px;line-height:1.2;}' +
         '.rh-driver{font-size:13px;font-weight:600;color:#3a4a3c;}' +
         '.rh-right{text-align:right;min-width:220px;}' +
         '.rh-row{margin-bottom:6px;} .rh-label{display:block;font-size:9px;letter-spacing:.06em;text-transform:uppercase;color:#8a9a8a;} .rh-value{font-size:12px;font-weight:600;color:#3a4a3c;}' +
         '.pr-hint{background:#f6f8ec;border:1px solid #e5e0a4;border-radius:8px;padding:10px 12px;font-size:12px;color:#7a6f12;margin:0 0 18px;}' +
         '.ai-sec{margin-bottom:24px;}' +
         '.ai-sec-title{font-size:12px;font-weight:700;text-transform:uppercase;color:#295738;border-bottom:1px solid #ece6cf;margin:0 0 12px;padding-bottom:6px;break-after:avoid;}' +
         '.ai-overview-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;align-items:stretch;}' +
         '.ai-ov{border:1px solid #e5e0a4;border-radius:10px;padding:12px 14px;min-height:64px;overflow-wrap:anywhere;break-inside:avoid;}' +
         '.ov-label{font-size:10px;color:#6f7d6f;text-transform:uppercase;margin-bottom:3px;} .ov-value{font-weight:700;font-size:16px;color:#295738;line-height:1.25;} .ov-sub{font-size:11px;color:#9aa89a;margin-top:3px;}' +
         '.ai-fin-table{width:100%;border-collapse:collapse;font-size:13px;} .ai-fin-table td{padding:9px 4px;border-bottom:1px dashed #ece6cf;} .ai-fin-table td.num{text-align:right;font-weight:600;white-space:nowrap;}' +
         '.ai-fin-table tr.total td{border-top:2px solid #d8d2b0;padding-top:12px;font-weight:700;color:#295738;} .ai-fin-table tr.net td{background:#eef6ee;font-weight:700;color:#295738;padding:12px 8px;}' +
         '.ai-detail-table{width:100%;border-collapse:collapse;font-size:12px;} .ai-detail-table thead{display:table-header-group;} .ai-detail-table th,.ai-detail-table td{border-bottom:1px solid #f0ede0;padding:8px 10px;text-align:left;} .ai-detail-table th{background:#f6f8ec;text-transform:uppercase;font-size:10px;letter-spacing:.03em;} .ai-detail-table .num{text-align:right;white-space:nowrap;} .ai-detail-table tr{break-inside:avoid;}' +
         '.ai-analysis-block{margin-bottom:14px;break-inside:avoid;} .ab-title{font-size:11px;font-weight:700;text-transform:uppercase;color:#7a6f4f;margin-bottom:4px;} .ab-body{font-size:13px;line-height:1.65;}' +
         '.ai-cap-pct{font-size:16px;font-weight:700;color:#295738;} .ai-cap-bar{height:10px;background:#ece6cf;border-radius:999px;overflow:hidden;margin-top:6px;} .ai-cap-fill{height:100%;background:linear-gradient(90deg,#a5d6a7,#295738);}' +
         '.ai-chart-box{height:auto;break-inside:avoid;} .ai-chart-box img{width:100%;max-height:320px;object-fit:contain;}' +
         '.ai-detail-scroll{overflow:visible;}' +
         '</style></head><body>' +
         headHtml + hint +
         bodyHtml +
         '</body></html>';

         openPrintFrame(html);
     }

     function printDailyReport() {
         const panel = document.getElementById('finalizedReport');
         if (!panel) return;
         const date = document.getElementById('closingReportDate').value || '';
         const metaText = ((document.getElementById('finalizedMeta') || {}).textContent || '').trim();
         const headHtml =
         '<div class="report-head">' +
         '<div class="rh-left">' +
         '<div class="rh-system">R-Connect Transport Management System</div>' +
         '<h1 class="rh-title">Daily Closing Report</h1>' +
         '<div class="rh-driver">' + esc(fmtDate(date)) + '</div>' +
         '</div>' +
         '<div class="rh-right">' + (metaText ? '<div class="rh-row"><span class="rh-label">Details</span><span class="rh-value">' + esc(metaText) + '</span></div>' : '') + '</div>' +
         '</div>';
         const html = '<!doctype html><html><head><title>Daily Closing Report - ' + esc(fmtDate(date)) + '</title><style>' +
         '@page{size:A4 landscape;margin:12mm;}' +
         'body{font-family:Poppins,Arial,sans-serif;color:#2e4836;padding:0;margin:0;line-height:1.45;font-size:12px;}' +
         '.report-head{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;border-bottom:2px solid #295738;padding-bottom:12px;margin-bottom:18px;}' +
         '.rh-system{font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#8a9a8a;margin-bottom:4px;} .rh-title{font-size:20px;font-weight:800;color:#295738;margin:0 0 4px;} .rh-driver{font-size:13px;font-weight:600;}' +
         '.rh-right{text-align:right;} .rh-label{display:block;font-size:9px;letter-spacing:.06em;text-transform:uppercase;color:#8a9a8a;} .rh-value{font-size:12px;font-weight:600;}' +
         '.finalized-head{display:none !important;}' +
         '.finalized-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:0 0 18px;} .kpi{border:1px solid #e5e0a4;border-radius:10px;padding:10px 12px;break-inside:avoid;} .k-label{font-size:10px;text-transform:uppercase;color:#6f7d6f;} .k-value{font-weight:700;color:#295738;font-size:15px;} .k-sub{font-size:11px;color:#9aa89a;}' +
         '.finalized-table{width:100%;border-collapse:collapse;font-size:11px;} .finalized-table thead{display:table-header-group;} .finalized-table th,.finalized-table td{border-bottom:1px solid #f0ede0;padding:6px 8px;text-align:left;} .finalized-table th{background:#f6f8ec;white-space:nowrap;} .finalized-table .num{text-align:right;white-space:nowrap;} .finalized-table tr{break-inside:avoid;}' +
         '.btn{display:none !important;} .status-badge{border:1px solid #ccc;border-radius:10px;padding:2px 8px;font-size:10px;}' +
         '</style></head><body>' + headHtml + panel.innerHTML + '</body></html>';
         openPrintFrame(html);
     }

     function aiExportUrl(format) {
         return '/admin/api/Driver/driver-report-export.php?format=' + format + '&driver_id=' + encodeURIComponent(aiReportDriverId) + '&' + analyticsQuery();
     }

     function fmtNum(n) { return (Number(n) || 0).toLocaleString('en-US'); }
     function fmtNum2(n) { return (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
     function fmtLiters(n) { return (Number(n) || 0).toFixed(2) + ' L'; }
     function fmtPeso(n) { return '₱' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
     function fmtShort(n) {
         const v = Number(n) || 0;
         if (v >= 1000000) return '₱' + (v / 1000000).toFixed(1) + 'M';
         if (v >= 1000) return '₱' + (v / 1000).toFixed(1) + 'k';
         return '₱' + v.toFixed(0);
     }
     function pct(v) { if (v === null || v === undefined || v === '') return 'N/A'; return String(v).replace(/%/g, '') + '%'; }
     function fmtDate(d) { if (!d) return '—'; return new Date(d + (d.length === 10 ? 'T00:00:00' : '')).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }); }
     function fmtDateTime(d) { if (!d) return '—'; const dt = new Date(d); return dt.toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }); }
     function showAlert(kind, title, message) {
         const container = document.getElementById('performanceAnalyticsSection');
         const existing = container.querySelector('.dpa-alert');
         if (existing) existing.remove();
         const el = document.createElement('div');
         el.className = 'alert dpa-alert ' + (kind === 'success' ? 'alert-success' : 'alert-danger');
         el.style.marginTop = '12px';
         el.innerHTML = '<b>' + title + ':</b> ' + message;
         container.prepend(el);
         setTimeout(() => el.remove(), 6000);
     }

     document.addEventListener('DOMContentLoaded', function () {
         loadDriverAnalytics();
         loadClosingStatus();

         try {
             const aiDriver = new URLSearchParams(window.location.search).get('ai_driver');
             if (aiDriver) {
                 const tgt = document.getElementById('performanceAnalyticsSection');
                 if (tgt) tgt.scrollIntoView({ behavior: 'smooth', block: 'start' });
                 openAiReport(aiDriver);
             }
         } catch (e) { console.error(e); }

         document.getElementById('generateReportBtn').addEventListener('click', generateClosingReport);
         document.getElementById('prevPageBtn').addEventListener('click', goPrevPage);
         document.getElementById('nextPageBtn').addEventListener('click', goNextPage);
         document.getElementById('closingReportDate').addEventListener('change', loadClosingStatus);

         const bindClick = function (id, fn) {
             const el = document.getElementById(id);
             if (el) el.addEventListener('click', fn);
         };
         bindClick('aiPrintBtn', function () { printAiReport('print'); });
         bindClick('aiExportPdfBtn', function () { printAiReport('pdf'); });
         bindClick('aiExportExcelBtn', function () { exportAiReport('excel'); });
         bindClick('dailyPrintBtn', function () { printDailyReport(); });
         bindClick('dailyExportPdfBtn', function () { downloadClosingReportPdf(); });
     });
 </script>