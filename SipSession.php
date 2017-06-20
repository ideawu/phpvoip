<?php
class SipSession
{
	public $role;
	public $state = 0;
	public $timers;

	private static $reg_timers = array(0, 0.5, 1, 2, 2);
	private static $call_timers = array(0, 0.5, 1, 2, 2);
	private static $call_id_prefix = 'call_';
	private static $tag_prefix = 'tag_';
	private static $branch_prefix = 'z9hG4bK_';
	
	public $call_id;
	public $branch;
	public $cseq;
	
	public $uri;
	public $from;
	public $from_tag;
	public $to;
	public $to_tag;
	
	function __construct(){
		$this->call_id = self::$call_id_prefix . SIP::token();
		$this->from_tag = self::$tag_prefix . SIP::token();
		$this->branch = self::$branch_prefix . SIP::token();
		$this->cseq = mt_rand(1, 100000);
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
			if($this->state = SIP::REGISTERING){
				Logger::debug("send REGISTER");
				$msg = new SipMessage();
				$msg->method = 'REGISTER';
				$msg->uri = $this->uri;
				
				$msg->from = $this->from;
				$msg->from_tag = $this->from_tag;
				$msg->to = $this->from;
				$msg->call_id = $this->call_id;
				$msg->branch = $this->branch;
				$msg->cseq = $this->cseq;
			}
		}else if($this->role == SIP::REGISTRAR){
			Logger::debug("send OK for REGISTER");
		}
		return $msg;
	}
	
	function on_recv($msg){
		
	}
}
