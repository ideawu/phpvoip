<?php
class SipCallerSession extends SipBaseCallSession
{
	function __construct($uri, $from, $to){
		parent::__construct();
		$this->role = SIP::CALLER;
		$this->set_state(SIP::NONE);

		$this->uri = $uri;
		$this->call_id = SIP::new_call_id();
		$this->local = $from;
		$this->local->set_tag(SIP::new_tag());
		$this->remote = $to;
	}
	
	function init(){
		$this->contact = new SipContact($this->local->username, $this->local_ip . ':' . $this->local_port);
		$this->set_state(SIP::CALLING);
		$new = $this->new_request();
		$new->calling();
	}

	function del_transaction($trans){
		parent::del_transaction($trans);
		if($this->is_state(SIP::CALLING) || $this->is_state(SIP::RINGING)){
			$this->close();
		}
	}

	function close(){
		if($this->is_state(SIP::CLOSING)){
			return;
		}

		if($this->is_state(SIP::CALLING)){
			foreach($this->transactions as $new){ // 应该倒序遍历
				Logger::debug("send CANCEL");
				$new->method = 'CANCEL';
				$new->close();
			}
		}else if($this->is_state(SIP::COMPLETING) || $this->is_state(SIP::COMPLETED)){
			foreach($this->transactions as $new){ // 应该倒序遍历
				Logger::debug("send BYE");
				$new->method = 'BYE';
				$new->close();
			}
		}
		
		$this->set_state(SIP::CLOSING);
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
