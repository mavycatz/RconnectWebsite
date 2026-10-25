<script>
function showAlert(type,title,message,onConfirm){
  var overlay=document.getElementById('customAlertOverlay');
  if(!overlay){
    alert(title+': '+message);
    if(typeof onConfirm==='function') onConfirm();
    return;
  }
  var header=overlay.querySelector('.custom-alert-header');
  var iconEl=overlay.querySelector('.custom-alert-icon');
  var titleEl=overlay.querySelector('.custom-alert-title');
  var msgEl=overlay.querySelector('.custom-alert-message');
  var btn=overlay.querySelector('.custom-alert-btn');
  var btnCancel=overlay.querySelector('.custom-alert-btn-cancel');
  var actions=overlay.querySelector('.custom-alert-actions');
  header.className='custom-alert-header '+type;
  if(type==='success'){
    iconEl.innerHTML='<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#2e4836" stroke-width="3"/><path d="M16 24l6 6 10-10" stroke="#2e4836" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    if(btnCancel)btnCancel.style.display='none';
    if(actions)actions.style.display='';
    btn.className='custom-alert-btn success';
    btn.textContent='Continue'
  }else{
    iconEl.innerHTML='<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#fff" stroke-width="3"/><path d="M18 18l12 12M30 18l-12 12" stroke="#fff" stroke-width="3" stroke-linecap="round"/></svg>';
    if(btnCancel)btnCancel.style.display='none';
    if(actions)actions.style.display='';
    btn.className='custom-alert-btn error';
    btn.textContent='Close'
  }
  titleEl.textContent=title;
  msgEl.textContent=message;
  btn.onclick=function(){overlay.classList.remove('active');if(typeof onConfirm==='function')onConfirm()};
  overlay.classList.add('active')
}
function showConfirm(title,message,onConfirm,onCancel){
  var overlay=document.getElementById('customAlertOverlay');
  if(!overlay)return;
  var header=overlay.querySelector('.custom-alert-header');
  var iconEl=overlay.querySelector('.custom-alert-icon');
  var titleEl=overlay.querySelector('.custom-alert-title');
  var msgEl=overlay.querySelector('.custom-alert-message');
  var btn=overlay.querySelector('.custom-alert-btn');
  var btnCancel=overlay.querySelector('.custom-alert-btn-cancel');
  var actions=overlay.querySelector('.custom-alert-actions');
  header.className='custom-alert-header warning';
  iconEl.innerHTML='<svg viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="22" stroke="#92400e" stroke-width="3"/><path d="M24 16v10M24 30v2" stroke="#92400e" stroke-width="3" stroke-linecap="round"/></svg>';
  if(actions)actions.style.display='flex';
  if(btnCancel)btnCancel.style.display='block';
  titleEl.textContent=title;
  msgEl.textContent=message;
  btn.className='custom-alert-btn confirm';
  btn.textContent='Confirm';
  btn.onclick=function(){overlay.classList.remove('active');if(typeof onConfirm==='function')onConfirm()};
  if(btnCancel){
    btnCancel.className='custom-alert-btn cancel';
    btnCancel.textContent='Cancel';
    btnCancel.onclick=function(){overlay.classList.remove('active');if(typeof onCancel==='function')onCancel()}
  }
  overlay.classList.add('active')
}

function nextStep(){
  var nameEl=document.getElementById("full_name");
  var full_name=(nameEl?nameEl.value:'').trim().replace(/\s+/g,' ');
  if(nameEl)nameEl.value=full_name;

  var emailEl=document.getElementById("email");
  var email=(emailEl?emailEl.value:'').trim();
  if(emailEl)emailEl.value=email;

  var phoneEl=document.getElementById("phone");
  var phone=(phoneEl?phoneEl.value:'').trim().replace(/[\s\-()]/g,'');
  if(phoneEl)phoneEl.value=phone;

  var dob=(document.getElementById("date_of_birth")?document.getElementById("date_of_birth").value:'').trim();
  var passEl=document.getElementById("pass");
  var password=passEl?passEl.value:'';
  var confirmEl=document.getElementById("confirm");
  var confirm=confirmEl?confirmEl.value:'';

  // 1. Full Name
  if(full_name===''){
    showAlert('warning','Full Name Required','Please enter your full name.');
    if(nameEl)nameEl.focus();
    return;
  }
  if(full_name.length<2){
    showAlert('error','Invalid Full Name','Full name must be at least 2 characters.');
    if(nameEl)nameEl.focus();
    return;
  }
  if(full_name.length>100){
    showAlert('error','Invalid Full Name','Full name cannot exceed 100 characters.');
    if(nameEl)nameEl.focus();
    return;
  }
  if(/\d/.test(full_name)){
    showAlert('error','Invalid Full Name','Full name must not contain numbers.');
    if(nameEl)nameEl.focus();
    return;
  }
  var nameRegex=/^[\p{L}\p{M}][\p{L}\p{M} .\-'’]{0,98}[\p{L}\p{M}.]$/u;
  if(!nameRegex.test(full_name)){
    showAlert('error','Invalid Full Name','Please enter a valid full name. Names can only contain letters, spaces, periods, hyphens, and apostrophes.');
    if(nameEl)nameEl.focus();
    return;
  }

  var nameWords=full_name.split(/\s+/).filter(Boolean);
  if(nameWords.length<2){
    showAlert('error','Full Name Incomplete','Please enter your full name (both first and last name).');
    if(nameEl)nameEl.focus();
    return;
  }

  var hasLongWord=nameWords.some(function(w){return w.length>=2;});
  if(!hasLongWord){
    showAlert('error','Invalid Full Name','Please enter a valid real name.');
    if(nameEl)nameEl.focus();
    return;
  }

  if(/([\p{L}])\1{2,}/iu.test(full_name)){
    showAlert('error','Invalid Full Name','Please enter a valid real name without repeated letters.');
    if(nameEl)nameEl.focus();
    return;
  }

  if(/[bcdfghjklmnpqrstvwxz]{5,}/i.test(full_name)){
    showAlert('error','Invalid Full Name','Please enter a valid real name.');
    if(nameEl)nameEl.focus();
    return;
  }

  var mashPattern=/(?:asdf|sdfg|dfgh|fghj|ghjk|hjkl|qwerty|werty|ertyu|rtyui|tyuio|yuiop|zxcv|xcvb|cvbn|vbnm|qazw|wsxe|edcr)/i;
  if(mashPattern.test(full_name)){
    showAlert('error','Invalid Full Name','Please enter a valid real name.');
    if(nameEl)nameEl.focus();
    return;
  }

  var dummyNames=['test','testing','dummy','sample','fake','asdf','qwerty','random','admin','unknown','none','null','na'];
  var lowerWords=nameWords.map(function(w){return w.toLowerCase().replace(/^[.\-'’]+|[.\-'’]+$/g,'');});
  var hasDummy=lowerWords.some(function(lw){return dummyNames.indexOf(lw)!==-1;});
  if(hasDummy){
    showAlert('error','Invalid Full Name','Please enter a valid real name.');
    if(nameEl)nameEl.focus();
    return;
  }

  if(lowerWords.length>=2&&lowerWords.every(function(w){return w===lowerWords[0];})){
    showAlert('error','Invalid Full Name','Please enter your full name (both first and last name).');
    if(nameEl)nameEl.focus();
    return;
  }

  var acceptedVowelless=['ma','st','sr','jr','dr','mr','ms','mrs','ng','ii','iii','iv','v','vi','vii','viii','ix','x'];
  var wordsValid=true;
  for(var i=0;i<nameWords.length;i++){
    var parts=nameWords[i].split(/[\-\'\’\.]+/);
    for(var j=0;j<parts.length;j++){
      var clean=parts[j].toLowerCase();
      if(clean.length<2)continue;
      if(acceptedVowelless.indexOf(clean)!==-1)continue;
      if(!/[aeiouyáéíóúâêîôûñ]/i.test(clean)){
        wordsValid=false;
        break;
      }
    }
    if(!wordsValid)break;
  }
  if(!wordsValid){
    showAlert('error','Invalid Full Name','Please enter a valid real name.');
    if(nameEl)nameEl.focus();
    return;
  }

  // 2. Email
  if(email===''){
    showAlert('warning','Email Required','Please enter your email address.');
    if(emailEl)emailEl.focus();
    return;
  }
  var emailPattern=/^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if(!emailPattern.test(email)||email.length>100||/[<>]/.test(email)){
    showAlert('error','Invalid Email','Please enter a valid email address.');
    if(emailEl)emailEl.focus();
    return;
  }

  // 3. Philippine Phone Number
  if(phone===''){
    showAlert('warning','Phone Number Required','Please enter your mobile number.');
    if(phoneEl)phoneEl.focus();
    return;
  }
  var phMobilePattern=/^(?:09|\+639|639)\d{9}$/;
  if(!phMobilePattern.test(phone)||/^09(\d)\1{8}$/.test(phone)){
    showAlert('error','Invalid Phone Number','Please enter a valid Philippine mobile number (e.g., 09123456789).');
    if(phoneEl)phoneEl.focus();
    return;
  }

  // 4. Date of Birth
  if(dob===''){
    showAlert('warning','Date of Birth Required','Please enter your date of birth.');
    var dobEl=document.getElementById("date_of_birth");
    if(dobEl)dobEl.focus();
    return;
  }
  if(typeof validateDateOfBirth==='function'&&!validateDateOfBirth(true)){
    showAlert('error','Invalid Date of Birth','Please enter a valid calendar date of birth (YYYY-MM-DD).');
    return;
  }

  // 5. Password
  if(!password){
    showAlert('warning','Password Required','Please enter your password.');
    if(passEl)passEl.focus();
    return;
  }
  if(password.trim().length===0){
    showAlert('error','Invalid Password','Password cannot consist solely of whitespace.');
    if(passEl)passEl.focus();
    return;
  }
  if(password.length<8){
    showAlert('error','Weak Password','Password must be at least 8 characters.');
    if(passEl)passEl.focus();
    return;
  }
  if(password.length>72){
    showAlert('error','Invalid Password','Password cannot exceed 72 characters.');
    if(passEl)passEl.focus();
    return;
  }
  if(confirm===''){
    showAlert('warning','Confirm Password','Please confirm your password.');
    if(confirmEl)confirmEl.focus();
    return;
  }
  if(password!==confirm){
    showAlert('error','Passwords Do Not Match','Please make sure both passwords match.');
    if(confirmEl)confirmEl.focus();
    return;
  }

  // Validate discount ID if not regular
  var paxTypeEl = document.getElementById("passenger_type_input");
  var paxCategory = paxTypeEl ? paxTypeEl.value : 'Regular';

  if (paxCategory !== 'Regular') {
    var idInput = document.getElementById('student_id');
    var idVal = (idInput ? idInput.value : '').trim();
    var idLabelEl = document.getElementById('idNumberLabelText');
    var idLabel = idLabelEl ? idLabelEl.textContent : 'ID Number';

    if (idVal === '') {
      showAlert('warning', 'ID Number Required', 'Please enter your ' + idLabel + ' to verify discount eligibility.');
      if (idInput) idInput.focus();
      return;
    }
    if (idVal.length < 3 || idVal.length > 50 || !/^[A-Za-z0-9][A-Za-z0-9 .\-\/]*$/.test(idVal)) {
      showAlert('error', 'Invalid ID Number', 'Please enter a valid ' + idLabel + ' (3-50 letters, numbers, hyphens, slashes, or periods).');
      if (idInput) idInput.focus();
      return;
    }
    var digitMatches = idVal.match(/\d/g);
    var digitCount = digitMatches ? digitMatches.length : 0;
    if (digitCount === 0) {
      showAlert('error', 'Invalid ID Number', idLabel + ' must contain numeric digits (e.g., 2024-12345 or OSCA-12345). Letters alone are not accepted.');
      if (idInput) idInput.focus();
      return;
    }
    if (digitCount < 3) {
      showAlert('error', 'Invalid ID Number', idLabel + ' must contain at least 3 numeric digits.');
      if (idInput) idInput.focus();
      return;
    }
    var digitsOnly = idVal.replace(/\D/g, '');
    var lettersOnly = idVal.replace(/[^A-Za-z]/g, '');
    if (/^(\d)\1{3,}$/.test(digitsOnly) || (lettersOnly === '' && ['123456','1234567','12345678','123456789','0123456','987654321'].indexOf(digitsOnly) !== -1)) {
      showAlert('error', 'Invalid ID Number', 'Please enter a valid, authentic ' + idLabel + '.');
      if (idInput) idInput.focus();
      return;
    }
    if (/(?:asdf|ghjk|qwerty|zxcv)/i.test(idVal)) {
      showAlert('error', 'Invalid ID Number', 'Please enter a valid ' + idLabel + ' without random keyboard letters.');
      if (idInput) idInput.focus();
      return;
    }

    var frontFile = document.getElementById('id_front');
    if (!frontFile || !frontFile.files || frontFile.files.length === 0) {
      showAlert('warning', 'ID Photo Required', 'Please take or upload a photo of your ID to verify discount eligibility.');
      return;
    }
  }

  // Populate Step 2 Summary Card
  var sumCat = document.getElementById('summaryCategoryText');
  var sumName = document.getElementById('summaryNameText');
  var sumContact = document.getElementById('summaryContactText');
  var sumIdTypeRow = document.getElementById('summaryIdTypeRow');
  var sumIdTypeText = document.getElementById('summaryIdTypeText');
  var sumIdNumberRow = document.getElementById('summaryIdNumberRow');
  var sumIdNumberLabel = document.getElementById('summaryIdNumberLabel');
  var sumIdNumberValue = document.getElementById('summaryIdNumberValue');

  if (sumCat) sumCat.textContent = paxCategory;
  if (sumName) sumName.textContent = full_name;
  if (sumContact) sumContact.textContent = [email, phone].filter(Boolean).join('\n');

  if (paxCategory !== 'Regular') {
    var idTypeInp = document.getElementById('id_type_input');
    var idTypeVal = idTypeInp ? idTypeInp.value : '';
    var idTypeDisplay = (typeof PASSENGER_ID_CONFIG !== 'undefined' && PASSENGER_ID_CONFIG[paxCategory] && PASSENGER_ID_CONFIG[paxCategory].idName) || idTypeVal || 'Discount ID';
    if (paxCategory === 'Senior Citizen') {
      idTypeDisplay = (idTypeVal === 'philsys-id') ? 'PhilSys / National ID' : 'Senior Citizen ID (OSCA)';
    }

    if (sumIdTypeRow) sumIdTypeRow.style.display = 'flex';
    if (sumIdTypeText) sumIdTypeText.textContent = idTypeDisplay;
    if (sumIdNumberRow) sumIdNumberRow.style.display = 'flex';
    if (sumIdNumberLabel) sumIdNumberLabel.textContent = (document.getElementById('idNumberLabelText') ? document.getElementById('idNumberLabelText').textContent : 'ID Number');
    if (sumIdNumberValue) sumIdNumberValue.textContent = (document.getElementById('student_id') ? document.getElementById('student_id').value : '');
  } else {
    if (sumIdTypeRow) sumIdTypeRow.style.display = 'none';
    if (sumIdNumberRow) sumIdNumberRow.style.display = 'none';
  }

  document.getElementById("step1").style.display="none";
  document.getElementById("step2").style.display="block";
  document.getElementById("progressLine").style.width="100%";
  document.getElementById("circle2").style.background="#4b794d";
}

function prevStep(){
  document.getElementById("step1").style.display="flex";
  document.getElementById("step2").style.display="none";
  document.getElementById("progressLine").style.width="0%";
  document.getElementById("circle2").style.background="#ccc";
}

function togglePasswordVisibility(inputId, iconId) {
  var input = document.getElementById(inputId);
  var icon = document.getElementById(iconId);
  if (!input) return;
  var isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';
  if (icon) {
    if (isPassword) {
      icon.classList.remove('bi-eye-slash');
      icon.classList.add('bi-eye');
    } else {
      icon.classList.remove('bi-eye');
      icon.classList.add('bi-eye-slash');
    }
  }
  var btn = icon ? icon.closest('button') : null;
  if (btn) {
    var label = isPassword ? 'Hide password' : 'Show password';
    btn.setAttribute('aria-label', label);
    btn.setAttribute('title', label);
  }
}

function togglePassword() {
  togglePasswordVisibility('pass', 'passEyeIcon');
}

function toggleConfirmPassword() {
  togglePasswordVisibility('confirm', 'confirmEyeIcon');
}

window.togglePasswordVisibility = togglePasswordVisibility;
window.togglePassword = togglePassword;
window.toggleConfirmPassword = toggleConfirmPassword;

function requestLocation(){
  if(navigator.geolocation){
    navigator.geolocation.getCurrentPosition(
      function(pos){
        document.getElementById("lat").value=pos.coords.latitude;
        document.getElementById("lng").value=pos.coords.longitude;
        showAlert('success','Location Captured','Your location was successfully captured.');
      },
      function(err){
        if(err.code===1){
          showAlert('error','Location Denied','Please allow location access to continue.');
        }else{
          showAlert('error','Location Error','Unable to retrieve your location. Please try again.');
        }
      }
    );
  }else{
    showAlert('error','Location Not Supported','Your browser does not support location.');
  }
}

var cameraStream=null;
var capturedImage=null;
var ocrCanvases={front:null,back:null};
var ocrQuality={front:null,back:null};
var lastOcrFields=null;
var guideOrientation='landscape';
var ID_ORIENTATIONS={
  'student-id':'portrait',
  'prc-id':'portrait',
  'passport':'portrait',
  'postal-id':'portrait',
  'senior-citizen-id':'landscape',
  'pwd-id':'landscape',
  'drivers-license':'landscape',
  'philsys-id':'landscape',
  'umid':'landscape',
  'barangay-id':'landscape',
  'coop-beneficiary-id':'landscape'
};

function startCamera(){
  var video=document.getElementById('cameraPreview');
  var noMsg=document.getElementById('noCameraMsg');
  var guide=document.getElementById('idGuideOverlay');
  var fsBtn=document.getElementById('fullscreenCameraBtn');
  if(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia){
    navigator.mediaDevices.getUserMedia({video:{facingMode:'environment',width:{ideal:1920},height:{ideal:1080}}}).then(function(stream){
      cameraStream=stream;
      video.srcObject=stream;
      video.style.display='block';
      noMsg.style.display='none';
      if(guide)guide.style.display='';
      if(fsBtn)fsBtn.style.display='inline-flex';
      document.getElementById('captureBtn').style.display='block';
    }).catch(function(){
      video.style.display='none';
      noMsg.style.display='flex';
      if(guide)guide.style.display='none';
      if(fsBtn)fsBtn.style.display='none';
      document.getElementById('captureBtn').style.display='none';
    })
  }else{
    video.style.display='none';
    noMsg.style.display='flex';
    if(guide)guide.style.display='none';
    if(fsBtn)fsBtn.style.display='none';
    document.getElementById('captureBtn').style.display='none';
  }
}

function setCameraFullscreen(on){
  var view=document.getElementById('cameraView');
  var btn=document.getElementById('fullscreenCameraBtn');
  if(!view)return;
  if(on){
    view.classList.add('id-camera-full');
    document.body.classList.add('id-camera-lock');
    if(btn)btn.innerHTML='<i class="bi bi-fullscreen-exit"></i> <span id="fullscreenCameraLabel">Exit full screen</span>';
  }else{
    view.classList.remove('id-camera-full');
    document.body.classList.remove('id-camera-lock');
    if(btn)btn.innerHTML='<i class="bi bi-arrows-fullscreen"></i> <span id="fullscreenCameraLabel">Full screen</span>';
  }
}

document.getElementById('fullscreenCameraBtn')&&document.getElementById('fullscreenCameraBtn').addEventListener('click',function(){
  var view=document.getElementById('cameraView');
  setCameraFullscreen(!(view&&view.classList.contains('id-camera-full')));
});

document.addEventListener('keydown',function(e){
  if(e.key==='Escape')setCameraFullscreen(false);
});

function stopCamera(){
  if(cameraStream){
    cameraStream.getTracks().forEach(function(t){t.stop()});
    cameraStream=null;
  }
}

document.addEventListener('DOMContentLoaded',function(){
  var video=document.getElementById('cameraPreview');
  var capBtn=document.getElementById('captureBtn');
  if(capBtn)capBtn.textContent='Capture Front';
  if(video) startCamera();
});

document.getElementById('captureBtn')&&document.getElementById('captureBtn').addEventListener('click',function(){
  var video=document.getElementById('cameraPreview');
  var canvas=document.getElementById('cameraCanvas');
  if(!video||!video.videoWidth){return}
  var isFront=!document.getElementById('id_front').files.length;

  canvas.width=video.videoWidth;
  canvas.height=video.videoHeight;
  canvas.getContext('2d').drawImage(video,0,0);

  var processed=preprocessCapture(canvas);
  var quality=analyzeCaptureQuality(processed);
  showQualityHint(quality,isFront?'front':'back');

  stopCamera();
  document.getElementById('retakeBtn').style.display='none';
  this.style.display='none';
  video.style.display='none';
  document.getElementById('previewContainer').style.display='block';
  canvas.toBlob(function(blob){
    // JPEG keeps the upload small (PNG photos are often >1MB and the OCR
    // service rejects large files). The preview uses the same blob.
    var file=new File([blob],'captured_id.jpg',{type:'image/jpeg'});
    var dt=new DataTransfer();
    dt.items.add(file);
    if(isFront){
      document.getElementById('id_front').files=dt.files;
      ocrCanvases.front=processed;
      ocrQuality.front=quality;
      var reader=new FileReader();
      reader.onload=function(e){
        document.getElementById('previewFront').src=e.target.result;
        var capBtn=document.getElementById('captureBtn');
        capBtn.textContent='Capture Back';
        capBtn.style.display='block';
        document.getElementById('retakeBtn').style.display='none';
        startCamera();
      };
      reader.readAsDataURL(blob);
      document.getElementById('scanStatus').innerHTML='Front captured. Now capture the back.';
    }else{
      document.getElementById('id_back').files=dt.files;
      ocrCanvases.back=processed;
      ocrQuality.back=quality;
      var reader2=new FileReader();
      reader2.onload=function(e){
        document.getElementById('previewBack').src=e.target.result;
        document.getElementById('scanStatus').innerHTML='Scanning ID...';
        setCameraFullscreen(false);
        scanID();
      };
      reader2.readAsDataURL(blob);
    }
  },'image/jpeg',0.85);
});

document.getElementById('retakeBtn')&&document.getElementById('retakeBtn').addEventListener('click',function(){
  this.style.display='none';
  document.getElementById('captureBtn').style.display='block';
  document.getElementById('previewContainer').style.display='none';
  document.getElementById('cameraPreview').style.display='block';
  startCamera();
});

function resetCapture(){
  setCameraFullscreen(false);
  var resetCapBtn=document.getElementById('captureBtn');
  if(resetCapBtn)resetCapBtn.textContent='Capture Front';
  document.getElementById('id_front').value='';
  document.getElementById('id_back').value='';
  document.getElementById('previewFront').src='';
  document.getElementById('previewBack').src='';
  document.getElementById('previewContainer').style.display='none';
  document.getElementById('retakeScanBtn').style.display='none';
  document.getElementById('cameraPreview').style.display='block';
  document.getElementById('captureBtn').style.display='block';
  ocrCanvases.front=null;
  ocrCanvases.back=null;
  ocrQuality.front=null;
  ocrQuality.back=null;
  lastOcrFields=null;
  var panel=document.getElementById('ocrConfirmPanel');
  if(panel)panel.style.display='none';
  var hint=document.getElementById('scanQualityHint');
  if(hint){hint.style.display='none';hint.textContent=''}
  var status=document.getElementById('scanStatus');
  if(status)status.innerHTML='Waiting for uploads...';
  startCamera();
}

async function scanID(){
  var frontFile=document.getElementById('id_front').files[0];
  var backFile=document.getElementById('id_back').files[0];
  if(!frontFile||!backFile){return}

  document.getElementById('previewContainer').style.display='block';
  var frontReader=new FileReader();
  frontReader.onload=function(e){document.getElementById('previewFront').src=e.target.result};
  frontReader.readAsDataURL(frontFile);
  var backReader=new FileReader();
  backReader.onload=function(e){document.getElementById('previewBack').src=e.target.result};
  backReader.readAsDataURL(backFile);

  var formData=new FormData();

  // Keep the upload small: large photos are slow and the OCR service rejects
  // oversized files. Compression failure falls back to the original file.
  var frontUpload=await compressIdFile(frontFile);
  var backUpload=await compressIdFile(backFile);
  // Registration must upload the exact bytes the server scanned. Previously
  // OCR received compressed photos while OTP received the original files,
  // invalidating the server scan evidence and forcing a second OCR request.
  var scannedFrontFiles = new DataTransfer();
  scannedFrontFiles.items.add(frontUpload);
  document.getElementById('id_front').files = scannedFrontFiles.files;
  var scannedBackFiles = new DataTransfer();
  scannedBackFiles.items.add(backUpload);
  document.getElementById('id_back').files = scannedBackFiles.files;
  formData.append('id_front',frontUpload,frontUpload.name||'id_front.jpg');
  formData.append('id_back',backUpload,backUpload.name||'id_back.jpg');

  var idTypeInp = document.getElementById('id_type_input');
  var idTypeVal = idTypeInp ? idTypeInp.value : selectedIdType;
  if(idTypeVal) formData.append('id_type', idTypeVal);

  var paxTypeEl = document.getElementById('passenger_type_input');
  var paxCategory = paxTypeEl ? paxTypeEl.value : 'Regular';
  formData.append('discount', paxCategory);

  formData.append('client_quality',JSON.stringify({front:ocrQuality.front,back:ocrQuality.back}));

  var scanTimer=null;
  try{
    document.getElementById('scanStatus').innerHTML='Scanning ID...';
    // The server enforces its own ~50s budget; this is the outer safety net so
    // a stalled connection never leaves the user waiting indefinitely.
    var scanController=new AbortController();
    scanTimer=setTimeout(function(){scanController.abort()},95000);
    var response=await fetch('/passenger/register/scan-id',{method:'POST',body:formData,signal:scanController.signal});
    var text=await response.text();
    var data;
    try{
      data=JSON.parse(text);
    }catch(parseErr){
      console.error('OCR response error:',text);
      var parseError=new Error('unreadable response');
      parseError.rawResponse=String(text).slice(0,500);
      throw parseError;
    }
    try{
      handleOcrResponse(data);
    }catch(uiError){
      // The scan itself succeeded but the review panel failed to render.
      // Never report that as "scanner unavailable".
      console.error('OCR display error:',uiError);
      var statusEl=document.getElementById('scanStatus');
      if(statusEl)statusEl.innerHTML='Details were read, but the review panel could not be shown. Please enter your details manually.';
    }
  }catch(err){
    console.error('scanID error:',err);
    if(err&&err.rawResponse){console.error('OCR raw response:',err.rawResponse)}
    if(err&&err.name==='AbortError'){
      document.getElementById('scanStatus').innerHTML='The scan took too long and was stopped. Please retry with a clearer or smaller photo.';
      document.getElementById('retakeScanBtn').style.display='block';
      showAlert('error','Scan Timed Out','The ID scan took too long. Please retry with better lighting, or enter your details manually.');
      return;
    }
    document.getElementById('scanStatus').innerHTML='We could not reach the ID scanner. You can still enter your details manually.';
    document.getElementById('retakeScanBtn').style.display='block';
    showAlert('error','Scanner Unavailable','The ID scanner is unavailable right now. Please retake the photo or enter your details manually.');
  }finally{
    if(scanTimer){clearTimeout(scanTimer)}
  }
}

/**
 * Re-encode an ID photo as a small JPEG before upload. Browsers cannot resize
 * without canvas, so the original file is used whenever that is impossible.
 * A smaller image is faster to scan and stays under the OCR service's limits.
 */
function compressIdFile(file){
  return new Promise(function(resolve){
    try{
      if(!file||!file.type||file.type.indexOf('image/')!==0){resolve(file);return}
      if(file.type==='image/jpeg'&&file.size<=700000){resolve(file);return}
      var url=URL.createObjectURL(file);
      var img=new Image();
      img.onload=function(){
        try{
          var maxDim=2000;
          var w=img.naturalWidth||img.width,h=img.naturalHeight||img.height;
          if(!w||!h){URL.revokeObjectURL(url);resolve(file);return}
          var scale=Math.min(1,maxDim/Math.max(w,h));
          var cw=Math.max(1,Math.round(w*scale)),ch=Math.max(1,Math.round(h*scale));
          var c=document.createElement('canvas');c.width=cw;c.height=ch;
          var ctx=c.getContext('2d');
          ctx.fillStyle='#ffffff';ctx.fillRect(0,0,cw,ch);
          ctx.drawImage(img,0,0,cw,ch);
          c.toBlob(function(blob){
            URL.revokeObjectURL(url);
            if(blob&&blob.size>0&&blob.size<file.size){
              var name=(file.name||'id').replace(/\.[^.]+$/,'')+'.jpg';
              resolve(new File([blob],name,{type:'image/jpeg'}));
            }else resolve(file);
          },'image/jpeg',0.85);
        }catch(e){URL.revokeObjectURL(url);resolve(file)}
      };
      img.onerror=function(){URL.revokeObjectURL(url);resolve(file)};
      img.src=url;
    }catch(e){resolve(file)}
  });
}

function handleOcrResponse(data){
  if (data && data.mismatch) {
    var status = document.getElementById('scanStatus');
    if (status) {
      status.innerHTML = '<span style="color:#dc3545;font-weight:600;"><i class="bi bi-exclamation-triangle-fill"></i> ' + data.message + '</span>';
    }
    showAlert('error', 'Document Mismatch', data.message);
    var panel = document.getElementById('ocrConfirmPanel');
    if (panel) panel.style.display = 'none';
    var retakeBtn = document.getElementById('retakeScanBtn');
    if (retakeBtn) retakeBtn.style.display = 'block';
    return;
  }

  var quality=(data&&data.quality)?data.quality:{level:'none',message:'Text could not be read clearly. Please retake the photo with better lighting.'};
  lastOcrFields=data||null;

  var status=document.getElementById('scanStatus');
  if(data&&data.success){
    status.innerHTML=(data&&data.full_name)?'Details read. Please check them below.':'Some details were read — please check them below.';
  }else{
    status.innerHTML=(data&&data.message)||quality.message||'Could not read the ID. Please retake the photo.';
  }

  var panel=document.getElementById('ocrConfirmPanel');
  if(panel){
    panel.style.display='block';

    var values={
      full_name:(data&&data.full_name)||'',
      id_number:(data&&data.id_number)||'',
      date_of_birth:(data&&data.date_of_birth)||'',
      address:(data&&data.address)||'',
      email:(data&&data.email)||'',
      phone:(data&&data.phone)||''
    };

    document.getElementById('ocrFullName').value=values.full_name;
    document.getElementById('ocrIdNumber').value=values.id_number;
    document.getElementById('ocrDateOfBirth').value=values.date_of_birth;
    document.getElementById('ocrAddress').value=values.address;
    document.getElementById('ocrEmail').value=values.email;
    document.getElementById('ocrPhone').value=values.phone;

    // Document-specific details (student school/year/validity, senior/PWD
    // expiry). Absent fields stay hidden instead of showing empty rows.
    var school=(data&&data.school)||'';
    var studentNumber=(data&&data.student_number)||'';
    var schoolYear=(data&&data.school_year)||'';
    var validity=(data&&data.validity_raw)||'';
    if(!validity&&data&&data.validity_until){validity=data.validity_until}

    var schoolRow=document.getElementById('ocrSchoolRow');
    var studentRow=document.getElementById('ocrStudentNumberRow');
    var schoolYearRow=document.getElementById('ocrSchoolYearRow');
    var validityRow=document.getElementById('ocrValidityRow');
    if(document.getElementById('ocrSchool'))document.getElementById('ocrSchool').value=school;
    if(document.getElementById('ocrStudentNumber'))document.getElementById('ocrStudentNumber').value=studentNumber;
    if(document.getElementById('ocrSchoolYear'))document.getElementById('ocrSchoolYear').value=schoolYear;
    if(document.getElementById('ocrValidity'))document.getElementById('ocrValidity').value=validity;
    if(schoolRow)schoolRow.style.display=school?'block':'none';
    if(studentRow)studentRow.style.display=studentNumber?'block':'none';
    if(schoolYearRow)schoolYearRow.style.display=schoolYear?'block':'none';
    if(validityRow)validityRow.style.display=validity?'block':'none';

    var docSection=document.getElementById('ocrDocumentSection');
    if(docSection){
      var hasDocDetails=!!(school||studentNumber||schoolYear||validity);
      docSection.style.display=hasDocDetails?'block':'none';
      var title=document.getElementById('ocrDocumentSectionTitle');
      if(title){title.textContent=studentNumber||schoolYear||school?'School Details':'Document Details'}
    }

    // Review flags come from the scanner's assessment. They are advisory only:
    // the administrator makes the final verification decision.
    var reviewSection=document.getElementById('ocrReviewSection');
    var reviewList=document.getElementById('ocrReviewFlags');
    var reviewFlags=(data&&data.review_flags&&data.review_flags.length)?data.review_flags:[];
    if(reviewSection&&reviewList){
      if(reviewFlags.length){
        reviewList.innerHTML=reviewFlags.map(function(f){
          var escaped=String(f).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]});
          return '<li>'+escaped+'</li>';
        }).join('');
        reviewSection.style.display='block';
      }else{
        reviewSection.style.display='none';
      }
    }

    var verification=(data&&data.verification)||'pending';
    var badge=document.getElementById('ocrStatusBadge');
    if(badge){
      if(verification==='verified'){
        badge.className='badge bg-success';
        badge.textContent='ID DETAILS READY';
      }else if(verification==='expired'){
        badge.className='badge bg-danger';
        badge.textContent='EXPIRED — REVIEW';
      }else if(verification==='needs_review'){
        badge.className='badge bg-warning text-dark';
        badge.textContent='PENDING REVIEW';
      }else{
        badge.className='badge bg-warning text-dark';
        badge.textContent='PENDING REVIEW';
      }
    }
    var statusDesc=document.getElementById('ocrStatusDesc');
    if(statusDesc){
      statusDesc.textContent=verification==='verified'
        ?'Your ID details have been read. Check that your name and ID number are correct before submitting.'
        :verification==='needs_review'
        ?'Some details could not be read clearly. Please correct them below; an administrator will review your document.'
        :(verification==='expired'
          ?'This document may be outdated. Please correct the details below or use a newer document; an administrator will review it.'
          :'Details were read from your ID. Please check and correct the fields below before submitting.');
    }
    var verificationInput=document.getElementById('ocr_verification');
    if(verificationInput){verificationInput.value=verification}

    applyOcrFieldsToForm({
      full_name:values.full_name,
      email:(data&&data.email)||'',
      phone:(data&&data.phone)||'',
      date_of_birth:values.date_of_birth,
      address:values.address,
      discount:(data&&data.discount)||''
    },false);

    var addressRow=document.getElementById('ocrAddressRow');
    var showAddress=!!(values.address||(data&&data.requires_address));
    addressRow.style.display=showAddress?'block':'none';

    markMissingOcrFields();

    renderOcrQualityNote();

    try{panel.scrollIntoView({behavior:'smooth',block:'nearest'})}catch(e){}
  }

  document.getElementById('retakeScanBtn').style.display=quality.level==='good'?'none':'block';
}

/**
 * Build the OCR panel note. Kept separate from handleOcrResponse so changing
 * the language re-renders it in the new language.
 */
function renderOcrQualityNote(){
  var data=lastOcrFields;
  if(!data){return}

  var note=document.getElementById('ocrQualityNote');
  if(!note){return}

  var quality=data.quality||{level:'none',message:''};
  note.className='ocr-confirm-note '+(quality.level==='none'?'bad':(quality.level==='partial'?'warn':''));

  var noteText=quality.message||'';
  if(!data.full_name){noteText+=' The full name could not be read — type it below.'}
  if(!data.id_number){noteText+=' The ID number could not be read — type it if it is printed on the ID.'}
  if(!data.phone){noteText+=' '+ocrText('phone_not_detected')}
  if(data.sides){
    var frontChars=Number(data.sides.front||0);
    var backChars=Number(data.sides.back||0);
    if(frontChars===0&&backChars>0){
      noteText+=' The front of the ID could not be read — retake the front photo.';
    }else if(backChars===0&&frontChars>0){
      noteText+=' The back of the ID could not be read — retake the back photo.';
    }
  }
  if(data.warning){noteText+=' '+data.warning}
  if(data.age_warning){noteText+=' '+data.age_warning}
  note.textContent=noteText;
}

/**
 * Read a string from the inline translations map for the active language,
 * falling back to English and then to the key itself.
 */
function ocrText(key){
  var lang=(typeof currentLang!=='undefined'&&currentLang)||'en';
  if(translations[lang]&&translations[lang]['ocr_'+key]){return translations[lang]['ocr_'+key]}
  if(translations.en&&translations.en['ocr_'+key]){return translations.en['ocr_'+key]}
  return ''
}

function applyOcrFieldsToForm(fields,overwrite,never){
  fields=fields||{};
  never=never||[];

  function setField(id,value){
    var el=document.getElementById(id);
    if(!el||!value){return}
    // A field the passenger owns is never replaced by an OCR value, not even
    // by the "apply details" action.
    if(never.indexOf(id)!==-1){return}
    if(overwrite||!String(el.value||'').trim()){el.value=value}
  }

  setField('full_name',fields.full_name);
  setField('email',fields.email);
  setField('phone',fields.phone);
  setField('date_of_birth',fields.date_of_birth);
  setField('address',fields.address);

  var studentField=document.querySelector('input[name="student_id"]');
  if(studentField&&fields.id_number&&(overwrite||!String(studentField.value||'').trim())){
    studentField.value=fields.id_number;
  }

  if(typeof updateIdGuidance==='function')updateIdGuidance();
}

function markMissingOcrFields(){
  ['ocrFullName','ocrIdNumber','ocrDateOfBirth','ocrAddress'].forEach(function(id){
    var el=document.getElementById(id);
    if(!el){return}
    var row=el.closest('.ocr-confirm-row');
    if(row&&row.style.display==='none'){return}
    el.classList.toggle('is-missing',!String(el.value||'').trim());
  });
}

function applyOcrDetails(){
  applyOcrFieldsToForm({
    full_name:document.getElementById('ocrFullName').value.trim(),
    id_number:document.getElementById('ocrIdNumber').value.trim(),
    date_of_birth:document.getElementById('ocrDateOfBirth').value,
    address:document.getElementById('ocrAddress').value.trim(),
    email:document.getElementById('ocrEmail').value.trim(),
    phone:document.getElementById('ocrPhone').value.trim(),
    discount:(lastOcrFields&&lastOcrFields.discount)||''
  },true,['phone']);

  var panel=document.getElementById('ocrConfirmPanel');
  if(panel)panel.style.display='none';
  document.getElementById('scanStatus').innerHTML='Details applied. You can still edit any field before continuing.';
}

[['ocrFullName','full_name'],['ocrDateOfBirth','date_of_birth'],['ocrAddress','address'],['ocrEmail','email'],['ocrPhone','phone']].forEach(function(pair){
  var input=document.getElementById(pair[0]);
  if(!input){return}
  input.addEventListener('input',function(){
    var target=document.getElementById(pair[1]);
    if(target)target.value=this.value;
    markMissingOcrFields();
  });
});

document.getElementById('ocrApplyBtn')&&document.getElementById('ocrApplyBtn').addEventListener('click',applyOcrDetails);
document.getElementById('ocrRetakeBtn')&&document.getElementById('ocrRetakeBtn').addEventListener('click',resetCapture);
document.getElementById('ocrManualBtn')&&document.getElementById('ocrManualBtn').addEventListener('click',function(){
  var panel=document.getElementById('ocrConfirmPanel');
  if(panel)panel.style.display='none';
  document.getElementById('scanStatus').innerHTML='Unable to read the ID clearly. You can enter the information manually below.';
  // Manual entry carries no OCR assessment; the account starts as Pending.
  var verificationInput=document.getElementById('ocr_verification');
  if(verificationInput)verificationInput.value='pending';
  var name=document.getElementById('full_name');
  if(name)name.focus();
});

function getGuideFrameInVideo(video){
  var stage=document.getElementById('idCameraStage');
  var frame=stage?stage.querySelector('.id-guide-frame'):null;
  if(!stage||!frame||!video.videoWidth){return null}
  var sr=stage.getBoundingClientRect();
  var fr=frame.getBoundingClientRect();
  if(!sr.width||!sr.height){return null}
  var vw=video.videoWidth;
  var vh=video.videoHeight;
  var scale=Math.max(sr.width/vw,sr.height/vh);
  var dispW=vw*scale;
  var dispH=vh*scale;
  var offX=(sr.width-dispW)/2;
  var offY=(sr.height-dispH)/2;
  var x=((fr.left-sr.left)-offX)/scale;
  var y=((fr.top-sr.top)-offY)/scale;
  var w=fr.width/scale;
  var h=fr.height/scale;
  x=Math.max(0,Math.min(vw-1,x));
  y=Math.max(0,Math.min(vh-1,y));
  w=Math.max(10,Math.min(vw-x,w));
  h=Math.max(10,Math.min(vh-y,h));
  return {x:x,y:y,w:w,h:h};
}

function preprocessCapture(sourceCanvas){
  var cw=sourceCanvas.width;
  var ch=sourceCanvas.height;

  var longest=Math.max(cw,ch);
  var targetLong=Math.min(2200,Math.max(1600,longest));
  var scale=targetLong/longest;
  var targetW=Math.max(1,Math.round(cw*scale));
  var targetH=Math.max(1,Math.round(ch*scale));

  var out=document.createElement('canvas');
  out.width=targetW;
  out.height=targetH;
  var ctx=out.getContext('2d');
  ctx.imageSmoothingEnabled=true;
  ctx.imageSmoothingQuality='high';
  ctx.drawImage(sourceCanvas,0,0,cw,ch,0,0,targetW,targetH);

  try{
    var img=ctx.getImageData(0,0,out.width,out.height);
    var d=img.data;
    var len=d.length;
    var sum=0;
    for(var i=0;i<len;i+=4){sum+=d[i]*0.299+d[i+1]*0.587+d[i+2]*0.114}
    var mean=sum/(len/4);
    if(mean<90||mean>180){
      var contrast=1.08;
      var brightness=128-mean*0.25;
      for(var j=0;j<len;j+=4){
        d[j]=clamp255((d[j]-128)*contrast+128+brightness);
        d[j+1]=clamp255((d[j+1]-128)*contrast+128+brightness);
        d[j+2]=clamp255((d[j+2]-128)*contrast+128+brightness);
      }
      ctx.putImageData(img,0,0);
    }
  }catch(e){}
  return out;
}

function clamp255(v){return v<0?0:(v>255?255:v)}

function analyzeCaptureQuality(canvas){
  try{
    var sample=document.createElement('canvas');
    var scale=Math.min(1,360/canvas.width);
    sample.width=Math.max(1,Math.round(canvas.width*scale));
    sample.height=Math.max(1,Math.round(canvas.height*scale));
    var sctx=sample.getContext('2d');
    sctx.drawImage(canvas,0,0,sample.width,sample.height);
    var data=sctx.getImageData(0,0,sample.width,sample.height).data;
    var n=sample.width*sample.height;
    var gray=new Float32Array(n);
    var sum=0,glare=0,dark=0;
    for(var i=0,j=0;i<data.length;i+=4,j++){
      var lum=data[i]*0.299+data[i+1]*0.587+data[i+2]*0.114;
      gray[j]=lum;
      sum+=lum;
      if(lum>244)glare++;
      if(lum<45)dark++;
    }
    var mean=sum/n;
    var edges=0,count=0;
    for(var y=1;y<sample.height-1;y++){
      for(var x=1;x<sample.width-1;x++){
        var idx=y*sample.width+x;
        var lap=4*gray[idx]-gray[idx-1]-gray[idx+1]-gray[idx-sample.width]-gray[idx+sample.width];
        edges+=Math.abs(lap);
        count++;
      }
    }
    var sharpness=count?edges/count:0;
    var glareRatio=glare/n;
    var darkRatio=dark/n;
    var level='good';
    var message='Photo looks clear.';
    if(mean<70||darkRatio>0.45){
      level='bad';
      message='The photo looks too dark. Move to a brighter area and retake.';
    }else if(glareRatio>0.09){
      level='bad';
      message='Glare detected on the ID. Tilt the ID slightly or move away from direct light and retake.';
    }else if(sharpness<6){
      level='warn';
      message='The ID may be slightly blurry. Hold the camera steady and retake if the text looks soft.';
    }else if(mean>210){
      level='warn';
      message='The photo looks overexposed. Reduce direct light and retake.';
    }
    return {level:level,message:message,brightness:Math.round(mean),glare:Math.round(glareRatio*100),sharpness:Math.round(sharpness*10)/10};
  }catch(e){
    return {level:'good',message:'',brightness:null,glare:null,sharpness:null};
  }
}

function showQualityHint(quality,side){
  var el=document.getElementById('scanQualityHint');
  if(!el){return}
  if(!quality||quality.level==='good'||!quality.message){
    el.style.display='none';
    el.textContent='';
    return;
  }
  el.className='id-quality-hint '+(quality.level==='bad'?'bad':'warn');
  el.textContent=(side==='front'?'Front photo: ':'Back photo: ')+quality.message;
}

function setGuideOrientation(mode){
  mode=mode==='portrait'?'portrait':'landscape';
  guideOrientation=mode;

  var frame=document.querySelector('.id-guide-frame');
  if(frame){
    frame.classList.toggle('landscape',mode==='landscape');
    frame.classList.toggle('portrait',mode==='portrait');
  }

  var chip=document.querySelector('.id-guide-orientation');
  if(chip){
    chip.textContent=mode==='portrait'
      ? 'Portrait ID • hold the ID upright'
      : 'Landscape ID • hold the ID sideways';
  }
}

var PASSENGER_ID_CONFIG = {
  'Regular': {
    requiresId: false,
    idType: '',
    idName: 'Regular Passenger',
    idFieldLabel: 'ID Number',
    idPlaceholder: 'No ID required',
    helper: 'No discount verification required.'
  },
  'Student': {
    requiresId: true,
    idType: 'student-id',
    idName: 'Student ID',
    idFieldLabel: 'Student ID Number',
    idPlaceholder: 'e.g., 2024-12345',
    helper: 'Upload your valid Student ID to verify discount eligibility.'
  },
  'Senior Citizen': {
    requiresId: true,
    isSeniorChoice: true,
    idType: 'senior-citizen-id',
    idName: 'Senior Citizen ID / PhilSys National ID',
    idFieldLabel: 'Senior Citizen ID / PhilSys Number',
    idPlaceholder: 'e.g., OSCA-123456 or PhilSys Number',
    helper: 'Senior citizens can verify using an OSCA Senior Citizen ID or PhilSys / National ID.'
  },
  'PWD': {
    requiresId: true,
    idType: 'pwd-id',
    idName: 'PWD ID',
    idFieldLabel: 'PWD ID Number',
    idPlaceholder: 'e.g., PWD-123456',
    helper: 'Upload your valid Person with Disability (PWD) ID.'
  },
  'Teacher': {
    requiresId: true,
    idType: 'teacher-id',
    idName: 'Teacher / School ID',
    idFieldLabel: 'Teacher / Faculty ID Number',
    idPlaceholder: 'e.g., FAC-2024-123',
    helper: 'Upload your valid Teacher or School Faculty ID.'
  },
  'Nurse': {
    requiresId: true,
    idType: 'prc-id',
    idName: 'PRC ID',
    idFieldLabel: 'PRC License Number',
    idPlaceholder: 'e.g., PRC-0123456',
    helper: 'Upload your valid Professional Regulation Commission (PRC) ID.'
  },
  'RPMVFMPC Member': {
    requiresId: true,
    idType: 'coop-beneficiary-id',
    idName: 'Cooperative Beneficiary ID',
    idFieldLabel: 'Cooperative Member ID Number',
    idPlaceholder: 'e.g., COOP-98765',
    helper: 'Upload your valid RPMVFMPC Cooperative Beneficiary ID.'
  }
};

function clearIdData() {
  var front = document.getElementById('id_front');
  var back = document.getElementById('id_back');
  if (front) front.value = '';
  if (back) back.value = '';

  var previewContainer = document.getElementById('previewContainer');
  if (previewContainer) previewContainer.style.display = 'none';

  var prevFront = document.getElementById('previewFront');
  var prevBack = document.getElementById('previewBack');
  if (prevFront) prevFront.src = '';
  if (prevBack) prevBack.src = '';

  var idInput = document.getElementById('student_id');
  if (idInput) idInput.value = '';

  var ocrPanel = document.getElementById('ocrConfirmPanel');
  if (ocrPanel) ocrPanel.style.display = 'none';

  ['ocrFullName', 'ocrIdNumber', 'ocrDateOfBirth', 'ocrAddress', 'ocrEmail', 'ocrPhone'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.value = '';
  });

  var status = document.getElementById('scanStatus');
  if (status) status.innerHTML = 'Waiting for uploads...';

  var retakeBtn = document.getElementById('retakeScanBtn');
  if (retakeBtn) retakeBtn.style.display = 'none';

  var hint = document.getElementById('scanQualityHint');
  if (hint) { hint.style.display = 'none'; hint.textContent = ''; }

  ocrCanvases.front = null;
  ocrCanvases.back = null;
  ocrQuality.front = null;
  ocrQuality.back = null;
  lastOcrFields = null;

  currentCaptureSide = 'front';
  var capBtn = document.getElementById('captureBtn');
  if (capBtn) capBtn.textContent = 'Capture Front';
  var rBtn = document.getElementById('retakeBtn');
  if (rBtn) rBtn.style.display = 'none';
}

function setSeniorIdType(idType) {
  var idInput = document.getElementById('id_type_input');
  if (idInput) idInput.value = idType;
  selectedIdType = idType;

  var oscaChoice = document.getElementById('seniorChoiceOsca');
  var philsysChoice = document.getElementById('seniorChoicePhilsys');
  if (oscaChoice && philsysChoice) {
    if (idType === 'senior-citizen-id') {
      oscaChoice.classList.add('active');
      philsysChoice.classList.remove('active');
      var r1 = oscaChoice.querySelector('input');
      if (r1) r1.checked = true;
    } else {
      philsysChoice.classList.add('active');
      oscaChoice.classList.remove('active');
      var r2 = philsysChoice.querySelector('input');
      if (r2) r2.checked = true;
    }
  }

  var idLabel = document.getElementById('idNumberLabelText');
  var idField = document.getElementById('student_id');
  if (idType === 'senior-citizen-id') {
    if (idLabel) idLabel.textContent = 'OSCA Senior Citizen ID Number';
    if (idField) idField.placeholder = 'e.g., OSCA-123456';
  } else {
    if (idLabel) idLabel.textContent = 'PhilSys / National ID Number';
    if (idField) idField.placeholder = 'e.g., 1234-5678-9012-3456';
  }

  updateAddressFieldRequirement(idType);
  if (typeof updateIdGuidance === 'function') updateIdGuidance();
}

function updateAddressFieldRequirement(idType) {
  var addrField = document.getElementById('addressField');
  if (!addrField) return;
  var requiresAddress = (idType === 'senior-citizen-id' || idType === 'philsys-id' || idType === 'coop-beneficiary-id');
  addrField.style.display = requiresAddress ? 'flex' : 'none';
}

function selectPassengerType(category, triggerReset) {
  var config = PASSENGER_ID_CONFIG[category] || PASSENGER_ID_CONFIG['Regular'];
  var currentCat = document.getElementById('passenger_type_input') ? document.getElementById('passenger_type_input').value : '';

  if (triggerReset && currentCat !== category) {
    clearIdData();
  }

  // Update active state in category button grid
  document.querySelectorAll('.category-btn').forEach(function(btn) {
    btn.classList.toggle('active', btn.getAttribute('data-category') === category);
  });

  var paxInput = document.getElementById('passenger_type_input');
  if (paxInput) paxInput.value = category;

  var idTypeInput = document.getElementById('id_type_input');
  var idSection = document.getElementById('idVerificationSection');
  var fixedBox = document.getElementById('fixedIdBox');
  var seniorBox = document.getElementById('seniorIdChoiceBox');

  if (!config.requiresId) {
    // Regular: completely hide ID verification section
    if (idSection) idSection.style.display = 'none';
    if (idTypeInput) idTypeInput.value = '';
    selectedIdType = '';
    updateAddressFieldRequirement('');
  } else {
    // Discount: show ID verification section
    if (idSection) idSection.style.display = 'block';

    if (config.isSeniorChoice) {
      if (fixedBox) fixedBox.style.display = 'none';
      if (seniorBox) seniorBox.style.display = 'block';
      var seniorSelected = document.querySelector('input[name="senior_id_radio"]:checked');
      var seniorVal = seniorSelected ? seniorSelected.value : 'senior-citizen-id';
      setSeniorIdType(seniorVal);
    } else {
      if (fixedBox) fixedBox.style.display = 'block';
      if (seniorBox) seniorBox.style.display = 'none';
      var titleEl = document.getElementById('fixedIdTitle');
      var helperEl = document.getElementById('fixedIdHelper');
      if (titleEl) titleEl.textContent = config.idName;
      if (helperEl) helperEl.textContent = config.helper;
      if (idTypeInput) idTypeInput.value = config.idType;
      selectedIdType = config.idType;

      var idLabel = document.getElementById('idNumberLabelText');
      var idField = document.getElementById('student_id');
      var idHint = document.getElementById('student_id_hint');
      if (idLabel) idLabel.textContent = config.idFieldLabel;
      if (idField) idField.placeholder = config.idPlaceholder;
      if (idHint) idHint.textContent = config.helper;

      updateAddressFieldRequirement(config.idType);
    }

    if (typeof updateIdGuidance === 'function') updateIdGuidance();
  }
}

function updateIdGuidance(){
  var block=document.getElementById('seniorGuidance');
  if(!block){return}
  var paxTypeEl=document.getElementById('passenger_type_input');
  var category=paxTypeEl?paxTypeEl.value:'Regular';

  var isSenior=selectedIdType==='senior-citizen-id'||selectedIdType==='philsys-id'||category==='Senior Citizen';
  var isPwd=selectedIdType==='pwd-id'||category==='PWD';
  var isStudent=selectedIdType==='student-id'||category==='Student';
  var isTeacher=selectedIdType==='teacher-id'||category==='Teacher';
  var isNurse=selectedIdType==='prc-id'||category==='Nurse';
  var isCoop=selectedIdType==='coop-beneficiary-id'||category==='RPMVFMPC Member';

  var label=document.querySelector('.id-guide-label');
  var title=document.getElementById('seniorGuidanceTitle');
  var sub=document.getElementById('seniorGuidanceSub');

  if(isSenior){
    block.style.display='block';
    if(selectedIdType==='philsys-id'){
      title.textContent='Position your PhilSys / National ID inside the frame.';
      sub.textContent='Show your full name, PhilSys Card Number, and date of birth.';
      if(label)label.textContent='Position your PhilSys / National ID inside the frame';
    }else{
      title.textContent='Position your Senior Citizen ID inside the frame.';
      sub.textContent='Show the ID number, full name, and date of birth (if available).';
      if(label)label.textContent='Position your Senior Citizen ID inside the frame';
    }
  }else if(isPwd){
    block.style.display='block';
    title.textContent='Position your PWD ID inside the frame.';
    sub.textContent='Show the PWD ID number, full name, and date of birth (if available).';
    if(label)label.textContent='Position your PWD ID inside the frame';
  }else if(isStudent){
    block.style.display='block';
    title.textContent='Position your Student ID inside the frame.';
    sub.textContent='Show the Student ID number, full name, and school (if available).';
    if(label)label.textContent='Position your Student ID inside the frame';
  }else if(isTeacher){
    block.style.display='block';
    title.textContent='Position your Teacher / School ID inside the frame.';
    sub.textContent='Show your faculty ID number, full name, and school name.';
    if(label)label.textContent='Position your Teacher / School ID inside the frame';
  }else if(isNurse){
    block.style.display='block';
    title.textContent='Position your PRC ID inside the frame.';
    sub.textContent='Show your PRC license number, full name, and profession.';
    if(label)label.textContent='Position your PRC ID inside the frame';
  }else if(isCoop){
    block.style.display='block';
    title.textContent='Position your Cooperative Beneficiary ID inside the frame.';
    sub.textContent='Show your cooperative ID number, full name, and chapter details.';
    if(label)label.textContent='Position your Cooperative Beneficiary ID inside the frame';
  }else{
    block.style.display='none';
    if(label)label.textContent='Place ID inside the frame';
  }

  setGuideOrientation(ID_ORIENTATIONS[selectedIdType]||'landscape');
}

document.addEventListener('DOMContentLoaded',function(){
  selectPassengerType('Regular', false);
  var tipsToggle=document.getElementById('idTipsToggle');
  var tipsList=document.getElementById('idGuideTips');
  if(tipsToggle&&tipsList){
    tipsToggle.addEventListener('click',function(){
      var open=tipsList.style.display!=='none';
      tipsList.style.display=open?'none':'flex';
      tipsToggle.setAttribute('aria-expanded',open?'false':'true');
    });
  }
});

var currentLang='en';

var translations={
  en:{
    ocr_phone_not_detected:"Phone number not detected — no phone number was found on the ID, so please enter your own below.",
    take_photo:"Take Photo",upload_file:"Upload File",no_camera:"Camera not available",
    camera_fallback:"Please use file upload instead",capture_photo:"Capture",retake:"Retake",
    front_id:"Front of ID",back_id:"Back of ID",front_label:"Front",back_label:"Back",
    waiting_upload:"Waiting for uploads...",autofill_hint:"Auto-fill your details using your ID",
    privacy_title:"Passenger Privacy Policy",privacy_effective_date:"Effective Date: July 8, 2026",
    privacy_intro:"R Connect Transportation Management System is committed to protecting the privacy and personal information of all passengers. We value your trust and are dedicated to ensuring that your personal data is collected, processed, and protected in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and the guidelines of the National Privacy Commission (NPC).",
    privacy_info_collect_heading:"Information We Collect",
    privacy_info_collect_desc:"To provide safe and efficient public transportation services, we may collect only the information necessary for your travel, including:",
    privacy_collect_name:"Full Name",privacy_collect_type:"Passenger Type (Regular, Student, Senior Citizen, PWD, Cooperative Member, etc.)",
    privacy_collect_ids:"Student ID, Senior Citizen ID, PWD ID, or Cooperative ID Number (when applicable for fare discounts)",
    privacy_collect_board:"Boarding Point",privacy_collect_dest:"Destination",privacy_collect_fare:"Fare Information",
    privacy_collect_date:"Date and Time of Travel",privacy_collect_qr:"QR Code Transaction Information",
    privacy_collect_note:"Only information necessary for transportation services is collected.",
    privacy_purpose_heading:"Purpose of Collecting Your Information",
    privacy_purpose_desc:"Your personal information is collected solely for transportation-related purposes, including:",
    privacy_purpose_id:"Passenger identification",privacy_purpose_fare:"Fare computation and discount verification",
    privacy_purpose_trip:"Recording passenger trips",privacy_purpose_count:"Passenger counting and occupancy monitoring",
    privacy_purpose_report:"Generating transportation and financial reports",
    privacy_purpose_ops:"Improving transportation operations",
    privacy_purpose_emergency:"Responding to emergencies or transportation-related incidents",
    privacy_purpose_comply:"Complying with applicable Philippine laws and transportation regulations",
    privacy_purpose_note:"Your information will not be used for advertising, marketing, commercial promotions, profiling, or any purpose unrelated to transportation services.",
    privacy_protect_heading:"Protection of Your Personal Data",
    privacy_protect_desc:"R Connect implements appropriate administrative, technical, and organizational security measures to protect your personal information against:",
    privacy_protect_access:"Unauthorized access",privacy_protect_disclose:"Unauthorized disclosure",
    privacy_protect_loss:"Loss or theft",privacy_protect_alter:"Alteration",privacy_protect_misuse:"Misuse",
    privacy_protect_destroy:"Destruction",
    privacy_protect_note:"Only authorized personnel with legitimate transportation-related responsibilities are allowed access to passenger information.",
    privacy_sharing_heading:"Data Sharing",
    privacy_sharing_desc:"Passenger information is kept confidential and will not be sold, rented, or shared with third parties except when:",
    privacy_sharing_law:"Required by Philippine law;",privacy_sharing_order:"Requested through a lawful order issued by a competent authority;",
    privacy_sharing_safety:"Necessary to protect public safety or investigate transportation-related incidents.",
    privacy_retention_heading:"Data Retention",
    privacy_retention_desc:"Passenger information is retained only for as long as necessary to fulfill transportation, operational, legal, and auditing requirements. After the required retention period, records are securely archived or permanently disposed of in accordance with applicable laws and company policies.",
    privacy_rights_heading:"Your Rights",
    privacy_rights_desc:"Under the Data Privacy Act of 2012 (Republic Act No. 10173), passengers have the right to:",
    privacy_rights_informed:"Be informed about how their personal information is collected and used.",
    privacy_rights_access:"Access their personal information.",
    privacy_rights_correct:"Request correction of inaccurate or incomplete information.",
    privacy_rights_delete:"Request deletion of personal information when permitted by law.",
    privacy_rights_object:"Object to unlawful processing of personal information.",
    privacy_rights_complaint:"File a complaint with the National Privacy Commission (NPC) if they believe their privacy rights have been violated.",
    privacy_consent_heading:"Passenger Consent",
    privacy_consent_desc:"By registering as a passenger, scanning your QR code, or using the R Connect Transportation Management System, you acknowledge that you have read and understood this Privacy Policy and consent to the collection and processing of your personal information solely for transportation purposes.",
    privacy_commit_heading:"Commitment to Privacy",
    privacy_commit_desc:"R Connect respects every passenger's right to privacy. We collect only the information necessary to provide safe, efficient, and reliable transportation services.",
    privacy_commit_note:"Passenger information shall be used exclusively for transportation-related operations and shall never be used for personal gain, marketing, advertising, commercial purposes, or any activity unrelated to the delivery and management of public transportation services.",
    privacy_commit_compliance:"All passenger information is handled confidentially and protected in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and the standards established by the National Privacy Commission (NPC) of the Philippines.",
    privacy_agree_label:"I have read and agree to the Privacy Policy",privacy_accept_btn:"Accept",privacy_scroll_hint:"Scroll to the end of the policy to enable the agreement.",
    full_name:"Full Name",email:"Email Address",phone:"Phone Number",date_of_birth:"Date of Birth",
    password:"Password",confirm_password:"Confirm Password",
    select_id_type:"Select ID Type",loading_id_types:"Loading ID types...",id_type_error:"Unable to load ID types",
    address:"Address",
    scan_upload:"Scan / Upload ID",autofill_details:"Auto-fill your details using your ID",
    continue:"Continue",back:"Back",create:"Create",
    location_access:"Location Access",allow_location:"Allow Location",
    create_account:"Create Your Account!",getting_started:"Let's get you started!",
    fill_information:"Please fill out your information",
    discount_type:"Discount Type",
    regular:"Regular",student:"Student",senior_pwd:"Senior / PWD",teacher_nurse:"Teacher / Nurse",cooperative_member:"Cooperative Member",
    location_description:"We need your location for routes and stops."
  },
  tl:{
    ocr_phone_not_detected:"Hindi natukoy ang numero ng telepono — walang numero na nakita sa ID, kaya pakilala ang sarili mong numero sa ibaba.",
    take_photo:"Kumuha ng Larawan",upload_file:"Mag-upload ng File",no_camera:"Walang camera na available",
    camera_fallback:"Mangyaring gumamit ng file upload",capture_photo:"Kuhanin",retake:"Ulitin",
    front_id:"Harap ng ID",back_id:"Likod ng ID",front_label:"Harap",back_label:"Likod",
    waiting_upload:"Naghihintay ng upload...",autofill_hint:"Auto-fill ang iyong detalye gamit ang ID",
    privacy_title:"Patakaran sa Pagkapribado ng Pasahero",privacy_effective_date:"Petsa ng Pagkabisa: Hulyo 8, 2026",
    privacy_intro:"Ang R Connect Transportation Management System ay nakatuon sa pagprotekta sa privacy at personal na impormasyon ng lahat ng pasahero. Pinahahalagahan namin ang inyong tiwala at nakatuon kami sa pagtiyak na ang inyong personal na datos ay nakolekta, naproseso, at protektado alinsunod sa Data Privacy Act of 2012 (Republic Act No. 10173) at mga alituntunin ng National Privacy Commission (NPC).",
    privacy_info_collect_heading:"Impormasyong Aming Kinokolekta",
    privacy_info_collect_desc:"Upang makapagbigay ng ligtas at mahusay na serbisyo sa pampublikong transportasyon, maaari lamang kaming mangolekta ng impormasyong kailangan para sa inyong paglalakbay, kabilang ang:",
    privacy_collect_name:"Buong Pangalan",privacy_collect_type:"Uri ng Pasahero (Regular, Estudyante, Senior Citizen, PWD, Miyembro ng Kooperatiba, atbp.)",
    privacy_collect_ids:"Student ID, Senior Citizen ID, PWD ID, o Cooperative ID Number (kapag naaangkop para sa diskwento sa pamasahe)",
    privacy_collect_board:"Lugar ng Pagsakay",privacy_collect_dest:"Destinasyon",privacy_collect_fare:"Impormasyon sa Pamasahe",
    privacy_collect_date:"Petsa at Oras ng Paglalakbay",privacy_collect_qr:"Impormasyon ng QR Code Transaction",
    privacy_collect_note:"Tanging impormasyong kailangan para sa mga serbisyo ng transportasyon ang kinokolekta.",
    privacy_purpose_heading:"Layunin ng Pagkolekta ng Inyong Impormasyon",
    privacy_purpose_desc:"Ang inyong personal na impormasyon ay kinokolekta para sa mga layuning may kaugnayan sa transportasyon lamang, kabilang ang:",
    privacy_purpose_id:"Pagkilala sa pasahero",privacy_purpose_fare:"Pagkalkula ng pamasahe at beripikasyon ng diskwento",
    privacy_purpose_trip:"Pagrekord ng mga biyahe ng pasahero",privacy_purpose_count:"Pagbibilang ng pasahero at pagsubaybay sa okupasyon",
    privacy_purpose_report:"Paggawa ng mga ulat sa transportasyon at pananalapi",
    privacy_purpose_ops:"Pagpapabuti ng operasyon ng transportasyon",
    privacy_purpose_emergency:"Pagtugon sa mga emerhensiya o insidenteng may kaugnayan sa transportasyon",
    privacy_purpose_comply:"Pagsunod sa mga naaangkop na batas ng Pilipinas at regulasyon sa transportasyon",
    privacy_purpose_note:"Ang inyong impormasyon ay hindi gagamitin para sa advertising, marketing, komersyal na promosyon, profiling, o anumang layuning hindi nauugnay sa serbisyo ng transportasyon.",
    privacy_protect_heading:"Proteksyon ng Inyong Personal na Datos",
    privacy_protect_desc:"Ang R Connect ay nagpapatupad ng angkop na administratibo, teknikal, at organisasyonal na mga hakbang sa seguridad upang protektahan ang inyong personal na impormasyon laban sa:",
    privacy_protect_access:"Hindi awtorisadong pag-access",privacy_protect_disclose:"Hindi awtorisadong pagsisiwalat",
    privacy_protect_loss:"Pagkawala o pagnanakaw",privacy_protect_alter:"Pagbabago",privacy_protect_misuse:"Maling paggamit",
    privacy_protect_destroy:"Pagkawasak",
    privacy_protect_note:"Tanging mga awtorisadong tauhan na may lehitimong responsibilidad sa transportasyon ang pinapayagang ma-access ang impormasyon ng pasahero.",
    privacy_sharing_heading:"Pagbabahagi ng Datos",
    privacy_sharing_desc:"Ang impormasyon ng pasahero ay pinapanatiling kumpidensyal at hindi ibebenta, uupahan, o ibabahagi sa mga third party maliban kung:",
    privacy_sharing_law:"Kinakailangan ng batas ng Pilipinas;",
    privacy_sharing_order:"Hiniling sa pamamagitan ng legal na utos mula sa isang karampatang awtoridad;",
    privacy_sharing_safety:"Kailangan upang protektahan ang kaligtasan ng publiko o mag-imbestiga ng mga insidenteng may kaugnayan sa transportasyon.",
    privacy_retention_heading:"Pagpapanatili ng Datos",
    privacy_retention_desc:"Ang impormasyon ng pasahero ay pinanatili lamang hanggang sa kinakailangan upang matupad ang transportasyon, operasyonal, legal, at pag-audit na mga kinakailangan. Pagkatapos ng kinakailangang panahon ng pagpapanatili, ang mga rekord ay ligtas na inaarchive o permanenteng itinatapon alinsunod sa mga naaangkop na batas at patakaran ng kumpanya.",
    privacy_rights_heading:"Inyong mga Karapatan",
    privacy_rights_desc:"Sa ilalim ng Data Privacy Act of 2012 (Republic Act No. 10173), ang mga pasahero ay may karapatang:",
    privacy_rights_informed:"Malaman kung paano kinokolekta at ginagamit ang kanilang personal na impormasyon.",
    privacy_rights_access:"Ma-access ang kanilang personal na impormasyon.",
    privacy_rights_correct:"Hilingin ang pagwawasto ng hindi tumpak o hindi kumpletong impormasyon.",
    privacy_rights_delete:"Hilingin ang pagbura ng personal na impormasyon kapag pinahihintulutan ng batas.",
    privacy_rights_object:"Tutulan ang labag sa batas na pagproseso ng personal na impormasyon.",
    privacy_rights_complaint:"Maghain ng reklamo sa National Privacy Commission (NPC) kung naniniwala silang nilabag ang kanilang mga karapatan sa privacy.",
    privacy_consent_heading:"Pahintulot ng Pasahero",
    privacy_consent_desc:"Sa pamamagitan ng pagrehistro bilang pasahero, pag-scan ng inyong QR code, o paggamit ng R Connect Transportation Management System, kinikilala ninyo na nabasa at naunawaan ninyo ang Patakarang ito sa Pagkapribado at pumapayag sa pagkolekta at pagproseso ng inyong personal na impormasyon para sa mga layunin ng transportasyon lamang.",
    privacy_commit_heading:"Pangako sa Pagkapribado",
    privacy_commit_desc:"Ang R Connect ay gumagalang sa karapatan ng bawat pasahero sa privacy. Kinokolekta lamang namin ang impormasyong kailangan upang makapagbigay ng ligtas, mahusay, at maaasahang serbisyo sa transportasyon.",
    privacy_commit_note:"Ang impormasyon ng pasahero ay gagamitin nang eksklusibo para sa mga operasyong may kaugnayan sa transportasyon at hindi kailanman gagamitin para sa personal na pakinabang, marketing, advertising, komersyal na layunin, o anumang aktibidad na hindi nauugnay sa paghahatid at pamamahala ng mga serbisyo sa pampublikong transportasyon.",
    privacy_commit_compliance:"Ang lahat ng impormasyon ng pasahero ay hinahawakan nang kumpidensyal at pinoprotektahan alinsunod sa Data Privacy Act of 2012 (Republic Act No. 10173) at mga pamantayang itinatag ng National Privacy Commission (NPC) ng Pilipinas.",
    privacy_agree_label:"Nabasa ko at sumasang-ayon ako sa Patakaran sa Pagkapribado",privacy_accept_btn:"Tanggapin",privacy_scroll_hint:"Mag-scroll hanggang sa dulo ng patakaran upang ma-enable ang kasunduan.",
    full_name:"Buong Pangalan",email:"Email Address",phone:"Numero ng Telepono",date_of_birth:"Petsa ng Kapanganakan",
    password:"Password",confirm_password:"Kumpirmahin ang Password",
    select_id_type:"Pumili ng Uri ng ID",loading_id_types:"Naglo-load ng mga uri ng ID...",id_type_error:"Hindi ma-load ang mga uri ng ID",
    address:"Address",
    scan_upload:"I-scan / I-upload ang ID",autofill_details:"Awtomatikong punan ang detalye gamit ang ID",
    continue:"Magpatuloy",back:"Bumalik",create:"Gumawa",
    location_access:"Access sa Lokasyon",allow_location:"Payagan ang Lokasyon",
    create_account:"Gumawa ng Iyong Account!",getting_started:"Simulan na natin!",
    fill_information:"Pakilagay ang iyong impormasyon",
    discount_type:"Uri ng Diskwento",
    regular:"Regular",student:"Estudyante",senior_pwd:"Senior / PWD",teacher_nurse:"Guro / Nurse",cooperative_member:"Miyembro ng Kooperatiba",
    location_description:"Kailangan namin ang iyong lokasyon para sa mga ruta at hintuan."
  },
  ilo:{
    ocr_phone_not_detected:"Naila ti numero ti telepono — awan ti numero a naKita iti ID, ngitang pannaka-enter ti kuditing mo iti idaba.",
    take_photo:"Mangala ti Retrato",upload_file:"Mang-upload ti File",no_camera:"Awan ti camera a disponible",
    camera_fallback:"Pangngaasi ta agusar ti file upload",capture_photo:"Alaen",retake:"Uliten",
    front_id:"Sango ti ID",back_id:"Likud ti ID",front_label:"Sango",back_label:"Likud",
    waiting_upload:"Aguray ti upload...",autofill_hint:"Auto-fill dagiti detalye babaen ti ID",
    privacy_title:"Pannakaseguro ti Pribasidad ti Pasahero",privacy_effective_date:"Epektibo a Petsa: Hulio 8, 2026",
    privacy_intro:"Ti R Connect Transportation Management System ket naitalaga iti panangsalaknib ti pribasidad ken personal nga impormasyon dagiti amin a pasahero. Planuemi ti panagtalek yo ken naitalagami a ti personal a datos yo ket maurnong, maproseso, ken maprotektaran a maitunos iti Data Privacy Act of 2012 (Republic Act No. 10173) ken dagiti pagannurotan ti National Privacy Commission (NPC).",
    privacy_info_collect_heading:"Impormasyon nga Urnonguenmi",
    privacy_info_collect_desc:"Tapno makaitarintined iti natalged ken nasayaat a serbisio ti publik a transportasion, mabalin laeng nga urnonguenmi ti impormasyon a nasken para iti panagbiahe yo, a pakairamanan:",
    privacy_collect_name:"Naan a Kompleto a Nagan",
    privacy_collect_type:"Kita ti Pasahero (Regular, Estudyante, Senior Citizen, PWD, Kameng ti Kooperatiba, ken dadduma pay)",
    privacy_collect_ids:"Student ID, Senior Citizen ID, PWD ID, wenno Cooperative ID Number (no maaplikar para iti diskuento)",
    privacy_collect_board:"Pagluganan",privacy_collect_dest:"Papanan",privacy_collect_fare:"Impormasyon ti Plete",
    privacy_collect_date:"Petsa ken Oras ti Panagbiahe",privacy_collect_qr:"Impormasyon ti QR Code Transaction",
    privacy_collect_note:"Laeng ti impormasyon a nasken para iti serbisio ti transportasion ti maurnong.",
    privacy_purpose_heading:"Panggep ti Panangurnong ti Impormasyon Yo",
    privacy_purpose_desc:"Ti personal nga impormasyon yo ket maurnong laeng para kadagiti panggep a mainaig iti transportasion, a pakairamanan:",
    privacy_purpose_id:"Panangilasin ti pasahero",privacy_purpose_fare:"Panagkarkulo ti plete ken panangpatalged ti diskuento",
    privacy_purpose_trip:"Panagrekord kadagiti biahe ti pasahero",privacy_purpose_count:"Panagbilang ti pasahero ken panangmonitor ti okupasion",
    privacy_purpose_report:"Panagpartuat kadagiti ulat ti transportasion ken pinansia",
    privacy_purpose_ops:"Panangpasayaat ti operasion ti transportasion",
    privacy_purpose_emergency:"Panangsungbat kadagiti emergencia wenno insidente a mainaig iti transportasion",
    privacy_purpose_comply:"Panagtulnog kadagiti maaplikar a linteg ti Filipinas ken pagannurotan ti transportasion",
    privacy_purpose_note:"Ti impormasyon yo ket saan a mausar para iti advertising, marketing, komersial a promosion, profiling, wenno aniaman a panggep a saan a mainaig iti serbisio ti transportasion.",
    privacy_protect_heading:"Pannakasalaknib ti Personal a Datos Yo",
    privacy_protect_desc:"Ipatungpal ti R Connect kadagiti umno nga administratibo, teknikal, ken organizatorial a panagtekel ti seguridad tapno masalakniban ti personal nga impormasyon yo manipud iti:",
    privacy_protect_access:"Saan nga autorisado a panagserrek",privacy_protect_disclose:"Saan nga autorisado a panangiwaragawag",
    privacy_protect_loss:"Pannakapukaw wenno pannakatakaw",privacy_protect_alter:"Panangbalbaliw",
    privacy_protect_misuse:"Di umno a panangusar",privacy_protect_destroy:"Pannakadadael",
    privacy_protect_note:"Laeng dagiti autorisado a personal a lehitimo a responsable iti transportasion ti mabalin a makaakses iti impormasion ti pasahero.",
    privacy_sharing_heading:"Panagbibingay ti Datos",
    privacy_sharing_desc:"Ti impormasion ti pasahero ket mapagtalinaed a kompidensial ken saan a mailako, maupaan, wenno maibingay kadagiti third party malaksid no:",
    privacy_sharing_law:"Mabalin a nasken ti linteg ti Filipinas;",
    privacy_sharing_order:"Mainkeddeng babaen ti legal nga orden manipud iti autorisado nga autoridad;",
    privacy_sharing_safety:"Nasken a masalakniban ti publiko wenno maimbestigaran dagiti insidente a mainaig iti transportasion.",
    privacy_retention_heading:"Panagtaginayon ti Datos",
    privacy_retention_desc:"Ti impormasion ti pasahero ket mataginayon laeng iti kabayag a nasken tapno matungpal dagiti kinasapul ti transportasion, operasion, legal, ken auditing. Kalpasan ti nasken a panawen ti panagtaginayon, dagiti rekord ket natalged nga i-archive wenno permanente a maibelleng a maitunos kadagiti maaplikar a linteg ken patakaran ti kompania.",
    privacy_rights_heading:"Dagiti Karbengan Yo",
    privacy_rights_desc:"Babaen iti Data Privacy Act of 2012 (Republic Act No. 10173), dagiti pasahero ket addaan iti karbengan a:",
    privacy_rights_informed:"Maammuan no kasano ti panangurnong ken panangusar iti personal nga impormasyon da.",
    privacy_rights_access:"Makasek iti personal nga impormasyon da.",
    privacy_rights_correct:"Agkiddaw ti panangpasayaat iti di umno wenno saan a kompleto nga impormasyon.",
    privacy_rights_delete:"Agkiddaw ti panangikkat iti personal nga impormasyon no mapalubosan ti linteg.",
    privacy_rights_object:"Tumakder iti iligal a panangproseso iti personal nga impormasyon.",
    privacy_rights_complaint:"Mangited ti reklamo iti National Privacy Commission (NPC) no patienda a nalabsing dagiti karbengan da iti pribasidad.",
    privacy_consent_heading:"Panagpalubos ti Pasahero",
    privacy_consent_desc:"Babaen ti panagrehistro kas pasahero, panangscan ti QR code, wenno panangusar ti R Connect Transportation Management System, bigbigen yo a nabasa ken naawatan yo daytoy a Pannakaseguro ti Pribasidad ken umanamong kayo iti panangurnong ken panangproseso iti personal nga impormasyon yo laeng para iti panggep ti transportasion.",
    privacy_commit_heading:"Panangikari iti Pribasidad",
    privacy_commit_desc:"Respetuen ti R Connect ti karbengan ti tunggal pasahero iti pribasidad. Urnonguenmi laeng ti impormasyon a nasken tapno makaitarintined iti natalged, nasayaat, ken mapagpannurrayan a serbisio ti transportasion.",
    privacy_commit_note:"Ti impormasion ti pasahero ket mausar laeng nga eksklusibo para kadagiti operasion a mainaig iti transportasion ken saan a pulos a mausar para iti personal a pagimbagan, marketing, advertising, komersial a panggep, wenno aniaman nga aktibidad a saan a mainaig iti panangitaginayon ken panangtarawidwid kadagiti serbisio ti publik a transportasion.",
    privacy_commit_compliance:"Amin nga impormasion ti pasahero ket tratuen a kompidensial ken maprotektaran a maitunos iti Data Privacy Act of 2012 (Republic Act No. 10173) ken dagiti pagalagadan nga insaad ti National Privacy Commission (NPC) ti Filipinas.",
    privacy_agree_label:"Nabasak ken umanamong ak iti Pannakaseguro ti Pribasidad",privacy_accept_btn:"Umanamong",privacy_scroll_hint:"I-scroll agingga iti gibus ti patakaran tapno ma-enable ti panagtulagan.",
    full_name:"Naan a Kompleto",email:"Email Address",phone:"Numero ti Telepono",date_of_birth:"Petsa ti Panagyanak",
    password:"Password",confirm_password:"Kumpirmaen ti Password",
    select_id_type:"Pumili ti Kita ti ID",loading_id_types:"Agkarga kadagiti kita ti ID...",id_type_error:"Di maiload dagiti kita ti ID",
    address:"Address",
    scan_upload:"I-scan / I-upload ti ID",autofill_details:"Auto-fill dagiti detalye babaen ti ID",
    continue:"Agpatuloy",back:"Agsubli",create:"Mangaramid",
    location_access:"Akses ti Lokasyon",allow_location:"Palubosan ti Lokasyon",
    create_account:"Mangaramid ti Account Mo!",getting_started:"Mangrugi tayo!",
    fill_information:"Pangngaasiyo ta punnoan dagiti impormasyon yo",
    discount_type:"Klase ti Diskwento",
    regular:"Regular",student:"Estudyante",senior_pwd:"Senior / PWD",teacher_nurse:"Maestra / Nurse",cooperative_member:"Kameng ti Kooperatiba",
    location_description:"Masapulmi ti lokasyon yo para kadagiti ruta ken pagsardengan."
  }
};

function applyLanguage(lang){
  currentLang=lang;
  document.querySelectorAll('[data-lang]').forEach(function(el){
    var key=el.getAttribute('data-lang');
    if(translations[lang]&&translations[lang][key]){
      el.innerText=translations[lang][key];
    }
  });
  // The OCR note is built in code, so re-render it in the new language.
  if(typeof renderOcrQualityNote==='function'){renderOcrQualityNote()}
}

document.addEventListener('DOMContentLoaded',function(){
  var langSelect=document.getElementById('languageSelect');
  if(langSelect){
    langSelect.addEventListener('change',function(){applyLanguage(this.value)});
  }
  var privacyLangSelect=document.getElementById('privacyLangSelect');
  if(privacyLangSelect){
    privacyLangSelect.addEventListener('change',function(){applyLanguage(this.value)});
  }
});

document.addEventListener('DOMContentLoaded',function(){
  var front=document.getElementById('id_front');
  var back=document.getElementById('id_back');
  console.log('Elements:',front,back);
  if(front)front.addEventListener('change',scanID);
  if(back)back.addEventListener('change',scanID);
});

// ID types are deterministically bound to passenger categories; legacy dropdown removed

function validateDateOfBirth(showEmpty){
  var el=document.getElementById('date_of_birth');
  if(!el)return true;
  var err=document.getElementById('date_of_birth_error');
  function showErr(msg){
    if(err){err.textContent=msg;err.style.display=msg?'block':'none';}
    el.style.borderColor=msg?'#e53935':'#c4b417';
  }
  var v=String(el.value||'').trim();
  if(!v){
    showErr(showEmpty?'Date of birth is required.':'');
    return !showEmpty;
  }
  if(!/^\d{4}-\d{2}-\d{2}$/.test(v)){
    showErr('Use YYYY-MM-DD format.');
    return false;
  }
  var y=Number(v.slice(0,4));
  var m=Number(v.slice(5,7));
  var d=Number(v.slice(8,10));
  var date=new Date(y,m-1,d);
  if(isNaN(date.getTime())||date.getFullYear()!==y||date.getMonth()+1!==m||date.getDate()!==d){
    showErr('Enter a valid calendar date.');
    return false;
  }
  if(date>new Date()){
    showErr('Date of birth cannot be in the future.');
    return false;
  }
  if(y<1900){
    showErr('Date of birth must be on or after 1900-01-01.');
    return false;
  }
  if(Math.floor((Date.now()-date.getTime())/(365.25*24*3600*1000))>120){
    showErr('Enter a valid date of birth.');
    return false;
  }
  showErr('');
  return true;
}

function initDateOfBirthField(){
  var el=document.getElementById('date_of_birth');
  if(!el)return;
  function formatDob(raw){
    var digits=String(raw||'').replace(/\D/g,'').slice(0,8);
    var out=digits;
    if(digits.length>4)out=digits.slice(0,4)+'-'+digits.slice(4);
    if(digits.length>6)out=digits.slice(0,4)+'-'+digits.slice(4,6)+'-'+digits.slice(6);
    return out;
  }
  el.addEventListener('input',function(){
    var formatted=formatDob(this.value);
    if(formatted!==this.value)this.value=formatted;
    if(String(this.value||'').length===10)validateDateOfBirth(false);
  });
  el.addEventListener('blur',function(){validateDateOfBirth(true);});
}

document.addEventListener('DOMContentLoaded',initDateOfBirthField);

document.getElementById('createAccountForm')&&document.getElementById('createAccountForm').addEventListener('submit',async function(e){
  e.preventDefault();

  if(!validateDateOfBirth(true)){
    showAlert('error','Invalid Date of Birth','Please check the date of birth field and try again.');
    var dobEl=document.getElementById('date_of_birth');
    if(dobEl&&dobEl.focus)dobEl.focus();
    return;
  }

  var paxInput = document.getElementById('passenger_type_input');
  var discountVal = paxInput ? paxInput.value : 'Regular';
  var idTypeInput = document.getElementById('id_type_input');
  var idTypeVal = idTypeInput ? idTypeInput.value : '';

  if(discountVal !== 'Regular'){
    if(!idTypeVal){
      showAlert('warning','ID Type Required','Please select a valid verification ID.');
      return;
    }
    var idInput = document.getElementById('student_id');
    var idVal = (idInput ? idInput.value : '').trim();
    var idLabelEl = document.getElementById('idNumberLabelText');
    var idLabel = idLabelEl ? idLabelEl.textContent : 'ID Number';

    if(idVal===''){
      showAlert('warning','ID Number Required','Please enter your '+idLabel+' to avail of the discount.');
      if(idInput&&idInput.focus)idInput.focus();
      return;
    }
    if(idVal.length<3||idVal.length>50||!/^[A-Za-z0-9][A-Za-z0-9 .\-\/]*$/.test(idVal)){
      showAlert('error','Invalid ID Number','Please enter a valid '+idLabel+' number (3-50 letters, numbers, hyphens, slashes, or periods).');
      if(idInput&&idInput.focus)idInput.focus();
      return;
    }

    var digitMatches=idVal.match(/\d/g);
    var digitCount=digitMatches?digitMatches.length:0;
    if(digitCount===0){
      showAlert('error','Invalid ID Number',idLabel+' must contain numeric digits (e.g., 2024-12345 or OSCA-12345). Letters alone are not accepted.');
      if(idInput&&idInput.focus)idInput.focus();
      return;
    }
    if(digitCount<3){
      showAlert('error','Invalid ID Number',idLabel+' must contain at least 3 numeric digits.');
      if(idInput&&idInput.focus)idInput.focus();
      return;
    }

    var digitsOnly=idVal.replace(/\D/g,'');
    var lettersOnly=idVal.replace(/[^A-Za-z]/g,'');
    if(/^(\d)\1{3,}$/.test(digitsOnly)||(lettersOnly===''&&['123456','1234567','12345678','123456789','0123456','987654321'].indexOf(digitsOnly)!==-1)){
      showAlert('error','Invalid ID Number','Please enter a valid, authentic '+idLabel+'.');
      if(idInput&&idInput.focus)idInput.focus();
      return;
    }

    if(/(?:asdf|ghjk|qwerty|zxcv)/i.test(idVal)){
      showAlert('error','Invalid ID Number','Please enter a valid '+idLabel+' without random keyboard letters.');
      if(idInput&&idInput.focus)idInput.focus();
      return;
    }

    var lowerId=idVal.toLowerCase();
    var dummyIds=['student','senior','pwd','special','id','id number','none','na','n/a','test','sample','asdf','qwerty','letters','random'];
    if(dummyIds.indexOf(lowerId)!==-1){
      showAlert('error','Invalid ID Number','Please enter your actual '+idLabel+'.');
      if(idInput&&idInput.focus)idInput.focus();
      return;
    }
  }

  var privacyAcceptedEl=document.getElementById('privacy_accepted');
  if(privacyAcceptedEl&&privacyAcceptedEl.value!=='1'){
    showAlert('warning','Privacy Policy Required','Please read and accept the Passenger Privacy Policy to continue.');
    var ps=document.getElementById('privacySection');
    if(ps){ps.style.display='flex';ps.classList.remove('fade-out');}
    return;
  }

  // Non-blocking busy state: sending the OTP can take a while (SMTP), so the
  // form must stay interactive instead of being covered by a modal.
  var submitBtn=this.querySelector('button[type="submit"]');
  var submitBtnHtml=submitBtn?submitBtn.innerHTML:'';
  if(submitBtn){submitBtn.disabled=true;submitBtn.innerHTML='Sending OTP...';}
  var restoreSubmit=function(){
    if(submitBtn){submitBtn.disabled=false;submitBtn.innerHTML=submitBtnHtml;}
  };

  var formData=new FormData(this);
  formData.set('discount', discountVal);
  formData.set('id_type', idTypeVal);
  var storeTimer=null;
  try{
    var storeController=new AbortController();
    storeTimer=setTimeout(function(){storeController.abort()},75000);
    var response=await fetch('/passenger/register',{method:'POST',body:formData,credentials:'same-origin',signal:storeController.signal});
    var result=await response.text();
    var data;
    try{
      data=JSON.parse(result);
    }catch(err){
      console.error('Store response error:',result);
      showAlert('error','Registration Error','Unable to process registration at this time. Please try again.');
      return;
    }
    if(data.success){
      var sendingAlert=document.getElementById('customAlertOverlay');
      if(sendingAlert)sendingAlert.classList.remove('active');
      document.getElementById('otpModal').style.display='flex';
      loadOTPModal();
    }else{
      var regMsg = (data && data.message) ? data.message : 'Failed to process registration.';
      if (window.PassengerSession && typeof window.PassengerSession.sanitizeErrorMessage === 'function') {
        regMsg = window.PassengerSession.sanitizeErrorMessage(regMsg, 'Failed to process registration.');
      }
      showAlert('error','Registration Error', regMsg);
    }
  }catch(netErr){
    console.error('Registration fetch error:',netErr);
    if(netErr&&netErr.name==='AbortError'){
      showAlert('error','Request Timed Out','Sending the OTP took too long. Please check your connection and try again.');
    }else{
      showAlert('error','Network Error','Please check your connection and try again.');
    }
  }finally{
    if(storeTimer){clearTimeout(storeTimer)}
    restoreSubmit();
  }
});

async function loadOTPModal(){
  var alertOverlay=document.getElementById('customAlertOverlay');
  if(alertOverlay)alertOverlay.classList.remove('active');
  try{
    var response=await fetch('/passenger/register/otp');
    if(!response.ok){throw new Error('OTP modal request failed with status '+response.status);}
    var html=await response.text();
    if(html.indexOf('id="otpVerifyForm"')===-1){throw new Error('OTP modal content missing');}
    document.getElementById('otpModalContent').innerHTML=html;
    initializeOTPForm();
    initResendOtp();
  }catch(err){
    console.error('Error loading OTP modal:',err);
    showAlert('error','Error','Unable to load OTP verification modal. Please reload the page and try again.');
  }
}

function styleOtpInput(input,filled){
  if(filled){
    input.style.borderColor='#2e7d32';
    input.style.backgroundColor='#f4fbf5';
  }else{
    input.style.borderColor='#cdd9b5';
    input.style.backgroundColor='#fff';
  }
}

function initializeOTPForm(){
  var form=document.getElementById('otpVerifyForm');
  if(!form)return;

  if(!window.PassengerOtp)return;

  var controller=PassengerOtp.init({
    ids:['regOtp1','regOtp2','regOtp3','regOtp4','regOtp5','regOtp6'],
    combined:'regOtpCombined',
    onUpdate:function(code,inputs){
      for(var i=0;i<inputs.length;i++){
        styleOtpInput(inputs[i],!!inputs[i].value);
      }
    }
  });

  if(!controller)return;

  var inputs=controller.inputs;
  var pasteBtn=document.getElementById('pasteOtpBtn');

  inputs.forEach(function(input){
    input.addEventListener('focus',function(){
      this.select();
      this.style.borderColor='#2e7d32';
      this.style.boxShadow='0 0 0 3px rgba(46,125,50,0.18)';
    });

    input.addEventListener('blur',function(){
      this.style.boxShadow='none';
      if(!this.value){
        this.style.borderColor='#cdd9b5';
        this.style.backgroundColor='#fff';
      }
    });
  });

  if(pasteBtn){
    pasteBtn.addEventListener('click',async function(){
      try{
        if(navigator.clipboard&&navigator.clipboard.readText){
          var text=await navigator.clipboard.readText();
          if(text){
            controller.fill(text);
            return;
          }
        }
      }catch(err){
        console.warn('Clipboard read error, fallback to prompt:',err);
      }
      var entered=prompt('Paste your 6-digit OTP code here:');
      if(entered){
        controller.fill(entered);
      }
    });
  }

  form.addEventListener('submit',async function(e){
    e.preventDefault();
    var code=controller.getCode();
    controller.sync();

    if(code.length!==6){
      showAlert('warning','Incomplete Code','Please enter all 6 digits of the OTP.');
      for(var i=0;i<inputs.length;i++){
        if(!inputs[i].value){
          inputs[i].style.borderColor='#e53935';
          inputs[i].focus();
          break;
        }
      }
      return;
    }

    var submitBtn=document.getElementById('verifyOtpSubmitBtn');
    if(submitBtn){
      submitBtn.disabled=true;
      submitBtn.textContent='Verifying...';
      submitBtn.style.opacity='0.7';
    }

    showAlert('success','Verifying OTP','Please wait while we verify your account...');

    try{
      var formData=new FormData(this);
      formData.set('otp',code);
      for(var j=0;j<inputs.length;j++){
        formData.set('otp_digit_'+(j+1),inputs[j].value);
      }

      var response=await fetch('/passenger/passengers/verify-otp',{
        method:'POST',
        body:formData,
        credentials:'same-origin'
      });

      var rawText=await response.text();
      var data;
      try{
        data=JSON.parse(rawText);
      }catch(parseErr){
        console.error('Non-JSON response:',rawText);
        showAlert('error','Verification Error','Unable to verify OTP right now. Please try again.');
        if(submitBtn){submitBtn.disabled=false;submitBtn.textContent='Verify OTP';submitBtn.style.opacity='1';}
        return;
      }

      if(data.success){
        if(data.passenger_code){
          try{sessionStorage.setItem('registered_passenger_code',data.passenger_code)}catch(e){}
        }
        var msg=data.passenger_code
          ?(data.message+'\nYour Passenger Code: '+data.passenger_code)
          :(data.message||'Passenger account created successfully.');

        showAlert('success','Registration Successful',msg,function(){
          window.location.href=data.redirect||'/passenger/login';
        });

        setTimeout(function(){
          window.location.href=data.redirect||'/passenger/login';
        },2500);
      }else{
        if(submitBtn){submitBtn.disabled=false;submitBtn.textContent='Verify OTP';submitBtn.style.opacity='1';}
        var verifyMsg = (data && data.message) ? data.message : 'Invalid OTP. Please check the code.';
        if (window.PassengerSession && typeof window.PassengerSession.sanitizeErrorMessage === 'function') {
          verifyMsg = window.PassengerSession.sanitizeErrorMessage(verifyMsg, 'Invalid OTP. Please check the code.');
        }
        showAlert('error','Verification Failed', verifyMsg);
      }
    }catch(error){
      console.error(error);
      if(submitBtn){submitBtn.disabled=false;submitBtn.textContent='Verify OTP';submitBtn.style.opacity='1';}
      showAlert('error','Unexpected Error','An unexpected error occurred. Please try again.');
    }
  });
}

function initResendOtp(){
  var btn=document.getElementById('resendOtpBtn');
  var timer=document.getElementById('resendTimer');
  var cooldown=60;
  if(!btn)return;

  function startTimer(seconds){
    btn.disabled=true;
    btn.style.opacity='0.6';
    btn.style.cursor='not-allowed';
    var remaining=seconds;
    if(timer)timer.textContent='Resend in '+remaining+'s';
    var interval=setInterval(function(){
      remaining--;
      if(remaining<=0){
        clearInterval(interval);
        btn.disabled=false;
        btn.style.opacity='1';
        btn.style.cursor='pointer';
        if(timer)timer.textContent='';
      }else{
        if(timer)timer.textContent='Resend in '+remaining+'s';
      }
    },1000);
  }

  startTimer(cooldown);

  btn.addEventListener('click',async function(){
    btn.disabled=true;
    btn.style.opacity='0.6';
    if(timer)timer.textContent='Sending...';
    try{
      var res=await fetch('/passenger/passengers/resend-otp',{method:'POST',credentials:'same-origin'});
      var data=await res.json();
      if(data.success){
        for(var i=1;i<=6;i++){
          var inp=document.getElementById('regOtp'+i);
          if(inp){
            inp.value='';
            inp.style.borderColor='#cdd9b5';
            inp.style.backgroundColor='#fff';
          }
        }
        var combined=document.getElementById('regOtpCombined');
        if(combined)combined.value='';
        var firstInp=document.getElementById('regOtp1');
        if(firstInp)firstInp.focus();

        showAlert('success','OTP Resent',data.message||'A new OTP has been sent to your email.');
        startTimer(cooldown);
      }else{
        showAlert('error','Failed',data.message||'Could not resend OTP.');
        btn.disabled=false;
        btn.style.opacity='1';
        if(timer)timer.textContent='';
      }
    }catch(err){
      showAlert('error','Network Error','Please check your connection and try again.');
      btn.disabled=false;
      btn.style.opacity='1';
      if(timer)timer.textContent='';
    }
  });
}
</script>
