<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();

include_once('UdpLink.php');
include_once('SIP.php');

$link = UdpLink::listen();


$sip = new SipAgent();
$sip->proxy_ip = '127.0.0.1';
$sip->proxy_port = 5060;
$sip->register('2001', '1000');

$time = microtime(1);
while(1){
	$old_time = $time;
	$time = microtime(1);
	$timespan = max(0, $time - $old_time);

	$msgs = $sip->outgoing($time, $timespan);
	foreach($msgs as $msg){
		$msg->ip = $link->local_ip;
		$msg->port = $link->local_port;
		$msg->username = $sip->username;
		echo $msg->encode() . "\n";
		
		$link->sendto($msg->encode(), $sip->proxy_ip, $sip->proxy_port);
	}
	
	
	$read = array();
	$write = array();
	$except = array();
	
	$read[] = $link->sock;

	$ret = @socket_select($read, $write, $except, 0, 100*1000);
	if($ret === false){
		Logger::error(socket_strerror(socket_last_error()));
		return false;
	}
	
	if($read){
		$ret = $link->recvfrom($ip, $port);
		var_dump($ret);
	}
}
