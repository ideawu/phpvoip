<?php
class SipRecycleSession extends SipSession
{
	function init(){
		$this->set_state(SIP::RECYCLE);
		$this->new_response('');
		$this->trans->state = SIP::RECYCLE;
		$this->trans->timers = array(100);
	}
	
	function incoming($msg){
		$trans = $this->trans;
		$trans->cseq = $msg->cseq;
		$trans->cseq_method = $msg->method;
		$trans->branch = $msg->branch;
		$trans->nowait();
		return true;
	}
	
	function outgoing(){
		$trans = $this->trans;
		$msg = new SipMessage();
		$msg->code = 481;
		return $msg;
	}
}
