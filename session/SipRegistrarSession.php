<?php
class SipRegistrarSession extends SipSession
{
	public $username;
	public $password;
	public $remote_branch;
	public $expires = 30;
	
	private $auth = array(
		'scheme' => 'Digest',
		'realm' => 'phpvoip',
		'algorithm' => 'MD5',
	);

	function __construct(){
		parent::__construct();
		$this->role = SIP::REGISTRAR;
		$this->set_state(SIP::NONE);
	}
	
	function init(){
		$this->set_state(SIP::TRYING);
		$new = $this->new_response($this->remote_branch);
		$new->trying();
	}
	
	function auth(){
		$this->set_state(SIP::AUTHING);
		$new = $this->new_response($this->remote_branch);
		$new->auth();
	}
	
	function new_response($branch){
		if($this->is_state(SIP::AUTHING)){
			$this->transactions = array();
			$new = parent::new_response($branch);
			$new->auth();
		}else if($this->is_state(SIP::COMPLETED)){
			// 客户端 refresh
			$new = parent::new_response($branch);
			$new->auth();
		}else{
			$new = parent::new_response($branch);
		}
		return $new;
	}
	
	/*
	有些实现，刷新注册时，使用新的 tag branch，仅 call_id 不变
	*/
	
	function incoming($msg, $trans){
		if($msg->method == 'REGISTER'){
			if($trans->state == SIP::TRYING){
				$trans->nowait();
				return true;
			}
			
			if($trans->state == SIP::AUTHING || $trans->state == SIP::COMPLETING){
				if(!$msg->auth){
					Logger::debug("recv duplicated REGISTER without auth info");
					$trans->nowait();
					return true;
				}
				
				$in_auth = SIP::decode_www_auth($msg->auth);
				$my_auth = SIP::www_auth($this->username, $this->password, $this->uri, 'REGISTER', $this->auth);
				if($in_auth['response'] !== $my_auth['response']){
					Logger::debug("auth failed");
					$trans->nowait();
					return true;
				}
				if($trans->state == SIP::COMPLETING){
					Logger::debug("recv duplicated REGISTER");
					$trans->nowait();
					return true;
				}
				
				if($this->is_state(SIP::COMPLETED)){
					Logger::debug("REGISTRAR " . $msg->from->address() . " renewed");
				}else{
					#Logger::debug("REGISTRAR " . $msg->from->address() . " registered");
					$this->local->set_tag(SIP::new_tag());
					$this->complete();
				}
				
				// 清除全部事务
				$this->transactions = array();
				
				$this->transactions[] = $trans;
				$trans->completing(); // 等待客户端可能的重传

				if($msg->expires <= 0){
					Logger::debug("client logout");
					$this->expires = 0;
				}else{
					$new = $this->new_request();
					$new->keepalive();
					$new->wait($this->expires + 5);
				}
				
				return true;
			}
		}
	}
	
	function outgoing($trans){
		if($trans->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->code = 100;
			$msg->cseq_method = 'REGISTER';
			return $msg;
		}else if($trans->state == SIP::AUTHING){
			$msg = new SipMessage();
			$msg->code = 401;
			$msg->cseq_method = 'REGISTER';

			if(!$this->auth['nonce']){
				$this->auth['nonce'] = SIP::long_token();
			}

			$str = SIP::encode_www_auth($this->auth);
			$msg->add_header('WWW-Authenticate', $str);
			return $msg;
		}else if($trans->state == SIP::COMPLETING){
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->cseq_method = 'REGISTER';
			$msg->expires = $this->expires;
			return $msg;
		}else if($trans->state == SIP::KEEPALIVE){
			Logger::debug("session expires, terminate");
			$this->terminate();
		}
	}
}
