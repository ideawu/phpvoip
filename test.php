<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();

include_once('UdpLink.php');
include_once('SIP.php');

$ip = '0.0.0.0';
$sip = SipEngine::create($ip);

$mod_register = new SipRegisterModule();
$mod_register->register('2001@bob.com', '1000', '127.0.0.1', 5060, $sip->local_ip, $sip->local_port);
#$mod_register->register('221', '123456', '172.16.10.100', 5060);

$mod_conference = new SipConferenceModule();

$sip->add_module($mod_register);
$sip->add_module($mod_conference);

while(1){
	$sip->loop();
}
