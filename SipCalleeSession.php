<?php
class SipCalleeSession extends SipSession
{
	function __construct($msg){
		parent::__construct();
		
		$this->role = SIP::CALLEE;
		$this->state = SIP::ACCEPTING;
		$this->timers = self::$call_timers;

		$this->uri = $msg->uri;
		$this->call_id = $msg->call_id;
		$this->branch = $msg->branch;
		$this->cseq = $msg->cseq;
		$this->from = $msg->from;
		$this->from_tag = $msg->from_tag;
		$this->to = $msg->to;
		$this->to_tag = SIP::new_tag();
	}
	
	function incoming($msg){
		if($this->state == SIP::ACCEPTING){
			if($msg->method == 'ACK'){
				Logger::debug("call established");
				$this->state = SIP::ESTABLISHED;
				$this->timers = self::$refresh_timers;
			}
		}else if($this->state == SIP::ESTABLISHED || $this->state == SIP::CLOSING){
			if($msg->method == 'BYE'){
				if($this->state == SIP::ESTABLISHED){
					Logger::debug("call close by BYE");
				}else{
					Logger::debug("recv BYE while closing");
				}
				$this->state = SIP::CLOSING;
				$this->timers = self::$closing_timers;
			}
		}
	}
	
	function outgoing(){
		if($this->state == SIP::ACCEPTING){
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->method = 'INVITE';
			return $msg;
		}else if($this->state == SIP::ESTABLISHED){
			// TODO: refresh
			$this->timers = self::$refresh_timers;
			Logger::debug("refresh dialog");
		}else if($this->state == SIP::CLOSING){
			// TESTING
			// static $i = 0;
			// if($i++%2 == 0){
			// 	echo "drop OK for BYE\n";
			// 	return;
			// }
			
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->method = 'BYE';
			return $msg;
		}
	}
}
