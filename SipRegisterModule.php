<?php
class SipRegisterModule extends SipModule
{
	function register($username, $password, $proxy_ip, $proxy_port, $local_ip, $local_port){
		$sess = new SipRegisterSession($username, $password, $proxy_ip, $proxy_port);
		if($local_ip === '0.0.0.0'){
			$local_ip = SIP::guess_local_ip($proxy_ip);
		}
		$sess->local_ip = $local_ip;
		$sess->local_port = $local_port;
		Logger::debug("NEW REGISTER session, {$sess->call_id} {$sess->from_tag} {$sess->to_tag}");
		$this->sessions[] = $sess;
	}
	
	function incoming($msg){
		parent::incoming($msg);
		
		foreach($this->sessions as $sess){
			if($msg->call_id === $sess->call_id && $msg->from_tag === $sess->from_tag){
				$sess->on_recv_msg($msg);
				return true;
			}
		}
		return false;
	}
	
	protected function del_session($sess){
		parent::del_session($sess);
		Logger::debug("DEL REGISTER session, {$sess->call_id} {$sess->from_tag} {$sess->to_tag}");
	}
}
