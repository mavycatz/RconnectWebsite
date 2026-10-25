<script>
window.isSubmitting = false;
</script>

<div class="modal fade" id="dataPrivacyModal" tabindex="-1" aria-labelledby="dataPrivacyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:100%;">
    <div class="modal-content" style="border-radius:15px; background:#fef8f3; border:2px solid #c6d48c; box-shadow:0 8px 20px rgba(0,0,0,0.3);">

      <div class="modal-header" style="border-bottom:none; background:linear-gradient(90deg,#306449,#b3be5b); color:#fff; border-top-left-radius:15px; border-top-right-radius:15px;">
        <h5 class="modal-title" id="dataPrivacyModalLabel">Data Privacy Notice and Passenger Declaration</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body" style="padding:25px; font-family:'Poppins', sans-serif; font-size:14px; color:#306449; text-align:justify; line-height:1.5; max-height:70vh; overflow-y:auto;">
        <p>Ang lahat ng impormasyong inilagay ng pasahero tulad ng Seat Number, Pangalan, Boarding Point, Destination, Uri ng Pasahero, at Paraan ng Pagbabayad ay kusang-loob na ibinigay at pinatutunayan na tama, totoo, at hindi peke.</p>
        <p>Ang mga datos na ito ay kokolektahin at gagamitin lamang para sa layunin ng biyahe, talaan ng pasahero, at tamang paniningil ng pamasahe.</p>
        <p>Ang pangongolekta at pagproseso ng impormasyon ay alinsunod sa Republic Act No. 10173 (Data Privacy Act of 2012) ng Pilipinas.</p>
      </div>

      <div class="modal-footer" style="border-top:none; justify-content:center; gap:15px; flex-wrap:wrap;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
          style="background:#b3be5b; border:none; color:#fff; border-radius:8px; padding:10px 25px;">
          Cancel
        </button>
        <button type="button" class="btn btn-primary" id="modalAgree"
          style="background:#4b794d; border:none; color:#fff; border-radius:8px; padding:10px 25px;">
          I Agree
        </button>
      </div>

    </div>
  </div>
</div>

<script type="module">
let isSubmitting = false; 

document.addEventListener('DOMContentLoaded', () => {

    const form = document.querySelector('form');
    const confirmBtn = document.getElementById('confirmSubmit');
    const modalAgree = document.getElementById('modalAgree');
    const modalEl = document.getElementById('dataPrivacyModal');

    if (!form || !confirmBtn || !modalAgree || !modalEl) return;

    const modal = new bootstrap.Modal(modalEl, {
        backdrop: 'static',
        keyboard: false
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style = '';
    });

    const boardingInput = document.getElementById('boarding-point');
    const destinationInput = document.getElementById('destination-point');

    function showAlert(icon, title, text = '') {
        if (typeof window.showAlert === 'function') {
            window.showAlert(icon, title, text);
        } else {
            alert(title + ': ' + text);
        }
    }

    function validateRoute(input) {
        if (!input.value.trim()) return false;
        return true;
    }

    confirmBtn.addEventListener('click', (e) => {
        e.preventDefault();

        if (!validateRoute(boardingInput) || !validateRoute(destinationInput)) {
            showAlert('warning', 'Invalid Route', 'Check your route.');
            return;
        }

        modal.show();
    });

    modalAgree.addEventListener('click', async (e) => {

        e.preventDefault();
        e.stopImmediatePropagation();

        if (isSubmitting) return;

        isSubmitting = true;

        modalAgree.disabled = true;
        confirmBtn.disabled = true;

        try {

            const formData = new FormData(form);

            const res = await fetch('/passenger/controllers/PassengerController.php', {
                method: 'POST',
                body: formData
            });

            const text = await res.text();
            console.log("RAW:", text);

            let data;

            try {
                data = JSON.parse(text);
            } catch {
                throw new Error("Invalid JSON");
            }

            if (data.success) {

                showAlert('success', 'Saved', 'Passenger added.');

                setTimeout(() => {
                    window.location.href =
                        `/passenger/views/payment.php?id=${data.passenger_id}&bus_id=${data.bus_id}&seat_number=${data.seat_number}`;
                }, 1200);

            } else {

                var msg = (data && data.message) ? data.message : 'Failed to save passenger.';
                if (window.PassengerSession && typeof window.PassengerSession.sanitizeErrorMessage === 'function') {
                    msg = window.PassengerSession.sanitizeErrorMessage(msg, 'Failed to save passenger.');
                }
                showAlert('error', 'Error', msg);

                isSubmitting = false;
                modalAgree.disabled = false;
                confirmBtn.disabled = false;
            }

        } catch (err) {

            console.error(err);

            showAlert('error', 'Server Error', 'Try again.');

            isSubmitting = false;
            modalAgree.disabled = false;
            confirmBtn.disabled = false;
        }

        modal.hide();
    });

});
</script>
