<?php
class SipCallerSession extends SipBaseCallSession
{
	function __construct(){
		parent::__construct();
		$this->role = SIP::CALLER;
		$this->set_state(SIP::NONE);
	}

	function del_transaction($trans){
		parent::del_transaction($trans);
		if($this->is_state(SIP::CALLING) || $this->is_state(SIP::RINGING)){
			$this->close();
		}
	}
	
	function init(){
		$this->set_state(SIP::CALLING);
		$new = $this->new_request();
		$new->calling();
	}

	function incoming($msg, $trans){
		$ret = parent::incoming($msg, $trans);
		if($ret === true){
			return true;
		}
		
		if($trans->state == SIP::CALLING){
			if($msg->code == 200){
				$this->remote_sdp = $msg->content;
				$this->complete();

				$trans->completing();

				$new = $this->new_request($trans->branch);
				$new->keepalive();
				return true;
			}
		}else if($trans->state == SIP::COMPLETING){
			if($msg->code == 200){
				Logger::debug("duplicated OK, resend ACK");
				$trans->nowait();
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
			$msg->add_header('Content-Type', 'application/sdp');
			if($this->local_sdp){
				$msg->content = $this->local_sdp;
			}
			return $msg;
		}else if($trans->state == SIP::COMPLETING){
			$msg = new SipMessage();
			$msg->method = 'ACK';
			return $msg;
		}
	}
}
