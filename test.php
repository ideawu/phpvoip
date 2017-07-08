<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();

include_once('SIP.php');

if($argv[1]){
	$port = intval($argv[1]);
}else{
	$port = 5060;
}

$ip = '0.0.0.0';
$link = SipLink::listen($ip, 0);

$msg = new SipMessage();
$msg->method = 'INVITE';
$msg->call_id = 1;
$msg->cseq = 1;
$msg->dst_ip = '127.0.0.1';
#$msg->dst_ip = '172.16.10.100';
$msg->dst_port = $port;
$msg->src_port = $link->local_port;
$msg->src_ip = SIP::guess_local_ip($msg->dst_ip);
$msg->from = new SipContact(1002, 'me.com');
$msg->to = new SipContact(1001, 'me.com');
$msg->uri = "{$msg->to->username}@{$msg->to->domain}";
$msg->from->set_tag(mt_rand());
$msg->branch = mt_rand();

$link->send($msg);
echo $msg->encode() . "\n";

while($ret = $link->recv()){
	echo $ret->encode() . "\n";
}

