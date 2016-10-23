#!/usr/bin/php

<?php
set_include_path(get_include_path() . PATH_SEPARATOR . "/usr/local/mgr5/include/php");
define('__MODULE__', "easypay");

require_once 'bill_util.php'
    ;
echo "Content-Type: text/xml\n\n";

Debug('get params: ' . print_r($_GET, true));
?> 
