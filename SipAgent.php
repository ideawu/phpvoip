<?php
class SipAgent
{
	public $local_ip;
	public $local_port;
	public $proxy_ip;
	public $proxy_port;
	public $username;
	public $password;
	public $domain;
	
	public $uri;
	public $from;
	public $contact;
	
	private $sessions = array();
	
	function register($username, $password, $proxy_ip, $proxy_port){
	}
	
	function oncall($msg){
		$sess = SipSession::oncall($msg);
		$this->sessions[] = $sess;
		Logger::debug("NEW session, {$sess->call_id} {$sess->from_tag} {$sess->to_tag}");
	}
	
	function incoming($msg){
		
		if($msg->method == 'INVITE'){
			// WTF?
			if($this->uri === $msg->uri || strpos($msg->uri, "sip:{$this->username}@") === 0){
				$this->oncall($msg);
				return true;
			}else{
			}
		}
	}
	
}
