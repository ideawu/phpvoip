<?php
class SipCallerSession extends SipSession
{
	function __construct(){
		parent::__construct();
		
		$this->role = SIP::CALLER;
		$this->state = SIP::TRYING;
		$this->timers = self::$call_timers;
		
		$this->call_id = SIP::new_call_id();
		$this->branch = SIP::new_branch();
		$this->from_tag = SIP::new_tag();
		$this->cseq = mt_rand(1, 10000);
	}

	function incoming($msg){
		if($this->state == SIP::TRYING){
			if($msg->is_response()){
				
			}
		}
	}
	
	function outgoing(){
		if($this->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->method = 'INVITE';
			return $msg;
		}
	}
}
