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
	}

	function incoming($msg, $trans){
		// $ret = parent::incoming($msg);
		// if($ret === true){
		// 	return true;
		// }
		
		if($trans->state == SIP::TRYING){
			if($msg->code == 200){
				$this->remote_tag = $msg->to_tag;
				$this->complete();

				// completing
				Logger::debug("recv OK, send ACK");
				$trans->state = SIP::COMPLETING;
				$trans->timers = array(0, 5);
				
				// completing 结束后再开始 keepalive? 不创建新的 transaction？
				// new keepalive transaction
				$new = $this->new_transaction(SIP::KEEPALIVE);
				$new->cseq = $trans->cseq;
				$new->branch = $trans->branch;
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
		
		// TODO:...
		if($trans->state == SIP::KEEPALIVE){
			$trans->refresh();
		}
	}
	
	function outgoing($trans){
		// $msg = parent::outgoing();
		// if($msg){
		// 	return $msg;
		// }
		if($trans->state == SIP::KEEPALIVE){
			Logger::debug("refresh " . $this->role_name() . " session {$this->call_id}");

			$msg = new SipMessage();
			if(in_array('INFO', $this->remote_allow)){
				$msg->method = 'INFO';
				$msg->add_header('Content-Type', 'application/msml+xml');
			}else{
				$msg->method = 'OPTIONS';
			}
			return $msg;
		}
		
		if($trans->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->method = 'INVITE';
			return $msg;
		}else if($trans->state == SIP::COMPLETING){
			static $i = 0;
			if($i++%2 == 0){
				Logger::debug("drop outgoing msg");
				return;
			}
			$msg = new SipMessage();
			$msg->method = 'ACK';
			return $msg;
		}
	}
}
