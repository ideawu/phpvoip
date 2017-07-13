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
		$this->local_cseq = SIP::new_cseq();
		$this->remote_cseq = $msg->cseq;

		$this->remote_sdp = $msg->content;

		$this->trans->uri = $msg->uri;
		$this->trans->method = $msg->method;
		$this->trans->cseq = $msg->cseq;
		$this->trans->branch = $msg->branch;
	}
	
	function init(){
		$this->contact = new SipContact($this->local->username, $this->local_ip . ':' . $this->local_port);
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
		$this->trans->content = $this->local_sdp;
		$this->trans->content_type = 'application/sdp';
		$this->trans->timers = array(0, 1, 2, 2, 2);
		if(!$this->local->tag()){
			$this->local->set_tag(SIP::new_tag());
		}
		$this->trans->to_tag = $this->local->tag();
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
			$this->trans->to_tag = $this->local->tag();
			$this->trans->timers = array(0, 1, 2, 2, 2);
		}else{
			$this->set_state(SIP::CLOSING);
			Logger::debug("callee send BYE to close session");
			
			// 发送 BYE, 直到收到 200
			$new = new SipTransaction();
			$new->uri = "sip:{$this->remote->username}@{$this->remote->domain}";
			$new->code = 0;
			$new->method = 'BYE';
			$new->cseq = $this->local_cseq;
			$new->branch = SIP::new_branch();
			$new->to_tag = $this->remote->tag();
			$new->timers = array(0, 1, 2, 2, 2);
			$this->transactions = array($new);
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
				$trans->to_tag = $this->local->tag();
				$trans->timers = array(0, 3);
			}else{
				// 不关闭
			}

			// response OK
			$new = new SipTransaction();
			$new->code = 200;
			$new->method = $msg->method;
			$new->cseq = $msg->cseq;
			$new->branch = $msg->branch; // 原 branch
			$new->to_tag = SIP::new_tag(); // TODO
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
				if(!$this->is_state(SIP::ACCEPTING)){
					Logger::debug("recv ACK when " . $this->state_text());
				}else{
					$this->complete();
					$trans->timers = array(0, 3); // 等待可能重传的 ACK

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
