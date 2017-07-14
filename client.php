<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();
Logger::escape(false);
Logger::showip(false);

include_once('SIP.php');

$ip = '0.0.0.0';
$port = 0;
$sip = SipEngine::create($ip, $port);

$i = 0;
$count = 0;

$sip->init();
while(1){
	$i ++;
	if($count < 500 && $i % 1 == 0){
		$user = $count + 3000;
		$count ++;

		Logger::debug("add user $user");
		$mod = new SipChannel($user, '123456', '127.0.0.1', 5070);
		$sip->add_module($mod);
	}
	$sip->loop();
}

