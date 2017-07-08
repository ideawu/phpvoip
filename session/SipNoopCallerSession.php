<?php
class SipNoopCallerSession extends SipSession
{
	public $local_sdp;
	public $remote_sdp;

	function __construct(){
		parent::__construct();
		$this->role = SIP::NOOP;
		$this->set_state(SIP::TRYING);
		
		$this->local = new SipContact();
		$this->remote = new SipContact();
		
		$this->remote_sdp = <<<TEXT
v=0
o=test 1499506858 1499506858 IN IP4 127.0.0.1
s=SIP Call
c=IN IP4 127.0.0.1
t=0 0
m=audio 27230 RTP/AVP 0 8
a=rtpmap:0 PCMU/8000
a=rtpmap:8 PCMA/8000
a=ptime:30
TEXT;
	}
	
	function init(){
		$new = $this->new_request();
		$new->trying();
		$new->timers = array(1, 1, 1, 1, 1, 1);
	}
	
	function incoming($msg, $trans){
		return null;
	}
	
	private $count = 0;

	function outgoing($trans){
		if(++$this->count == 2){
			$this->complete();
			$trans->wait(999);
		}
		return null;
	}
}

