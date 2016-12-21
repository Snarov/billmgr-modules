<?php
/* 
	Честно взято с сайта EasyPay
*/


set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/local/mgr5/include/php");
define('__MODULE__', "easypay");


require_once 'bill_util.php';

$logfilepath	= '/usr/local/mgr5/var/pmeasypay.log'; //путь к файлу логирования
$web_key	= 'dh48djklhgl5893j'; //ключ, участвующий в электронной подписи сообщения 
$mer_no = 'ok3102';


$post_params = array (
	'order_mer_code' => 
		isset($_POST['order_mer_code']) ? $_POST['order_mer_code'] : '',	
	'sum' => 
		isset($_POST['sum']) ? $_POST['sum'] : '',	
	'mer_no' => 
		isset($_POST['mer_no']) ? $_POST['mer_no'] : '',	
	'card' => 
		isset($_POST['card']) ? $_POST['card'] : '',	
	'purch_date' => 
		isset($_POST['purch_date']) ? $_POST['purch_date'] : '',	
	'notify_signature' => 
		isset($_POST['notify_signature']) ? $_POST['notify_signature'] : '',
	'xml_data' => 
		isset($_POST['xml_data']) ? $_POST['xml_data'] : '',	
);

main ($logfilepath, $web_key, $post_params);

function main($logfilepath, $web_key, $post_params) {
	if($mer_no != $post_params['mer_no']) {
		$status = 'FAILED | wrong merchant number'; 
		header("HTTP/1.0 400 Bad Request");
		print $status;
	}
	
	// пример обработки ежесуточного реестра платежей
	if (isset($_POST['ep_notify_register'])) { // обработка реестра платежей
		$processed = ProcessDailyNotify($_POST['ep_notify_register']);
		if ($processed) { //реестр обработан
			$status = 'OK | the register is processed'; // статус обработки
			header("HTTP/1.0 200 OK"); // генерация HTTP-заголовка
			print $status; // формирование контента
		} else { //реестр не обработан
			$status = 'FAILED | the register is not processed'; 
			header("HTTP/1.0 400 Bad Request");
			print $status;
		}
	} else { // обработка уведомления
		//вычисляем электронную подпись и сравниваем с переданной
		$notify_signature = CreateAuthorizationKey ($web_key, $post_params);
		if($notify_signature == $post_params['notify_signature']) {
			//осуществляем обработку уведомления и логирование как пример
			$processed = ProcessNotify($post_params);
			if ($processed) { //уведомление обработано
				$status = 'OK | the notice is processed'; //статус обр.
				header("HTTP/1.0 200 OK"); // генерация HTTP-заголовка
				print $status; // формирование контента
			} else { //уведомление не обработано
				$status = 'FAILED | the notice is not processed'; 
				header("HTTP/1.0 400 Bad Request");
				print $status;
			}
		} else { //неверная электронная подпись
			$status = 'FAILED | incorrect digital signature'; 
			header("HTTP/1.0 400 Bad Request");
			print $status;
		}
		//подготовка параметров для логирования
		$params_line = '';
		foreach ($post_params as $key=>$value) {
			$params_line .= "$key=$value; ";
		}
		CreateLog($status, $params_line); //логирование
	}
}

function CreateAuthorizationKey ($web_key, $post_params) {//функция вычисления эл. подписи
	// правило вычисления: 
	// notify_signature = md5(order_mer_code. sum. mer_no. card. purch_date. web_key) 
	
	$hash = md5($post_params['order_mer_code'].$post_params['sum'].
	$post_params['mer_no'].$post_params['card'].$post_params['purch_date'].$web_key);
	
	return $hash;
}

function ProcessNotify($post_params) { // функция реализует логику обработки 

	$payed = LocalQuery('payment.setpaid', array('elid' => $post_params['order_mer_code'],));
	return isset($payed->ok) && $payed->tparams->elid;
}


function ProcessDailyNotify($post_params) { // функция реализует логику обработки реестра
	//TODO 
	return true;
}

function CreateLog ($status, $request) { //функция добавления записи в логфайл
	global $logfilepath;
	$date = date("d.m.Y H:i:s");
	$str = $date."\t".$status."\n\t"."ORDER_INFORMATION: ".$request."\n";
	$log = fopen($logfilepath, 'a');
	if($log) {
		fwrite($log, $str);
		fclose($log);
	}
}

?>
