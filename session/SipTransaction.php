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
	public $expires = null;
	
	public $timers = array();

	function nowait(){
		if($this->timers && $this->timers[0] <= 0.002){
			return;
		}else{
			array_unshift($this->timers, 0);
		}
	}
	
	function wait($seconds){
		array_unshift($this->timers, $seconds);
	}
}
