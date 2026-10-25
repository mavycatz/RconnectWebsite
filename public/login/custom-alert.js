function showAlert(type, title, message, onConfirm, options)
{
  var overlay = document.getElementById('customAlertOverlay');
  if (!overlay) return;

  var header = overlay.querySelector('.custom-alert-header');
  var iconEl = overlay.querySelector('.custom-alert-icon');
  var titleEl = overlay.querySelector('.custom-alert-title');
  var msgEl = overlay.querySelector('.custom-alert-message');
  var btn = overlay.querySelector('.custom-alert-btn');
  var btnCancel = overlay.querySelector('.custom-alert-btn-cancel');
  var actions = overlay.querySelector('.custom-alert-actions');

  header.className = 'custom-alert-header ' + type;

  if (type === 'success') {
    iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#2e4836" stroke-width="3"/><path d="M16 24l6 6 10-10" stroke="#2e4836" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    if (btnCancel) btnCancel.style.display = 'none';
    if (actions) actions.style.display = '';
    btn.className = 'custom-alert-btn success';
    btn.textContent = 'Continue';
  } else {
    iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#fff" stroke-width="3"/><path d="M18 18l12 12M30 18l-12 12" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>';
    if (btnCancel) btnCancel.style.display = 'none';
    if (actions) actions.style.display = '';
    btn.className = 'custom-alert-btn error';
    btn.textContent = 'Close';
  }

  titleEl.textContent = title;
  if (options && options.html === true && message != null) {
    msgEl.innerHTML = message;
  } else {
    msgEl.textContent = message;
  }

  btn.onclick = function () {
    overlay.classList.remove('active');
    if (typeof onConfirm === 'function') onConfirm();
  };

  overlay.classList.add('active');
}

function showConfirm(title, message, onConfirm, onCancel, options)
{
  var overlay = document.getElementById('customAlertOverlay');
  if (!overlay) return;

  var header = overlay.querySelector('.custom-alert-header');
  var iconEl = overlay.querySelector('.custom-alert-icon');
  var titleEl = overlay.querySelector('.custom-alert-title');
  var msgEl = overlay.querySelector('.custom-alert-message');
  var btn = overlay.querySelector('.custom-alert-btn');
  var btnCancel = overlay.querySelector('.custom-alert-btn-cancel');
  var actions = overlay.querySelector('.custom-alert-actions');

  header.className = 'custom-alert-header warning';
  iconEl.innerHTML = '<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#92400e" stroke-width="3"/><path d="M24 16v10M24 30v2" stroke="#92400e" stroke-width="3" stroke-linecap="round"/></svg>';

  if (actions) actions.style.display = 'flex';
  if (btnCancel) btnCancel.style.display = 'block';

  titleEl.textContent = title;
  if (options && options.html === true && message != null) {
    msgEl.innerHTML = message;
  } else {
    msgEl.textContent = message;
  }

  btn.className = 'custom-alert-btn confirm';
  btn.textContent = 'Confirm';
  btn.onclick = function () {
    overlay.classList.remove('active');
    if (typeof onConfirm === 'function') onConfirm();
  };

  if (btnCancel) {
    btnCancel.className = 'custom-alert-btn cancel';
    btnCancel.textContent = 'Cancel';
    btnCancel.onclick = function () {
      overlay.classList.remove('active');
      if (typeof onCancel === 'function') onCancel();
    };
  }

  overlay.classList.add('active');
}
