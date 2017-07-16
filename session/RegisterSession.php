<?php
class RegisterSession extends SipSession
{
	public $username;
	public $password;
	public $domain;
	const MIN_EXPIRES = 29;
	const MAX_EXPIRES = 120;
	private $expires = self::MIN_EXPIRES;
	private $auth;
	
	function __construct($username, $password, $remote_ip, $remote_port, $domain=null){
		parent::__construct();
		$this->role = SIP::REGISTER;

		$this->remote_ip = $remote_ip;
		$this->remote_port = $remote_port;
		$this->username = $username;
		$this->password = $password;
		$this->domain = $domain? $domain : $this->remote_ip;

		$this->call_id = SIP::new_call_id();
		$this->local = new SipContact($this->username, $this->domain);
		$this->local->set_tag(SIP::new_tag());
		$this->remote = new SipContact($this->username, $this->domain);
	}
	
	function init(){
		$this->set_state(SIP::TRYING);
		$this->contact = new SipContact($this->local->username, $this->local_ip . ':' . $this->local_port);
		$this->register();
	}
	
	function close(){
		$this->set_state(SIP::CLOSING);
		$this->expires = 0;
		$this->register();
	}
	
	private function register(){
		$this->remote->del_tag();
		$this->transactions = array();
		
		$uri = "sip:{$this->domain}";
		$new = $this->new_request('REGISTER', $uri);
		$new->expires = $this->expires;
		$new->timers = array(0, 1, 2, 2, 10);
		$this->trans = $new;
	}
	
	function incoming($msg, $trans){
		if($msg->code == 100){
			return true;
		}
		if($msg->code == 401){
			$this->register();

			if($trans->auth){ // 原 trans
				Logger::error("{$this->local->username} auth failed");
				$this->trans->wait(3);
			}
			if($msg->auth){
				$auth = $msg->auth;
				$this->trans->auth = SIP::www_auth($this->username, $this->password, $this->trans->uri, 'REGISTER', $auth);
			}
			return true;
		}
		if($msg->code == 423){
			// 423 Interval Too Brief
			$v = $msg->get_header('Min-Expires');
			if($v){
				$this->expires = max(self::MIN_EXPIRES, intval($v));
			}
			$this->register();
			return true;
		}
		if($msg->code == 200){
			if($this->expires <= 0){
				Logger::debug("Register closed.");
				$this->terminate();
				return true;
			}
			$expires = min(self::MAX_EXPIRES, $this->expires);
			$renew = intval($expires * 0.6);
			if($this->is_state(SIP::COMPLETED)){
				Logger::debug("REGISTER " .$this->local->address(). " renewed, expires: $expires, renew: $renew");
			}else{
				Logger::debug("REGISTER " .$this->local->address(). " registered, expires: $expires, renew: $renew");
				$this->complete();
			}
	
			$this->register();
			$this->local->set_tag(SIP::new_tag());
			$this->trans->wait($renew);
			return true;
		}
	}
}