<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();

include_once('UdpLink.php');
include_once('SIP.php');

$ip = '127.0.0.1'; // ???
#$ip = '172.26.0.96';
$sip = SipEngine::create($ip);

$mod_register = new SipRegisterModule();
$mod_register->register('1001@bob.com', '1000', '127.0.0.1', 5060);
#$mod_register->register('221', '123456', '172.16.10.100', 5060);

$mod_conference = new SipConferenceModule();

$sip->add_module($mod_register);
$sip->add_module($mod_conference);

while(1){
	$sip->loop();
}
