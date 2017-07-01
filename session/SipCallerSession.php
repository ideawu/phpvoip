<?php
class SipCallerSession extends SipBaseCallSession
{
	function __construct(){
		parent::__construct();
		
		$this->role = SIP::CALLER;
		$this->state = SIP::CALLING;
		
		$this->call_id = SIP::new_call_id();
		$this->local_tag = SIP::new_tag();
		
		$this->new_transaction(SIP::CALLING, self::$call_timers);
	}

	function del_transaction($trans){
		parent::del_transaction($trans);
		if($trans->state == SIP::CALLING){
			// 实现 cancel
			$this->close();
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
		
		if($trans->state == SIP::CALLING){
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
		
		if($trans->state == SIP::CALLING){
			$msg = new SipMessage();
			$msg->method = 'INVITE';
			return $msg;
		}else if($trans->state == SIP::COMPLETING){
			static $i = 0;
			if($i++%2 == 0){
				Logger::debug("manually drop outgoing msg ACK");
				return;
			}
			$msg = new SipMessage();
			$msg->method = 'ACK';
			return $msg;
		}
	}
}
