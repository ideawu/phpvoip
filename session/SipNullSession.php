<?php
class SipNullSession extends SipSession
{
	function __construct(){
		$this->role = SIP::NONE;
		$this->state = SIP::TRYING;
		$this->timers = array(9999999);
	}

	function incoming($msg){
		return null;
	}
	
	function outgoing(){
		return null;
	}
}

