console.log('🔥 passengerlog.js LOADED');

let routeOrder = [];

async function loadRouteOrder()
{
    try
    {
        const res =
            await fetch(
                '/passenger/index.php?route=api/destinations'
            );

        const data =
            await res.json();

        if (
            data.success &&
            Array.isArray(
                data.destinations
            )
        )
        {
            routeOrder =
                data.destinations.map(
                    d => d.destination
                );
        }
    }
    catch (error)
    {
        console.error(
            'Failed to load route order',
            error
        );
    }
}

import fareRules from '/fare-engine/utils/calculateFare.js';
import calculateFare from '/fare-engine/utils/calculateFare.js';

function normalize(str) {
    if (!str || typeof str !== 'string') return '';
    return str.trim().toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

function similarity(a, b) {
    a = normalize(a);
    b = normalize(b);
    if (!a || !b) return 0;
    let matches = 0;
    const bArr = b.split('');
    a.split('').forEach(char => {
        const idx = bArr.indexOf(char);
        if (idx !== -1) {
            matches++;
            bArr.splice(idx, 1);
        }
    });
    return matches / Math.max(a.length, b.length);
}

function smartMatch(value, options) {
    if (!value || !options.length) return null;
    const normValue = normalize(value);
    let exact = options.find(opt => normalize(opt) === normValue);
    if (exact) return exact;
    let best = null;
    let bestScore = 0;
    options.forEach(opt => {
        const score = similarity(normValue, opt);
        if (score > bestScore) {
            bestScore = score;
            best = opt;
        }
    });
    return bestScore >= 0.4 ? best : null;
}

function initForm() {

    const form = document.querySelector('form');
    const confirmBtn = document.getElementById('confirmSubmit');
    const modalAgree = document.getElementById('modalAgree');
    const dataPrivacyModalEl = document.getElementById('dataPrivacyModal');
    if (!form || !confirmBtn || !modalAgree || !dataPrivacyModalEl) return;

    const dataPrivacyModal = new bootstrap.Modal(dataPrivacyModalEl, { backdrop: 'static', keyboard: false });

    const boardingInput = document.getElementById('boarding-point');
    const destinationInput = document.getElementById('destination-point');
    const boardingList = document.getElementById('boarding-list');
    const destinationList = document.getElementById('destination-list');
    const fareContainer = document.getElementById('fare-container');
    const calculatedFare = document.getElementById('calculated-fare');
    const passengerTypeRadios = document.querySelectorAll('input[name="passenger_type"]');
    const studentContainer = document.getElementById('student-number-container');
    const licenseContainer = document.getElementById('license-container');

    let hiddenFareInput = document.getElementById('fare-hidden');
    if (!hiddenFareInput) {
        hiddenFareInput = document.createElement('input');
        hiddenFareInput.type = 'hidden';
        hiddenFareInput.name = 'fare';
        hiddenFareInput.id = 'fare-hidden';
        form.appendChild(hiddenFareInput);
    }

    routeOrder.forEach(point => {
        const elFrom = document.createElement('option');
        elFrom.value = point;
        boardingList.appendChild(elFrom);

        const elTo = document.createElement('option');
        elTo.value = point;
        destinationList.appendChild(elTo);
    });

    function updateFare() {
        fareContainer.style.display = 'block';
        const from = smartMatch(boardingInput.value, routeOrder);
        const to = smartMatch(destinationInput.value, routeOrder);
        const typeRadio = document.querySelector('input[name="passenger_type"]:checked');
        const type = typeRadio ? typeRadio.value.toLowerCase() : 'regular';

        if (!from || !to) {
            calculatedFare.value = '₱0.00';
            hiddenFareInput.value = '0';
            return;
        }

        const fare = calculateFare(from, to, type, fareRules) || 0;
        calculatedFare.value = `₱${fare.toFixed(2)}`;
        hiddenFareInput.value = fare.toFixed(2);
    }

    function toggleFields() {
        const typeRadio = document.querySelector('input[name="passenger_type"]:checked');
        const type = typeRadio ? typeRadio.value.toLowerCase() : '';

        studentContainer.style.display = 'none';
        licenseContainer.style.display = 'none';

        document.getElementById('student-number').value = '';
        document.getElementById('license-number').value = '';

        if (type === 'student') {
            studentContainer.style.display = 'block';
        } else if (type === 'teacher' || type === 'nurse') {
            licenseContainer.style.display = 'block';
        }
    }

    [boardingInput, destinationInput].forEach(input =>
        input.addEventListener('input', updateFare)
    );

    passengerTypeRadios.forEach(radio =>
        radio.addEventListener('change', () => {
            toggleFields();
            updateFare();
        })
    );

    toggleFields();
    updateFare();

const paymentInputs = document.querySelectorAll('input[name="payment_mode"]');
const hybridInputs = document.getElementById('hybrid-inputs');
const hybridCash = document.querySelector('input[name="hybrid_cash"]');
const hybridGcash = document.querySelector('input[name="hybrid_gcash"]');

function updatePaymentUI() {

    const selected = document.querySelector('input[name="payment_mode"]:checked');

    paymentInputs.forEach(input => {
        const label = document.querySelector(`label[for="${input.id}"]`);
        if (!label) return;

        if (input.checked) {
            label.style.borderColor = '#4b794d';
            label.style.backgroundColor = '#4b794d';
            label.style.color = '#fff';
        } else {
            label.style.borderColor = input.value === 'Hybrid' ? '#b3be5b' : '#ccc';
            label.style.backgroundColor = input.value === 'Hybrid' ? '#fff4d3' : 'transparent';
            label.style.color = '#000';
        }
    });

    if (selected && selected.value === 'Hybrid') {
        if (hybridInputs) hybridInputs.style.display = 'flex';
    } else {
        if (hybridInputs) hybridInputs.style.display = 'none';
        if (hybridCash) hybridCash.value = '';
        if (hybridGcash) hybridGcash.value = '';
    }
}

paymentInputs.forEach(input => {
    input.addEventListener('change', updatePaymentUI);
});

updatePaymentUI();

    function validateRoute(input) {
        const match = smartMatch(input.value, routeOrder);
        if (!match) {
            input.style.borderColor = 'red';
            input.style.backgroundColor = '#ffe6e6';
            return false;
        } else {
            input.style.borderColor = '';
            input.style.backgroundColor = '';
            input.value = match;
            return true;
        }
    }

    confirmBtn.addEventListener('click', (e) => {
        e.preventDefault();

        const boardingValid = validateRoute(boardingInput);
        const destinationValid = validateRoute(destinationInput);

        if (!boardingValid || !destinationValid) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid route',
                text: 'Please select valid boarding and destination points from the list.',
                timer: 5000,
                showConfirmButton: true,
                position: 'center'
            });
            return;
        }

        dataPrivacyModal.show();
    });

    modalAgree.addEventListener('click', async () => {

        const boardingValid = validateRoute(boardingInput);
        const destinationValid = validateRoute(destinationInput);
        if (!boardingValid || !destinationValid) return;

        const paymentRadio = document.querySelector('input[name="payment_mode"]:checked');
        if (!paymentRadio) return;

        if (paymentRadio.value === 'Hybrid') {
            const cash = parseFloat(hybridCash.value) || 0;
            const gcash = parseFloat(hybridGcash.value) || 0;
            const total = parseFloat(hiddenFareInput.value) || 0;

            if ((cash + gcash).toFixed(2) !== total.toFixed(2)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Hybrid payment mismatch',
                    text: 'Cash + GCash must equal total fare.',
                    timer: 5000,
                    showConfirmButton: true,
                    position: 'center'
                });
                return;
            }
        }

        const requiredFields = form.querySelectorAll('[required]');
        for (let field of requiredFields) {
            if (!field.value || (field.type === 'checkbox' && !field.checked)) {
                field.focus();
                Swal.fire({
                    icon: 'warning',
                    title: 'Please fill out all required fields.',
                    timer: 5000,
                    showConfirmButton: true,
                    position: 'center'
                });
                return;
            }
        }

        const formData = new FormData(form);
        formData.set('bus_id', form.querySelector('input[name="bus_id"]').value);
        formData.set('seat_number', form.querySelector('input[name="seat_number"]').value);
        formData.set('fare', hiddenFareInput.value || '0');
        formData.set('passenger_type', form.querySelector('input[name="passenger_type"]:checked').value);
        formData.set('payment_mode', paymentRadio.value);
        formData.set('agree', 'on');

        try {
            const res = await fetch('/passenger/controllers/PassengerController.php', {
                method: 'POST',
                body: formData
            });

            const data = await res.json().catch(() => null);

            if (!res.ok || !data?.success || !data?.token) {
                Swal.fire({
                    icon: 'error',
                    title: 'Unable to Proceed',
                    text: data?.message || 'Something went wrong. Please try again.',
                    timer: 5000,
                    showConfirmButton: true,
                    position: 'center'
                });
                return;
            }

            Swal.fire({
                icon: 'success',
                title: 'Passenger Saved',
                text: 'Seat has been successfully assigned.',
                timer: 1500,
                showConfirmButton: false,
                position: 'center'
            });

            setTimeout(() => {
                window.location.href = `/passenger/views/payment.php?token=${data.token}`;
            }, 1500);

        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Server error. Please try again.',
                timer: 5000,
                showConfirmButton: true,
                position: 'center'
            });
        }
    });

    if (window.seatTaken) {
        Swal.fire({
            icon: 'error',
            title: 'This seat is already taken. Please choose a different seat.',
            timer: 5000,
            showConfirmButton: true,
            position: 'center'
        });
    }
}
const passengerRadios = document.querySelectorAll("input[name='passenger_type']");
const studentContainer = document.getElementById("student-number-container");
const licenseContainer = document.getElementById("license-container");
const imageContainer = document.getElementById("id-image-container");

passengerRadios.forEach(radio => {
    radio.addEventListener("change", () => {
        const value = radio.value;

        studentContainer.style.display = "none";
        licenseContainer.style.display = "none";
        imageContainer.style.display = "none";

        if (value === "Student") {
            studentContainer.style.display = "block";
            imageContainer.style.display = "block";
        } else if (value === "Teacher" || value === "Nurse" || value === "Senior") {
            licenseContainer.style.display = "block";
            imageContainer.style.display = "block";
        }
    });
});

document.addEventListener('DOMContentLoaded', async () => {
    await loadRouteOrder();
    initForm();
});