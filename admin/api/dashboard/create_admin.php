<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

header('Content-Type: application/json');

/* -----------------------------
LOAD DATABASE
----------------------------- */

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/Database.php';

require_once $_SERVER['DOCUMENT_ROOT'].'/admin/services/Validation/UserValidation.php';

/* -----------------------------
LOAD PHPMailer (MANUAL)
----------------------------- */

require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/PHPMailer/src/Exception.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/PHPMailer/src/PHPMailer.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/app/Core/PHPMailer/src/SMTP.php';

use App\Core\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* -----------------------------
RESPONSE FUNCTION
----------------------------- */

function respond($success,$message,$extra=[]){
    echo json_encode(array_merge([
        "success"=>$success,
        "message"=>$message
    ],$extra),JSON_PRETTY_PRINT);
    exit;
}

try{

/* -----------------------------
ALLOW SAFE GET
----------------------------- */

if($_SERVER['REQUEST_METHOD']==='GET'){
    respond(true,"API ready");
}

/* -----------------------------
DATABASE
----------------------------- */

$db = Database::get();

/* -----------------------------
FORM DATA
----------------------------- */

$username   = trim($_POST['username'] ?? '');
$email      = trim($_POST['email'] ?? '');
$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$phone      = trim($_POST['phone'] ?? '');
$department = trim($_POST['department'] ?? '');
$role       = $_POST['role'] ?? 'admin';
$password   = $_POST['password'] ?? '';
$address    = trim($_POST['address'] ?? '');
$bio        = trim($_POST['bio'] ?? '');

if(!$username || !$email || !$password){
    respond(false,"Required fields missing");
}

/* -----------------------------
SERVER-SIDE VALIDATION
----------------------------- */

foreach([
    uv_username($username),
    uv_email($email),
    uv_password($password,"Password",true),
    uv_enum($role,["admin","super_admin"],"role"),
    $first_name !== "" ? uv_name($first_name,"First name") : null,
    $last_name !== "" ? uv_name($last_name,"Last name") : null,
    $phone !== "" ? uv_phone($phone,"Phone") : null,
    uv_maxlen($department,100,"Department"),
    uv_maxlen($address,255,"Address"),
    uv_maxlen($bio,500,"Bio")
] as $validationError){
    if($validationError !== null){
        respond(false,$validationError);
    }
}

/* -----------------------------
CHECK DUPLICATE USER
----------------------------- */

$check = $db->prepare("
SELECT id,username,email
FROM users
WHERE LOWER(username)=LOWER(:username)
OR LOWER(email)=LOWER(:email)
LIMIT 1
");

$check->execute([
    ':username'=>$username,
    ':email'=>$email
]);

$existing = $check->fetch(PDO::FETCH_ASSOC);

if($existing){
    respond(false,"Username or email already exists",[
        "existing"=>$existing
    ]);
}

/* -----------------------------
PASSWORD HASH
----------------------------- */

$hash = password_hash($password,PASSWORD_BCRYPT);

/* -----------------------------
UPLOAD PROFILE PHOTO
----------------------------- */

$photoPath=null;

if(!empty($_FILES['profile_photo']['name'])){

    $dir=$_SERVER['DOCUMENT_ROOT'].'/uploads/admins/';

    if(!is_dir($dir)){
        mkdir($dir,0777,true);
    }

    $name=time().'_'.basename($_FILES['profile_photo']['name']);
    $target=$dir.$name;

    if(!move_uploaded_file($_FILES['profile_photo']['tmp_name'],$target)){
        respond(false,"File upload failed");
    }

    $photoPath='/uploads/admins/'.$name;
}

/* -----------------------------
INSERT ADMIN
----------------------------- */

$sql="INSERT INTO users
(username,first_name,last_name,email,phone,profile_photo,bio,address,password,role,department)
VALUES
(:username,:first_name,:last_name,:email,:phone,:profile_photo,:bio,:address,:password,:role,:department)";

$stmt=$db->prepare($sql);

$executed=$stmt->execute([
':username'=>$username,
':first_name'=>$first_name,
':last_name'=>$last_name,
':email'=>$email,
':phone'=>$phone,
':profile_photo'=>$photoPath,
':bio'=>$bio,
':address'=>$address,
':password'=>$hash,
':role'=>$role,
':department'=>$department
]);

if(!$executed){
    respond(false,"Database insert failed",[
        "error"=>$stmt->errorInfo()
    ]);
}

/* -----------------------------
SEND EMAIL
----------------------------- */

$emailStatus="Email skipped";

try{

$mail = new PHPMailer(true);

$mail->isSMTP();
$mail->Host       = 'smtp.hostinger.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'rconnect@rconnecttms.online';
$mail->Password   = 'MavyAbrenica#15';
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$mail->Port       = 465;

$mail->CharSet="UTF-8";

$mail->SMTPOptions=[
'ssl'=>[
'verify_peer'=>false,
'verify_peer_name'=>false,
'allow_self_signed'=>true
]
];

$mail->setFrom('rconnect@rconnecttms.online','System Administrator');

$mail->addAddress($email,$first_name.' '.$last_name);

$mail->isHTML(true);

$loginUrl="https://rconnecttms.online/public/login/";

$mail->Subject="Administrator Account Created";

$mail->Body = "

<div style='background:#f4f6f8;padding:40px 0;font-family:Poppins,Arial,sans-serif;'>

<div style='max-width:600px;margin:auto;background:#ffffff;border-radius:10px;
box-shadow:0 6px 20px rgba(0,0,0,0.08);overflow:hidden;'>

<!-- HEADER -->
<div style='background:linear-gradient(90deg,#0e7f00,#03580a);padding:25px;text-align:center;color:white;'>
<h2 style='margin:0;font-weight:600;'>R-Connect Management System</h2>
<p style='margin:5px 0 0;font-size:14px;opacity:0.9;'>Administrator Account Access</p>
</div>

<!-- BODY -->
<div style='padding:35px;color:#333;'>

<p style='font-size:16px;margin-bottom:20px;'>
Hello <strong>{$first_name}</strong>,
</p>

<p style='font-size:15px;line-height:1.6;margin-bottom:25px;'>
Your administrator account has been successfully created in the 
<strong>R-Connect Transport Management System</strong>.
You can now log in and start managing the system.
</p>

<!-- LOGIN BUTTON -->
<div style='text-align:center;margin:30px 0;'>

<a href='{$loginUrl}'
style='background:#0e7f00;
color:#ffffff;
padding:14px 28px;
font-size:15px;
font-weight:600;
text-decoration:none;
border-radius:6px;
display:inline-block;'>

Access R-Connect Dashboard

</a>

</div>

<!-- LOGIN DETAILS -->
<div style='background:#f8faf9;border:1px solid #e6eee8;border-radius:8px;padding:20px;margin-top:10px;'>

<p style='margin:0 0 8px;font-size:14px;color:#666;'>Username</p>

<div style='font-size:20px;font-weight:700;color:#0e7f00;margin-bottom:10px;'>
{$username}
</div>

<p style='margin:0;font-size:13px;color:#777;'>
Please change your password immediately after your first login.
</p>

</div>

</div>

<!-- FOOTER -->
<div style='background:#fafafa;border-top:1px solid #eee;padding:20px;text-align:center;font-size:12px;color:#888;'>

<p style='margin:0;'>R-Connect Transport Management System</p>

<p style='margin:6px 0 0;'>
If you did not expect this email, please contact your system administrator.
</p>

</div>

</div>

</div>

";

$mail->send();

$emailStatus="Email sent";

}catch(Exception $mailError){

$emailStatus="Email failed";
$emailError=$mailError->getMessage();

}

/* -----------------------------
SUCCESS RESPONSE
----------------------------- */

respond(true,"Admin created successfully",[
"username"=>$username,
"email"=>$email,
"email_status"=>$emailStatus,
"email_error"=>$emailError ?? null
]);

}catch(Throwable $e){

respond(false,"Fatal server error",[
"error"=>$e->getMessage(),
"file"=>$e->getFile(),
"line"=>$e->getLine()
]);

}