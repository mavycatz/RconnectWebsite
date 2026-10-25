<?php

class Permission
{
    public static function allow(
        array $roles
    )
    {
        session_start();

        if(
            !isset(
                $_SESSION['role']
            )
        ){
            header(
                'Location:/public/login/index.php'
            );
            exit;
        }

        if(
            !in_array(
                $_SESSION['role'],
                $roles
            )
        ){
            http_response_code(403);

            die(
                'Access Denied'
            );
        }
    }
}