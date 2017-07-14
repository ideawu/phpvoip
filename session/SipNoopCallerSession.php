<?php
class SipNoopCallerSession extends SipSession
{
	public $local_sdp;
	public $remote_sdp;

	function __construct(){
		parent::__construct();
		$this->role = SIP::NOOP;
		$this->set_state(SIP::TRYING);
		
		$this->local = new SipContact('@');
		$this->remote = new SipContact('@');
		$this->remote_sdp = <<<TEXT
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
		$this->trans->timers = array(0.5, 0);
	}
	
	function close(){
		$this->set_state(SIP::CLOSING);
		$this->trans->timers = array(1);
	}
	
	function incoming($msg, $trans){
		return null;
	}
	
	private $count = 0;

	function outgoing($trans){
		$this->trans->timers = array(0.5, 0);
		$this->count += 1;
		if($this->count == 1){
			$this->set_state(SIP::RINGING);
		}
		if($this->count == 2){
			//$this->terminate();
			$this->complete();
		}
		if($this->count == 10){
			#$this->terminate();
		}
		return null;
	}
}

