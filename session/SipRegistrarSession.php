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

	}
	
	function incoming($msg){
	}
	
	function outgoing(){
	}
}
