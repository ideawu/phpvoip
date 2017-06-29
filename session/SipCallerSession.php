<?php
class SipCallerSession extends SipSession
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
		if($msg->cseq_method == 'OPTIONS' || $msg->cseq_method == 'INFO'){
			if($msg->is_request()){
				//
			}else{
				if($msg->code == 481){ // Call/Transaction Does Not Exist
					Logger::info("recv 481, close " . $this->role_name());
					$this->close();
				}else if($msg->code >= 200){
					$this->refresh();
				}
			}
			return;
		}
		
		if($this->state == SIP::TRYING){
			if($msg->code == 200){
				Logger::debug($this->role_name() . " session {$this->call_id} established");
				$this->state = SIP::COMPLETING;
				$this->timers = self::$now_timers;
			}else if($msg->code >= 300){
				Logger::debug($this->role_name() . " {$this->call_id} failed by {$msg->code}");
				$this->close();
			}
		}else if($this->state == SIP::COMPLETED){
			if($msg->method == 'BYE'){
				Logger::debug($this->role_name() . " {$this->call_id} close by BYE");
				$this->onclose();
			}else if($msg->code == 200){
				// 收到重复的 200，回复 ACK
				$this->state = SIP::COMPLETING;
				$this->timers = self::$now_timers;
			}
		}else if($this->state == SIP::FIN_WAIT){
			if($msg->method == 'BYE'){
				Logger::debug($this->role_name() . " {$this->call_id} FIN_WAIT => CLOSE_WAIT");
				$this->onclose();
			}
		}else if($this->state == SIP::CLOSE_WAIT){
			if($msg->method == 'BYE'){
				Logger::debug("recv BYE while CLOSE_WAIT");
				// 立即发送 OK
				array_unshift($this->timers, 0);
			}
		}
	}
	
	function outgoing(){
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
		}else if($this->state == SIP::COMPLETED){
			Logger::debug("refresh " . $this->role_name() . " session {$this->call_id}");

			$msg = new SipMessage();
			if(in_array('INFO', $this->remote_allow)){
				$msg->method = 'INFO';
				$msg->add_header('Content-Type', 'application/msml+xml');
				if(!$this->info_cseq){
					$this->info_cseq = $this->cseq;
				}
			}else{
				$msg->method = 'OPTIONS';
				if(!$this->options_cseq){
					$this->options_cseq = $this->cseq;
				}
			}
			return $msg;
		}else if($this->state == SIP::FIN_WAIT){
			// 发送 BYE
		}else if($this->state == SIP::CLOSE_WAIT){
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->cseq_method = 'BYE';
			return $msg;
		}
	}
}
