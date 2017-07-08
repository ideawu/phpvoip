<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();
Logger::escape(false);
Logger::showip(false);

include_once('SIP.php');

$ip = '0.0.0.0';
$port = 0;
$sip = SipEngine::create($ip, $port);

$mod = new SipChannel('3002@carol.com', '123456', '127.0.0.1', 5070);
$sip->add_module($mod);

$sip->init();
while(1){
	$sip->loop();
}

