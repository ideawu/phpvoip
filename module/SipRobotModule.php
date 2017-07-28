<?php
/*
这是一个终端应用的例子。终端应用和远程终端代理类似，在 callout() 中返回一个 Session。
常见的终端应用，如 IVR，录音。这些终端应用可作为模块，也可能被做成真正的远程终端。
*/
class SipRobotModule extends SipModule
{
	function callin($msg){
		return null;
	}
	
	function callout($msg){
		// $ret = new NoopCallerSession();
		// $ret->init();
		// return $ret;
		
		$caller = new LocalCaller();
		$callee = new LocalCallee();
		$caller->callee = $callee;
		$callee->caller = $caller;

		$caller->call_id = $msg->call_id;
		$caller->remote_sdp = $this->engine->exchange->sdp($msg->remote_ip);
		$caller->init();
		
		$caller->call_id = $msg->call_id;
		$callee->init();
		$callee->set_callback(array($this, 'sess_callback'));

		// callee 自己保留，将 caller 返回给引擎管理
		$this->add_session($callee);
		return $caller;
	}

	function sess_callback($sess){
		if($sess->is_state(SIP::CLOSED)){
			Logger::debug("session closed.");
		}
	}
}
