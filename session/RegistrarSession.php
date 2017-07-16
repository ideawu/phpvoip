<?php
class RegistrarSession extends SipSession
{
	public $username;
	public $password;
	const MIN_EXPIRES = 60; // 似乎某些 UAC 不支持少于60
	const MAX_EXPIRES = 120;
	public $expires = self::MIN_EXPIRES;
	private $auth;

	function __construct($msg){
		parent::__construct();
		$this->role = SIP::REGISTRAR;

		$this->call_id = $msg->call_id;
		$this->local = clone $msg->to;
		$this->remote = clone $msg->from;
		$this->contact = clone $this->remote;
		$this->remote_cseq = $msg->cseq;
		$this->remote_allow = $msg->allow;

		$this->trans->uri = clone $msg->uri;
		$this->trans->method = $msg->method;
		$this->trans->cseq = $msg->cseq;
		$this->trans->branch = $msg->branch;
	}
	
	function init(){
		$this->set_state(SIP::TRYING);
		$this->trans->code = 100;
		$this->trans->timers = array(0, 1, 2, 2);
	}
	
	function auth(){
		$this->set_state(SIP::AUTHING);
		$this->trans->code = 401;
		$this->trans->timers = array(0, 5);
		$this->auth = array(
			'scheme' => 'Digest',
			'realm' => 'phpvoip',
			'algorithm' => 'MD5',
			'nonce' => SIP::long_token(),
		);
	}
	
	function close(){
		$this->set_state(SIP::CLOSING);
	}
	
	function incoming($msg, $trans){
		// 新 cseq trans，需要将原来的 trans 替换掉。Registrar 同时只允许1个trans
		if($trans != $this->trans){
			$trans->timers = $this->trans->timers;
			$this->transactions = array($trans);
			$this->trans = $trans;
		}
		
		if($msg->method == 'REGISTER'){
			if($msg->expires > 0 && $msg->expires < self::MIN_EXPIRES){
				$trans->code = 423;
				$trans->nowait();
				return true;
			}
			if(!$msg->auth && $this->auth){
				$trans->code = 401;
				$trans->auth = $this->auth;
				$trans->nowait();
				return true;
			}
			// 账号密码验证
			$in_auth = $msg->auth;
			$my_auth = SIP::www_auth($this->username, $this->password, $msg->uri->encode(), 'REGISTER', $this->auth);
			if($in_auth['response'] !== $my_auth['response']){
				Logger::debug("auth failed");
				$trans->code = 401;
				$trans->nowait();
				return true;
			}
			if($msg->expires <= 0 || ($msg->expires >= self::MIN_EXPIRES && $msg->expires <= self::MAX_EXPIRES)){
				$this->expires = $msg->expires;
			}else{
				$this->expires = self::MIN_EXPIRES;
			}

			$trans->auth = null;
			$trans->code = 200;
			$trans->expires = $this->expires;

			if($this->is_state(SIP::CLOSING)){
				Logger::debug("recv REGISTER while closing");
				$trans->nowait();
				return true;
			}
			if($this->expires <= 0){
				$this->close();
				$trans->timers = array(0, 1);
				return true;
			}
			
			if($this->is_state(SIP::COMPLETED)){
				Logger::debug("recv REGISTER while completed");
			}else{
				$this->complete();
			}

			$trans->to_tag = SIP::new_tag();
			$trans->expires = $this->expires;
			$trans->timers = array(0, $this->expires);
			return true;
		}
	}
	
	function outgoing($trans){
		$msg = parent::outgoing($trans);
		if($msg && $msg->code === 423){
			$msg->add_header('Min-Expires', self::MIN_EXPIRES);
		}
		return $msg;
	}
}
