<?php
abstract class BaseCallSession extends SipSession
{
	function __construct(){
		parent::__construct();
	}

	protected function incoming($msg, $trans){		
		if(parent::incoming($msg, $trans)){
			return true;
		}

		// INVITE & re-INVITE
		if($msg->method === 'INVITE'){
			if($this->is_state(SIP::COMPLETED)){
				Logger::debug("recv re-INVITE after completed");
				$trans->code = 200;
				$trans->timers = array(0, 1, 2, 2, 2);
				$this->transactions = array($trans);
				$this->trans = $trans;
			}else{
				Logger::debug("recv duplicated INVITE");
				$trans->nowait();
			}
			return true;
		}
		if($msg->method === 'ACK' && $trans->method === 'INVITE'){
			if($msg->branch === $trans->branch){
				Logger::debug("duplicated ACK, ignore");
				return true;
			}
			if($this->is_state(SIP::COMPLETING)){
				$this->complete();
			}else{
				Logger::debug("recv ACK when " . $this->state_text());
				$trans->timers = array();
			}
			$this->keepalive();
			return true;
		}
		if($msg->code >= 200 && $trans->method === 'INVITE'){
			Logger::debug("recv {$msg->code} for {$trans->method}, closing");
			$this->set_state(SIP::CLOSING);
			$this->remote->set_tag($msg->to->tag());
			
			$trans->method = 'ACK';
			$trans->timers = array(0, 0);
			$this->transactions = array($trans);
			$this->trans = $trans;
			return true;
		}

		// bye
		if($msg->method === 'BYE'){
			$this->transactions = array();
			// if($this->is_state(SIP::TRYING) || $this->is_state(SIP::RINGING)){
			if($this->trans->code > 0 && $this->trans->code < 200){
				$this->trans->code = 487; // Request Terminated
				$this->trans->timers = array(0, 0);
				$this->transactions[] = $this->trans;
			}
			$this->set_state(SIP::CLOSING);

			// response OK
			$trans->code = 200;
			$trans->timers = array(0, 0);
			$this->transactions[] = $trans;
			return true;
		}
		if($msg->is_response() && $msg->cseq_method === 'BYE'){
			if($msg->code >= 200){
				Logger::debug("recv 200 for BYE, terminate");
				$this->terminate();
				return true;
			}
			#Logger::debug("recv {$msg->code} for {$msg->cseq_method}, do nothing");
			return true;
		}

		// keepalive
		if($msg->method === 'INFO' || $msg->method === 'OPTIONS'){
			$trans->code = 200;
			$trans->timers = array(0, 0);
			return true;
		}		
		if($msg->is_response() && ($trans->method === 'INFO' || $trans->method === 'OPTIONS')){
			if($msg->code >= 200){
				Logger::debug("keepalive updated");
				$this->del_transaction($trans);
				$this->keepalive();
				return true;
			}
			#Logger::debug("recv {$msg->code} for {$msg->cseq_method}, do nothing");
			return true;
		}
	}
}
