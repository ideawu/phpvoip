<?php
/*
本地会话成对出现，一个充当 caller，另一个充当 callee。它们之间的通信通过
相互调用来完成，不需要发送实际的网络报文。
*/
class LocalCallee extends SipSession
{
	public $caller;
	public $local_sdp;
	private $interval = 0.5; // TESTING
	
	function __construct(){
		parent::__construct();
		$this->role = SIP::CALLEE;
		
		$this->local = new SipContact('@');
		$this->remote = new SipContact('@');
		$this->local_sdp = <<<TEXT
v=0
o=yate 1499684812 1499684812 IN IP4 127.0.0.1
s=SIP Call
c=IN IP4 127.0.0.1
t=0 0
m=audio 28300 RTP/AVP 109 0 8 101
a=rtpmap:109 iLBC/8000
a=fmtp:109 mode=30
a=rtpmap:0 PCMU/8000
a=rtpmap:8 PCMA/8000
a=rtpmap:101 telephone-event/8000
a=ptime:30
TEXT;
	}
	
	function init(){
		$this->set_state(SIP::TRYING);
		$this->trans->timers = array($this->interval, 0);
	}
	
	function ringing(){
		if($this->is_state(SIP::RINGING)){
			return;
		}
		$this->set_state(SIP::RINGING);
		$this->caller->ringing();
	}

	function completing(){
		if($this->is_state(SIP::COMPLETING)){
			return;
		}
		$this->set_state(SIP::COMPLETING);
		$this->trans->timers = array($this->interval, 0);
		$this->caller->completing();
	}

	function complete(){
		if($this->is_state(SIP::COMPLETED)){
			return;
		}
		parent::complete();
		$this->trans->timers = array($this->interval, 0);
		$this->caller->completing();
	}
	
	function close(){
		$this->terminate();
	}
	
	function terminate(){
		if($this->is_state(SIP::CLOSED)){
			return;
		}
		parent::terminate();
		$this->caller->terminate();
	}

	function incoming($msg, $trans){
		return null;
	}
	
	function outgoing($trans){
		if($this->is_state(SIP::COMPLETED)){
			$this->trans->timers = array(10, 0);
		}else{
			$this->trans->timers = array($this->interval, 0);
		}
		
		if($this->is_state(SIP::TRYING)){
			$this->ringing();
		}else if($this->is_state(SIP::RINGING)){
			$this->completing();
		}else if($this->is_state(SIP::COMPLETING)){
			$this->complete();
		}
		return null;
	}
}

