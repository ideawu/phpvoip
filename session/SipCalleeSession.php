<?php
class SipCalleeSession extends SipSession
{
	public $local_sdp;
	public $remote_sdp;

	function __construct($msg){
		parent::__construct();
		$this->role = SIP::CALLEE;

		$this->call_id = $msg->call_id;
		$this->local = clone $msg->to;
		$this->remote = clone $msg->from;
		$this->remote_cseq = $msg->cseq;
		$this->remote_allow = $msg->allow;

		$this->remote_sdp = $msg->content;

		$this->trans->uri = $msg->uri;
		$this->trans->method = $msg->method;
		$this->trans->cseq = $msg->cseq;
		$this->trans->branch = $msg->branch;
	}
	
	function init(){
		$this->set_state(SIP::TRYING);
		$this->contact = new SipContact($this->local->username, $this->local_ip . ':' . $this->local_port);
		$this->trans->code = 100;
		$this->trans->timers = array(0.3, 1, 2, 2, 10);
	}
	
	function ringing(){
		$this->set_state(SIP::RINGING);
		$this->trans->code = 180;
		$this->trans->timers = array(0, 3, 3, 3, 3, 3);
		if(!$this->local->tag()){
			$this->local->set_tag(SIP::new_tag());
		}
	}
	
	function accept(){
		$this->set_state(SIP::ACCEPTING);
		$this->trans->code = 200;
		$this->trans->timers = array(0, 1, 2, 2, 2);
		if(!$this->local->tag()){
			$this->local->set_tag(SIP::new_tag());
		}
	}
	
	function close(){
		if($this->is_state(SIP::CLOSING)){
			return;
		}
		if($this->is_state(SIP::TRYING) || $this->is_state(SIP::RINGING)){
			$this->set_state(SIP::CLOSING);
			Logger::debug("callee response 486 to close session");
			// 回复 BUSY, 直到收到 ACK
			$this->trans->code = 486; // Busy Here
			$this->trans->timers = array(0, 1, 2, 2, 2);
		}else{
			$this->bye();
		}
	}
	
	function incoming($msg, $trans){		
		if(parent::incoming($msg, $trans)){
			return true;
		}
		
		if($msg->method === 'CANCEL'){
			if($this->is_state(SIP::TRYING) || $this->is_state(SIP::RINGING)){
				$this->set_state(SIP::CLOSING);
				
				$trans->code = 487; // Request Terminated
				$trans->timers = array(0, 1, 2, 2, 2);
			}else{
				// 不关闭
			}

			// response OK
			$new = new SipTransaction();
			$new->code = 200;
			$new->method = $msg->method;
			$new->cseq = $msg->cseq;
			$new->branch = $msg->branch; // 原 branch
			$new->to_tag = SIP::new_tag(); // 新 branch
			$new->timers = array(0, 0);
			$this->transactions[] = $new;
			return true;
		}
		
		if($msg->method === 'INVITE'){
			if($this->is_state(SIP::COMPLETED)){
				Logger::debug("recv re-INVITE after completed");
			}else{
				Logger::debug("recv duplicated INVITE");
			}
			$trans->cseq = $msg->cseq;
			$trans->branch = $msg->branch;
			$trans->nowait();
			return true;
		}
		if($msg->method === 'ACK'){
			if($msg->branch === $trans->branch){
				Logger::debug("duplicated ACK, ignore");
				return true;
			}
			if($trans->method === 'INVITE'){
				if($this->is_state(SIP::ACCEPTING)){
					$this->complete();
				}else{
					Logger::debug("recv ACK when " . $this->state_text());
				}
				$trans->timers = array(3); // 等待可能重传的 ACK

				$this->transactions = array();
				$this->transactions[] = $trans;
				$this->keepalive();
				return true;
			}
		}
	}
	
	protected function outgoing($trans){
		$msg = parent::outgoing($trans);
		if($msg && ($msg->is_response() && $msg->code == 200 && $msg->cseq_method === 'INVITE')){
			$msg->content = $this->local_sdp;
			$msg->content_type = 'application/sdp';
		}
		return $msg;
	}
}
