<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const TIMEOUT = 900000;
    let logoutTimer;

    function resetTimer() {
        clearTimeout(logoutTimer);
        logoutTimer = setTimeout(() => {
            window.location.href = "/public/login/index.php?expired=1";
        }, TIMEOUT);
    }

    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeydown = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;

    let allLogsData = [];
    let logShowAll = false;
    let searchDebounceTimer = null;

    function getLogType(status, description) {
        const s = (status || '').toLowerCase();
        const desc = (description || '').toLowerCase();
        if (s === 'failed' || s === 'error' || desc.includes('error') || desc.includes('failed')) return 'error';
        if (s === 'warning' || desc.includes('warning') || desc.includes('advisory') || desc.includes('variance') || desc.includes('discrepancy')) return 'warning';
        if (s === 'success' || desc.includes('success') || desc.includes('confirmed') || desc.includes('dispatched') || desc.includes('completed')) return 'success';
        return 'info';
    }

    function resolveLogType(item) {
        if (item && typeof item.type === 'string' && item.type !== '') {
            return item.type;
        }
        return getLogType(item ? item.status : '', item ? item.description : '');
    }

    function getStatusBadge(type) {
        switch (type) {
            case 'error':
            return '<span class="sys-badge sys-badge-error"><i class="bi bi-x-circle-fill"></i> ERROR</span>';
            case 'warning':
            return '<span class="sys-badge sys-badge-warning"><i class="bi bi-exclamation-triangle-fill"></i> WARNING</span>';
            case 'success':
            return '<span class="sys-badge sys-badge-success"><i class="bi bi-check-circle-fill"></i> SUCCESS</span>';
            default:
            return '<span class="sys-badge sys-badge-info"><i class="bi bi-info-circle-fill"></i> INFO</span>';
        }
    }

    function getStatusText(type) {
        const labels = { error: 'ERROR', warning: 'WARNING', success: 'SUCCESS', info: 'INFO' };
        const key = labels[type] ? type : 'info';
        return `<span class="sys-status-text status-${key}"><span class="dot"></span>${labels[key]}</span>`;
    }

    function getStatusColor(type) {
        switch (type) {
            case 'error': return '#dc3545';
            case 'warning': return '#d4a017';
            case 'success': return '#198754';
            default: return '#0d6efd';
        }
    }

    function extractEntities(text, recordId) {
        const combined = (text || '') + ' ' + (recordId || '');
        const entities = [];
        const seen = new Set();

        const tripMatches = combined.match(/Trip\s*#?\d+/gi);
        if (tripMatches) {
            tripMatches.forEach(m => {
                const clean = m.replace(/\s+/g, ' ');
                if (!seen.has(clean.toLowerCase())) {
                    seen.add(clean.toLowerCase());
                    entities.push({ label: clean, icon: 'bi-signpost-2-fill' });
                }
            });
        }

        const busMatches = combined.match(/Bus[- ]\d+/gi);
        if (busMatches) {
            busMatches.forEach(m => {
                const clean = m.replace(/\s+/g, '-');
                if (!seen.has(clean.toLowerCase())) {
                    seen.add(clean.toLowerCase());
                    entities.push({ label: clean, icon: 'bi-bus-front-fill' });
                }
            });
        }

        const drvMatches = combined.match(/(?:Driver\s+)?DRV-\d+/gi);
        if (drvMatches) {
            drvMatches.forEach(m => {
                const clean = m.replace(/^Driver\s+/i, 'Driver ');
                if (!seen.has(clean.toLowerCase())) {
                    seen.add(clean.toLowerCase());
                    entities.push({ label: clean, icon: 'bi-person-badge-fill' });
                }
            });
        }

        const shiftMatches = combined.match(/Shift\s*#?\d+/gi);
        if (shiftMatches) {
            shiftMatches.forEach(m => {
                const clean = m.replace(/\s+/g, ' ');
                if (!seen.has(clean.toLowerCase())) {
                    seen.add(clean.toLowerCase());
                    entities.push({ label: clean, icon: 'bi-clock-fill' });
                }
            });
        }

        if (recordId && !seen.has(recordId.toLowerCase()) && !recordId.startsWith('SYS-') && !recordId.startsWith('AUTH-')) {
            entities.push({ label: recordId, icon: 'bi-hash' });
        }

        return entities;
    }

    async function loadSystemLogs() {
        const searchEl = document.getElementById('systemLogSearch');
        const moduleEl = document.getElementById('systemLogModule');
        const statusEl = document.getElementById('systemLogStatus');
        const userEl = document.getElementById('systemLogUser');
        const dateEl = document.getElementById('systemLogDate');

        const params = new URLSearchParams();
        if (searchEl && searchEl.value.trim()) params.set('search', searchEl.value.trim());
        if (moduleEl && moduleEl.value) params.set('module', moduleEl.value);
        if (statusEl && statusEl.value) params.set('status', statusEl.value);
        if (userEl && userEl.value) params.set('user', userEl.value);
        if (dateEl && dateEl.value) params.set('date', dateEl.value);

        let url = '/admin/api/system/getSystemLogs.php';
        const qs = params.toString();
        if (qs) url += '?' + qs;

        try {
            const res = await fetch(url);
            const json = await res.json();
            if (!json.success) return;

            allLogsData = json.data || [];
            const total = json.total !== undefined ? json.total : allLogsData.length;
            const grandTotal = json.grand_total !== undefined ? json.grand_total : total;

            renderKPIs(countLogTypes(allLogsData));

            renderCompactMetrics(allLogsData);

            renderLatestActivityTable(allLogsData);

            renderLogFeed(allLogsData);

            updateResultCounter(allLogsData.length, total, grandTotal, params);

            if (json.users_list && userEl) {
                populateUserDropdown(json.users_list, userEl.value);
            }
            if (json.modules_list && moduleEl) {
                populateModuleDropdown(json.modules_list, moduleEl.value);
            }

            const timeEl = document.getElementById('lastUpdatedTime');
            if (timeEl) {
                const nowStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                timeEl.innerHTML = '<i class="bi bi-clock-history"></i> Updated ' + nowStr;
            }
        } catch (e) {
            console.error('System log load error:', e);
        }
    }

    function countLogTypes(logs) {
        const counts = { error: 0, success: 0, warning: 0, info: 0 };
        (logs || []).forEach(item => {
            const type = resolveLogType(item);
            if (counts[type] === undefined) {
                counts.info++;
            } else {
                counts[type]++;
            }
        });
        return counts;
    }

    function renderKPIs(counts) {
        const errCount = counts.error || 0;
        const succCount = counts.success || 0;
        const warnCount = counts.warning || 0;
        const monCount = counts.info || 0;

        const elErr = document.getElementById('countErrors');
        const elSucc = document.getElementById('countSuccess');
        const elWarn = document.getElementById('countWarnings');
        const elMon = document.getElementById('countMonitor');

        if (elErr) elErr.textContent = errCount.toLocaleString();
        if (elSucc) elSucc.textContent = succCount.toLocaleString();
        if (elWarn) elWarn.textContent = warnCount.toLocaleString();
        if (elMon) elMon.textContent = monCount.toLocaleString();

        const trErr = document.getElementById('trendErrors');
        const trSucc = document.getElementById('trendSuccess');
        const trWarn = document.getElementById('trendWarnings');
        const trMon = document.getElementById('trendMonitor');

        const totalOps = errCount + succCount + warnCount + monCount;
        const succPct = totalOps > 0 ? ((succCount / totalOps) * 100).toFixed(1) : '0.0';

        if (trErr) {
            trErr.innerHTML = errCount > 0 
            ? `<i class="bi bi-exclamation-circle-fill"></i> ${errCount} in current view`
            : `<i class="bi bi-check-all"></i> Zero errors in current view`;
        }
        if (trSucc) {
            trSucc.innerHTML = `<i class="bi bi-graph-up-arrow"></i> ${succPct}% of current view`;
        }
        if (trWarn) {
            trWarn.innerHTML = warnCount > 0 
            ? `<i class="bi bi-flag-fill"></i> ${warnCount} active warnings`
            : `<i class="bi bi-shield-check"></i> Zero warnings`;
        }
        if (trMon) {
            trMon.innerHTML = `<i class="bi bi-broadcast"></i> ${monCount} monitor events in view`;
        }
    }

    function renderCompactMetrics(logs) {
        const list = logs || [];
        const total = list.length;

        const moduleCounts = {};
        list.forEach(item => {
            const name = item.module || 'System';
            moduleCounts[name] = (moduleCounts[name] || 0) + 1;
        });
        let topModule = '-';
        let topCount = 0;
        Object.keys(moduleCounts).forEach(name => {
            if (moduleCounts[name] > topCount) {
                topCount = moduleCounts[name];
                topModule = name;
            }
        });
        const topPct = total > 0 ? Math.round((topCount / total) * 100) : 0;

        const modEl = document.getElementById('statActiveModule');
        const pctEl = document.getElementById('statActivePct');
        if (modEl) modEl.textContent = topModule;
        if (pctEl) pctEl.textContent = topPct + '%';

        const latestTimeEl = document.getElementById('statLatestTime');
        if (latestTimeEl) {
            if (list.length && list[0].created_at) {
                const d = new Date(list[0].created_at);
                latestTimeEl.textContent = isNaN(d.getTime()) ? list[0].created_at : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } else {
                latestTimeEl.textContent = '-';
            }
        }

        const sparklineWrap = document.getElementById('activitySparkline');
        if (sparklineWrap) {
            const buckets = {};
            const order = [];
            list.forEach(item => {
                if (!item.created_at) return;
                const d = new Date(item.created_at);
                if (isNaN(d.getTime())) return;
                const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:00`;
                if (buckets[key] === undefined) {
                    buckets[key] = 0;
                    order.push(key);
                }
                buckets[key]++;
            });

            const recent = order.sort().slice(-8);
            if (recent.length) {
                let maxVal = 1;
                recent.forEach(key => {
                    if (buckets[key] > maxVal) maxVal = buckets[key];
                });

                let sparkHtml = '';
                recent.forEach(key => {
                    const cnt = buckets[key];
                    const pct = Math.max(14, Math.round((cnt / maxVal) * 100));
                    sparkHtml += `<div class="sys-spark-bar" style="height:${pct}%;" title="${escapeHtml(key)}: ${cnt} events"></div>`;
                });
                sparklineWrap.innerHTML = sparkHtml;
            } else {
                sparklineWrap.innerHTML = '<span style="font-size:0.7rem;color:#889988;margin:auto;">No activity in view</span>';
            }
        }
    }

    function renderLatestActivityTable(events) {
        const tbody = document.getElementById('latestActivityTableBody');
        if (!tbody) return;

        if (!events || !events.length) {
            tbody.innerHTML = `
            <tr>
                <td colspan="5" class="sys-empty-state">
                    <i class="bi bi-inbox sys-empty-icon"></i>
                    <p class="mb-0">No system activity found in database.</p>
                </td>
            </tr>`;
            return;
        }

        let html = '';
        events.forEach(item => {
            const type = resolveLogType(item);
            const badge = getStatusText(type);
            const moduleName = item.module || 'System';
            const user = item.username || item.user_role || 'System';

            let timeStr = '-';
            if (item.created_at) {
                const d = new Date(item.created_at);
                timeStr = isNaN(d.getTime()) ? item.created_at : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            const activityDesc = item.description || item.action || 'Operation recorded';

            html += `
            <tr>
                <td><span class="sys-cell-time">${timeStr}</span></td>
                <td>${badge}</td>
                <td>
                    <span class="sys-cell-module d-inline-block text-truncate" style="max-width:120px;" title="${escapeHtml(moduleName)}">
                        ${escapeHtml(moduleName)}
                    </span>
                </td>
                <td>
                    <span class="sys-cell-activity d-inline-block text-truncate" style="max-width:340px;" title="${escapeHtml(activityDesc)}">
                        ${escapeHtml(activityDesc)}
                    </span>
                </td>
                <td>
                    <span class="sys-cell-user d-inline-block text-truncate" style="max-width:150px;" title="${escapeHtml(user)}">
                        ${escapeHtml(user)}
                    </span>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    function setActiveSessionsLive(isLive) {
        const badge = document.getElementById('activeSessionsLiveBadge');
        if (!badge) return;
        badge.classList.toggle('d-none', !isLive);
    }

    function updateActiveSessionCount(count) {
        const countBadge = document.getElementById('activeSessionCount');
        if (countBadge) countBadge.textContent = `${count} Active`;
    }

    function renderActiveSessionsState(state, payload) {
        const container = document.getElementById('activeSessionsContainer');
        if (!container) return;

        if (state === 'loading') {
            setActiveSessionsLive(false);
            container.innerHTML = `
            <div class="sys-empty-state my-auto">
                <div class="spinner-border spinner-border-sm text-success mb-2" role="status"></div>
                <p class="mb-0" style="font-size:0.78rem;">Loading active sessions...</p>
            </div>`;
            return;
        }

        if (state === 'error') {
            setActiveSessionsLive(false);
            container.innerHTML = `
            <div class="sys-empty-state my-auto">
                <i class="bi bi-exclamation-triangle sys-empty-icon"></i>
                <p class="mb-1" style="font-size:0.8rem;font-weight:600;color:#6b7c6b;">Unable to load active sessions</p>
                <small style="color:#92a492;">${escapeHtml(payload || 'The request failed.')}</small>
                <div class="mt-2">
                    <button type="button" class="sys-btn-clear" onclick="loadActiveSessions(true)">
                        <i class="bi bi-arrow-repeat"></i> Retry
                    </button>
                </div>
            </div>`;
            return;
        }

        if (state === 'empty') {
            setActiveSessionsLive(true);
            updateActiveSessionCount(0);
            container.innerHTML = `
            <div class="sys-empty-state my-auto">
                <i class="bi bi-person-slash sys-empty-icon"></i>
                <p class="mb-1" style="font-size:0.8rem;font-weight:600;color:#6b7c6b;">No active sessions</p>
                <small style="color:#92a492;">Users currently logged in will appear here in real time.</small>
            </div>`;
            return;
        }

        const sessions = payload || [];
        setActiveSessionsLive(true);
        updateActiveSessionCount(sessions.length);

        let html = '';
        sessions.forEach(s => {
            const name = s.name || s.username || 'Active User';
            const role = s.role || 'User';
            const activity = s.activity || 'Active in system';
            const timeAgo = s.last_active_time || 'Now';
            const initials = s.initials || 'U';
            const isSelf = !!s.is_current_user;

            html += `
            <div class="sys-session-item">
                <div class="sys-avatar ${isSelf ? 'is-self' : ''}" title="${escapeHtml(name)}">
                    ${escapeHtml(initials)}
                </div>
                <div class="sys-session-info">
                    <div class="sys-session-name">
                        <span>${escapeHtml(name)}</span>
                        ${isSelf ? '<span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:0.65rem;">You</span>' : ''}
                        <span class="sys-session-role">${escapeHtml(role)}</span>
                    </div>
                    <div class="sys-session-activity" title="${escapeHtml(activity)}">
                        ${escapeHtml(activity)}
                    </div>
                </div>
                <div class="sys-session-time">
                    <span class="sys-live-dot" style="width:5px;height:5px;"></span>
                    <span>${escapeHtml(timeAgo)}</span>
                </div>
            </div>`;
        });

        container.innerHTML = html;
    }

    function renderLogFeed(logs) {
        const container = document.getElementById('systemLogCards');
        const paginationWrap = document.getElementById('logPaginationContainer');
        const btnShowMore = document.getElementById('btnShowMoreLogs');
        if (!container) return;

        if (!logs || !logs.length) {
            container.innerHTML = `
            <div class="sys-empty-state">
                <i class="bi bi-search sys-empty-icon"></i>
                <p class="mb-1" style="font-size:0.85rem;font-weight:600;">No system logs match current filters</p>
                <small style="color:#8ca08c;">Try adjusting your search query, status level, or date range.</small>
            </div>`;
            if (paginationWrap) paginationWrap.style.display = 'none';
            return;
        }

        const limit = logShowAll ? logs.length : 12;
        const slice = logs.slice(0, limit);

        let html = '';
        slice.forEach(log => {
            const type = resolveLogType(log);
            const color = getStatusColor(type);
            const badge = getStatusBadge(type);
            const moduleName = log.module || 'System';
            const actionDesc = log.description || log.action || 'No description recorded';
            const user = log.username || log.user_role || 'System';
            const ip = log.ip_address || '127.0.0.1';

            let timeStr = '-';
            if (log.created_at) {
                const d = new Date(log.created_at);
                timeStr = isNaN(d.getTime()) ? log.created_at : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) + ' · ' + d.toLocaleDateString();
            }

            const entities = extractEntities(log.description || log.action, log.record_id);
            let entitiesHtml = '';
            entities.forEach(ent => {
                entitiesHtml += `<span class="sys-entity-badge"><i class="bi ${ent.icon}"></i> ${escapeHtml(ent.label)}</span>`;
            });

            html += `
            <div class="sys-log-card status-${type}">
                <div class="sys-log-header">
                    <div class="sys-log-title">
                        <span style="color:${color};font-size:0.85rem;">●</span>
                        <span>${escapeHtml(moduleName)}</span>
                    </div>
                    <div>${badge}</div>
                </div>

                <div class="sys-log-desc">
                    ${escapeHtml(actionDesc)}
                </div>

                <div class="sys-log-meta">
                    <span><i class="bi bi-clock me-1"></i>${timeStr}</span>
                    <span>·</span>
                    <span><i class="bi bi-person me-1"></i><strong>${escapeHtml(user)}</strong></span>
                    ${entitiesHtml ? '<span>·</span>' + entitiesHtml : ''}
                    <span>·</span>
                    <span><i class="bi bi-hdd-network me-1"></i>IP: ${escapeHtml(ip)}</span>
                </div>
            </div>`;
        });

        container.innerHTML = html;

        if (logs.length > 12) {
            if (paginationWrap) paginationWrap.style.display = 'block';
            if (btnShowMore) {
                const remaining = logs.length - 12;
                btnShowMore.innerHTML = logShowAll
                ? '<i class="bi bi-chevron-up"></i> Show Less Logs'
                : `<i class="bi bi-chevron-down"></i> Show More Logs (${remaining} more)`;
            }
        } else {
            if (paginationWrap) paginationWrap.style.display = 'none';
        }
    }

    function toggleShowMoreLogs() {
        logShowAll = !logShowAll;
        renderLogFeed(allLogsData);
    }

    function updateResultCounter(renderedCount, total, grandTotal, params) {
        const counterEl = document.getElementById('sysResultCounter');
        const tagsEl = document.getElementById('activeFilterTags');

        if (counterEl) {
            counterEl.textContent = `Showing ${renderedCount} of ${grandTotal.toLocaleString()} system events`;
        }

        if (tagsEl) {
            let tagHtml = '';
            const search = params.get('search');
            const module = params.get('module');
            const status = params.get('status');
            const user = params.get('user');
            const date = params.get('date');

            if (search) tagHtml += `<span class="badge bg-success text-white" style="font-size:0.68rem;">Search: "${escapeHtml(search)}"</span>`;
            if (module) tagHtml += `<span class="badge bg-secondary text-white" style="font-size:0.68rem;">Mod: ${escapeHtml(module)}</span>`;
            if (status) tagHtml += `<span class="badge bg-warning text-dark" style="font-size:0.68rem;">Status: ${escapeHtml(status)}</span>`;
            if (user) tagHtml += `<span class="badge bg-info text-dark" style="font-size:0.68rem;">User: ${escapeHtml(user)}</span>`;
            if (date && date !== 'all') tagHtml += `<span class="badge bg-light text-dark border" style="font-size:0.68rem;">Date: ${escapeHtml(date)}</span>`;

            tagsEl.innerHTML = tagHtml;
        }
    }

    function populateUserDropdown(users, currentVal) {
        const select = document.getElementById('systemLogUser');
        if (!select) return;

        let optHtml = '<option value="">All Users</option>';
        users.forEach(u => {
            const isSel = (u === currentVal) ? 'selected' : '';
            optHtml += `<option value="${escapeHtml(u)}" ${isSel}>${escapeHtml(u)}</option>`;
        });
        select.innerHTML = optHtml;
    }

    function populateModuleDropdown(modules, currentVal) {
        const select = document.getElementById('systemLogModule');
        if (!select) return;

        let optHtml = '<option value="">All Modules</option>';
        modules.forEach(m => {
            const isSel = (m === currentVal) ? 'selected' : '';
            optHtml += `<option value="${escapeHtml(m)}" ${isSel}>${escapeHtml(m)}</option>`;
        });
        select.innerHTML = optHtml;
    }

    let activeSessionsInFlight = false;

    async function loadActiveSessions(showLoading = false) {
        if (activeSessionsInFlight) return;
        activeSessionsInFlight = true;

        if (showLoading) {
            renderActiveSessionsState('loading');
        }

        try {
            const res = await fetch('/admin/api/system/getActiveSessions.php', {
                headers: { 'Accept': 'application/json' }
            });
            let json = null;
            try {
                json = await res.json();
            } catch (parseError) {
                json = null;
            }
            if (!res.ok) {
                throw new Error((json && json.message) ? json.message : ('Request failed with status ' + res.status));
            }
            if (!json || json.success !== true) {
                throw new Error((json && json.message) ? json.message : 'Unsuccessful response from server');
            }
            const sessions = json.data || [];
            renderActiveSessionsState(sessions.length ? 'success' : 'empty', sessions);
        } catch (e) {
            console.error('Active sessions load error:', e);
            renderActiveSessionsState('error', e && e.message ? e.message : 'The request failed.');
        } finally {
            activeSessionsInFlight = false;
        }
    }

    function filterSystemLogs() {
        logShowAll = false;
        loadSystemLogs();
    }

    function handleSearchInput() {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
            filterSystemLogs();
        }, 280);
    }

    function clearSystemFilters() {
        const s1 = document.getElementById('systemLogSearch');
        const s2 = document.getElementById('systemLogModule');
        const s3 = document.getElementById('systemLogStatus');
        const s4 = document.getElementById('systemLogUser');
        const s5 = document.getElementById('systemLogDate');

        if (s1) s1.value = '';
        if (s2) s2.value = '';
        if (s3) s3.value = '';
        if (s4) s4.value = '';
        if (s5) s5.value = 'all';

        logShowAll = false;
        loadSystemLogs();
    }

    function manualRefresh() {
        const icon = document.getElementById('refreshIcon');
        if (icon) icon.classList.add('fa-spin');
        Promise.all([loadSystemLogs(), loadActiveSessions()]).finally(() => {
            if (icon) icon.classList.remove('fa-spin');
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadSystemLogs();
        loadActiveSessions();

        setInterval(loadSystemLogs, 15000);
        setInterval(loadActiveSessions, 20000);
    });
</script>
</body>
</html>
