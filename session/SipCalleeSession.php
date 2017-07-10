<?php
class SipCalleeSession extends SipSession
{
	public $local_sdp;
	public $remote_sdp;
	public $remote_branch;
	
	function __construct($msg){
		parent::__construct();
		$this->role = SIP::CALLEE;
		$this->set_state(SIP::NONE);

		$this->uri = $msg->uri;
		$this->call_id = $msg->call_id;
		$this->local = clone $msg->to;
		$this->remote = clone $msg->from;
		$this->remote_cseq = $msg->cseq;
		$this->remote_sdp = $msg->content;
		
		$this->remote_branch = $msg->branch;
	}
	
	function init(){
		$this->contact = new SipContact($this->local->username, $this->local_ip . ':' . $this->local_port);
		// 不能在 100 响应中返回 totag，所以这里不生成 local tag
		$this->set_state(SIP::TRYING);
		$this->new_response($this->remote_branch);
		$this->trans->trying();
	}
	
	function ringing(){
		$this->set_state(SIP::RINGING);
		if(!$this->local->tag()){
			$this->local->set_tag(SIP::new_tag());
			$this->trans->to->set_tag($this->local->tag());
		}
		$this->trans->ringing();
	}
	
	function accept(){
		$this->set_state(SIP::COMPLETING);
		if(!$this->local->tag()){
			$this->local->set_tag(SIP::new_tag());
			$this->trans->to->set_tag($this->local->tag());
		}
		$this->trans->accept();
	}
	
	function close(){
		if($this->is_state(SIP::TRYING) || $this->is_state(SIP::RINGING)){
			$this->new_response();
			$this->trans->onclose();
			$this->trans->code = 487;
			$this->trans->method = 'INVITE';
		}else{
			$this->new_request();
			$this->trans->close();
			$this->trans->method = 'BYE';
		}
		$this->set_state(SIP::CLOSING);
		return;
	}

	function on_new_request($msg){
		// 其它状态下，禁止接收新请求。
		if(($this->is_state(SIP::COMPLETING) || $this->is_state(SIP::COMPLETED)) && $msg->method === 'ACK'){
			parent::on_new_request($msg);
			$this->trans->accept();
			return true;
		}
		if($this->is_state(SIP::COMPLETING) || $this->is_state(SIP::COMPLETED) && $msg->method === 'INVITE'){
			parent::on_new_request($msg);
			$this->trans->accept();
			return true;
		}
		return false;
	}
	
	function incoming($msg){
		$trans = $this->trans;
		if($trans->state == SIP::TRYING || $trans->state == SIP::RINGING){
			if($msg->method == 'INVITE'){
				Logger::debug("recv duplicated INVITE while " . SIP::state_text($trans->state));
				$trans->nowait();
				return true;
			}
		}
		if($trans->state == SIP::COMPLETING){
			if($msg->method == 'INVITE'){
				Logger::debug("recv duplicated INVITE while " . SIP::state_text($trans->state));
				$trans->nowait();
				return true;
			}
			if($msg->method == 'ACK'){
				// 放在 complete 前面，因为 complete 的回调可能会关闭会话（测试时）
				#$new = $this->new_request($trans->branch);
				$this->remote_branch = $msg->branch;
				$new = $this->new_request($this->remote_branch);
				$new->keepalive();
				$new->wait(100000); // TODO:

				if($this->is_completed()){
					Logger::debug("recv ACK when completed");
				}else{
					Logger::debug("recv ACK, complete callee");
					$this->complete();
				}
				
				return true;
			}
		}
		if($trans->state == SIP::FIN_WAIT){
			if($msg->code == 200){
				Logger::info("recv " . $msg->brief() . ", terminate " . $this->role_name());
				$this->terminate();
				return true;
			}
		}
		if($trans->state == SIP::CLOSE_WAIT){
			if($msg->method == 'ACK'){
				Logger::info("recv " . $msg->brief() . ", terminate " . $this->role_name());
				$this->terminate();
				return true;
			}
		}
	}
	
	function outgoing(){
		$trans = $this->trans;
		if($trans->state == SIP::FIN_WAIT){
			$msg = new SipMessage();
			if($trans->code){
				$msg->code = $trans->code;
				$msg->cseq_method = $trans->method;
			}else{
				$msg->method = $trans->method;
			}
			// 对方收到 CANCEL 后，会先回复 487 Request Terminated 给之前的请求，
			// 然后回复 200 给 CANCEL
			return $msg;
		}
		if($trans->state == SIP::CLOSE_WAIT){
			$msg = new SipMessage();
			if($trans->code){
				$msg->code = $trans->code;
				$msg->cseq_method = $trans->method;
			}else{
				$msg->method = $trans->method;
			}
			// 对方收到 CANCEL 后，会先回复 487 Request Terminated 给之前的请求，
			// 然后回复 200 给 CANCEL
			return $msg;
		}
		
		if($trans->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->code = 100;
			$msg->cseq_method = 'INVITE';
			return $msg;
		}else if($trans->state == SIP::RINGING){
			$msg = new SipMessage();
			$msg->code = 180;
			$msg->cseq_method = 'INVITE';
			return $msg;
		}else if($trans->state == SIP::COMPLETING){
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->cseq_method = 'INVITE';
			
			$msg->add_header('Content-Type', 'application/sdp');
			if($this->local_sdp){
				$msg->content = $this->local_sdp;
			}
			return $msg;
		}
	}
}
