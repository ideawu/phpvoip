<?php
abstract class SipBaseCallSession extends SipSession
{
	function __construct(){
		parent::__construct();
	}
	
	function incoming($msg, $trans){
		if($trans->state == SIP::KEEPALIVE){
			if($msg->code == 200 || $msg->code == 415){ // 415 Unsupported Media Type
				$trans->keepalive();
				return true;
			}
		}else if($trans->state == SIP::FIN_WAIT){
			if($msg->code == 200){
				Logger::info("recv {$msg->code} {$msg->reason}, finish CLOSE_WAIT " . $this->role_name());
				$this->terminate();
				return true;
			}else if($msg->method == 'BYE'){
				Logger::debug($this->role_name() . " {$this->call_id} FIN_WAIT => CLOSE_WAIT");
				$this->onclose($msg);
				return true;
			}
			return false;
		}else if($trans->state == SIP::CLOSE_WAIT){
			if($msg->method == 'BYE'){
				Logger::debug("recv BYE while CLOSE_WAIT");
				array_unshift($trans->timers, 0);
				return true;
			}
			return false;
		}

		if($msg->method == 'BYE'){
			Logger::debug($this->role_name() . " {$this->call_id} close by BYE");
			$this->onclose($msg);
			return true;
		}else if($msg->code == 481 || $msg->code >= 500){ // Call/Transaction Does Not Exist
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
	}
	
	function outgoing($trans){
		if($trans->state == SIP::KEEPALIVE){
			Logger::debug("refresh " . $this->role_name() . " session {$this->call_id}");

			// 某些 PBX 没有检测客户端异常，所以 keepalive 未必有效。
			$msg = new SipMessage();
			if(in_array('INFO', $this->remote_allow)){
				$msg->method = 'INFO';
				$msg->add_header('Content-Type', 'application/sdp');
			}else{
				$msg->method = 'OPTIONS';
			}
			return $msg;
		}else if($trans->state == SIP::FIN_WAIT){
			$msg = new SipMessage();
			if($this->state == SIP::COMPLETED){
				$msg->method = 'BYE';
			}else{
				$msg->method = 'CANCEL';
			}
			return $msg;
		}else if($trans->state == SIP::CLOSE_WAIT){
			static $i = 0;
			if($i++%2 == 0){
				Logger::debug("manually drop outgoing msg 200 BYE");
				return;
			}
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->cseq_method = 'BYE';
			return $msg;
		}
	}
	
}
