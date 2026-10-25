<link rel="stylesheet" href="/admin/assets/css/system-configuration.css?v=<?= time() ?>">

<div class="sc-page" id="systemConfigurationSection">

    <div class="sc-page-header">

        <div class="sc-page-heading">
            <h6 class="sc-page-title">Module Configuration &amp; Master Data</h6>
            <p class="sc-page-subtitle">Configure the vehicle types and fuel prices used across R-Connect.</p>
        </div>

        <div class="sc-info-card" role="note">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span>Changes here are shared by the admin, finance, and operations modules.</span>
        </div>

    </div>

    <div class="sc-grid">


        <section class="sc-card" aria-labelledby="vehicleManagementTitle">

            <div class="sc-card-header">

                <div class="sc-card-heading">
                    <span class="sc-card-icon" aria-hidden="true">
                        <i class="bi bi-bus-front"></i>
                    </span>
                    <div>
                        <h6 class="sc-card-title" id="vehicleManagementTitle">Vehicle Management</h6>
                        <p class="sc-card-subtitle">Vehicle types and their seating capacity.</p>
                    </div>
                </div>

                <div class="sc-card-actions">
                    <input
                        type="text"
                        id="vehicleTypeFilter"
                        class="form-control sc-input"
                        placeholder="Filter vehicle types..."
                        aria-label="Filter vehicle types"
                        style="max-width:220px;"
                    >
                    <button id="addVehicleTypeBtn" class="btn sc-btn sc-btn-primary" type="button">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        Add Vehicle Type
                    </button>
                </div>

            </div>

            <div class="sc-table-wrap">

                <table class="sc-table">

                    <thead>
                        <tr>
                            <th scope="col">Vehicle</th>
                            <th scope="col" class="sc-num">Seats</th>
                            <th scope="col" class="sc-num">Standing</th>
                            <th scope="col" class="sc-actions-col">Actions</th>
                        </tr>
                    </thead>

                    <tbody id="vehicleTypesTable">
                        <tr class="sc-skeleton-row">
                            <td><span class="sc-skeleton sc-skeleton-text"></span></td>
                            <td><span class="sc-skeleton sc-skeleton-num"></span></td>
                            <td><span class="sc-skeleton sc-skeleton-num"></span></td>
                            <td><span class="sc-skeleton sc-skeleton-icon"></span></td>
                        </tr>
                        <tr class="sc-skeleton-row">
                            <td><span class="sc-skeleton sc-skeleton-text"></span></td>
                            <td><span class="sc-skeleton sc-skeleton-num"></span></td>
                            <td><span class="sc-skeleton sc-skeleton-num"></span></td>
                            <td><span class="sc-skeleton sc-skeleton-icon"></span></td>
                        </tr>
                        <tr class="sc-skeleton-row">
                            <td><span class="sc-skeleton sc-skeleton-text"></span></td>
                            <td><span class="sc-skeleton sc-skeleton-num"></span></td>
                            <td><span class="sc-skeleton sc-skeleton-num"></span></td>
                            <td><span class="sc-skeleton sc-skeleton-icon"></span></td>
                        </tr>
                    </tbody>

                </table>

            </div>

            <div id="vehicleTypesState" class="sc-state" hidden></div>

        </section>


        <section class="sc-card" aria-labelledby="fuelPriceTitle">

            <div class="sc-card-header">

                <div class="sc-card-heading">
                    <span class="sc-card-icon" aria-hidden="true">
                        <i class="bi bi-fuel-pump"></i>
                    </span>
                    <div>
                        <h6 class="sc-card-title" id="fuelPriceTitle">Fuel Price Configuration</h6>
                        <p class="sc-card-subtitle">Current price per liter by fuel type.</p>
                    </div>
                </div>

                <div class="sc-card-actions">
                    <input
                        type="text"
                        id="fuelTypeFilter"
                        class="form-control sc-input"
                        placeholder="Filter fuel types..."
                        aria-label="Filter fuel types"
                        style="max-width:200px;"
                    >
                    <button id="addFuelTypeBtn" class="btn sc-btn sc-btn-primary" type="button">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        Add Fuel Type
                    </button>
                    <span id="fuelSaveStatus" class="sc-status is-clean" role="status" aria-live="polite">
                        No changes
                    </span>
                    <button id="saveFuelPricesBtn" class="btn sc-btn sc-btn-primary" type="button" disabled>
                        <i class="bi bi-floppy" aria-hidden="true"></i>
                        Save
                    </button>
                </div>

            </div>

            <div class="sc-table-wrap">

                <table class="sc-table">

                    <thead>
                        <tr>
                            <th scope="col">Fuel Type</th>
                            <th scope="col" class="sc-num sc-price-col">Price</th>
                            <th scope="col" class="sc-actions-col">Actions</th>
                        </tr>
                    </thead>

                    <tbody id="fuelPricesTable">
                        <tr class="sc-skeleton-row">
                            <td><span class="sc-skeleton sc-skeleton-text"></span></td>
                            <td><span class="sc-skeleton sc-skeleton-price"></span></td>
                            <td><span class="sc-skeleton sc-skeleton-icon"></span></td>
                        </tr>
                        <tr class="sc-skeleton-row">
                            <td><span class="sc-skeleton sc-skeleton-text"></span></td>
                            <td><span class="sc-skeleton sc-skeleton-price"></span></td>
                            <td><span class="sc-skeleton sc-skeleton-icon"></span></td>
                        </tr>
                    </tbody>

                </table>

            </div>

            <div id="fuelPricesState" class="sc-state" hidden></div>

        </section>

    </div>

</div>


<div class="modal fade" id="vehicleTypeModal" tabindex="-1" aria-labelledby="vehicleTypeModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content sc-modal">

            <div class="modal-header sc-modal-header">
                <h5 class="modal-title" id="vehicleTypeModalLabel">Add Vehicle Type</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body sc-modal-body">

                <input type="hidden" id="vehicleTypeId" value="">

                <div class="sc-field">
                    <label class="form-label" for="vehicleTypeName">Vehicle Name</label>
                    <input
                        type="text"
                        id="vehicleTypeName"
                        class="form-control sc-input"
                        maxlength="100"
                        placeholder="e.g. City Bus"
                        autocomplete="off"
                    >
                    <div class="sc-field-error" id="vehicleTypeNameError" hidden></div>
                </div>

                <div class="sc-field-row">

                    <div class="sc-field">
                        <label class="form-label" for="vehicleTypeSeats">Seats</label>
                        <input
                            type="number"
                            id="vehicleTypeSeats"
                            class="form-control sc-input"
                            min="1"
                            max="999"
                            step="1"
                            inputmode="numeric"
                            placeholder="45"
                        >
                        <div class="sc-field-error" id="vehicleTypeSeatsError" hidden></div>
                    </div>

                    <div class="sc-field">
                        <label class="form-label" for="vehicleTypeStanding">Standing Capacity</label>
                        <input
                            type="number"
                            id="vehicleTypeStanding"
                            class="form-control sc-input"
                            min="0"
                            max="999"
                            step="1"
                            inputmode="numeric"
                            placeholder="10"
                        >
                        <div class="sc-field-error" id="vehicleTypeStandingError" hidden></div>
                    </div>

                </div>

            </div>

            <div class="modal-footer sc-modal-footer">
                <button type="button" class="btn sc-btn sc-btn-light" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" id="saveVehicleTypeBtn" class="btn sc-btn sc-btn-primary">
                    <i class="bi bi-floppy" aria-hidden="true"></i>
                    <span id="saveVehicleTypeLabel">Save Vehicle</span>
                </button>
            </div>

        </div>

    </div>

</div>


<div class="modal fade" id="fuelTypeModal" tabindex="-1" aria-labelledby="fuelTypeModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content sc-modal">

            <div class="modal-header sc-modal-header">
                <h5 class="modal-title" id="fuelTypeModalLabel">Add Fuel Type</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body sc-modal-body">

                <div class="sc-field">
                    <label class="form-label" for="fuelTypeName">Fuel Type</label>
                    <input
                        type="text"
                        id="fuelTypeName"
                        class="form-control sc-input"
                        maxlength="50"
                        placeholder="e.g. Diesel"
                        autocomplete="off"
                    >
                    <div class="sc-field-error" id="fuelTypeNameError" hidden></div>
                </div>

                <div class="sc-field">
                    <label class="form-label" for="fuelTypePrice">Price per Liter</label>
                    <div class="sc-price-input sc-price-input-block">
                        <span class="sc-peso">&#8369;</span>
                        <input
                            type="number"
                            id="fuelTypePrice"
                            class="form-control sc-input sc-fuel-price"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            placeholder="0.00"
                        >
                    </div>
                    <div class="sc-field-error" id="fuelTypePriceError" hidden></div>
                </div>

            </div>

            <div class="modal-footer sc-modal-footer">
                <button type="button" class="btn sc-btn sc-btn-light" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" id="saveFuelTypeBtn" class="btn sc-btn sc-btn-primary">
                    <i class="bi bi-floppy" aria-hidden="true"></i>
                    <span id="saveFuelTypeLabel">Save Fuel Type</span>
                </button>
            </div>

        </div>

    </div>

</div>

<script src="/admin/assets/js/system-configuration.js?v=<?= time() ?>"></script>
