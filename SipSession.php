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
	
	public $uri;
	// 最多存2个。第1个要么处于重传态，要么完成态。第2个要么处于完成态，要么关闭态。
	public $transactions = array();
	
	private $callback;
	
	function __construct(){
		$this->local = new SipContact();
		$this->remote = new SipContact();
		$this->local_cseq = SIP::new_cseq();
	}
	
	// abstract function init();
	abstract function incoming($msg, $trans);
	abstract function outgoing($trans);
	
	function set_callback($callback){
		$this->callback = $callback;
	}
	
	function state(){
		return $this->state;
	}
	
	function is_state($state){
		return $this->state === $state;
	}
	
	function set_state($new){
		$old = $this->state;
		$this->state = $new;
		if(!$old !== $new && $this->callback){
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
		// if($this->state != SIP::COMPLETED){
		// 	Logger::debug($this->brief() . " established");
		// }
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
		$this->transactions = array();
		$new = $this->new_request();
		$new->method = $method;
		$new->close();
		// foreach($this->transactions as $trans){
		// 	$this->transactions = array();
		// 	$new = $this->new_request($trans->branch);
		// 	$new->close();
		// 	break;
		// 	// $trans->close();
		// }
	}
	
	function onclose($msg){
		foreach($this->transactions as $trans){
			// 如果是在被动关闭，就让现有的关闭流程继续，否则将从主动关闭转为被动关闭
			if($trans->state == SIP::CLOSE_WAIT){
				return;
			}
		}
		$this->set_state(SIP::CLOSING);
		$this->transactions = array();
		$new = $this->new_response($msg->branch);
		$new->method = $msg->method;
		$new->onclose();
	}
	
	function terminate(){
		$this->set_state(SIP::CLOSED);
		$this->transactions = array();
	}
	
	function new_request($branch=null){
		$this->local_cseq ++;
		
		$trans = new SipTransaction();
		$trans->branch = ($branch===null)? SIP::new_branch() : $branch;
		if(!$this->local){
			$bt = debug_backtrace(false);
			foreach($bt as $b){
				echo "{$b['file']} {$b['line']}\n";
			}
		}
		$trans->local_tag = $this->local->tag();
		$trans->remote_tag = $this->remote->tag();
		$trans->cseq = $this->local_cseq;
		$this->add_transaction($trans);
		return $trans;
	}
	
	function new_response($branch){
		$trans = new SipTransaction();
		$trans->branch = $branch; //
		$trans->local_tag = $this->local->tag();
		$trans->remote_tag = $this->remote->tag();
		$trans->cseq = $this->remote_cseq;
		$this->add_transaction($trans);
		return $trans;
	}
	
	function add_transaction($trans){
		$this->transactions[] = $trans;
	}
	
	function del_transaction($trans){
		foreach($this->transactions as $index=>$tmp){
			if($tmp === $trans){
				unset($this->transactions[$index]);
				break;
			}
		}
	}
}
