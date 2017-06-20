<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();

include_once('UdpLink.php');

$link = UdpLink::listen('127.0.0.1', 5050);

while(1){
	$ip = null;
	$port = null;
	$data = $link->recvfrom($ip, $port);
	Logger::debug("recv from $ip:$port");
	echo $data . "\n";
}
