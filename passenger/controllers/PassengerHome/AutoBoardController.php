<?php

class AutoBoardController{

    public function index(){

        if(
            !isset(
                $_SESSION['passenger_id']
            )
        ){

            header(
                'Location:/passenger/login'
            );

            exit;
        }

        require
            $_SERVER['DOCUMENT_ROOT'] .
            '/passenger/views/auto-board.php';

    }

}