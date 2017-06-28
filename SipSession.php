<?php
abstract class SipSession
{
	public $role;
	public $state = 0;
	public $timers;

	public $local_ip;
	public $local_port;
	public $remote_ip;
	public $remote_port;

	protected $expires = 59;
	protected static $reg_timers = array(0, 0.5, 1, 2, 4, 2);
	protected static $call_timers = array(0, 0.5, 1, 2, 4, 2);
	protected static $refresh_timers = array(10, 2);
	protected static $closing_timers = array(0, 5);
	
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
	
	function close(){
		$this->state = SIP::CLOSING;
		$this->timers = self::$closing_timers;
	}
}
