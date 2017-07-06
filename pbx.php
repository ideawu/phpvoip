<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();
Logger::escape(false);
Logger::showip(false);

include_once('SIP.php');

$ip = '0.0.0.0';
$port = 5070;
$sip = SipEngine::create($ip, $port);

$mod = new SipChannel('2005@carol.com', '1000', '127.0.0.1', 5060);
#$sip->add_module($mod);
$mod = new SipChannel('221', '123456', '172.16.10.100', 5060);
#$sip->add_module($mod);

$mod = new SipRegistrar();
$mod->add_user('3001', '123456');
$mod->add_user('3002', '123456');
$mod->add_user('3003', '123456');
$mod->add_user('3004', '123456');
$mod->add_user('3005', '123456');
$sip->add_module($mod);
		
$mod = new SipRobotModule();
$sip->add_module($mod, -1);

$sip->init();
while(1){
	$sip->loop();
}

