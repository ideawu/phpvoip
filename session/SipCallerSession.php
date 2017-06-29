<?php
class SipCallerSession extends SipBaseCallSession
{
	function __construct(){
		parent::__construct();
		
		$this->role = SIP::CALLER;
		$this->state = SIP::TRYING;
		$this->timers = self::$call_timers;
		
		$this->call_id = SIP::new_call_id();
		$this->branch = SIP::new_branch();
		$this->from_tag = SIP::new_tag();
		$this->cseq = mt_rand(1, 10000);
	}

	function incoming($msg){
		$ret = parent::incoming($msg);
		if($ret === true){
			return true;
		}
		
		if($this->state == SIP::TRYING){
			if($msg->code == 200){
				$this->state = SIP::COMPLETING;
				$this->timers = self::$now_timers;
				return true;
			}
		}else if($this->state == SIP::COMPLETED){
			if($msg->code == 200){
				// 收到重复的 200，回复 ACK
				// TODO: 不应该修改状态，但 incoming 怎么返回消息呢？
				$this->state = SIP::COMPLETING;
				$this->timers = self::$now_timers;
				return true;
			}
		}
	}
	
	function outgoing(){
		$msg = parent::outgoing();
		if($msg){
			return $msg;
		}
		
		if($this->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->method = 'INVITE';
			return $msg;
		}else if($this->state == SIP::COMPLETING){
			$this->complete();
			$this->refresh();
			
			$msg = new SipMessage();
			$msg->method = 'ACK';
			return $msg;
		}
	}
}
