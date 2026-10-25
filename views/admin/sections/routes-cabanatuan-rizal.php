<link rel="stylesheet" href="/admin/assets/css/fare-pricing.css?v=<?= time() ?>">

<div class="fp-page" id="farePricingSection">

    <div class="fp-header">

        <div class="fp-header-left">

            <div>
                <h6 class="fp-title">Fare Pricing</h6>
                <p class="fp-subtitle">Manage fares by destination and passenger type.</p>
            </div>

            <select
                id="routeSelector"
                class="form-select fp-route-select"
                aria-label="Route"
            >
                <option value="Cabanatuan-Rizal">
                    Cabanatuan &rarr; Rizal
                </option>

                <option value="Rizal-Cabanatuan">
                    Rizal &rarr; Cabanatuan
                </option>
            </select>

            <span
                id="fareSaveStatus"
                class="fp-status is-clean"
                role="status"
                aria-live="polite"
            >
                No changes
            </span>

        </div>

        <div class="fp-header-actions">

            <input
                type="text"
                id="fareDestinationFilter"
                class="form-control"
                placeholder="Filter destinations..."
                aria-label="Filter destinations"
                style="max-width:220px;"
            >

            <button
                id="saveFarePricingBtn"
                class="btn fp-btn fp-btn-primary"
                type="button"
                disabled
            >
                Save Changes
            </button>

            <button
                id="printFarePricingBtn"
                class="btn fp-btn fp-btn-light"
                type="button"
            >
                Print
            </button>

        </div>

    </div>

    <div class="fp-summary">

        <span class="fp-summary-count">
            <strong id="fareDestinationCount">0</strong> destinations
        </span>

        <button
            id="addFareRowBtn"
            class="btn fp-btn fp-btn-light"
            type="button"
        >
            + Add Destination
        </button>

    </div>

    <div id="fareSelectionToolbar" class="fp-toolbar" hidden>

        <span id="fareSelectionCount" class="fp-toolbar-count">
            0 selected
        </span>

        <div class="fp-toolbar-actions">

            <div class="dropdown fp-move-dropdown">

                <button
                    class="btn fp-btn fp-btn-light dropdown-toggle"
                    type="button"
                    id="moveFareDropdown"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    Move
                </button>

                <ul class="dropdown-menu" aria-labelledby="moveFareDropdown">
                    <li>
                        <button class="dropdown-item" type="button" data-action="move-top">
                            Move to top
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item" type="button" data-action="move-bottom">
                            Move to bottom
                        </button>
                    </li>
                </ul>

            </div>

            <button
                id="deleteFareRowsBtn"
                class="btn fp-btn fp-btn-danger-ghost"
                type="button"
                disabled
            >
                Delete Selected
            </button>

            <button
                id="clearFareSelectionBtn"
                class="btn fp-btn fp-btn-light"
                type="button"
            >
                Clear Selection
            </button>

        </div>

    </div>

    <div class="fp-grid-wrap">

        <div class="fp-grid-scroll">

            <table class="fp-grid">

                <thead>
                    <tr>
                        <th class="fp-col-check">
                            <input
                                type="checkbox"
                                id="selectAllFareRows"
                                aria-label="Select all destinations"
                            >
                        </th>
                        <th class="fp-col-num">#</th>
                        <th class="fp-col-order">Order</th>
                        <th class="fp-col-destination">Destination</th>
                        <th class="fp-col-fare">Regular</th>
                        <th class="fp-col-fare">Student/Senior</th>
                        <th class="fp-col-fare">Special</th>
                        <th class="fp-col-actions">Actions</th>
                    </tr>
                </thead>

                <tbody id="farePricingTable"></tbody>

            </table>

        </div>

        <div id="fareGridEmpty" class="fp-empty" hidden>
            No fare records found.
        </div>

    </div>

    <div class="fp-footer">
        <span id="fareRangeText" hidden>
            Showing 0 of 0 destinations
        </span>
    </div>

</div>

<script src="/admin/assets/js/fare-pricing.js?v=<?= time() ?>"></script>
