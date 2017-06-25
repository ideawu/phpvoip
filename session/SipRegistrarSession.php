<?php
class SipRegistrarSession extends SipSession
{
	public $username;
	public $password;
	public $domain;

	function __construct($msg){
		parent::__construct();
		
		$this->role = SIP::REGISTRAR;
		$this->state = SIP::PROCEEDING;
		$this->timers = self::$reg_timers;

	}
	
	function incoming($msg){
		if($this->state == SIP::PROCEEDING || $this->state == SIP::REG_REFRESH){
		}else if($this->state == SIP::REGISTERED){
		}
	}
	
	function outgoing(){
		if($this->state == SIP::PROCEEDING || $this->state == SIP::REG_REFRESH){
		}else if($this->state == SIP::REGISTERED){
			// expired
		}
	}
}
