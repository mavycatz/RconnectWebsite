document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('toggleFilter');
    const filterPanel = document.getElementById('stickyFilterPanel');

    toggleBtn.addEventListener('click', () => {
        filterPanel.style.display = filterPanel.style.display === 'none' ? 'block' : 'none';
    });

    document.getElementById('clearFilterSticky').addEventListener('click', () => {
        document.getElementById('busFilterFormSticky').reset();
    });
});