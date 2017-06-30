<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();
Logger::escape(false);

include_once('SIP.php');

$ip = '0.0.0.0';
$sip = SipEngine::create($ip);

$mod = new SipRegisterModule();
$sip->add_module($mod);
$mod->register('2005@bob.com', '1000', '127.0.0.1', 5060);
#$mod->register('221', '123456', '172.16.10.100', 5060);

while(1){
	$sip->loop();
}
