#!/usr/bin/php
<?php

set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/local/mgr5/include/php");
define('__MODULE__', "pmparkingerip");
define('ALLOWED_TIME_DELTA', 300);

require_once 'bill_util.php';

$param = CgiInput(true);

$account = 9999999997 - $param["account"]; //tmp
$status = $param["status"];
$amount = $param["amount"];
$time = $param["time"];
$hmac = $param["hmac"];

/* $out_xml = simplexml_load_string("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<result/>\n"); */

//TODO проверка идентификатора услуги

// Проверка HMAC
if ( ! VerifyHMAC($param) ) {
   Error("HMAC неверен. Параметры запроса:" . print_r($param, true));
   http_response_code(403);
   $out_xml->addChild("status_code", "403");
   echo $out_xml->asXML();
//    Debug("out: ". $out_xml->asXML());
   die(1);

}

if ( $status != 1  ) { //обрабатываются только что оплаченные платежи
    echo "Content-Type: text/xml\n\n";
    die(0);
}

$user = GetUserByAccount($account);

if ( empty($user) ) {
    Error("Попытка зачислить средства на счет несуществующего пользователя. Параметры запроса: " . print_r($param, true));
    http_response_code(422);
    $out_xml->addChild("status_code", "422");
    echo $out_xml->asXML();
//     Debug("out: ". $out_xml->asXML());
    die(1);

}

$paymethodId = GetEripPaymethodId();
$payment = LocalQuery('payment.add', array('paymethod' => $paymethodId, 'amount' => $amount, 'sok' => 'yes', 'su' => $user['name'], ));
$payed = LocalQuery('payment.setpaid', array('elid' => $payment->payment_id,));

if ( isset($payed->ok) && $payed->tparams->elid ) {
    echo "Content-Type: text/xml\n\n";
//     Debug("Оплата прошла успешно. Параметры запроса:"  . print_r($param, true));
    die(0);
} else {
    Error("Не удалось провести платеж. Параметры запроса: " . print_r($param, true));
    http_response_code(500);
    $out_xml->addChild("status_code", "500");
    echo $out_xml->asXML();
    die(1);
}
                                                                                                                         

?>
