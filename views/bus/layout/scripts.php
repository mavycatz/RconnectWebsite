<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    async function loadBusStats() {
        try {
            const res = await fetch('/admin/api/stats_bus.php');
            const result = await res.json();

            if (!result.success) {
                console.error(result.message || 'Failed to load bus statistics.');
                return;
            }

            const data = result.data;

            document.getElementById('totalBuses').textContent = data.total ?? 0;
            document.getElementById('withQR').textContent = data.with_qr ?? 0;
            document.getElementById('withoutQR').textContent = data.without_qr ?? 0;
            document.getElementById('pendingQR').textContent = data.pending_qr ?? 0;

        } catch (err) {
            console.error('Error fetching bus stats:', err);
        }
    }

    document.addEventListener('DOMContentLoaded', loadBusStats);
</script>