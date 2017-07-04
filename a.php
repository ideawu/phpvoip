<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();

include_once('SIP.php');

$link = SipLink::listen('0.0.0.0');
$msg = new SipMessage();
$msg->method = 'INFO';
$msg->method = 'OPTIONS';
$msg->uri = 'sip:bob.com';
$msg->branch = SIP::new_branch();
$msg->from = new SipContact('2001', 'bob.com');
$msg->to = new SipContact('2001', 'bob.com');
$msg->from->set_tag(mt_rand());
$msg->to->set_tag(mt_rand());

$msg->contact = $msg->from;
$msg->call_id = 'call_1';
$msg->cseq = '100';
$msg->dst_ip = '127.0.0.1';
$msg->dst_port = 5060;
$link->send($msg);
echo $msg->encode() . "\n\n";

while(1){
	$ret = $link->recv();
	echo $ret->encode() . "\n\n";
}


