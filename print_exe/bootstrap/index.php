<?php
$roorPath = dirname(__FILE__);
include($roorPath.'/escpos/autoload.php');
include($roorPath.'/lib/Server.php');
include($roorPath.'/lib/Dayin.php');

$server_socket = new Server_socket('127.0.0.1',8008,1000);  
$server_socket->start();
?>