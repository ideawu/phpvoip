<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();

include_once('UdpLink.php');

if($argv[1]){
	$port = intval($argv[1]);
}else{
	$port = 5060;
}

$link = UdpLink::listen('127.0.0.1', $port);

while(1){
	$ip = null;
	$port = null;
	$data = $link->recvfrom($ip, $port);
	Logger::debug("recv from $ip:$port");
	echo $data . "\n";
}
