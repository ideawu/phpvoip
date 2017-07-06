<?php
class SipRegistrarSession extends SipSession
{
	public $username;
	public $password;
	public $domain;

	function __construct(){
		parent::__construct();
		$this->role = SIP::REGISTRAR;
		$this->set_state(SIP::NONE);
	}
	
	function init(){
		$this->set_state(SIP::TRYING);
		$new = $this->new_response($msg->branch);
		$new->trying();
	}
	
	function incoming($msg, $trans){
		return false;
	}
	
	function outgoing($trans){
		Logger::debug("");
		return array();
	}
}
