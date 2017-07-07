<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();

include_once('SIP.php');

if($argv[1]){
	$port = intval($argv[1]);
}else{
	$port = 5070;
}

$ip = '0.0.0.0';
$link = SipLink::listen($ip, 0);

$msg = new SipMessage();
$msg->method = 'INVITE';
$msg->dst_ip = '127.0.0.1';
$msg->dst_port = $port;
$msg->from = new SipContact(1);
$msg->to = new SipContact(2);

$link->send($msg);

