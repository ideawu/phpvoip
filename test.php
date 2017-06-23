<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();

include_once('UdpLink.php');
include_once('SIP.php');

$ip = '127.0.0.1';
$ip = '172.26.0.96';
$link = UdpLink::listen($ip);


$sip = new SipAgent();
$sip->proxy_ip = '172.26.0.96';
$sip->proxy_ip = '172.16.10.100';
$sip->proxy_port = 5060;
#$sip->domain = 'alice.com';
#$sip->register('1001', '1000');
$sip->register('221', '123456');

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
		$buf = $msg->encode();
		
		Logger::debug("send " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason) . ' ' . $msg->from);
		#echo '  > ' . str_replace("\n", "\n  > ", trim($buf)) . "\n\n";
		
		$link->sendto($msg->encode(), $sip->proxy_ip, $sip->proxy_port);
	}
	
	
	$read = array();
	$write = array();
	$except = array();
	
	$read[] = $link->sock;

	if($msgs){
		$timeout = 0;
	}else{
		$timeout = 100*1000;
	}
	$ret = @socket_select($read, $write, $except, 0, $timeout);
	if($ret === false){
		Logger::error(socket_strerror(socket_last_error()));
		return false;
	}
	#Logger::debug('');
	
	if($read){
		$buf = $link->recvfrom($ip, $port);
		$msg = new SipMessage();
		if($msg->decode($buf) > 0){
			Logger::debug("recv " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason) . ' ' . $msg->from);
			#echo '  < ' . str_replace("\n", "\n  < ", trim($buf)) . "\n\n";
			$sip->incomming($msg);
		}
	}
}
