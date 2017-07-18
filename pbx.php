<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();
Logger::escape(false);
Logger::showip(false);

include_once('SIP.php');

$ip = '0.0.0.0';
$port = 5070;
$sip = SipEngine::create($ip, $port);

// $mod = new SipChannel('2005@carol.com', '1000', '127.0.0.1', 5060);
// $sip->add_module($mod);
// $mod = new SipChannel('2004@carol.com', '1000', '127.0.0.1', 5060);
// $sip->add_module($mod);
// $mod = new SipChannel('2003@carol.com', '1000', '127.0.0.1', 5060);
// $sip->add_module($mod);
$mod = new SipChannel('221@me.com', '123456', '172.16.10.100', 5060);
$sip->add_module($mod);
// $mod = new SipChannel('222@me.com', '123456', '172.16.10.100', 5060);
// $sip->add_module($mod);
// $mod = new SipChannel('223@me.com', '123456', '172.16.10.100', 5060);
// $sip->add_module($mod);

$mod = new SipRobotModule();
$sip->add_module($mod, -1);

$mod = new SipRegistrar();
for($i=0; $i<10000; $i++){
	$mod->add_user($i + 3000, '123456');
}
$sip->add_module($mod);

$sip->init();
while(1){
	$sip->loop();
}

