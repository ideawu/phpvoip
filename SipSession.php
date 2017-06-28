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

	protected $expires = 60;
	protected static $reg_timers = array(0, 0.5, 1, 2, 4, 2);
	protected static $call_timers = array(0, 0.5, 1, 2, 4, 2);
	protected static $refresh_timers = array(10, 2);
	protected static $closing_timers = array(0, 5);
	protected static $now_timers = array(0, 0);
	
	public $call_id; // session id
	public $branch;  // transaction id
	public $cseq;    // command/transaction seq
	
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
		$this->state = SIP::COMPLETED;
	}
	
	function refresh_after($seconds=10){
		$this->timers = self::$reg_timers;
		$this->timers[0] = $seconds;
	}
	
	/*
	当某一个步骤超时时调用此方法，默认将关闭 Session。子类可以重写，
	更改状态并进行其它操作。
	*/
	function timeout(){
		$this->state = SIP::CLOSED;
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
