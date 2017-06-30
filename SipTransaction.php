<?php
class SipTransaction
{
	public $branch;
	public $local_tag;
	public $remote_tag;
	public $cseq;
	
	public $state;
	public $timers;
	
	protected static $refresh_timers = array(5, 3, 1, 1);
	protected static $closing_timers = array(0, 5);
		
	function __construct(){
	}
	
	function refresh($after=null){
		$this->timers = self::$refresh_timers;
		if($after !== null){
			$this->timers[0] = $after;
		}
	}
	
	function wait($seconds){
		if($this->timers){
			$this->timers[0] += $seconds;
		}else{
			$this->timers = array($seconds, 0);
		}
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
