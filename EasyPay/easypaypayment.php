#!/usr/bin/php
<?php
set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/local/mgr5/include/php");
define('__MODULE__', "easypay");


require_once 'bill_util.php';

echo "Content-Type: text/html\n\n";

$client_ip = ClientIp();
$param = CgiInput();

//значения, получения которых ожидает изипэй (https://ssl.easypay.by/light/)
$info = LocalQuery("payment.info", array("elid" => $param["elid"], ));

$merno = (string)$info->payment->paymethod[1]->MER_NO;
$webkey = (string)$info->payment->paymethod[1]->WEB_KEY;
$id = (string)$info->payment[0]->id;
$sum = (string)$info->payment[0]->paymethodamount;
$description = iconv( 'UTF-8', 'cp1251', (string)$info->payment[0]->description);
$full_description = iconv('UTF-8', 'cp1251', 'Код клиента: ' .  (string)$info->payment[0]->user_id . PHP_EOL .
                  'Имя клиента: ' . (string)$info->payment[0]->userrealname . PHP_EOL .
                          'Email клиента: ' . (string)$info->payment[0]->userremail); 
                          
Debug("Desc : $full_description");
$customer_name = iconv( 'UTF-8', 'cp1251', 
$hash = md5( $merno . $webkey . $id . $sum );

$gateway_url = 'https://ssl.easypay.by/test/client_weborder.php';
$expire = 30;

$post_debug = 1;
$post_encoding = 'cp1251';



$page_text = <<<TXT
  <html>
	 <head>
    	 <meta http-equiv='Content-Type' content='text/html; charset=windows-1251'/>
	     <link rel='shortcut icon' href='billmgr.ico' type='image/x-icon' />
	</head>
	<body onload="document.easypayform.submit()">
	    <form name="easypayform" action="$gateway_url" method="post">
    	    <input type="hidden" name="EP_MerNo" value="$merno" />
	        <input type="hidden" name="EP_Expires" value="$expire" />
	        <input type="hidden" name="EP_Hash" value="$hash" />


	        <input type="hidden" name="EP_Debug" value="$post_debug" />


    		<input type="hidden" name="EP_OrderNo" value="$id" />
            <input type="hidden" name="EP_Comment" value="$description" />
		    <input type="hidden" name="EP_OrderInfo" value="$full_description" /> 
		    <input type="hidden" name="EP_Sum" value="$sum" />
            <input type="hidden" name="EP_Encoding" value="$post_encoding" />
	
	    </form>
     </body>
  </html>
TXT;

echo $page_text;


?>
