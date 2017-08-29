<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();
Logger::escape(false);
Logger::showip(false);

include_once('SIP.php');

$ip = '0.0.0.0';
$port = 0;
$sip = SipEngine::create($ip, $port);

$count = 0;
$stime = microtime(1);

$sip->init();
$user = 8599;
$mod = new SipChannel($user, 'aaaa1111', '172.60.3.110', 5060);
$sip->add_module($mod);
while(1){
	$sip->loop();
}

