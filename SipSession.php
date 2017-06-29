<?php
abstract class SipSession
{
	// 指向本 Session 所属的 module。
	public $module;
	
	public $role;
	public $state = 0;
	public $renew = false;
	public $timers;

	public $local_ip;
	public $local_port;
	public $remote_ip;
	public $remote_port;
	
	public $remote_allow = array();

	protected $expires = 60;
	protected static $reg_timers = array(0, 0.5, 1, 2, 4, 2);
	protected static $call_timers = array(0, 0.5, 1, 2, 4, 2);
	protected static $refresh_timers = array(3, 2, 1, 1);
	protected static $closing_timers = array(0, 5);
	protected static $now_timers = array(0, 0);
	
	public $call_id; // session id
	public $branch;  // transaction id
	public $cseq;    // command/transaction seq
	public $options_cseq; // 用于 OPTIONS
	public $info_cseq; // 用于 INFO
	
	public $uri;
	
	public $from;
	public $from_tag; // session id
	public $to;
	public $to_tag;   // session id
	
	protected $auth;
	
	function __construct(){
	}
	
	abstract function incoming($msg);
	abstract function outgoing();
	
	function role_name(){
		if($this->role == SIP::REGISTER){
			return 'REGISTER';
		}else if($this->role == SIP::REGISTRAR){
			return 'REGISTRAR';
		}else if($this->role == SIP::CALLER){
			return 'CALLER';
		}else if($this->role == SIP::CALLEE){
			return 'CALLEE';
		}
	}
	
	function complete(){
		Logger::debug($this->role_name() . " session {$this->call_id} established");
		$this->state = SIP::COMPLETED;
	}
	
	function refresh($after=null){
		$this->timers = self::$refresh_timers;
		if($after !== null){
			$this->timers[0] = $after;
		}
	}
	
	function terminate(){
		$this->state = SIP::CLOSED;
		$this->timers = array();
	}
	
	// 主动关闭
	function close(){
		$this->state = SIP::FIN_WAIT;
		$this->timers = self::$closing_timers;
	}
	
	// 被动关闭
	function onclose(){
		$this->state = SIP::CLOSE_WAIT;
		$this->timers = self::$closing_timers;
	}
}
