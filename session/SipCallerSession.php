<?php
class SipCallerSession extends SipBaseCallSession
{
	function __construct(){
		parent::__construct();
		
		$this->role = SIP::CALLER;
		$this->state = SIP::TRYING;
		
		$this->call_id = SIP::new_call_id();
		$this->branch = SIP::new_branch();
		$this->local_tag = SIP::new_tag();
		$this->cseq = mt_rand(1, 10000);
		
		$this->new_transaction(SIP::TRYING, self::$call_timers);
		// TODO: 实现 INVITE 超时后，放送 BYE/CANCEL
	}
	
	function del_transaction($trans){
		parent::del_transaction($trans);
		if($this->state == SIP::TRYING){
			if(!$this->transactions){
				$new = $this->new_transaction(SIP::TRYING);
				$new->close(); // TODO: 应该用 cancel？
			}
		}
	}

	function incoming($msg, $trans){
		if($msg->code >= 180){
			$this->remote_tag = $msg->to_tag;
		}
		
		$ret = parent::incoming($msg, $trans);
		if($ret === true){
			return true;
		}
		
		if($trans->state == SIP::TRYING){
			if($msg->code == 200){
				$this->complete();

				Logger::debug("recv OK, send ACK");
				$trans->state = SIP::COMPLETING;
				$trans->timers = array(0, 10);

				$new = $this->new_transaction(SIP::KEEPALIVE);
				$new->branch = $trans->branch;
				$new->remote_tag = $this->remote_tag;
				$new->refresh();

				return true;
			}
		}else if($trans->state == SIP::COMPLETING){
			if($msg->code == 200){
				Logger::debug("duplicated OK, resend ACK");
				array_unshift($trans->timers, 0);
				return true;
			}
		}
	}
	
	function outgoing($trans){
		$msg = parent::outgoing($trans);
		if($msg){
			return $msg;
		}
		
		if($trans->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->method = 'INVITE';
			return $msg;
		}else if($trans->state == SIP::COMPLETING){
			static $i = 0;
			if($i++%2 == 0){
				Logger::debug("manually drop outgoing msg");
				return;
			}
			$msg = new SipMessage();
			$msg->method = 'ACK';
			return $msg;
		}
	}
}
