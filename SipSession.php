<?php
abstract class SipSession
{
	public $role;
	private $state = 0;

	public $local_ip;
	public $local_port;
	public $remote_ip;
	public $remote_port;
	
	public $remote_allow = array();

	public $call_id; // session id
	public $local;
	public $remote;
	public $contact;
	public $local_cseq;
	public $remote_cseq;
	public $local_contact;
	public $remote_contact;
	
	public $uri;
	public $trans;

	private $callback;
	
	function __construct(){
		$this->local = new SipContact();
		$this->remote = new SipContact();
		$this->local_cseq = SIP::new_cseq();
	}
	
	abstract function init();
	abstract function on_new_request($msg);
	abstract function on_request($msg);
	abstract function on_response($msg);
	
	function incoming($msg){
		if(!$msg->is_reqeust()){
			$ret = $this->check_trans($msg);
			if(!$ret){
				$ret = $this->on_new_request();
				if(!$ret){
					Logger::debug("drop request");
					return false;
				}
			}
			$this->trans->cseq = $msg->cseq;
			$this->trans->branch = $msg->branch;
			return $this->on_request($msg);
		}else{
			$ret = $this->check_trans($msg);
			if(!$ret){
				Logger::debug("drop response");
				return false;
			}
			return $this->on_response($msg);
		}
	}
	
	function outgoing(){
	}
	
	/*
	当会话收到一个新的cseq请求消息时，调用本方法，默认创建一个回复事务。子类可以改写本方法，
	判断某个状态和某些消息类型才创建新的回复事务。
	*/
	function on_new_request($msg){
		Logger::debug("recv new request, create new response");
		$this->remote_cseq = $msg->cseq;
		$this->new_response($msg->branch);
		return true;
	}
	
	function set_callback($callback){
		$this->callback = $callback;
	}
	
	function state(){
		return $this->state;
	}
	
	function is_state($state){
		return $this->state === $state;
	}
	
	function is_completed(){
		return $this->state === SIP::COMPLETED;
	}
	
	function set_state($new){
		$old = $this->state;
		$this->state = $new;
		if($old !== $new && $this->callback){
			Logger::debug($this->brief() . " state = " . $this->state_text());
			call_user_func($this->callback, $this);
		}
	}
	
	function role_name(){
		if($this->role == SIP::REGISTER){
			return 'REGISTER';
		}else if($this->role == SIP::REGISTRAR){
			return 'REGISTRAR';
		}else if($this->role == SIP::CALLER){
			return 'CALLER';
		}else if($this->role == SIP::CALLEE){
			return 'CALLEE';
		}else{
			return 'NOOP';
		}
	}
	
	function state_text(){
		return SIP::state_text($this->state);
	}
	
	function brief(){
		return $this->role_name() .' '. $this->local->address() .'=>'. $this->remote->address();
	}
	
	function complete(){
		$this->set_state(SIP::COMPLETED);
	}
	
	function close(){
		// 主动关闭只执行一次
		if($this->is_state(SIP::CLOSING)){
			return;
		}
		if($this->is_state(SIP::COMPLETED) || $this->is_state(SIP::COMPLETING)){
			$method = 'BYE';
		}else{
			$method = 'CANCEL';
		}
		$this->set_state(SIP::CLOSING);
		$this->trans->close();
		$this->trans->method = $method;
		return;
	}
	
	function onclose($msg){
		// 如果是在被动关闭，就让现有的关闭流程继续，否则将从主动关闭转为被动关闭
		if($this->trans->state == SIP::CLOSE_WAIT){
			return;
		}

		$this->set_state(SIP::CLOSING);
		if($msg->method == 'BYE' || $msg->method == 'CANCEL'){
			$this->trans->code = 200;
			$this->trans->method = $msg->method;
		}else{
			$this->trans->method = 'ACK';
		}
		$this->trans->onclose();
	}
	
	function terminate(){
		$this->set_state(SIP::CLOSED);
	}
	
	function new_request($branch=null){
		$this->local_cseq ++;
		
		$this->trans = new SipTransaction();
		$this->trans->branch = ($branch===null)? SIP::new_branch() : $branch;
		$this->trans->from = clone $this->local;
		$this->trans->to = clone $this->remote;
		$this->trans->cseq = $this->local_cseq;
		return $this->trans;
	}
	
	function new_response($branch){
		$this->trans = new SipTransaction();
		$this->trans->branch = $branch;
		$this->trans->from = clone $this->remote;
		$this->trans->to = clone $this->local;
		$this->trans->cseq = $this->remote_cseq;
		return $this->trans;
	}
}
