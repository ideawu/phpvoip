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
	}
	
	function outgoing(){
	}
}
