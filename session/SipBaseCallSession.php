<?php
abstract class SipBaseCallSession extends SipSession
{
	function __construct(){
		parent::__construct();
	}
	
	function incoming($msg){
		if($msg->cseq_method == 'OPTIONS' || $msg->cseq_method == 'INFO'){
			if($msg->code == 481 || $msg->code >= 500){
				// 让后面逻辑处理
			}else if($msg->code >= 200){
				$this->refresh();
				return true;
			}else{
				return true;
			}
		}

		if($msg->code == 481 || $msg->code >= 500){ // Call/Transaction Does Not Exist
			Logger::info("recv {$msg->code} {$msg->reason}, terminate " . $this->role_name());
			$this->terminate();
			return true;
		}else if($msg->code >= 300 && $msg->code < 400){
			// ...
			Logger::info("nothing to do with {$msg->code}");
		}else if($msg->code >= 400){
			Logger::info("recv {$msg->code} {$msg->reason}, terminate " . $this->role_name());
			$this->terminate();
			return true;
		}

		if($this->state == SIP::COMPLETED){
			if($msg->method == 'BYE'){
				Logger::debug($this->role_name() . " {$this->call_id} close by BYE");
				$this->onclose();
				return true;
			}
			return false;
		}else if($this->state == SIP::FIN_WAIT){
			if($msg->code == 200){
				Logger::info("recv {$msg->code} {$msg->reason}, finish CLOSE_WAIT " . $this->role_name());
				$this->terminate();
				return true;
			}else if($msg->method == 'BYE'){
				Logger::debug($this->role_name() . " {$this->call_id} FIN_WAIT => CLOSE_WAIT");
				$this->onclose();
				return true;
			}
			return false;
		}else if($this->state == SIP::CLOSE_WAIT){
			if($msg->method == 'BYE'){
				Logger::debug("recv BYE while CLOSE_WAIT");
				// 立即发送 OK
				array_unshift($this->timers, 0);
				return true;
			}
			return false;
		} 
	}
	
	function outgoing(){
		if($this->state == SIP::COMPLETED){
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
			$msg = new SipMessage();
			$msg->method = 'BYE';
			return $msg;
		}else if($this->state == SIP::CLOSE_WAIT){
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->cseq_method = 'BYE';
			return $msg;
		}
	}
	
}
