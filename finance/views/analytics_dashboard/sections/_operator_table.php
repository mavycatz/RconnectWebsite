<div class="fad-table-card">
    <div class="fad-table-header">
        <div>
            <div class="fad-chart-title" style="margin:0;"><i class="bi bi-people"></i> Per-Operator Financial Detail</div>
            <div class="fad-table-sub">Gross income, fuel, expenses, net income, remittance status</div>
        </div>
        <div class="fad-table-actions">
            <button type="button" class="fad-sort-btn" id="fadSortGross"><i class="bi bi-sort-down"></i> Gross</button>
            <button type="button" class="fad-sort-btn" id="fadSortNet"><i class="bi bi-sort-down"></i> Net</button>
        </div>
    </div>
    <div class="fad-table-wrap">
        <table class="fad-table">
            <thead>
                <tr>
                    <th>Operator</th>
                    <th class="fad-th-num">Gross Income</th>
                    <th class="fad-th-num">Fuel</th>
                    <th class="fad-th-num">Other Expenses</th>
                    <th class="fad-th-num">Net Income</th>
                    <th class="fad-th-num">Trips</th>
                    <th class="fad-th-num">Passengers</th>
                    <th class="fad-th-num">Fare (Pass / Other)</th>
                    <th>Remittance Status</th>
                    <th class="fad-th-num">Expected / Actual</th>
                </tr>
            </thead>
            <tbody id="fadOperatorTbody">
                <tr class="fad-skeleton-row">
                    <td><div class="fad-skel fad-skel-operator"></div></td>
                    <td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>
                    <td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>
                    <td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>
                    <td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>
                    <td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>
                    <td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>
                    <td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>
                    <td><div class="fad-skel fad-skel-badge"></div></td>
                    <td class="fad-td-num"><div class="fad-skel fad-skel-num"></div></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>