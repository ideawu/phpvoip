<?php
class RegistrarSession extends SipSession
{
	public $username;
	public $password;
	const MIN_EXPIRES = 30;
	const MAX_EXPIRES = 120; // 似乎某些 UAC 不支持少于60
	public $expires = self::MAX_EXPIRES;
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

		$this->trans->uri = $msg->uri;
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
		if($msg->method == 'REGISTER'){
			$trans->cseq = $msg->cseq;
			$trans->branch = $msg->branch;
			
			if($msg->expires > 0 && $msg->expires < self::MIN_EXPIRES){
				$trans->code = 423;
				$trans->nowait();
				$this->transactions = array($trans);
				return true;
			}
			if(!$msg->auth && $this->auth){
				$trans->code = 401;
				$trans->auth = $this->auth;
				$trans->nowait();
				$this->transactions = array($trans);
				return true;
			}
			// 账号密码验证
			$in_auth = $msg->auth;
			$my_auth = SIP::www_auth($this->username, $this->password, $msg->uri, 'REGISTER', $this->auth);
			if($in_auth['response'] !== $my_auth['response']){
				Logger::debug("auth failed");
				$trans->code = 401;
				$trans->nowait();
				$this->transactions = array($trans);
				return true;
			}

			$trans->auth = null;
			$trans->code = 200;

			if($this->is_state(SIP::CLOSING)){
				Logger::debug("recv REGISTER while closing");
				$trans->nowait();
				$this->transactions = array($trans);
				return true;
			}
			if($this->is_state(SIP::COMPLETED)){
				Logger::debug("recv REGISTER while completed");
				$trans->nowait();
				$this->transactions = array($trans);
				return true;
			}
			if($msg->expires <= 0){
				Logger::debug($this->remote->address() . " client logout");
				$this->close();
				$this->expires = 0;
				$trans->expires = 0;
				$trans->timers = array(0, 1);
				$this->transactions = array($trans);
				return true;
			}
			if($msg->expires >= self::MIN_EXPIRES && $msg->expires <= self::MAX_EXPIRES){
				$this->expires = $msg->expires;
			}

			$this->complete();
			$this->local->set_tag(SIP::new_tag());
			$trans->expires = $this->expires;
			$trans->timers = array(0, $this->expires);
			$this->transactions = array($trans);
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
