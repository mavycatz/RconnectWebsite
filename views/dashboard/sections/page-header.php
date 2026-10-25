  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <style>
      .dash-page-head {
          margin-bottom: 18px;
      }

      @media(max-width:575px) {
          .dash-page-head {
              margin-bottom: 14px;
          }
      }
  </style>

  <div class="dash-page-head d-flex justify-content-between align-items-end flex-wrap gap-3">

      <div>
          <h1 style="font-weight:800; font-size:1.4rem; color:#295738; margin:0 0 2px;">
        Dashboard
          </h1>
          <p style="font-weight:500; font-size:0.82rem; color:#8a9a8a; margin:0;">
        Overview of ongoing trips, bus routes, and performance metrics
          </p>
      </div>

      <div class="d-flex align-items-center gap-2">
          <span class="dash-filters-label">Period</span>
          <div class="dash-seg" id="dashQuickFilter" role="group" aria-label="Dashboard period">
              <button type="button" class="dash-seg-btn" data-range="today">Today</button>
              <button type="button" class="dash-seg-btn" data-range="week">Week</button>
              <button type="button" class="dash-seg-btn" data-range="month">Month</button>
              <button type="button" class="dash-seg-btn" data-range="year">Year</button>
          </div>
      </div>
  </div>
