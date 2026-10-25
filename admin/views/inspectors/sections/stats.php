  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
      .inspector-stats-section,
      .inspector-stats-section h1,
      .inspector-stats-section h2,
      .inspector-stats-section div,
      .inspector-stats-section i,
      .inspector-stats-section p {
          font-family: 'Poppins', sans-serif;
      }
      .inspector-stat-card {
          background: #fffdf8;
          border: 1px solid #e5e0a4;
          border-radius: 17px;
          padding: 1.1rem 1.25rem;
          height: 100%;
          box-shadow: 0 3px 10px rgba(0, 0, 0, .04);
          position: relative;
          overflow: hidden;
      }
      .inspector-stat-card .accent-line {
          position: absolute;
          top: 0; left: 0;
          width: 4px;
          height: 100%;
          background: #a5d6a7;
          border-radius: 17px 0 0 17px;
      }
      .inspector-stat-card .stat-icon {
          width: 44px;
          height: 44px;
          border-radius: 12px;
          background: #e9f2e9;
          display: flex;
          align-items: center;
          justify-content: center;
          flex-shrink: 0;
          margin-left: 16px;
      }
      .inspector-stat-card .stat-icon i {
          font-size: 1.15rem;
          color: #295738;
      }
      .inspector-stat-card .stat-label {
          font-size: .72rem;
          font-weight: 600;
          color: #8a9a8a;
          letter-spacing: .7px;
          text-transform: uppercase;
          margin-bottom: 4px;
      }
      .inspector-stat-card .stat-value {
          margin: 0;
          font-weight: 800;
          font-size: 2rem;
          color: #295738;
          line-height: 1.1;
      }
      .inspector-stat-card .stat-sub {
          color: #a8b3a8;
          font-size: .68rem;
          margin-top: 4px;
      }
  </style>

  <div class="inspector-stats-section">
      <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
              <h1 style="font-weight:700; font-size:1.35rem; color:#295738; margin:0 0 2px;">
        Inspector Management
              </h1>
              <p style="font-weight:400; font-size:0.8rem; color:#8a9a8a; margin:0;">
        Manage inspector profiles, assignments, operational status, and inspection records.
              </p>
          </div>
      </div>

      <div class="row g-4 mb-4">

          <div class="col-lg-3 col-md-6 col-12">
              <div class="inspector-stat-card">
                  <div class="accent-line"></div>
                  <div class="d-flex justify-content-between align-items-center">
                      <div>
                          <div class="stat-label">Total Inspectors</div>
                          <h2 id="totalInspectors" class="stat-value">0</h2>
                          <div class="stat-sub">Registered inspectors</div>
                      </div>
                      <div class="stat-icon">
                          <i class="bi bi-person-badge-fill"></i>
                      </div>
                  </div>
              </div>
          </div>

          <div class="col-lg-3 col-md-6 col-12">
              <div class="inspector-stat-card">
                  <div class="accent-line"></div>
                  <div class="d-flex justify-content-between align-items-center">
                      <div>
                          <div class="stat-label">Active Inspectors</div>
                          <h2 id="activeInspectors" class="stat-value">0</h2>
                          <div class="stat-sub">Currently active</div>
                      </div>
                      <div class="stat-icon">
                          <i class="bi bi-person-check-fill"></i>
                      </div>
                  </div>
              </div>
          </div>

          <div class="col-lg-3 col-md-6 col-12">
              <div class="inspector-stat-card">
                  <div class="accent-line"></div>
                  <div class="d-flex justify-content-between align-items-center">
                      <div>
                          <div class="stat-label">Total Dispatchers</div>
                          <h2 id="totalDispatchers" class="stat-value">0</h2>
                          <div class="stat-sub">Registered dispatchers</div>
                      </div>
                      <div class="stat-icon">
                          <i class="bi bi-headset"></i>
                      </div>
                  </div>
              </div>
          </div>

          <div class="col-lg-3 col-md-6 col-12">
              <div class="inspector-stat-card">
                  <div class="accent-line"></div>
                  <div class="d-flex justify-content-between align-items-center">
                      <div>
                          <div class="stat-label">Active Dispatchers</div>
                          <h2 id="activeDispatchers" class="stat-value">0</h2>
                          <div class="stat-sub">Currently active</div>
                      </div>
                      <div class="stat-icon">
                          <i class="bi bi-headset"></i>
                      </div>
                  </div>
              </div>
          </div>

      </div>

      <script>
          async function loadInspectorStats() {
              try {
                  const response = await fetch('/admin/api/Inspectors/InspectorsStats.php?action=getStats');
                  const result = await response.json();
                  if (!result.success) { console.error(result.message); return; }
                  const stats = result.data;
                  document.getElementById('totalInspectors').textContent = stats.total ?? 0;
                  document.getElementById('activeInspectors').textContent = stats.active ?? 0;
                  document.getElementById('totalDispatchers').textContent = stats.total_dispatchers ?? 0;
                  document.getElementById('activeDispatchers').textContent = stats.active_dispatchers ?? 0;
              } catch (error) {
                  console.error('Failed to load inspector stats:', error);
              }
          }
          document.addEventListener('DOMContentLoaded', loadInspectorStats);
      </script>
  </div>
