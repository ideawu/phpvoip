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
			if($msg->code == 200){
				Logger::debug("caller session {$this->call_id} established");
				$this->state = SIP::COMPLETING;
				$this->timers = self::$now_timers;
			}else if($msg->code >= 300){
				$this->close();
			}
		}
	}
	
	function outgoing(){
		if($this->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->method = 'INVITE';
			$msg->headers[] = array('Session-Expires', 90);
			return $msg;
		}else if($this->state == SIP::COMPLETING){
			$this->complete();
			$this->refresh_after();
			
			$msg = new SipMessage();
			$msg->method = 'ACK';
			return $msg;
		}else if($this->state == SIP::COMPLETED){
			$this->refresh_after();
			Logger::debug("refresh caller session {$this->call_id}");
		}
	}
}
