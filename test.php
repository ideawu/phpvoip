<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();

include_once('UdpLink.php');
include_once('SIP.php');

$ip = '0.0.0.0'; // ???
$ip = '172.26.0.96';
$sip = SipEngine::create($ip);
$sip->register('1001@bob.com', '1000', '127.0.0.1', 5060);
#$sip->register('221', '123456', '172.16.10.100', 5060);

$time = microtime(1);
while(1){
	$old_time = $time;
	$time = microtime(1);
	$timespan = max(0, $time - $old_time);

	$sip->loop($time, $timespan);
}
