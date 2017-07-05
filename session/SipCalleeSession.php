<?php
class SipCalleeSession extends SipBaseCallSession
{
	private $remote_branch;
	
	function __construct($msg){
		parent::__construct();
		
		$this->role = SIP::CALLEE;
		$this->set_state(SIP::TRYING);

		$this->call_id = $msg->call_id;
		$this->remote->set_tag($msg->from->tag());
		$this->remote_branch = $msg->branch;
		$this->remote_cseq = $msg->cseq;
	
		$new = $this->new_response($this->remote_branch);
		$new->trying();
		
		if(!$this->remote_allow){
			$str = $msg->get_header('Allow');
			if($str){
				$this->remote_allow = preg_split('/[, ]+/', trim($str));
			}
		}
	}
	
	function brief(){
		return $this->role_name() .' '. $this->remote->address() .'=>'. $this->local->address();
	}

	function del_transaction($trans){
		parent::del_transaction($trans);
		if($this->is_state(SIP::TRYING) || $this->is_state(SIP::RINGING)){
			$this->close();
		}
	}
	
	function ringing(){
		// 在这里也可以生成 local.tag?
		$this->set_state(SIP::RINGING);
		
		$this->transactions = array();
		$new = $this->new_response($this->remote_branch);
		$new->ringing();
	}
	
	function completing(){
		$this->set_state(SIP::COMPLETING);
		$this->local->set_tag(SIP::new_tag());
		
		$this->transactions = array();
		$new = $this->new_response($this->remote_branch);
		$new->completing();
	}
	
	function incoming($msg, $trans){
		$ret = parent::incoming($msg, $trans);
		if($ret === true){
			return true;
		}
		
		if($trans->state == SIP::COMPLETING){
			if($msg->method == 'ACK'){
				$this->complete();
				
				$this->del_transaction($trans);
				
				$new = $this->new_request();
				$new->branch = $trans->branch; // 
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
		$msg = parent::outgoing($trans);
		if($msg){
			return $msg;
		}
		
		if($trans->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->code = 100;
			$msg->reason = 'Trying';
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
