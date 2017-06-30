<?php
class SipNullSession extends SipSession
{
	function __construct(){
		$this->role = SIP::NONE;
		$this->state = SIP::TRYING;
		$this->timers = array(1, 0);
	}
	
	function incoming($msg){
		return null;
	}
	
	private $count = 0;

	function outgoing(){
		$this->timers = array(1, 0);
		if(++$this->count == 3){
			$this->complete();
		}
		return null;
	}
}

