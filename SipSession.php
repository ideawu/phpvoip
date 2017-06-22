<?php
class SipSession
{
	public $role;
	public $state = 0;
	public $timers;
	
	public $username;
	public $password;

	private $expires = 60;
	private static $reg_timers = array(0, 0.5, 1, 2, 2);
	private static $call_timers = array(0, 0.5, 1, 2, 2);
	private static $dialog_timers = array(10, 2);
	private static $call_id_prefix = 'call_';
	private static $tag_prefix = 'tag_';
	private static $branch_prefix = 'z9hG4bK_';
	
	public $call_id; // session id
	public $branch;  // transaction id
	public $cseq;    // command/transaction seq
	
	public $uri;
	public $from;
	public $from_tag; // session id
	public $to;
	public $to_tag;   // session id
	
	private $auth;
	
	function __construct(){
	}
	
	static function register($username, $password){
		$sess = new SipSession();
		$sess->username = $username;
		$sess->password = $password;
		
		$sess->call_id = self::$call_id_prefix . SIP::token();
		$sess->branch = self::$branch_prefix . SIP::token();
		$sess->cseq = mt_rand(1, 10000);
		$sess->from_tag = self::$tag_prefix . SIP::token();
		
		$sess->role = SIP::REGISTER;
		$sess->state = SIP::REGISTERING;
		$sess->timers = self::$reg_timers;
		
		return $sess;
	}
	
	static function oncall($msg){
		$sess = new SipSession();
		$sess->uri = $msg->uri;
		$sess->call_id = $msg->call_id;
		$sess->branch = $msg->branch;
		$sess->cseq = $msg->cseq;
		$sess->from = $msg->from;
		$sess->from_tag = $msg->from_tag;
		$sess->to = $msg->to;
		$sess->to_tag = self::$tag_prefix . SIP::token();
		
		$sess->role = SIP::CALLEE;
		$sess->state = SIP::ACCEPTING;
		$sess->timers = self::$call_timers;
		
		return $sess;
	}
	
	// 返回要发送的消息
	function to_send(){
		$msg = null;
		if($this->role == SIP::REGISTER){
			$msg = $this->role_register_send();
		}else if($this->role == SIP::REGISTRAR){
			$msg = $this->role_registrar_send();
		}else if($this->role == SIP::CALLER){
			$msg = $this->role_caller_send();
		}else if($this->role == SIP::CALLEE){
			$msg = $this->role_callee_send();
		}
		return $msg;
	}
	
	function on_recv($msg){
		if($msg->is_response()){
			if($msg->cseq != $this->cseq){
				Logger::debug("drop msg, msg.cseq: {$msg->cseq} != sess.cseq: {$this->cseq}");
				return;
			}
			if($msg->branch != $this->branch){
				Logger::debug("drop msg, msg.branch: {$msg->branch} != sess.branch: {$this->branch}");
				return;
			}
		}
		
		if($this->role == SIP::REGISTER){
			$this->role_register_recv($msg);
		}else if($this->role == SIP::REGISTRAR){
			$this->role_registrar_recv($msg);
		}else if($this->role == SIP::CALLER){
			$this->role_caller_recv($msg);
		}else if($this->role == SIP::CALLEE){
			$this->role_callee_recv($msg);
		}
	}
	
	private function role_register_send(){
		if($this->state == SIP::REGISTERING || $this->state == SIP::AUTHING || $this->state == SIP::REG_REFRESH){
			$this->branch = self::$branch_prefix . SIP::token();
			
			$msg = new SipMessage();
			$msg->method = 'REGISTER';
			$msg->uri = $this->uri;
			$msg->call_id = $this->call_id;
			$msg->branch = $this->branch;
			$msg->cseq = $this->cseq;
			
			$msg->from = $this->from;
			$msg->from_tag = $this->from_tag;
			$msg->to = $this->from;
			if($this->state == SIP::AUTHING){
				$msg->headers[] = array('Authorization', $this->auth);
			}
			return $msg;
		}else if($this->state == SIP::REGISTERED){
			Logger::debug("refresh registration");
			$this->state = SIP::REG_REFRESH;
			$this->to_tag = null;
			$this->cseq ++;
		}
	}
	
	private function role_register_recv($msg){
		if($this->state == SIP::REGISTERING || $this->state == SIP::AUTHING || $this->state == SIP::REG_REFRESH){
			if($msg->is_response()){
				if($msg->code == 200){
					Logger::debug("registered ");
					$this->to_tag = $msg->to_tag;
					$this->state = SIP::REGISTERED;

					// registration refresh
					$expires = $msg->expires - 5;
					$expires = min($this->expires, max(5, $expires));
					Logger::debug("expires: $expires");
					$this->timers = self::$reg_timers;
					$this->timers[0] = $expires;
				}else if($msg->code == 401){
					if($this->state == SIP::AUTHING){
						Logger::error("auth failed");
						$this->timers = self::$reg_timers;
						$this->timers[0] = 3; // wait before retry
					}else{
						Logger::debug("auth");
						$this->timers = self::$reg_timers;
					}
					$this->cseq ++;
					$this->state = SIP::AUTHING;
					$this->auth = $this->www_auth($msg->auth);
				}
			}
		}else if($this->state == SIP::REGISTERED){
			Logger::debug("recv, exit.");
			die();
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

	private function role_registrar_send(){
	}
	
	private function role_registrar_recv($msg){
	}

	private function role_caller_send(){
	}
	
	private function role_caller_recv($msg){
	}

	private function role_callee_send(){
		if($this->state == SIP::ACCEPTING){
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->method = 'INVITE';
			$msg->uri = $this->uri;
			$msg->call_id = $this->call_id;
			$msg->branch = $this->branch;
			$msg->cseq = $this->cseq;
			
			$msg->from = $this->from;
			$msg->from_tag = $this->from_tag;
			$msg->to = $this->from;
			$msg->to_tag = $this->to_tag;
			return $msg;
		}else if($this->state == SIP::ESTABLISHED){
			// TODO: refresh
			$this->timers = self::$dialog_timers;
			Logger::debug("refresh dialog");
		}else if($this->state == SIP::CLOSING){
			$this->state = SIP::CLOSED;
			
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->reason = 'OK';
			$msg->method = 'BYE';
			$msg->uri = $this->uri;
			$msg->call_id = $this->call_id;
			$msg->branch = $this->branch;
			$msg->cseq = $this->cseq;
			
			$msg->from = $this->from;
			$msg->from_tag = $this->from_tag;
			$msg->to = $this->from;
			$msg->to_tag = $this->to_tag;
			return $msg;
		}
	}
	
	private function role_callee_recv($msg){
		if($this->state == SIP::ACCEPTING){
			if($msg->method == 'ACK'){
				Logger::debug("call established");
				$this->state = SIP::ESTABLISHED;
				$this->timers = self::$dialog_timers;
			}
		}else if($this->state == SIP::ESTABLISHED){
			if($msg->method == 'BYE'){
				Logger::debug("call closed by BYE");
				$this->state = SIP::CLOSING;
				$this->cseq = $msg->cseq;
				$this->branch = $msg->branch;
				array_unshift($this->timers, 0);
			}
		}
	}
}
