<?php
class SipCallerSession extends SipSession
{
	function __construct(){
		parent::__construct();
		
		$this->role = SIP::CALLER;
		$this->state = SIP::CALLING;
		$this->timers = self::$call_timers;

		// $this->remote_ip = $remote_ip;
		// $this->remote_port = $remote_port;
		// $this->username = $username;
		// $this->password = $password;
		// $this->domain = $domain;
		// 
		// $this->uri = "sip:{$this->domain}";
		// $this->from = "\"{$this->username}\" <sip:{$this->username}@{$this->domain}>";
		// $this->to = $this->from;
		// $this->contact = $this->from;
		// 
		// $this->call_id = SIP::new_call_id();
		// $this->branch = SIP::new_branch();
		// $this->from_tag = SIP::new_tag();
		// $this->cseq = mt_rand(1, 10000);
	}

	function incoming($msg){
	}
	
	function outgoing(){
		Logger::debug("nothing to send");
	}
}
