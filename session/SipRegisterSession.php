<?php
class SipRegisterSession extends SipSession
{
	public $username;
	public $password;
	public $domain;

	function __construct($username, $password, $remote_ip, $remote_port){
		parent::__construct();

		$ps = explode('@', $username);
		if(count($ps) > 1){
			$username = $ps[0];
			$domain = $ps[1];
		}else{
			$domain = $remote_ip;
		}
		
		$this->role = SIP::REGISTER;
		$this->state = SIP::TRYING;
		
		$this->remote_ip = $remote_ip;
		$this->remote_port = $remote_port;
		$this->username = $username;
		$this->password = $password;
		$this->domain = $domain;

		$this->uri = "sip:{$this->domain}";
		$this->local = "<sip:{$this->username}@{$this->domain}>";
		$this->remote = $this->local;
		$this->contact = $this->local;
		
		$this->call_id = SIP::new_call_id();
		$this->local_tag = SIP::new_tag();

		$this->new_transaction(SIP::TRYING, self::$reg_timers);
	}
	
	function incoming($msg, $trans){
		if($trans->state == SIP::TRYING || $trans->state == SIP::AUTHING){
			if($msg->code == 100){
				// 收到 100 更新重传定时器
				$trans->timers[0] += 0.5;
			}else if($msg->code == 200){
				$this->auth = null;
				if($this->state == SIP::COMPLETED){
					Logger::debug("REGISTER {$this->local} renewed");
				}else{
					Logger::debug("REGISTER {$this->local} registered");
					$this->remote_tag = $msg->to_tag;
					$this->complete();
				}
				
				$this->del_transaction($trans);
				$expires = min($this->expires, max($this->expires, $msg->expires)) - 5;
				// $expires = 5;
				Logger::debug("expires: $expires");
				$new = $this->new_transaction(SIP::TRYING);
				$new->wait($expires);
			}else if($msg->code == 401){
				$this->auth = $this->www_auth($msg->auth);
				
				$this->del_transaction($trans);
				$new = $this->new_transaction(SIP::AUTHING, self::$reg_timers);
				if($trans->state == SIP::AUTHING){
					Logger::error("{$this->local} auth failed");
					$new->wait(3);
				}else{
					Logger::debug("{$this->local} auth");
				}
			}else if($msg->code == 423){
				// 423 Interval Too Brief
				$v = $msg->get_header('Min-Expires');
				if($v){
					$this->expires = max($this->expires, intval($v));
				}
				$this->new_transaction(SIP::TRYING, self::$reg_timers);
			}
		}
	}
	
	// 返回要发送的消息
	function outgoing($trans){
		if($trans->state == SIP::TRYING || $trans->state == SIP::AUTHING){
			$msg = new SipMessage();
			$msg->method = 'REGISTER';
			$msg->expires = $this->expires;
			if($trans->state == SIP::AUTHING){
				$msg->add_header('Authorization', $this->auth);
			}
			$msg->username = $this->username;
			$msg->password = $this->password;
			return $msg;
		}
	}

	private function www_auth($str){
		$auth = SIP::parse_www_auth($str);
		$scheme = $auth['scheme'];
		$realm = $auth['realm'];
		$nonce = $auth['nonce'];
		if($scheme == 'Digest'){
			$ha1 = md5($this->username .':'. $realm .':'. $this->password);
		    $ha2 = md5('REGISTER' .':'. $this->uri);
			if(isset($auth['qpop']) && $auth['qpop'] == 'auth'){
				//MD5(HA1:nonce:nonceCount:cnonce:qop:HA2)
			}else{
				$res = md5($ha1 .':'. $nonce .':'. $ha2);
			}
		    $ret = $scheme . ' username="'.$this->username.'", realm="'.$realm.'", nonce="'.$nonce.'", uri="'.$this->uri.'", response="'.$res.'", algorithm=MD5';
			return $ret;
		}
	}
}