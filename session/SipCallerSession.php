<?php
class SipCallerSession extends SipBaseCallSession
{
	function __construct(){
		parent::__construct();
		
		$this->role = SIP::CALLER;
		$this->set_state(SIP::CALLING);
		
		$this->call_id = SIP::new_call_id();
		$this->local_tag = SIP::new_tag();
		
		$new = $this->new_request();
		$new->calling();
	}

	function del_transaction($trans){
		parent::del_transaction($trans);
		if($this->is_state(SIP::CALLING) || $this->is_state(SIP::RINGING)){
			$this->close();
		}
	}

	function incoming($msg, $trans){
		$ret = parent::incoming($msg, $trans);
		if($ret === true){
			return true;
		}
		
		if($trans->state == SIP::CALLING){
			if($msg->code == 200){
				$this->complete();

				$trans->completing();

				$new = $this->new_request();
				$new->branch = $trans->branch;
				$new->remote_tag = $this->remote_tag;
				$new->keepalive();

				return true;
			}
		}else if($trans->state == SIP::COMPLETING){
			if($msg->code == 200){
				Logger::debug("duplicated OK, resend ACK");
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
		
		if($trans->state == SIP::CALLING){
			$msg = new SipMessage();
			$msg->method = 'INVITE';
			return $msg;
		}else if($trans->state == SIP::COMPLETING){
			static $i = 0;
			if($i++%2 == 0){
				Logger::debug("manually drop outgoing msg ACK");
				return;
			}
			$msg = new SipMessage();
			$msg->method = 'ACK';
			return $msg;
		}
	}
}
