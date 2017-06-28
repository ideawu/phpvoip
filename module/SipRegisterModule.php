<?php
class SipRegisterModule extends SipModule
{
	function register($username, $password, $remote_ip, $remote_port, $local_ip, $local_port){
		$sess = new SipRegisterSession($username, $password, $remote_ip, $remote_port);
		if($local_ip === '0.0.0.0'){
			$local_ip = SIP::guess_local_ip($remote_ip);
		}
		$sess->local_ip = $local_ip;
		$sess->local_port = $local_port;
		$this->add_session($sess);
	}
	
}
