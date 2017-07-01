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
		$this->remote = $msg->from;
		$this->remote_tag = $msg->from_tag;
		$this->contact = $msg->contact;
	
		$new = $this->new_response();
		$new->trying();
		
		if(!$this->remote_allow){
			$str = $msg->get_header('Allow');
			if($str){
				$this->remote_allow = preg_split('/[, ]+/', trim($str));
			}
		}
	}
	
	function ringing(){
		$this->local_tag = SIP::new_tag();
		$this->state = SIP::RINGING;
		
		$this->transactions = array();
		$new = $this->new_response();
		$new->ringing();
	}
	
	function completing(){
		$this->state = SIP::COMPLETING;
		
		$this->transactions = array();
		$new = $this->new_response();
		$new->completing();
	}
	
	function incoming($msg, $trans){
		$ret = parent::incoming($msg);
		if($ret === true){
			return true;
		}
		
		if($trans->state == SIP::COMPLETING){
			if($msg->method == 'ACK'){
				$this->complete();
				
				$this->del_transaction($trans);
				
				$new = $this->new_request();
				$new->branch = $trans->branch;
				$new->remote_tag = $this->remote_tag;
				$new->keepalive();
				
				return true;
			}else if($msg->method == 'INVITE'){
				Logger::debug("recv duplicated INVITE, resend OK");
				array_unshift($trans->timers, 0);
				return true;
			}
		}
	}
	
	function outgoing($trans){
		$msg = parent::outgoing();
		if($msg){
			return $msg;
		}
		
		if($trans->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->code = 100;
			$msg->reason = 'TRYING';
			$msg->cseq_method = 'INVITE';
			return $msg;
		}else if($trans->state == SIP::RINGING){
			$msg = new SipMessage();
			$msg->code = 180;
			$msg->reason = 'Ringing';
			$msg->cseq_method = 'INVITE';
			return $msg;
		}else if($trans->state == SIP::COMPLETING){
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->cseq_method = 'INVITE';
			return $msg;
		}
	}
}
