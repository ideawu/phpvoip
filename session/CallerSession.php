<?php
class CallerSession extends BaseCallSession
{
	public $local_sdp;
	public $remote_sdp;

	function __construct($from, $to){
		parent::__construct();
		$this->role = SIP::CALLER;

		$this->uri = new SipUri($to->username, $to->domain);
		$this->call_id = SIP::new_call_id();
		$this->local = $from;
		$this->local->set_tag(SIP::new_tag());
		$this->remote = $to;

		$this->trans->uri = clone $this->uri;
		$this->trans->method = 'INVITE';
		$this->trans->cseq = $this->local_cseq;
		$this->trans->branch = $this->local_branch;
	}
	
	function init(){
		$this->set_state(SIP::TRYING);
		$this->contact = new SipContact($this->local->username, $this->local_ip . ':' . $this->local_port);
		$this->trans->timers = array(0, 1, 2, 2, 10);
	}

	function close(){
		if($this->is_state(SIP::CLOSING)){
			return;
		}
		if($this->is_state(SIP::TRYING) || $this->is_state(SIP::RINGING)){
			$this->set_state(SIP::CLOSING);
			Logger::debug("caller send CANCEL to close session");
			// 发送 BYE, 直到收到 200
			$new = new SipTransaction();
			$new->uri = new SipUri($this->remote->username, $this->remote->domain);
			$new->method = 'CANCEL';
			$new->cseq = $this->local_cseq;
			$new->branch = $this->local_branch;
			$new->timers = array(0, 1, 2, 2, 2);
			$this->remote->del_tag(); // TODO
			$this->transactions[] = $new;
		}else{
			$this->bye();
		}
	}
	
	function completing(){
		$this->set_state(SIP::COMPLETING);
	}
	
	function complete(){
		parent::complete();
	}

	protected function incoming($msg, $trans){
		if(parent::incoming($msg, $trans)){
			return true;
		}
		if($msg->is_response() && $msg->cseq_method === 'INVITE'){
			if($msg->code === 100){
				$trans->timers = array(5);
				return true;
			}
			if($msg->code === 180){
				$this->set_state(SIP::RINGING);
				$this->remote->set_tag($msg->to->tag());
				$trans->timers = array(15);
				return true;
			}
			if($msg->code === 200){
				$this->remote->set_tag($msg->to->tag());
				$this->remote_sdp = $msg->content;
				if($this->is_state(SIP::TRYING) || $this->is_state(SIP::RINGING)){
					$this->completing();
					$this->complete(); // 自动 complete
					$this->keepalive();
				
					$trans->timers = array(3); // Timer D
					$this->transactions[] = $trans;
				}else{
					Logger::debug("recv 200 when " . $this->state_text());
				}

				$new = new SipTransaction();
				$new->uri = new SipUri($this->remote->username, $this->remote->domain);
				$new->method = 'ACK';
				$new->cseq = $msg->cseq;
				$new->branch = SIP::new_branch();
				$new->timers = array(0, 0);
				$this->transactions[] = $new;

				return true;
			}
			return false;
		}
	}
	
	protected function outgoing($trans){
		$msg = parent::outgoing($trans);
		if($msg && ($msg->is_request() && $msg->method === 'INVITE')){
			$msg->content = $this->local_sdp;
			$msg->content_type = 'application/sdp';
		}
		return $msg;
	}
}
