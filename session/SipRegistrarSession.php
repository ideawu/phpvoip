<?php
class SipRegistrarSession extends SipSession
{
	public $username;
	public $password;
	private $expires = 180; // 似乎某个 UAC 不支持少于60
	
	private $auth = array(
		'scheme' => 'Digest',
		'realm' => 'phpvoip',
		'algorithm' => 'MD5',
	);

	function __construct($msg){
		parent::__construct();
		$this->role = SIP::REGISTRAR;

		$this->call_id = $msg->call_id;
		$this->local = clone $msg->to;
		$this->remote = clone $msg->from;
		$this->contact = clone $msg->contact;
		$this->remote_cseq = $msg->cseq;
		$this->remote_allow = $msg->allow;

		$this->trans->uri = $msg->uri;
		$this->trans->method = $msg->method;
		$this->trans->cseq = $msg->cseq;
		$this->trans->branch = $msg->branch;
	}
	
	function init(){
		$this->trying();
	}
	
	function trying(){
		$this->set_state(SIP::TRYING);
		$this->trans->code = 100;
		$this->trans->timers = array(0.5, 1, 2, 2);
	}
	
	function auth(){
		$this->set_state(SIP::AUTHING);
		$this->trans->code = 401;
		$this->trans->timers = array(0, 5);

		$this->auth['nonce'] = SIP::long_token();
		$this->trans->auth = $this->auth;
	}
	
	function close(){
		$this->set_state(SIP::CLOSING);
	}
	
	function incoming($msg, $trans){
		if($msg->method == 'REGISTER'){
			$trans->cseq = $msg->cseq;
			$trans->branch = $msg->branch;

			if(!$msg->auth){
				Logger::debug("recv duplicated REGISTER without auth info");
				$trans->code = 401;
				$trans->nowait();
				return true;
			}
			// 账号密码验证
			$in_auth = SIP::decode_www_auth($msg->auth);
			$my_auth = SIP::www_auth($this->username, $this->password, $msg->uri, 'REGISTER', $this->auth);
			if($in_auth['response'] !== $my_auth['response']){
				Logger::debug("auth failed");
				$trans->code = 401;
				$trans->nowait();
				return true;
			}

			$trans->auth = null;
			$trans->code = 200;

			if($this->is_state(SIP::CLOSING)){
				Logger::debug("recv REGISTER while closing");
				$trans->nowait();
				return true;
			}
			if($this->is_state(SIP::COMPLETED)){
				Logger::debug("recv REGISTER while completed");
				$trans->nowait();
				return true;
			}
			if($msg->expires <= 0){
				Logger::debug($this->remote->address() . " client logout");
				$this->close();
				$trans->expires = 0;
				$trans->timers = array(0, 0);
				return true;
			}

			$this->complete();
			$this->local->set_tag(SIP::new_tag());
			
			$trans->expires = $this->expires;
			$trans->timers = array(0, $this->expires);
			return true;
		}
	}
	
}
