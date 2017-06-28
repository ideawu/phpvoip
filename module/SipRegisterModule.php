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
	
	function callin($msg){
		foreach($this->sessions as $sess){
			if($msg->src_ip === $sess->proxy_ip && $msg->src_port === $sess->proxy_port){
				$ret = new SipCalleeSession($msg);
				$ret->local_ip = $sess->local_ip;
				$ret->local_port = $sess->local_port;
				$ret->proxy_ip = $sess->proxy_ip;
				$ret->proxy_port = $sess->proxy_port;

				$ret->username = $sess->username;
				$ret->contact = $sess->contact;
				return $ret;
			}
		}
	}
	
}
