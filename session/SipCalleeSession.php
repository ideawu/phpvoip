<?php
class SipCalleeSession extends SipSession
{
	function __construct($msg){
		parent::__construct();
		
		$this->role = SIP::CALLEE;
		$this->state = SIP::ACCEPTING;
		$this->timers = self::$call_timers;

		$this->uri = $msg->uri;
		$this->call_id = $msg->call_id;
		$this->branch = $msg->branch;
		$this->cseq = $msg->cseq;
		$this->from = $msg->from;
		$this->from_tag = $msg->from_tag;
		$this->to = $msg->to;
		$this->to_tag = SIP::new_tag();
	}
	
	function incoming($msg){
		if($this->state == SIP::ACCEPTING){
			if($msg->method == 'ACK'){
				Logger::debug("call {$this->call_id} established");
				$this->state = SIP::ESTABLISHED;
				$this->timers = self::$refresh_timers;
			}
		}else if($this->state == SIP::ESTABLISHED || $this->state == SIP::CLOSING){
			if($msg->method == 'BYE'){
				if($this->state == SIP::ESTABLISHED){
					Logger::debug("call {$this->call_id} close by BYE");
				}else{
					Logger::debug("recv BYE while closing");
				}
				$this->close();
			}
		}
	}
	
	function outgoing(){
		if($this->state == SIP::ACCEPTING){
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->method = 'INVITE';
			$msg->headers[] = array('Session-Expires', 90);
			return $msg;
		}else if($this->state == SIP::ESTABLISHED){
			$this->timers = self::$refresh_timers;
			Logger::debug("refresh call {$this->call_id}");
			
			// TODO: re-invite?
			// $msg = new SipMessage();
			// $msg->method = 'INVITE';
			// $msg->to_tag = $this->to_tag;
			// return $msg;
		}else if($this->state == SIP::CLOSING){
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->method = 'BYE';
			return $msg;
		}
	}
}
