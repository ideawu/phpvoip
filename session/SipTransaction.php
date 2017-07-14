<?php
class SipTransaction
{
	public $uri;
	public $method;
	public $code;
	public $cseq;
	public $branch;
	
	public $to_tag;
	// public $via;
	public $auth;
	public $content = '';
	public $content_type = null;
	public $expires = null;
	
	public $timers = array();

	function nowait(){
		if($this->timers && $this->timers[0] <= 0.01){
			return;
		}else{
			array_unshift($this->timers, 0);
		}
	}
	
	function wait($seconds){
		if($this->timers && $this->timers[0] <= 0.01){
			$this->timers[0] += $seconds;
		}else{
			array_unshift($this->timers, $seconds);
		}
	}
}
