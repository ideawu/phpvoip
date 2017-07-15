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
while(1){
	$i ++;
	if($count < 10){
		$ts = microtime(1) - $stime;
		if($ts > 0.2 || $ts < 0){
			$stime = microtime(1);
			
			$user = $count + 3000;
			$count ++;

			Logger::debug("add user $user");
			$mod = new SipChannel($user, '123456', '127.0.0.1', 5070);
			$sip->add_module($mod);
		}
	}
	$sip->loop();
}

