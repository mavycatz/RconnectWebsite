(function () {
    'use strict';
    const active = new Set();
    document.addEventListener('show.bs.modal', function (event) {
        if (!event.target.classList.contains('fin-report-modal')) return;
        active.add(event.target);
        document.body.classList.add('fin-reports-modal-open');
    });
    document.addEventListener('hidden.bs.modal', function (event) {
        if (!event.target.classList.contains('fin-report-modal')) return;
        active.delete(event.target);
        if (!active.size) document.body.classList.remove('fin-reports-modal-open');
    });
})();
