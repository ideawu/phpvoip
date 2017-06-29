<?php
class SipCalleeSession extends SipBaseCallSession
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
		$this->local = $msg->from;
		$this->local_tag = $msg->from_tag;
		$this->remote = $msg->to;
		$this->remote_tag = SIP::new_tag();
		$this->contact = $msg->contact;
	}
	
	function incoming($msg){
		$ret = parent::incoming($msg);
		if($ret === true){
			return true;
		}
		if($this->state == SIP::TRYING){
			if($msg->method == 'ACK'){
				$this->complete();
				$this->refresh();
				return true;
			}else if($msg->method == 'INVITE'){
				Logger::debug("recv duplicated INVITE, resend OK");
				$this->timers = self::$call_timers;
			}
		}
	}
	
	function outgoing(){
		$msg = parent::outgoing();
		if($msg){
			return $msg;
		}
		
		if($this->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->cseq_method = 'INVITE';
			return $msg;
		}
	}
}
