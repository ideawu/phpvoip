<?php
/*
SipChannel 将向上级(对局) Server 注册自己。 Channel 是 NAT 网关。
*/
class SipChannel extends SipModule
{
	private $sess;
	private $username;
	private $password;
	private $remote_ip;
	private $remote_port;
	private $local_ip;
	private $local_port;
	
	function __construct($username, $password, $remote_ip, $remote_port){
		$this->username = $username;
		$this->password = $password;
		$this->remote_ip = $remote_ip;
		$this->remote_port = $remote_port;
	}
	
	function init(){
		parent::init();
		
		$local_ip = $this->engine->local_ip;
		$local_port = $this->engine->local_port;
		if($local_ip === '0.0.0.0'){
			$local_ip = SIP::guess_local_ip($this->remote_ip);
		}
		$sess = new SipRegisterSession($this->username, $this->password, $this->remote_ip, $this->remote_port);
		$sess->local_ip = $local_ip;
		$sess->local_port = $local_port;
		$this->add_session($sess);
		
		$this->sess = $sess;
		$this->local_ip = $local_ip;
		$this->local_port = $local_port;
		// set_callback
	}

	function callin($msg){
		$sess = $this->sess;
		// if not registered return null;
		
		if($msg->src_ip !== $sess->remote_ip || $msg->src_port !== $sess->remote_port){
			return;
		}

		$call = new SipCalleeSession($msg);
		$call->local_ip = $sess->local_ip;
		$call->local_port = $sess->local_port;
		$call->remote_ip = $sess->remote_ip;
		$call->remote_port = $sess->remote_port;
		return $call;
	}

	function callout($msg){
		$sess = $this->sess;
		// if not registered return null;

		$ps1 = explode('@', $msg->to);
		$ps2 = explode('@', $sess->local);
		// 只验证 username
		if($ps1[0] === $ps2[0]){
			Logger::debug("SipChannel is not UAC, drop msg with to=self");
			return null;
		}

		$ps1 = explode('@', $msg->from);
		$ps2 = explode('@', $sess->local);
		// 只验证 username
		if($ps1[0] === $ps2[0]){
			// TODO: 验证 uri, contact ...
			$caller = new SipCallerSession();
			$caller->local_ip = $this->local_ip;
			$caller->local_port = $this->local_port;
			$caller->remote_ip = $this->remote_ip;
			$caller->remote_port = $this->remote_port;
			$caller->uri = $msg->uri;
			$caller->local = $msg->from;
			$caller->remote = $msg->to;
			// 如果要保留原呼叫人，则设为 $msg->contact
			$caller->contact = $sess->contact;
			return $caller;
		}
		
		return null;
	}
}
