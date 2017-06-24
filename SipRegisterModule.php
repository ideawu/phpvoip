<?php
class SipRegisterModule extends SipModule
{
	function register($username, $password, $proxy_ip, $proxy_port){
		$sess = new SipRegisterSession($username, $password, $proxy_ip, $proxy_port);
		Logger::debug("NEW REGISTER session, {$sess->call_id} {$sess->from_tag} {$sess->to_tag}");
		$this->sessions[] = $sess;
	}
	
	function incoming($msg){
		parent::incoming($msg);
		
		foreach($this->sessions as $sess){
			if($msg->call_id === $sess->call_id && $msg->from_tag === $sess->from_tag){
				$sess->on_recv($msg);
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
