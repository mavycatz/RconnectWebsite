<h5 class="mb-3">Route Assignment</h5>

<div class="table-responsive">
    <table class="table table-striped table-bordered" style="border-color:#d4e6a5;">

        <thead style="background:#e6f0c4; color:#2f4f2f;">
            <tr>
                <th>Bus ID</th>
                <th>Route Name</th>
                <th>Terminal (From-To)</th>
                <th>Driver</th>
                <th>Schedule</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody style="background:#f9fbe7;">

        <?php foreach ($buses as $bus): ?>
        <tr>
            <td><?= $bus['bus_id'] ?></td>
<td><?= $bus['route_name'] ?></td>
<td><?= $bus['terminal'] ?></td>
<td><?= $bus['driver'] ?></td>
<td><?= $bus['schedule'] ?></td>
<td><?= $bus['status'] ?></td>
</tr>
        <?php endforeach; ?>

    </tbody>

    </table>
    </div>