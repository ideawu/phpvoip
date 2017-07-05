<?php
class SipNoopCallerSession extends SipSession
{
	function __construct(){
		$this->role = SIP::NOOP;
		$this->set_state(SIP::TRYING);
		
		$this->local = new SipContact();
		$this->remote = new SipContact();
		
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

