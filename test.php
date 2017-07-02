<?php
include_once('/data/lib/iphp/framework/Logger.php');
Logger::init();
Logger::escape(false);

include_once('SIP.php');

$ip = '0.0.0.0';
$sip = SipEngine::create($ip);

$mod = new SipChannel('2005@bob.com', '1000', '127.0.0.1', 5060);
$sip->add_module($mod);
// $mod = new SipChannel('221', '123456', '172.16.10.100', 5060);
// $sip->add_module($mod);
		
$mod = new SipRobotModule();
$sip->add_module($mod, -1);

$sip->init();
while(1){
	$sip->loop();
}

/*
$remote_ip = '127.0.0.1';
$remote_port = 5060;
$local_ip = $this->engine->local_ip;
$local_port = $this->engine->local_port;
if($local_ip === '0.0.0.0'){
	$local_ip = SIP::guess_local_ip($remote_ip);
}

$caller = new SipCallerSession();
$caller->local_ip = $local_ip;
$caller->local_port = $local_port;
$caller->remote_ip = $remote_ip;
$caller->remote_port = $remote_port;
$caller->uri = "sip:1001@{$local_ip}";
$caller->local = "<sip:2005@{$local_ip}>";
$caller->remote = "<{$caller->uri}>";
$caller->contact = $caller->local;
*/
