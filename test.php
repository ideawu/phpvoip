<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();

include_once('SIP.php');

$str = 'sip:a@192.168.1.4:5070;ob';
$uri = new SipUri();
$uri->decode($str);
echo $uri->encode() . "\n";

$str = '"a" <sip:3005@192.168.1.4:5070;transport=UDP>;tag=7265285e';
$addr = new SipContact();
$addr->decode($str);
var_dump($addr);
echo $addr->encode() . "\n";


die();





if($argv[1]){
	$port = intval($argv[1]);
}else{
	$port = 5060;
}

$ip = '127.0.0.1';
$link = SipLink::listen($ip, 5070);

$server_ip = '127.0.0.1';
$server_port = 5060;

$client_ip = '';
$client_port = 0;

while(1){
	$read = array($link->sock);
	$write = array();
	$except = array();
	$ret = @socket_select($read, $write, $except, 0, 20*1000);
	if($read){
		$msg = $link->recv();
		if(!$msg){
			continue;
		}
		if(!$client_ip){
			$client_ip = $msg->src_ip;
			$client_port = $msg->src_port;
		}
		
		if($msg->src_port == $client_port){
			if($msg->method == 'INVITE'){
				static $i = 0;
				$i ++;
				if($i > 1 && $i < 5){
					Logger::debug("drop INVITE");
					continue;
				}
			}
			// 转发给 server
			$msg->dst_ip = $server_ip;
			$msg->dst_port = $server_port;
			$msg->src_ip = $link->local_ip;
			$msg->src_port = $link->local_port;
			$msg->del_header('Route');
			$msg->uri = str_replace(':5070', '', $msg->uri);
			$link->send($msg);
		}else{
			// 转发给 client
			$msg->dst_ip = $client_ip;
			$msg->dst_port = $client_port;
			$msg->src_ip = $link->local_ip;
			$msg->src_port = $link->local_port;
			$msg->del_header('Route');
			if($msg->contact){
				$msg->contact->domain = str_replace("$server_port", "{$link->local_port}", $msg->contact->domain);
			}
			#$msg->via = str_replace("rport={}", 'rport', $msg->via);
			$link->send($msg);
		}
	}
}
