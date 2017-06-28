<?php
class SipRegistrarSession extends SipSession
{
	public $username;
	public $password;
	public $domain;

	function __construct($msg){
		parent::__construct();
		
		$this->role = SIP::REGISTRAR;
		$this->state = SIP::TRYING;
		$this->timers = self::$reg_timers;

	}
	
	function incoming($msg){
		if($this->state == SIP::TRYING || $this->state == SIP::RENEWING){
		}else if($this->state == SIP::ESTABLISHED){
		}
	}
	
	function outgoing(){
		if($this->state == SIP::TRYING || $this->state == SIP::RENEWING){
		}else if($this->state == SIP::ESTABLISHED){
			// expired
		}
	}
}
