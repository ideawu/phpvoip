<?php
class SipCalleeSession extends SipBaseCallSession
{
	function __construct($msg){
		parent::__construct();
		
		$this->role = SIP::CALLEE;
		$this->state = SIP::TRYING;

		$this->uri = $msg->uri;
		$this->call_id = $msg->call_id;
		$this->remote_cseq = $msg->cseq;
		$this->local = $msg->to;
		$this->local_tag = SIP::new_tag();
		$this->remote = $msg->from;
		$this->remote_tag = $msg->from_tag;
		$this->contact = $msg->contact;
		
		// TODO:
		if(!$this->remote_allow){
			$str = $msg->get_header('Allow');
			if($str){
				$this->remote_allow = preg_split('/[, ]+/', trim($str));
			}
		}
	}
	
	function incoming($msg, $trans){
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
	
	function ring(){
		$this->state = SIP::RINGING;
		$this->timers = self::$ring_timers;
	}
	
	function accept(){
		$this->state = SIP::TRYING;
		$this->timers = self::$call_timers;
	}
	
	function outgoing($trans){
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
		}else if($this->state == SIP::RINGING){
			$msg = new SipMessage();
			$msg->code = 180;
			$msg->reason = 'Ringing';
			$msg->cseq_method = 'INVITE';
			return $msg;
		}
	}
}
