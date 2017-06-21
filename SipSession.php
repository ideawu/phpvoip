<?php
class SipSession
{
	public $role;
	public $state = 0;
	public $timers;
	
	public $username;
	public $password;

	private static $reg_timers = array(0, 0.5, 1, 2, 2);
	private static $call_timers = array(0, 0.5, 1, 2, 2);
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
		$this->call_id = self::$call_id_prefix . SIP::token();
		$this->from_tag = self::$tag_prefix . SIP::token();
		$this->branch = self::$branch_prefix . SIP::token();
		$this->cseq = mt_rand(1, 10000);
	}
	
	static function register(){
		$sess = new SipSession();
		$sess->role = SIP::REGISTER;
		$sess->state = SIP::REGISTERING;
		$sess->timers = self::$reg_timers;
		return $sess;
	}
	
	// 返回要发送的消息
	function to_send(){
		$msg = null;
		if($this->role == SIP::REGISTER){
			$this->branch = self::$branch_prefix . SIP::token();
			if($this->state == SIP::REGISTERING || $this->state == SIP::AUTHING){
				$msg = new SipMessage();
				$msg->method = 'REGISTER';
				$msg->uri = $this->uri;
				
				$msg->from = $this->from;
				$msg->from_tag = $this->from_tag;
				$msg->to = $this->from;
				$msg->call_id = $this->call_id;
				$msg->branch = $this->branch;
				$msg->cseq = $this->cseq;
				if($this->state == SIP::AUTHING){
					$msg->headers[] = array('Authorization', $this->auth);
				}
			}else if($this->state == SIP::ESTABLISHED){
				Logger::debug("refresh registration");
				$this->state = SIP::REGISTERING;
				$this->cseq ++;
			}
		}else if($this->role == SIP::REGISTRAR){
			Logger::debug("send OK for REGISTER");
		}
		return $msg;
	}
	
	function on_recv($msg){
		if($this->role == SIP::REGISTER){
			if($this->state == SIP::REGISTERING || $this->state == SIP::AUTHING){
				if($msg->is_response()){
					if($msg->code == 200){
						Logger::debug("registered ");
						$this->to_tag = $msg->to_tag;
						$this->state = SIP::ESTABLISHED;

						// registration refresh
						$expires = $msg->expires - 5;
						$expires = min(60, max(5, $expires));
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
						
						foreach($msg->headers as $ps){
							if($ps[0] == 'WWW-Authenticate'){
								$this->auth = $this->www_auth($ps[1]);
								break;
							}
						}
						return;
					}
				}
			}else if($this->state == SIP::ESTABLISHED){
				Logger::debug("recv, exit.");
				die();
			}
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
