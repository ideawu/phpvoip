<?php
/*
SipChannel 将向上级(对局) Server 注册自己。 Channel 是 NAT 网关。
*/
class SipChannel extends SipModule
{
	private $sess;
	private $username;
	private $password;
	private $domain;
	private $remote_ip;
	private $remote_port;
	private $local_ip;
	private $local_port;
	private $contact;
	
	function __construct($user, $password, $remote_ip, $remote_port){
		$ps = explode('@', $user);
		if(count($ps) == 2){
			$username = $ps[0];
			$domain = $ps[1];
		}else{
			$username = $user;
			$domain = $remote_ip;
		}

		$this->username = $username;
		$this->password = $password;
		$this->domain = $domain;
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
		$this->local_ip = $local_ip;
		$this->local_port = $local_port;
		$this->contact = new SipContact($this->username, "{$this->local_ip}:{$this->local_port}");

		$sess = new SipRegisterSession($this->username, $this->password, $this->remote_ip, $this->remote_port, $this->domain);
		$sess->local_ip = $local_ip;
		$sess->local_port = $local_port;
		$sess->contact = clone $this->contact;
		$this->add_session($sess);
		
		$this->sess = $sess;
		$this->sess->set_callback(array($this, 'sess_callback'));
	}
	
	function sess_callback($sess){
		if($sess->is_state(SIP::COMPLETED)){
			Logger::debug("channel connected, " . $sess->contact->encode());
			$this->contact = clone $sess->contact;
		}
	}

	function callin($msg){
		$sess = $this->sess;
		if(!$sess->is_state(SIP::COMPLETED)){
			return null;
		}
		
		if($msg->src_ip !== $sess->remote_ip || $msg->src_port !== $sess->remote_port){
			return null;
		}
		// TODO: 验证 uri, contact ...
		if($msg->to->username !== $sess->username){
			Logger::debug("to.username:{$msg->to->username} != username:{$sess->username}");
			return null;
		}

		$call = new SipCalleeSession($msg);
		$call->local_ip = $this->local_ip;
		$call->local_port = $this->local_port;
		$call->remote_ip = $this->remote_ip;
		$call->remote_port = $this->remote_port;
		$call->uri = $msg->uri;
		$call->local = clone $msg->to; // TODO: 可能需要重新生成 local
		$call->remote = clone $msg->from;
		$call->contact = clone $msg->contact;
		return $call;
	}

	function callout($msg){
		$sess = $this->sess;
		if(!$sess->is_state(SIP::COMPLETED)){
			return null;
		}

		if($msg->to->username === $sess->username){
			Logger::error("SipChannel is not UAC, drop msg with to=self");
			return null;
		}

		// TODO: 验证 uri, contact ...
		if($msg->from->username == $sess->username){
			$call = new SipCallerSession();
			$call->local_ip = $this->local_ip;
			$call->local_port = $this->local_port;
			$call->remote_ip = $this->remote_ip;
			$call->remote_port = $this->remote_port;
			$call->uri = $msg->uri;
			$call->local = clone $msg->from; // TODO: 可能需要重新生成 local
			$call->remote = clone $msg->to;
			$call->contact = clone $this->contact; // 如果要保留原呼叫人，则设为 $msg->contact
			return $call;
		}
		
		return null;
	}
}
