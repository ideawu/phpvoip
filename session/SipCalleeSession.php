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
				Logger::debug($this->role_name() . " session {$this->call_id} established");
				$this->complete();
				$this->refresh();
			}
		}else if($this->state == SIP::COMPLETED){
			if($msg->method == 'BYE'){
				Logger::debug($this->role_name() . " {$this->call_id} close by BYE");
				$this->onclose();
			}
		}else if($this->state == SIP::FIN_WAIT){
			if($msg->method == 'BYE'){
				Logger::debug($this->role_name() . " {$this->call_id} FIN_WAIT => CLOSE_WAIT");
				$this->onclose();
			}
		}else if($this->state == SIP::CLOSE_WAIT){
			if($msg->method == 'BYE'){
				Logger::debug("recv BYE while CLOSE_WAIT");
				// 立即发送 OK
				array_unshift($this->timers, 0);
			}
		}
	}
	
	function outgoing(){
		if($this->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->cseq_method = 'INVITE';
			return $msg;
		}else if($this->state == SIP::COMPLETED){
			Logger::debug("refresh " . $this->role_name() . " session {$this->call_id}");
		
			// TODO: re-invite?
			// $msg = new SipMessage();
			// $msg->method = 'INVITE';
			// $msg->to_tag = $this->to_tag;
			// return $msg;
		}else if($this->state == SIP::FIN_WAIT){
			// 发送 BYE
		}else if($this->state == SIP::CLOSE_WAIT){
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->cseq_method = 'BYE';
			return $msg;
		}
	}
}
