<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();

include_once('SIP.php');

$ip = '0.0.0.0';
$sip = SipEngine::create($ip);

$mod = new SipRegisterModule();
$mod->register('2001@bob.com', '1000', '127.0.0.1', 5060, $sip->local_ip, $sip->local_port);
#$mod->register('221', '123456', '172.16.10.100', 5060);
$sip->add_module($mod);

while(1){
	$sip->loop();
}
