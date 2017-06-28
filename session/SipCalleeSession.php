<?php
class SipCalleeSession extends SipSession
{
	function __construct($msg){
		parent::__construct();
		
		$this->role = SIP::CALLEE;
		$this->state = SIP::TRYING;
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
		if($this->state == SIP::TRYING){
			if($msg->method == 'ACK'){
				Logger::debug("callee session {$this->call_id} established");
				$this->complete();
				$this->refresh_after();
			}
		}else if($this->state == SIP::COMPLETED || $this->state == SIP::CLOSING){
			if($msg->method == 'BYE'){
				if($this->state == SIP::COMPLETED){
					Logger::debug("call {$this->call_id} close by BYE");
				}else{
					Logger::debug("recv BYE while closing");
				}
				$this->closing();
			}
		}
	}
	
	function outgoing(){
		if($this->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->cseq_method = 'INVITE';
			#$msg->headers[] = array('Session-Expires', 90);
			return $msg;
		}else if($this->state == SIP::COMPLETED){
			$this->refresh_after();
			Logger::debug("refresh callee session {$this->call_id}");
			
			// TODO: re-invite?
			// $msg = new SipMessage();
			// $msg->method = 'INVITE';
			// $msg->to_tag = $this->to_tag;
			// return $msg;
		}else if($this->state == SIP::CLOSING){
			// TODO: 这是被动关闭的情况，主动关闭呢？
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->cseq_method = 'BYE';
			return $msg;
		}
	}
}
