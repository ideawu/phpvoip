<?php
class SipRegisterSession extends SipSession
{
	public $username;
	public $password;
	public $domain;

	const MIN_EXPIRES = 60;
	const MAX_EXPIRES = 120;
	private $auth;
	private $expires = self::MIN_EXPIRES;
	
	function __construct($username, $password, $remote_ip, $remote_port, $domain=null){
		parent::__construct();
		$this->role = SIP::REGISTER;
		$this->set_state(SIP::NONE);
		
		$this->remote_ip = $remote_ip;
		$this->remote_port = $remote_port;
		$this->username = $username;
		$this->password = $password;
		$this->domain = $domain? $domain : $this->remote_ip;

		$this->uri = "sip:{$this->domain}";
		$this->local = new SipContact($this->username, $this->domain);
		$this->remote = new SipContact($this->username, $this->domain);
		
		$this->call_id = SIP::new_call_id();
		$this->local->set_tag(SIP::new_tag());
	}
	
	function init(){
		$this->set_state(SIP::TRYING);
		$this->register();
	}
	
	function register(){
		$this->new_request();
		$this->trans->register();
	}
	
	function auth(){
		// 不改变 session 状态
		$this->register();
	}
	
	function incoming($msg){
		$trans = $this->trans;
		if($trans->state == SIP::TRYING){
			if($msg->code == 200){
				$expires = min(self::MAX_EXPIRES, $this->expires);
				if($this->is_completed()){
					Logger::debug("REGISTER " .$this->local->address(). " renewed, expires: $expires");
				}else{
					Logger::debug("REGISTER " .$this->local->address(). " registered, expires: $expires");
					$this->complete();
				}

				$this->auth = null;
				$this->remote->set_tag($msg->to->tag());
		
				$this->register();
				$this->trans->wait($expires/2);
				return true;
			}
			if($msg->code == 100){
				return true;
			}
			if($msg->code == 401){
				$this->auth();
				if($this->auth){
					Logger::error("{$this->local->username} auth failed");
					$trans->wait(3);
				}
				$this->auth = $this->www_auth($msg->auth);
				return true;
			}
			if($msg->code == 423){
				// 423 Interval Too Brief
				$v = $msg->get_header('Min-Expires');
				if($v){
					$this->expires = max(self::MIN_EXPIRES, intval($v));
					$this->register();
				}
			}
		}
	}

	function outgoing(){
		$trans = $this->trans;
		if($trans->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->method = 'REGISTER';
			$msg->expires = $this->expires;
			if($this->auth){
				$msg->add_header('Authorization', $this->auth);
			}
			$msg->username = $this->username;
			$msg->password = $this->password;
			return $msg;
		}
	}

	private function www_auth($str){
		$auth = SIP::decode_www_auth($str);
		$auth = SIP::www_auth($this->username, $this->password, $this->uri, 'REGISTER', $auth);
		return SIP::encode_www_auth($auth);
	}
}