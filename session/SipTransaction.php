<?php
class SipTransaction
{
	public $branch;
	public $local;
	public $remote;
	public $cseq;
	public $method;
	public $code;
	
	public $state;
	public $timers = array();

	protected static $register_timers = array(0, 0.5, 1, 2, 3, 2);
	
	protected static $calling_timers = array(0, 0.5, 0.5, 2, 3, 3, 3, 3);
	protected static $trying_timers = array(0, 1, 1, 1);
	protected static $ringing_timers = array(0, 3, 3, 3, 3, 3);
	protected static $accept_timers = array(0, 1, 1, 1); // 重复发送OK直到收到ACK
	protected static $completing_timers = array(0, 5);
	
	protected static $keepalive_timers = array(10, 3, 1, 1);
	protected static $closing_timers = array(0, 0.5, 0.5, 1, 0);
	protected static $onclose_timers = array(0, 1);
		
	function __construct(){
	}
	
	function nowait(){
		if($this->timers && $this->timers <= 0.002){
			return;
		}else{
			array_unshift($this->timers, 0);
		}
	}
	
	function wait($seconds){
		if($this->timers){
			$this->timers[0] += $seconds;
		}else{
			$this->timers = array($seconds, 0);
		}
	}
	
	function register(){
		$this->state = SIP::TRYING;
		$this->timers = self::$register_timers;
		$this->remote->del_tag();;
	}
	
	function auth(){
		$this->state = SIP::AUTHING;
		$this->timers = array(0, 5);
		$this->local->del_tag();;
	}
	
	function calling(){
		$this->state = SIP::CALLING;
		$this->timers = self::$calling_timers;
	}
	
	function trying(){
		$this->state = SIP::TRYING;
		$this->timers = self::$trying_timers;
	}
	
	function ringing(){
		$this->state = SIP::RINGING;
		$this->timers = self::$ringing_timers;
	}
	
	function accept(){
   		// 等待 ACK
		$this->state = SIP::COMPLETING;
		$this->timers = self::$accept_timers;
	}
	
	function completing(){
		$this->state = SIP::COMPLETING;
		$this->timers = self::$completing_timers;
	}

	function keepalive(){
		$this->state = SIP::KEEPALIVE;
		$this->timers = self::$keepalive_timers;
	}
	
	// 主动关闭
	function close(){
		$this->state = SIP::FIN_WAIT;
		$this->timers = self::$closing_timers;
	}
	
	// 被动关闭
	function onclose(){
		$this->state = SIP::CLOSE_WAIT;
		$this->timers = self::$onclose_timers;
	}
}
