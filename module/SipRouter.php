<?php
class SipRouter extends SipModule
{
	// 记录 session 间的关系。
	protected $dialogs = array();
	
	function callin($msg){
		return null;
	}
	
	function callout($msg){
		// TODO: 在此进行路由转换
		
		// TESTING:
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

			return $caller;
		}
		
		return null;
	}
	
	function add_route($callee, $caller){
		$dia = new SipDialog($callee, $caller);
		$this->dialogs[] = $dia;
		$this->add_session($callee);
		$this->add_session($caller);
	}
	
	function add_session($sess){
		parent::add_session($sess);
	}
	
	function del_session($sess){
		parent::del_session($sess);
	}
	
	function complete_session($sess){
		parent::complete_session($sess);
	}
}
