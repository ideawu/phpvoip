<?php
class SipNullSession extends SipSession
{
	function __construct(){
		$this->role = SIP::NONE;
		$this->state = SIP::TRYING;
		
		$this->new_transaction(SIP::TRYING, array(1, 0));
	}
	
	function incoming($msg, $trans){
		return null;
	}
	
	private $count = 0;

	function outgoing($trans){
		if($this->state == SIP::COMPLETED || $this->state == SIP::TRYING){
			$this->del_transaction($trans);
			$this->new_transaction(SIP::TRYING, array(1, 0));
		}
		if(++$this->count == 5){
			$this->complete();
		}
		return null;
	}
}

