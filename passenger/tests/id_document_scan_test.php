<?php
declare(strict_types=1);
// Pure extraction/assessment checks for the ID scan pipeline. The scanner is
// included in library mode so no HTTP response, upload or OCR network call is
// made: only the parsing functions run against fixture text.
define('SCAN_ID_LIB_ONLY', true);
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../controllers/Create_Account/scanID.php';

$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}

$y = (int) date('Y');
$y1 = $y + 1;
$y2 = $y + 2;

// ---- Student ID: school vs name separation and full detail extraction ----
$student = implode("\n", [
    'UNIVERSITY OF SAINT LOUIS',
    'STUDENT ID',
    'NAME: DELA CRUZ, MARIA A.',
    'STUDENT NO: 2023-00123',
    'COURSE: BS ACCOUNTANCY',
    "SCHOOL YEAR: {$y}-{$y1}",
    "VALID UNTIL: JUNE 30, {$y2}",
]);
$s = parse_document('student-id', [$student], [''], $student, 'Student', 90);

check(stripos($s['full_name'], 'University') === false && $s['full_name'] === 'Dela Cruz, Maria A.', 'Student name must not be the school name');
check(stripos($s['school'], 'University') !== false && stripos($s['school'], 'Dela Cruz') === false, 'School name is extracted separately from the person name');
check($s['student_number'] === '2023-00123', 'Student number extraction');
check($s['school_year'] === "{$y}-{$y1}", 'School year extraction from the S.Y. label');
check($s['validity_until'] === "{$y2}-06-30", 'Validity date extraction supports future dates');
check($s['verification'] === 'pending', 'A clean student document is suggested as pending, never verified');
check(in_array('Confirm current enrollment before approving the student discount.', $s['review_flags'], true), 'Student review requires confirming current enrollment');

// ---- A specific school must outrank a parent organisation / umbrella brand ----
check(extract_school("PHINMA EDUCATION\nARAULLO UNIVERSITY") === 'Araullo University', 'extract_school prefers a specific institution over the parent organisation');
check(extract_school("DEPARTMENT OF EDUCATION\nMALIGAYA ELEMENTARY SCHOOL") === 'Maligaya Elementary School', 'extract_school prefers the school over the DepEd umbrella');
check(extract_school('PHINMA EDUCATION') === 'Phinma Education', 'extract_school still falls back to the umbrella brand when it is the only line');
check(extract_school('SCHOOL: ARAULLO UNIVERSITY') === 'Araullo University', 'A leading SCHOOL: label is stripped from the extracted name');

$phinma = implode("\n", [
    'PHINMA EDUCATION',
    'ARAULLO UNIVERSITY',
    'STUDENT ID',
    'NAME: ABRENICA, MARVHIL S.',
    'STUDENT NO: 01-2324-043373',
    "SCHOOL YEAR: {$y}-{$y1}",
]);
$p = parse_document('student-id', [$phinma], [''], $phinma, 'Student', 90);
check(stripos($p['school'], 'Araullo') !== false && stripos($p['school'], 'PHINMA') === false, 'The parsed school is the specific university, not the parent organisation');

$deped = implode("\n", [
    'DEPARTMENT OF EDUCATION',
    'MALIGAYA ELEMENTARY SCHOOL',
    'NAME: SANTOS, JUAN D.',
    'LRN: 123456789012',
]);
$d = parse_document('student-id', [$deped], [''], $deped, 'Student', 90);
check(stripos($d['school'], 'Maligaya') !== false, 'The parsed school is the specific elementary school, not the DepEd umbrella');

// Expiry values must never be mistaken for a date of birth.
$noDobStudent = implode("\n", ['COLLEGE OF SAINT MARY', 'STUDENT ID', 'NAME: GARCIA, LEO P.', 'STUDENT NO: 2024-00987', "SCHOOL YEAR: {$y}-{$y1}", "VALID UNTIL: DECEMBER 31, {$y2}"]);
$n = parse_document('student-id', [$noDobStudent], [''], $noDobStudent, 'Student', 90);
check($n['date_of_birth'] === '', 'An expiry date is not reported as the date of birth');

// Outdated validity / school year are flagged as expired for review.
$oldStudent = implode("\n", ['SCHOOL OF ARTS', 'STUDENT ID', 'NAME: LIM, JOSE M.', 'STUDENT NO: 1111-222', 'SCHOOL YEAR: 2018-2019', 'VALID UNTIL: JANUARY 1, 2019']);
$o = parse_document('student-id', [$oldStudent], [''], $oldStudent, 'Student', 90);
check($o['verification'] === 'expired', 'Outdated student ID or school year is flagged as expired');

// Unclear student documents go to needs_review.
$vagueStudent = implode("\n", ['STUDENT ID', 'NAME: TAN, BEN C.']);
$v = parse_document('student-id', [$vagueStudent], [''], $vagueStudent, 'Student', 90);
check($v['verification'] === 'needs_review', 'Student document missing school details needs review');

// ---- School year must never be treated as the ID validity ----
$syOnly = extract_validity_dates("SCHOOL YEAR: {$y}-{$y1}");
check($syOnly['from'] === '' && $syOnly['until'] === '', 'A school year is not the ID validity period');
$rangeValidity = extract_validity_dates("VALID ID {$y}-" . ($y + 4));
check($rangeValidity['until'] === ($y + 4) . '-12-31', 'A multi-year validity range is supported');

// ---- Senior Citizen ----
$senior = implode("\n", ['SENIOR CITIZEN ID', 'OSCA ID NO: 1234-5678', 'NAME: SANTOS, JUAN D.', 'DATE OF BIRTH: JANUARY 1, 1960', 'ADDRESS: PUROK 2, BRGY POBLACION']);
$sn = parse_document('senior-citizen-id', [$senior], [''], $senior, 'Senior Citizen', 90);
check($sn['verification'] === 'pending', 'Senior with a valid birth date is fine without an expiry date');
check($sn['id_number'] === '1234-5678', 'OSCA number extraction');
check($sn['date_of_birth'] === '1960-01-01', 'Senior date of birth extraction');
foreach ($sn['review_flags'] as $flag) {
    check(stripos($flag, 'expiry') === false && stripos($flag, 'expire') === false, 'Senior review never demands an expiry date');
}

$underage = implode("\n", ['SENIOR CITIZEN ID', 'OSCA NO: 9999-0000', 'NAME: YOUNG, PEDRO A.', 'DATE OF BIRTH: JANUARY 1, 1990']);
$un = parse_document('senior-citizen-id', [$underage], [''], $underage, 'Senior Citizen', 90);
check($un['verification'] === 'needs_review' && !empty(array_filter($un['review_flags'], static fn($f) => stripos($f, 'below 60') !== false)), 'Senior under 60 is flagged for manual review');

// ---- PWD ----
$pwd = implode("\n", ['PWD ID', 'PWD NO: 1234-5678', 'NAME: REYES, ANA B.']);
$p = parse_document('pwd-id', [$pwd], [''], $pwd, 'PWD', 90);
check($p['id_number'] === '1234-5678', 'PWD number extraction across label lines');
check($p['verification'] === 'pending', 'A PWD ID without an expiry date is not rejected');
check(!empty(array_filter($p['review_flags'], static fn($f) => stripos($f, 'No expiry date') !== false)), 'PWD without expiry is noted for manual verification');
check(empty($p['disability_type'] ?? null) && empty($p['medical_info'] ?? null), 'No medical or disability information is collected');

$expiredPwd = implode("\n", ['PWD ID NO: 5555-6666', 'NAME: CRUZ, ELI F.', 'VALID UNTIL: JANUARY 1, 2020']);
$ep = parse_document('pwd-id', [$expiredPwd], [''], $expiredPwd, 'PWD', 90);
check($ep['verification'] === 'expired', 'An expired PWD ID is flagged as expired');

// ---- Regular passenger needs no document assessment ----
$regular = parse_document('student-id', [''], [''], '', 'Regular', 0);
check($regular['verification'] === 'needs_review' || $regular['verification'] === 'pending', 'Scanner assessment only ever suggests pending/needs_review/expired');
foreach (['student-id', 'senior-citizen-id', 'pwd-id'] as $type) {
    check(assess_document($type, ['full_name' => 'X Y', 'id_number' => '1', 'date_of_birth' => '1960-01-01', 'student_number' => '1', 'school' => 'X', 'school_year' => '2000-2001', 'validity_from' => '', 'validity_until' => '2030-01-01', 'age' => 66, 'low_quality' => false], date('Y-m-d'))['verification'] !== 'verified', "$type assessment never auto-verifies");
}

// PWD and Standard share no discount shortcut.
check(discount_from_flags(['PWD ID'], '') === 'PWD', 'PWD documents resolve to the PWD discount, not Senior');
check(discount_from_flags(['Student ID'], '') === 'Student', 'Student documents resolve to the Student discount');

// ---- Reported label-less layout: school must not become the name ----
$labelLess = implode("\n", [
    'UNIVERSITY OF PANGASINAN',
    'JUAN DELA CRUZ',
    'BS INFORMATION TECHNOLOGY',
    '2026-2030',
]);
$ll = parse_document('student-id', [$labelLess], [''], $labelLess, 'Student', 95);
check($ll['full_name'] === 'Juan Dela Cruz', 'Label-less layout: the person name is selected');
check(stripos($ll['school'], 'University') !== false && stripos($ll['school'], 'Juan') === false, 'Label-less layout: school extracted separately from the name');
check($ll['course'] === 'BS INFORMATION TECHNOLOGY', 'Label-less layout: course detected without a label');
check(($ll['validity_until'] ?? '') === '2030-12-31', 'Label-less layout: multi-year range read as the validity period');
check($ll['verification'] !== 'verified', 'Label-less layout still requires administrator verification');

// A school line placed first with a keyword is never chosen as the name.
$schoolTop = implode("\n", ['PANGASINAN STATE UNIVERSITY', 'MARIA SANTOS', 'BS INFORMATION TECHNOLOGY']);
$st = parse_document('student-id', [$schoolTop], [''], $schoolTop, 'Student', 95);
check($st['full_name'] === 'Maria Santos', 'School line cannot win the name slot');

// Ambiguous name candidates go to manual review instead of a wrong answer.
$tie = implode("\n", ['SAINT LOUIS ANNEX', 'JUAN DELA CRUZ']);
$tieParsed = parse_document('student-id', [$tie], [''], $tie, 'Student', 95);
check($tieParsed['full_name'] === '', 'Ambiguous name candidates are withheld for review');
check($tieParsed['verification'] === 'needs_review', 'Ambiguous name is flagged for manual review');

// A NAME label whose value could not be read must not fall back to the school.
$mangled = implode("\n", ['STUDENT ID', 'NAME:', 'UNIVERSITY OF PANGASINAN', 'JUAN DELA CRUZ']);
$mg = parse_document('student-id', [$mangled], [''], $mangled, 'Student', 95);
check($mg['full_name'] === '', 'Unreadable NAME value is not replaced with the school name');

// ---- Course extraction guards ----
check(extract_course('BS INFORMATION TECHNOLOGY') === 'BS INFORMATION TECHNOLOGY', 'Course extraction from a label-less line');
check(extract_course('UNIVERSITY OF PANGASINAN') === '', 'School line is not reported as a course');

// ---- Multi-year and en-dash validity ----
check(extract_validity_dates('VALID UNTIL 2026–2030')['until'] === '2030-12-31', 'Multi-year validity with an en dash is supported');
check(extract_validity_dates('VALID UNTIL 2026 – 2030')['until'] === '2030-12-31', 'Spaced en-dash validity range is supported');

// ---- Document mismatch: correct documents accepted, wrong ones rejected ----
check(check_id_document_mismatch('student-id', 'Student', ['Student ID', 'Barangay ID']) === null, 'Student ID accepted when another keyword is also detected');
check(check_id_document_mismatch('senior-citizen-id', 'Senior Citizen', ['PhilSys / National ID', 'Senior Citizen ID']) === null, 'Senior ID accepted when PhilSys is also detected');
check(check_id_document_mismatch('student-id', 'Student', ["Driver's License"]) !== null, 'A clearly different document is still rejected');
check(check_id_document_mismatch('pwd-id', 'PWD', ['Barangay ID']) !== null, 'PWD still requires a PWD ID');

// ---- Provider responses: throttling and exit codes ----
$throttled = ocr_space_parse('{"error":"E581: Free OCR API overloaded currently, so your free ocr api key is throttled.","details":"Temporary condition. Please retry in a few minutes."}');
check($throttled['text'] === '' && ocr_error_is_throttle($throttled['error']) && ocr_error_is_limit($throttled['error']), 'Throttled provider response is recognised as a service condition');
$normalOcr = ocr_space_parse('{"OCRExitCode":1,"ParsedResults":[{"ParsedText":"JUAN DELA CRUZ"}]}');
check($normalOcr['text'] === 'JUAN DELA CRUZ' && $normalOcr['error'] === null, 'Normal provider response parses');
$exit99 = ocr_space_parse('{"OCRExitCode":99,"ErrorMessage":"Image is not valid"}');
check($exit99['error'] !== null, 'Non-success provider exit codes surface as errors');

// ---- OCR key override ----
putenv('OCR_SPACE_KEY=TESTKEY123');
check(ocr_space_key() === 'TESTKEY123', 'Environment OCR key override is used');
putenv('OCR_SPACE_KEY');

// ---- Real PHINMA Education student ID layout (from an actual upload) ----
$phinma = implode("\n", [
    'PHINMA EDUCATION',
    'MARVHIL S. ABRENICA',
    '01-2324-043373',
    'BS in Information Technology',
    'Valid for SY:2023-2027',
]);
$ph = parse_document('student-id', [$phinma], [''], $phinma, 'Student', 95);
check($ph['full_name'] === 'Marvhil S. Abrenica', 'PHINMA layout: the student name is extracted, not the school');
check(stripos($ph['school'], 'Phinma') !== false && stripos($ph['full_name'], 'Phinma') === false, 'PHINMA layout: school is PHINMA Education, separate from the name');
check($ph['student_number'] === '01-2324-043373', 'PHINMA layout: student number format 01-2324-043373');
check($ph['school_year'] === '2023-2027', 'PHINMA layout: SY:2023-2027 is read as the school year');
check($ph['validity_until'] === '' || $ph['validity_until'] === null, 'PHINMA layout: school year is not misread as the expiry date');
check($ph['course'] !== '' && stripos($ph['course'], 'Information Technology') !== false, 'PHINMA layout: course detected');
check($ph['verification'] !== 'verified', 'PHINMA layout still requires administrator verification');

// An EDUCATION keyword alone classifies the card as a student document.
check(in_array('Student ID', detect_type_flags(strtoupper($phinma)), true), 'EDUCATION keyword classifies the document as a Student ID');
check(extract_school('PHINMA EDUCATION') !== '', 'EDUCATION is recognised as a school keyword');
check(extract_name('PHINMA EDUCATION') === '', 'A school line is never returned as a person name');

// ---- Server-side discount auto-verification ----

// Name/date/id comparators.
check(ocr_fields_agree_name('MARVHIL S. ABRENICA', 'Marvhil Abrenica'), 'Name agreement tolerates a missing middle initial');
check(ocr_fields_agree_name('DELA CRUZ, MARIA A.', 'Maria Dela Cruz'), 'Name agreement ignores order and punctuation');
check(!ocr_fields_agree_name('JUAN CRUZ', 'MARIA CRUZ'), 'A shared surname alone is not a name match');
check(!ocr_fields_agree_name('SANTOS', 'JUAN SANTOS'), 'A lone OCR token cannot cover a fuller declared name');

check(ocr_normalize_date('2005-09-15') === '2005-09-15', 'Y-m-d date normalization');
check(ocr_normalize_date('15/09/2005') === '2005-09-15', 'D/M/Y date normalization');
check(ocr_normalize_date('20050915') === '2005-09-15', 'YYYYMMDD date normalization');
check(ocr_normalize_date('15092005') === '2005-09-15', 'DDMMYYYY date normalization');
check(ocr_normalize_date('garbage') === null, 'Unparseable dates resolve to null');

check(ocr_identity_number('01-2324-043373') === '012324043373', 'ID numbers normalize to alphanumerics');
check(ocr_ids_agree('01-2324-043373', '2324-043373'), 'ID agreement accepts near-identical containment');
check(!ocr_ids_agree('2023-00123', '2023-04567'), 'ID agreement rejects a different number');
check(!ocr_ids_agree('', '2023-00123'), 'ID agreement rejects a missing declared number');

// A stored image is required: missing files stay pending, never verified.
$missing = resolve_auto_verification('', ['id_type' => 'student-id', 'discount' => 'Student']);
check($missing['checked'] === false && $missing['status'] === 'pending', 'A missing document cannot be auto-verified');

$verifyImage = tempnam(sys_get_temp_dir(), 'rconnect_idverify_');
file_put_contents($verifyImage, 'fixture');

$cleanStudent = implode("\n", ['PHINMA EDUCATION', 'MARVHIL S. ABRENICA', '01-2324-043373', 'BS in Information Technology', 'Valid for SY:2023-2027']);
$provideClean = static fn(string $p): array => ['text' => $cleanStudent, 'error' => null, 'score' => 95];

$studentAuto = resolve_auto_verification($verifyImage, [
    'id_type' => 'student-id', 'discount' => 'Student',
    'full_name' => 'Marvhil S. Abrenica', 'date_of_birth' => '', 'student_id' => '01-2324-043373',
], $provideClean);
check($studentAuto['checked'] === true && $studentAuto['status'] === 'verified', 'A clean, matching student document auto-verifies');

$nameMismatch = resolve_auto_verification($verifyImage, [
    'id_type' => 'student-id', 'discount' => 'Student',
    'full_name' => 'Juan Dela Cruz', 'date_of_birth' => '', 'student_id' => '01-2324-043373',
], $provideClean);
check($nameMismatch['status'] === 'needs_review', 'A name mismatch blocks auto-verification');

$idMismatch = resolve_auto_verification($verifyImage, [
    'id_type' => 'student-id', 'discount' => 'Student',
    'full_name' => 'Marvhil S. Abrenica', 'date_of_birth' => '', 'student_id' => '99-9999-99999',
], $provideClean);
check($idMismatch['status'] === 'needs_review', 'An ID number mismatch blocks auto-verification');

$unreadable = resolve_auto_verification($verifyImage, [
    'id_type' => 'student-id', 'discount' => 'Student', 'full_name' => 'X Y', 'student_id' => '1',
], static fn(string $p): array => ['text' => '', 'error' => null]);
check($unreadable['checked'] === false && $unreadable['status'] === 'pending', 'An unreadable document stays pending for review');

$providerDown = resolve_auto_verification($verifyImage, [
    'id_type' => 'student-id', 'discount' => 'Student', 'full_name' => 'X Y', 'student_id' => '1',
], static fn(string $p): array => ['text' => '', 'error' => 'service busy']);
check($providerDown['checked'] === false && $providerDown['status'] === 'pending', 'OCR outages never grant a discount');

$seniorText = implode("\n", ['SENIOR CITIZEN ID', 'OSCA ID NO: 1234-5678', 'NAME: SANTOS, JUAN D.', 'DATE OF BIRTH: JANUARY 1, 1960']);
$seniorAuto = resolve_auto_verification($verifyImage, [
    'id_type' => 'senior-citizen-id', 'discount' => 'Senior',
    'full_name' => 'SANTOS, JUAN D.', 'date_of_birth' => '1960-01-01', 'student_id' => '1234-5678',
], static fn(string $p): array => ['text' => $seniorText, 'error' => null, 'score' => 95]);
check($seniorAuto['status'] === 'verified', 'A valid senior document auto-verifies');

$underageText = implode("\n", ['SENIOR CITIZEN ID', 'OSCA NO: 9999-0000', 'NAME: YOUNG, PEDRO A.', 'DATE OF BIRTH: JANUARY 1, 1990']);
$underageAuto = resolve_auto_verification($verifyImage, [
    'id_type' => 'senior-citizen-id', 'discount' => 'Senior',
    'full_name' => 'YOUNG, PEDRO A.', 'date_of_birth' => '1990-01-01', 'student_id' => '9999-0000',
], static fn(string $p): array => ['text' => $underageText, 'error' => null, 'score' => 95]);
check($underageAuto['status'] === 'needs_review', 'A senior below 60 does not auto-verify');

// ---- Both sides are combined: a detail only on the back still verifies ----
$backImage = tempnam(sys_get_temp_dir(), 'rconnect_idback_');
file_put_contents($backImage, 'fixture');

$frontIdentity = implode("\n", ['PHINMA EDUCATION', '01-2324-043373']);
$backNameAndSchool = implode("\n", ['MARVHIL S. ABRENICA', 'BS in Information Technology']);
$provideSides = static fn(string $p): array => [
    'text' => (realpath($p) === realpath($verifyImage)) ? $frontIdentity : $backNameAndSchool,
    'error' => null,
    'score' => 95,
];

$sidedStudent = resolve_auto_verification($verifyImage, [
    'id_type' => 'student-id', 'discount' => 'Student',
    'full_name' => 'Marvhil S. Abrenica', 'date_of_birth' => '', 'student_id' => '01-2324-043373',
], $provideSides, $backImage);
check($sidedStudent['checked'] === true && $sidedStudent['status'] === 'verified', 'A name found only on the back side still auto-verifies');

// The same fields combine inside parse_document itself.
$sideParsed = parse_document('student-id', [$frontIdentity], [$backNameAndSchool], $frontIdentity . "\n" . $backNameAndSchool, 'Student', 95);
check($sideParsed['full_name'] === 'Marvhil S. Abrenica', 'Both sides: name is read from the back when the front carries only the number');
check(stripos($sideParsed['school'], 'Phinma') !== false, 'Both sides: school text is combined from both sides');

// An unreadable front is recovered from a readable back instead of being
// marked incomplete.
$backFullRead = implode("\n", ['PHINMA EDUCATION', 'MARVHIL S. ABRENICA', '01-2324-043373']);
$sidedFallback = resolve_auto_verification($verifyImage, [
    'id_type' => 'student-id', 'discount' => 'Student',
    'full_name' => 'Marvhil S. Abrenica', 'date_of_birth' => '', 'student_id' => '01-2324-043373',
], static fn(string $p): array => [
    'text' => (realpath($p) === realpath($verifyImage)) ? '' : $backFullRead,
    'error' => null,
    'score' => 95,
], $backImage);
check($sidedFallback['checked'] === true && $sidedFallback['status'] === 'verified', 'An unreadable front is verified when the back carries the details');

// A senior's birth date on the back side confirms seniority.
$frontSeniorSide = implode("\n", ['SENIOR CITIZEN ID', 'OSCA ID NO: 1234-5678']);
$backSeniorDob = implode("\n", ['DATE OF BIRTH: JANUARY 1, 1960', 'NAME: SANTOS, JUAN D.']);
$sidedSenior = resolve_auto_verification($verifyImage, [
    'id_type' => 'senior-citizen-id', 'discount' => 'Senior',
    'full_name' => 'SANTOS, JUAN D.', 'date_of_birth' => '1960-01-01', 'student_id' => '1234-5678',
], static fn(string $p): array => [
    'text' => (realpath($p) === realpath($verifyImage)) ? $frontSeniorSide : $backSeniorDob,
    'error' => null,
    'score' => 95,
], $backImage);
check($sidedSenior['status'] === 'verified', 'A senior DOB only on the back still confirms seniority');

// The same scanned bytes, saved under a new registration path, retain the
// enhanced OCR result without a second provider call at OTP confirmation.
file_put_contents($backImage, 'back-fixture-cache');
$_SESSION['passenger_document_ocr'] = [];
ocr_cache_document_side($verifyImage, [$frontIdentity], 95);
ocr_cache_document_side($backImage, [$backNameAndSchool], 95);
$cachedStudent = resolve_auto_verification($verifyImage, [
    'id_type' => 'student-id', 'discount' => 'Student',
    'full_name' => 'Marvhil S. Abrenica', 'date_of_birth' => '', 'student_id' => '01-2324-043373',
], null, $backImage);
check($cachedStudent['status'] === 'verified', 'OTP verifies exact previously scanned front and back without repeating OCR');
// Registration saves the scanned bytes at another filesystem path.
$savedFront = tempnam(sys_get_temp_dir(), 'rconnect_saved_front_');
$savedBack = tempnam(sys_get_temp_dir(), 'rconnect_saved_back_');
copy($verifyImage, $savedFront);
copy($backImage, $savedBack);
$otpSavedScan = resolve_auto_verification($savedFront, [
    'id_type' => 'student-id', 'discount' => 'Student',
    'full_name' => 'Marvhil S. Abrenica', 'student_id' => '01-2324-043373',
], null, $savedBack);
check($otpSavedScan['status'] === 'verified', 'Saved registration uploads reuse scanned evidence by content, not filename');
ocr_remember_verified_document($verifyImage, $backImage, [
    'id_type' => 'student-id', 'discount' => 'Student', 'full_name' => 'Marvhil S. Abrenica',
    'student_id' => '01-2324-043373', 'date_of_birth' => '',
], $cachedStudent);
unset($_SESSION['passenger_document_ocr']);
$acceptedScan = resolve_auto_verification($savedFront, [
    'id_type' => 'student-id', 'discount' => 'Student', 'full_name' => 'Marvhil S. Abrenica',
    'student_id' => '01-2324-043373',
], null, $savedBack);
check($acceptedScan['status'] === 'verified', 'Server-verified scan remains verified at OTP without another OCR parsing pass');
check(ocr_recall_verified_document($savedFront, $savedBack, [
    'id_type' => 'student-id', 'discount' => 'Student', 'full_name' => 'Different Person', 'student_id' => '01-2324-043373',
]) === null, 'Verified scan cannot approve a different submitted identity');
check(ocr_recall_verified_document($savedFront, $savedBack, [
    'id_type' => 'pwd-id', 'discount' => 'PWD', 'full_name' => 'Marvhil S. Abrenica', 'student_id' => '01-2324-043373',
]) === null, 'Verified scan cannot approve a different discount category');
unset($_SESSION['passenger_verified_scan']);
ocr_cache_document_side($verifyImage, [$frontIdentity], 95);
ocr_cache_document_side($backImage, [$backNameAndSchool], 95);
@unlink($savedFront);
@unlink($savedBack);
$cachedMismatch = resolve_auto_verification($verifyImage, [
    'id_type' => 'student-id', 'discount' => 'Student',
    'full_name' => 'Different Passenger', 'student_id' => '01-2324-043373',
], null, $backImage);
check($cachedMismatch['status'] === 'needs_review', 'Server scan cache still checks declared identity');
file_put_contents($backImage, 'replacement-image');
check(ocr_cached_document_side($backImage) === null, 'Different uploaded image cannot reuse an earlier scan');
$hash = hash_file('sha256', $verifyImage);
$_SESSION['passenger_document_ocr'][$hash]['expires'] = time() - 1;
check(ocr_cached_document_side($verifyImage) === null, 'Expired scan evidence is not reused');
unset($_SESSION['passenger_document_ocr']);

@unlink($backImage);
@unlink($verifyImage);

echo "$checks ID document scan checks passed.\n";
