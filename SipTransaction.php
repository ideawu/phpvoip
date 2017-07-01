<?php
class SipTransaction
{
	public $branch;
	public $local_tag;
	public $remote_tag;
	public $cseq;
	
	public $state;
	public $timers;

	protected static $register_timers = array(0, 0.5, 1, 2, 3, 2);
	
	protected static $calling_timers = array(0, 0.5, 0.5, 2, 3, 3, 3, 3);
	protected static $trying_timers = array(0, 1, 1, 1);
	protected static $ring_timers = array(0, 3, 3, 3, 3, 3);
	protected static $completing_timers = array(0, 5);
	
	protected static $keepalive_timers = array(5, 3, 1, 1);
	protected static $closing_timers = array(0, 1, 2, 2, 0);
	protected static $onclose_timers = array(0, 5);
		
	function __construct(){
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
