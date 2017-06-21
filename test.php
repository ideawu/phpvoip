<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();

include_once('UdpLink.php');
include_once('SIP.php');

// $str = <<<EOT
// REGISTER sip:127.0.0.1 SIP/2.0
// From: "2001" <sip:2001@127.0.0.1>;tag=tag_40846_1497969530.985607
// To: "2001" <sip:2001@127.0.0.1>
// Call-ID: call_21043_1497969530.985595
// CSeq: 24134 REGISTER
// Via: SIP/2.0/UDP 127.0.0.1:63862;rport;branch=z9hG4bK_28176_1497969530.985612
// Contact: <sip:2001@127.0.0.1:63862>
// User-Agent: phpvoip
// Content-Length: 1
//
// a
// EOT;
//
// $msg = new SipMessage();
// $msg->decode($str);
// echo $str . "\n\n";
// echo $msg->encode() . "\n";
// die();

$link = UdpLink::listen();


$sip = new SipAgent();
#$sip->domain = 'alice.com';
$sip->proxy_ip = '172.26.0.96';
$sip->proxy_port = 5060;
$sip->register('1001', '1000');

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
		
		Logger::debug("send");
		echo '  > ' . str_replace("\n", "\n  > ", trim($buf)) . "\n\n";
		
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
		$buf = $link->recvfrom($ip, $port);
		Logger::debug("recv");
		echo '  < ' . str_replace("\n", "\n  < ", trim($buf)) . "\n\n";
		
		$msg = new SipMessage();
		if($msg->decode($buf) > 0){
			$sip->incomming($msg);
		}
	}
}
