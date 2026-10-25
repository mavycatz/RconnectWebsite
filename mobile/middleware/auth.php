<?php

require_once __DIR__."/../utils/token.php";
require_once __DIR__."/../utils/response.php";

$headers = getallheaders();

if(!isset($headers['Authorization'])){
    jsonResponse(false,"Unauthorized");
}

$token = str_replace("Bearer ","",$headers['Authorization']);

$user = verifyToken($token);

if(!$user){
    jsonResponse(false,"Invalid token");
}