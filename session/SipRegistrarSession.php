<?php
class SipRegistrarSession extends SipSession
{
	public $username;
	public $password;
	public $domain;

	function __construct(){
		parent::__construct();
		
		$this->role = SIP::REGISTRAR;
		$this->set_state(SIP::TRYING);
	}
	
	function trying(){
		$new = $this->new_response($msg->branch);
		$new->trying();
	}
	
	function incoming($msg, $trans){
	}
	
	function outgoing($trans){
	}
}
