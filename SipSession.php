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
	public $local_tag;
	public $remote_tag;
	public $local_cseq;
	public $remote_cseq;
	public $local;
	public $remote;
	public $contact;
	
	public $uri;
	// 最多存2个。第1个要么处于重传态，要么完成态。第2个要么处于完成态，要么关闭态。
	public $transactions = array();
	
	private $callback;
	
	function __construct(){
		$this->local_cseq = mt_rand(100, 1000);
	}
	
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
			return 'NONE';
		}
	}
	
	function complete(){
		if($this->state != SIP::COMPLETED){
			Logger::debug($this->role_name() . " session {$this->call_id} established");
		}
		$this->set_state(SIP::COMPLETED);
	}
	
	function close(){
		// TODO: $this->set_state(SIP::CLOSING);
		$this->transactions = array();
		$new = $this->new_request();
		$new->close();
	}
	
	function onclose($msg){
		// TODO: $this->set_state(SIP::CLOSING);
		$this->transactions = array();
		$new = $this->new_response($msg->branch);
		$new->onclose();
	}
	
	function terminate(){
		$this->set_state(SIP::CLOSED);
		$this->transactions = array();
	}
	
	function new_request(){
		$this->local_cseq ++;
		
		$trans = new SipTransaction();
		$trans->branch = SIP::new_branch();
		$trans->local_tag = $this->local_tag;
		$trans->remote_tag = $this->remote_tag;
		$trans->cseq = $this->local_cseq;
		$this->add_transaction($trans);
		return $trans;
	}
	
	function new_response($branch){
		$trans = new SipTransaction();
		$trans->branch = $branch; //
		$trans->local_tag = $this->local_tag;
		$trans->remote_tag = $this->remote_tag;
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
