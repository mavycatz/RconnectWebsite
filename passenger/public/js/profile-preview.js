/*
 * Profile photo preview for the passenger profile edit modal.
 *
 * The edit modal is injected dynamically, so the handler is delegated on the
 * document: selecting a file in #profileInput immediately shows it in
 * #profilePreview before the form is saved. The previous object URL is revoked
 * when a new selection replaces it.
 */
(function () {
    'use strict';

    var objectUrl = null;

    function preview(input, previewImage) {
        if (!input || !previewImage || !input.files || !input.files[0]) {
            return;
        }

        if (objectUrl) {
            try {
                URL.revokeObjectURL(objectUrl);
            } catch (e) {}
            objectUrl = null;
        }

        objectUrl = URL.createObjectURL(input.files[0]);
        previewImage.src = objectUrl;
    }

    document.addEventListener('change', function (event) {
        var input = event.target;
        if (input && input.id === 'profileInput') {
            preview(input, document.getElementById('profilePreview'));
        }
    });

    window.PassengerProfilePreview = { preview: preview };
})();
