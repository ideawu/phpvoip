<?php
class SipRegisterModule extends SipModule
{
	function register($username, $password, $remote_ip, $remote_port){
		$local_ip = $this->engine->local_ip;
		$local_port = $this->engine->local_port;
		if($local_ip === '0.0.0.0'){
			$local_ip = SIP::guess_local_ip($remote_ip);
		}
		$sess = new SipRegisterSession($username, $password, $remote_ip, $remote_port);
		$sess->local_ip = $local_ip;
		$sess->local_port = $local_port;
		$this->add_session($sess);
	}
	
	function up_session($sess){
		parent::up_session($sess);
		
		// TESTING
		if($sess->role != SIP::REGISTER){
			return;
		}
		
		$remote_ip = $sess->remote_ip;
		$remote_port = $sess->remote_port;
		#$remote_port = 5050;
		
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
		$caller->from = "<sip:2001@{$local_ip}>";
		$caller->to = "<{$caller->uri}>";
		$caller->contact = $caller->from;
		
		$this->add_session($caller);
	}
	
}
