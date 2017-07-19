<?php
/*
本地会话成对出现，一个充当 caller，另一个充当 callee。它们之间的通信通过
相互调用来完成，不需要发送实际的网络报文。
*/
class LocalCaller extends SipSession
{
	public $callee;
	public $remote_sdp;

	function __construct(){
		parent::__construct();
		$this->role = SIP::CALLER;
		
		$this->local = new SipContact('@');
		$this->remote = new SipContact('@');
	}
	
	function init(){
		$this->set_state(SIP::TRYING);
		$this->trans->timers = array(10, 0);
	}
	
	function ringing(){
		$this->set_state(SIP::RINGING);
	}

	function completing(){
		$this->set_state(SIP::COMPLETING);
	}
	
	// 对于 LocalCaller，上层应该主动调用 caller.complete() 方法
	function complete(){
		parent::complete();
		$this->callee->complete();
	}
	
	function close(){
		$this->terminate();
	}
	
	function terminate(){
		if($this->is_state(SIP::CLOSED)){
			return;
		}
		parent::terminate();
		$this->callee->terminate();
	}
	
	function incoming($msg, $trans){
		return null;
	}

	function outgoing($trans){
		if($this->is_state(SIP::COMPLETING)){
			$this->complete();
		}
		if($this->is_state(SIP::COMPLETED)){
			$this->trans->timers = array(10, 0);
		}
		return null;
	}
}

