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
		$new = $this->new_response($this->remote_branch);
		$new->trying();
	}
	
	function brief(){
		return $this->role_name() .' '. $this->remote->address() .'=>'. $this->local->address();
	}

	function del_transaction($trans){
		parent::del_transaction($trans);
		if($this->is_state(SIP::TRYING) || $this->is_state(SIP::RINGING)){
			Logger::debug("del_transaction close");
			$this->close();
		}
	}

	function close(){
		if($this->is_state(SIP::CLOSING)){
			return;
		}

		if($this->is_state(SIP::TRYING) || $this->is_state(SIP::RINGING)){
			foreach($this->transactions as $new){ // 应该倒序遍历
				Logger::debug("reply Busy Here");
				$new->code = 486;
				$new->method = 'INVITE';
				$new->close();
			}
		}else if($this->is_state(SIP::COMPLETING) || $this->is_state(SIP::COMPLETED)){
			foreach($this->transactions as $new){ // 应该倒序遍历
				Logger::debug("Send bye");
				$new->method = 'BYE';
				$new->close();
			}
		}
		
		$this->set_state(SIP::CLOSING);
	}
		
	function ringing(){
		$this->set_state(SIP::RINGING);
		if(!$this->local->tag()){
			$this->local->set_tag(SIP::new_tag());
		}
		
		$this->transactions = array();
		$new = $this->new_response($this->remote_branch);
		$new->ringing();
	}
	
	function completing(){
		$this->set_state(SIP::COMPLETING);
		if(!$this->local->tag()){
			$this->local->set_tag(SIP::new_tag());
		}
		
		$this->transactions = array();
		$new = $this->new_response($this->remote_branch);
		$new->accept();
	}
	
	function incoming($msg, $trans){
		$ret = parent::incoming($msg, $trans);
		if($ret === true){
			return true;
		}
		
		if($msg->method == 'INVITE'){
			Logger::debug("recv duplicated INVITE");
			if($this->is_state(SIP::COMPLETED)){
				$trans->completing();
			}else{
				$trans->nowait();
			}
			if($msg->content){
				$this->remote_sdp = $msg->content;
			}
			return true;
		}
		if($trans->state == SIP::COMPLETING && !$this->is_state(SIP::COMPLETED)){
			if($msg->method == 'ACK'){
				Logger::debug("recv ACK, complete callee");
				$this->complete();
				
				// 清除全部事务
				$this->transactions = array();
				
				$new = $this->new_request($trans->branch);
				$new->keepalive();
				
				return true;
			}
		}
	}
	
	function outgoing($trans){
		$msg = parent::outgoing($trans);
		if($msg){
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
