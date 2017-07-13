<?php
class SipCalleeSession
{
	public $local_sdp;
	public $remote_sdp;

	function __construct($msg){
		parent::__construct();
		$this->role = SIP::CALLEE;

		$this->call_id = $msg->call_id;
		$this->local = clone $msg->to;
		$this->remote = clone $msg->from;
		$this->local_cseq = SIP::new_cseq();
		$this->remote_cseq = $msg->cseq;
		$this->local_contact = new SipContact($this->local->username, $this->local->domain);
		$this->remote_contact = clone $msg->contact;

		$this->remote_sdp = $msg->content;

		$this->trans->uri = $msg->uri;
		$this->trans->method = $msg->method;
		$this->trans->cseq = $msg->cseq;
		$this->trans->branch = $msg->branch;
	}
	
	function init(){
		$this->trying();
	}
	
	function trying(){
		$this->set_state(SIP::TRYING);
		$this->trans->code = 100;
		$this->trans->timers = array(0.5, 1, 2, 2);
	}
	
	function ringing(){
		$this->set_state(SIP::RINGING);
		$this->trans->code = 180;
		$this->trans->timers = array(0, 15);
		if(!$this->local->tag()){
			$this->local->set_tag(SIP::new_tag());
		}
	}
	
	function accept(){
		$this->set_state(SIP::ACCEPTING);
		$this->trans->code = 200;
		$this->trans->timers = array(0, 1, 2, 2);
		if(!$this->local->tag()){
			$this->local->set_tag(SIP::new_tag());
		}
	}
	
	function close(){
		if($this->is_state(SIP::CLOSING)){
			return;
		}
		$this->set_state(SIP::CLOSING);
		
		if($this->is_state(SIP::TRYING) && $this->is_state(SIP::RINGING)){
			// 回复 BUSY, 直到收到 ACK
			$this->trans->code = 486; // Busy Here
			$this->trans->timers = array(0, 1, 2, 2);
		}else{
			// 发送 BYE，直到收到 200
			$this->trans->uri = "sip:{$this->remote->username}@{$this->remote->domain}";
			$this->trans->code = 0;
			$this->trans->method = 'BYE';
			$this->trans->cseq = $this->local_cseq;
			$this->trans->branch = SIP::new_branch();
			$this->trans->timers = array(0, 1, 2, 2);
		}
	}
	
	function incoming($msg, $trans){		
		if(parent::incoming($msg, $trans)){
			return true;
		}
		
		if($msg->method === 'CANCEL'){
			if($this->is_state(SIP::TRYING) || $this->is_state(SIP::RINGING)){
				$trans->code = 487; // Request Terminated
				$trans->timers = array(0, 5);
				
				$this->set_state(SIP::CLOSING);
			}else{
				// 不关闭
			}

			// response OK
			$new = new SipTransaction();
			$new->code = 200;
			$new->method = $msg->method;
			$new->cseq = $msg->cseq;
			$new->branch = SIP::new_branch(); // 新 branch
			$new->to_tag = $this->local->tag();
			$new->timers = array(0, 0);
			$this->transactions[] = $new;
			return true;
		}
		
		if($msg->method === 'BYE'){
			if($this->is_state(SIP::TRYING) || $this->is_state(SIP::RINGING)){
				$trans->code = 487; // Request Terminated
				$trans->timers = array(0, 5);
			}else{
				$trans->timers = array(0, 5); // 等待超时关闭
			}
			$this->set_state(SIP::CLOSING);

			// response OK
			$new = new SipTransaction();
			$new->code = 200;
			$new->method = $msg->method;
			$new->cseq = $msg->cseq;
			$new->branch = $msg->branch; // 原 branch
			$new->to_tag = $this->local->tag();
			$new->timers = array(0, 0);
			$this->transactions[] = $new;
			return true;
		}

		if($msg->method === 'INVITE'){
			$trans->cseq = $msg->cseq;
			$trans->branch = $msg->branch;
			$trans->nowait();
			return true;
		}
		if($msg->method === 'ACK'){
			if($trans->code >= 300){
				$this->terminate();
				return true;
			}
			if($trans->method === 'INVITE'){
				if($this->is_state(SIP::COMPLETED)){
					Logger::debug("duplicated ACK, ignore");
					$trans->timers = array(0, 5); // 等待可能重传的 ACK
				}else{
					$this->complete();
					$trans->timers = array(0, 5); // 等待可能重传的 ACK
					
					// keepalive
					$new = new SipTransaction();
					$new->method = 'OPTIONS';
					$new->uri = $msg->uri;
					$new->cseq = $msg->cseq;
					$new->branch = $msg->branch;
					$new->to_tag = $this->local->tag();
					$new->timers = array(10000); // TODO:
					
					$this->trans = $new;
					$this->transactions[] = $new;
				}
				return true;
			}
		}
	}
}
