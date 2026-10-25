document.addEventListener('DOMContentLoaded', function () {

    // --- Static Charts ---
    new Chart(document.getElementById('revenueChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['Jan 20','Jan 21','Jan 22','Jan 23'],
            datasets: [
                { label: 'Revenue', data: [5000,7000,6000,8000], backgroundColor: 'rgba(40,167,69,0.7)' },
                { label: 'Profit', data: [3000,4000,3500,4500], backgroundColor: 'rgba(0,123,255,0.7)' },
                { label: 'Expenses', data: [2000,3000,2500,3500], backgroundColor: 'rgba(220,53,69,0.7)' }
            ]
        },
        options: { responsive:true, maintainAspectRatio:false }
    });

    new Chart(document.getElementById('dailyTripChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: ['Jan 20','Jan 21','Jan 22','Jan 23'],
            datasets: [{
                label: 'Trips',
                data: [20,25,18,30],
                borderColor: 'rgba(0,123,255,1)',
                backgroundColor: 'rgba(0,123,255,0.2)',
                fill:true,
                tension:0.3
            }]
        },
        options: { responsive:true, maintainAspectRatio:false }
    });

    new Chart(document.getElementById('passengerChart').getContext('2d'), {
        type: 'pie',
        data: {
            labels: ['Station A','Station B','Station C','Station D'],
            datasets:[{
                data: [300,250,400,330],
                backgroundColor: [
                    'rgba(255,99,132,0.7)',
                    'rgba(54,162,235,0.7)',
                    'rgba(255,206,86,0.7)',
                    'rgba(75,192,192,0.7)'
                ],
                borderColor:'#fff',
                borderWidth:1
            }]
        },
        options:{ responsive:true, maintainAspectRatio:false }
    });

    // --- Driver Performance Chart ---
    const driverSelect = document.getElementById('driverSelect');
    const fromMonth = document.getElementById('fromMonth');
    const fromYear = document.getElementById('fromYear');
    const toMonth = document.getElementById('toMonth');
    const toYear = document.getElementById('toYear');
    const btnUpdate = document.getElementById('btnUpdate');
    const btnClear = document.getElementById('btnClear');
    const canvas = document.getElementById('driverPerformanceChart');

    if (!canvas) return; // exit if canvas missing

    // Set proper canvas height
    canvas.style.height = '400px';
    canvas.height = 400;

    const chart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: [],
            datasets: [
                { label: 'Trips', data: [], backgroundColor: 'rgba(0,123,255,0.7)' },
                { label: 'Revenue (₱)', data: [], backgroundColor: 'rgba(40,167,69,0.7)' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { title: { display: true, text: 'Date' } },
                y: { beginAtZero: true, title: { display: true, text: 'Trips / Revenue' } }
            },
            plugins: {
                legend: { position: 'top' },
                tooltip: { mode: 'index', intersect: false }
            }
        }
    });

    // --- Helpers ---
    function getDateRange() {
        let start = new Date('2000-01-01');
        let end = new Date('2100-12-31');

        if (fromYear.value) {
            const month = fromMonth.value ? fromMonth.value - 1 : 0;
            start = new Date(fromYear.value, month, 1);
        }

        if (toYear.value) {
            const month = toMonth.value ? toMonth.value - 1 : 11;
            end = new Date(toYear.value, month + 1, 0);
        }

        return { start, end };
    }

    function formatDate(dateStr) {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateStr).toLocaleDateString('en-US', options);
    }

    function getFilteredData() {
        const { start, end } = getDateRange();
        const drivers = driverSelect.value ? [driverSelect.value] : Object.keys(driverStats);
        let data = [];

        drivers.forEach(driver => {
            if (!driverStats[driver]) return;
            driverStats[driver].forEach(d => {
                const dt = new Date(d.date);
                if (dt >= start && dt <= end) data.push({ date: d.date, trips: d.trips, revenue: d.revenue });
            });
        });

        // Merge by date
        const map = {};
        data.forEach(d => {
            if (!map[d.date]) map[d.date] = { date: d.date, trips: 0, revenue: 0 };
            map[d.date].trips += d.trips;
            map[d.date].revenue += d.revenue;
        });

        return Object.values(map).sort((a,b) => new Date(a.date) - new Date(b.date));
    }

    function updateChart() {
        const data = getFilteredData();
        chart.data.labels = data.map(d => formatDate(d.date));
        chart.data.datasets[0].data = data.map(d => d.trips);
        chart.data.datasets[1].data = data.map(d => d.revenue);
        chart.update();
    }

    // --- Event Listeners ---
    btnUpdate.addEventListener('click', updateChart);
    btnClear.addEventListener('click', () => {
        driverSelect.value = '';
        fromMonth.value = '';
        fromYear.value = '';
        toMonth.value = '';
        toYear.value = '';
        updateChart();
    });

    [driverSelect, fromMonth, fromYear, toMonth, toYear].forEach(el => {
        el.addEventListener('change', updateChart);
    });

    // Initial render
    updateChart();

});
