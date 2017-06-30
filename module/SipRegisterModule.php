<?php
class SipRegisterModule extends SipModule
{
	function register($username, $password, $remote_ip, $remote_port){
		$local_ip = $this->engine->local_ip;
		$local_port = $this->engine->local_port;
		if($local_ip === '0.0.0.0'){
			$local_ip = SIP::guess_local_ip($remote_ip);
		}
		$sess = new SipRegisterSession($username, $password, $remote_ip, $remote_port);
		$sess->local_ip = $local_ip;
		$sess->local_port = $local_port;
		$this->add_session($sess);
	}
	
	function callin($msg){
		foreach($this->sessions as $sess){
			if($msg->src_ip !== $sess->remote_ip || $msg->src_port !== $sess->remote_port){
				continue;
			}
			$ps1 = explode('@', $msg->to);
			$ps2 = explode('@', $sess->local);
			// 只验证 username
			if($ps1[0] !== $ps2[0]){
				Logger::debug("{$msg->to} {$sess->local}");
				continue;
			}
			// TODO: 验证 uri

			$call = new SipCalleeSession($msg);
			$call->local_ip = $sess->local_ip;
			$call->local_port = $sess->local_port;
			$call->remote_ip = $sess->remote_ip;
			$call->remote_port = $sess->remote_port;
			return $call;
		}
	}
	
	function callout($msg){
		return null;
		// foreach($this->sessions as $sess){
		// 	if($msg->to !== $sess->remote){
		// 		continue;
		// 	}
		// 	// TODO: 验证 uri
		//
		// 	$call = new SipCallerSession();
		// 	$call->local_ip = $sess->local_ip;
		// 	$call->local_port = $sess->local_port;
		// 	$call->remote_ip = $sess->remote_ip;
		// 	$call->remote_port = $sess->remote_port;
		// 	$call->uri = $msg->uri;
		// 	$call->local = $msg->from;
		// 	$call->remote = $msg->to;
		// 	$call->contact = $msg->contact;
		// 	return $call;
		// }
	}
	
	function complete_session($sess){
		parent::complete_session($sess);
		
		// TESTING:
		if($sess->role !== SIP::REGISTER){
			return;
		}
		if(1){
			$remote_ip = '127.0.0.1';
			$remote_port = 5060;
			$local_ip = $this->engine->local_ip;
			$local_port = $this->engine->local_port;
			if($local_ip === '0.0.0.0'){
				$local_ip = SIP::guess_local_ip($remote_ip);
			}

			$caller = new SipCallerSession();
			$caller->local_ip = $local_ip;
			$caller->local_port = $local_port;
			$caller->remote_ip = $remote_ip;
			$caller->remote_port = $remote_port;
			$caller->uri = "sip:1001@{$local_ip}";
			$caller->local = "<sip:2005@{$local_ip}>";
			$caller->remote = "<{$caller->uri}>";
			$caller->contact = $caller->local;

			$this->add_session($caller);
		}
	}
}
