<?php
class SipCalleeSession extends SipBaseCallSession
{
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

	// function close(){
	// 	if($this->is_state(SIP::CLOSING)){
	// 		return;
	// 	}
	//
	// 	if($this->is_state(SIP::TRYING) || $this->is_state(SIP::RINGING)){
	// 		foreach($this->transactions as $new){ // 应该倒序遍历
	// 			Logger::debug("reply Busy Here");
	// 			$new->code = 486;
	// 			$new->method = 'INVITE';
	// 			$new->close();
	// 		}
	// 	}else if($this->is_state(SIP::COMPLETING) || $this->is_state(SIP::COMPLETED)){
	// 		foreach($this->transactions as $new){ // 应该倒序遍历
	// 			Logger::debug("Send bye");
	// 			$new->method = 'BYE';
	// 			$new->close();
	// 		}
	// 	}
	//
	// 	$this->set_state(SIP::CLOSING);
	// }
		
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

	function on_new_request($msg){
		// 其它状态下，禁止接收新请求。
		if(($this->is_state(SIP::COMPLETING) || $this->is_state(SIP::COMPLETED)) && $msg->method === 'ACK'){
			parent::on_new_request($msg);
			$this->remote_branch = $msg->branch;
			$this->trans->accept();
			return true;
		}
		if($this->is_state(SIP::COMPLETED) && $msg->method === 'INVITE'){
			parent::on_new_request($msg);
			$this->trans->accept();
			return true;
		}
		return false;
	}
	
	function incoming($msg){
		// $ret = parent::incoming($msg);
		// if($ret === true){
		// 	return true;
		// }
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
				if($this->is_completed()){
					Logger::debug("recv ACK when completed");
				}else{
					Logger::debug("recv ACK, complete callee");
					$this->complete();
				}
				
				$new = $this->new_request($trans->branch);
				$new->keepalive();
				$new->wait(9999);
				return true;
			}
		}
	}
	
	function outgoing(){
		// $msg = parent::outgoing($trans);
		// if($msg){
		// 	return $msg;
		// }
		$trans = $this->trans;
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
