#!/usr/bin/php

<?php
//Поскольку billmgr не хочет регистрировать хэндлер события то приходится создавать такой воркэраунд - поллить  и еще раз поллить

include 'lib/JsonRPC/Client.php';

use JsonRPC\Client;

define('DEBUG', true);
define('__MODULE__', 'pmparkingerip');

define('USERS_FILENAME', '/usr/local/mgr5/addon/new-users-poll/users_with_bills');
define('ERIPAPI_ADDR', 'https://eripapi.parking.by');
define('API_CONN_TIMEOUT', 20);
                                                                                                                                                                                                                                                                       
define('AMOUNT', 0);                                                                                                                                                                                                                                                   
define('URL', 'https://panel.parking.by:1500/mancgi/parkingerippullresult.php');                                                                                                                                                                                       
define('CURRENCY_CODE', 933);                                                                                                                                                                                                                                          
                                                                                                                                                                                                                                                                       
date_default_timezone_set('Europe/Minsk');                                                                                                                                                                                                                             
                                                                                                                                                                                                                                                                       
$log_file = fopen("/usr/local/mgr5/var/". __MODULE__ .".log", "a");                                                                                                                                                                                                    
                                                                                                                                                                                                                                                                       
function Info($str) {                                                                                                                                                                                                                                                  
        global $log_file;                                                                                                                                                                                                                                              
        fwrite($log_file, date("M j H:i:s") ." [". getmypid() ."] ". __MODULE__ ." \033[1;33mINFO ". $str ."\033[0m\n");                                                                                                                                               
}                                                                                                                                                                                                                                                                      
                                                                                                                                                                                                                                                                       
function Error($str) {                                                                                                                                                                                                                                                 
        global $log_file;                                                                                                                                                                                                                                              
        fwrite($log_file, date("M j H:i:s") ." [". getmypid() ."] ". __MODULE__ ." \033[1;31mERROR ". $str ."\033[0m\n");                                                                                                                                              
}                                                                                                                                                                                                                                                                      
                                                                                                                                                                                                                                                                       
function LocalQuery($function, $param, $auth = NULL) {                                                                                                                                                                                                                 
        $cmd = "/usr/local/mgr5/sbin/mgrctl -m billmgr -o xml " . escapeshellarg($function) . " ";                                                                                                                                                                     
        foreach ($param as $key => $value) {                                                                                                                                                                                                                           
                $cmd .= escapeshellarg($key) . "=" . escapeshellarg($value) . ' ';                                                                                                                                                                                     
        }                                                                                                                                                                                                                                                              

        if ( !is_null($auth) ) {
                $cmd .= " auth=" . escapeshellarg($auth);
        }

        $out = array();
        exec($cmd, $out);
        $out_str = "";
        foreach ($out as $value) {
                $out_str .= $value . "\n";
        }

        return simplexml_load_string($out_str);
}

function GetEripPaymethodId() {
    $paymethods = LocalQuery('paymethod', array());

    foreach ( $paymethods as $paymethod ) {
        if ( __MODULE__ == (string)$paymethod->module[0] ) {
            $paymethodId = (string)$paymethod->id[0];
            break;
        }
    }

    return $paymethodId;
}

$users_xml_list = LocalQuery('user', array());
$users_with_bills = file(USERS_FILENAME, FILE_IGNORE_NEW_LINES);

$max_registered_user_id = (int)$users_xml_list->elem[count($users_xml_list->elem) - 1 ]->account_id;
$max_user_with_bill_id = (int) end( $users_with_bills);
//var_dump($max_registered_user_id); var_dump( $max_user_with_bill_id );

if ( $max_registered_user_id > $max_user_with_bill_id ) {
    $paymethodId = GetEripPaymethodId();
    if ( empty ( $paymethodId ) ) {
        Error('Unable to determine paymethod ID');
        die( 1 );
    }

    $paymethod = LocalQuery('paymethod.edit', array('elid' => $paymethodId, ));
    $api_user = (string)$paymethod->API_USER[0];
    $api_password = (string)$paymethod->API_PASSWORD[0];
    $secret_key = (string)$paymethod->API_KEY[0];
    $erip_id = (string)$paymethod->ERIP_ID[0];

    $client = new Client(ERIPAPI_ADDR, API_CONN_TIMEOUT);
    $client->debug = DEBUG;
    $client->ssl_verify_peer = false;

    $client->authentication($api_user, $api_password);
    
    for ( $i = 0; $i < count( $users_xml_list->elem ); $i++ ) {
        $account = (string)$users_xml_list->elem[$i]->account_id;
        Info(print_r($users_xml_list->elem[$i], true));
        //Проверяем, зарегистирован (имеет счет) в API пользователь с номером $account
        $user_registered = false;
        foreach ( $users_with_bills as $registered_user ) {
            if ( $registered_user == $account ) {
                $user_registered = true;
                break;
            }
        }

        if ( ! $user_registered ) {
            
            $erip_api_account = $account; //tmp
            $info = array('customerFullname' => (string) $users_xml_list->elem[$i]->realname[0],
                          'additionalInfo' => " E-mail: {$users_xml_list->elem[$i]->email}"
            );

            $time = time();
            $hmac = hash_hmac('sha512', $erip_id . $erip_api_account . AMOUNT . CURRENCY_CODE . implode($info) . URL . $time, $secret_key);

            try {
               $result = $client->createBill( [ 'eripID' => $erip_id, 'personalAccNum' => $erip_api_account, 'amount' => AMOUNT, 'currencyCode' => CURRENCY_CODE,  'info' => $info, 'callbackURL' => URL, 'time' => $time, 'hmac' => $hmac] );

               if ( $result > 0 ) {
                    file_put_contents( USERS_FILENAME, $account . PHP_EOL, FILE_APPEND);
                    Info("Bill for user with code $account created");
               }
            }catch (Exception $e) {
                Error('Error creating user bill: ' . $e->getMessage());
                continue;
            }
        }
    }
}
