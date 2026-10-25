<div class="modal fade" id="editBusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Bus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editBusForm">
                    <input type="hidden" name="bus_id_hidden" id="bus_id_hidden">

                    <input type="text" name="bus_id" id="bus_id" placeholder="Bus ID" class="form-control mb-2" required>
                    <input type="text" name="plate_number" id="plate_number" placeholder="Plate Number" class="form-control mb-2" required>
                    <input type="text" name="bus_type" id="bus_type" placeholder="Bus Type" class="form-control mb-2">
                    <input type="number" name="seats" id="seats" placeholder="Seats" class="form-control mb-2">
                    <input type="text" name="driver" id="driver" placeholder="Driver" class="form-control mb-2">
                    <input type="text" name="route_name" id="route_name" placeholder="Route Name" class="form-control mb-2">
                    <input type="text" name="terminal" id="terminal" placeholder="Terminal" class="form-control mb-2">
                    <input type="text" name="schedule" id="schedule" placeholder="Schedule" class="form-control mb-2">
                    <input type="text" name="status" id="status" placeholder="Status" class="form-control mb-2">

                    <button type="submit" class="btn btn-primary">Update Bus</button>
                </form>
            </div>
        </div>
    </div>
</div>
