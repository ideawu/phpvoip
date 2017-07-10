<?php
/*
会话关闭后，放入回收站一段时间，防止不规范的 SIP 实现重用 Call-ID。
*/
class SipRecycle extends SipModule
{
	function callin($msg){
		return null;
	}
	function callout($msg){
		return null;
	}

	function recycle_session($sess){
		$new = new SipRecycleSession();
		$new->local_ip = $sess->local_ip;
		$new->local_port = $sess->local_port;
		$new->remote_ip = $sess->remote_ip;
		$new->remote_port = $sess->remote_port;

		$new->uri = $sess->uri;
		$new->call_id = $sess->call_id;
		$new->local = clone $sess->local;
		$new->remote = clone $sess->remote;
		
		$new->init();
		$this->add_session($new);
	}
	
	function incoming($msg){
		foreach($this->sessions as $sess){
			if($msg->src_ip !== $sess->remote_ip || $msg->src_port !== $sess->remote_port){
				continue;
			}
			if($msg->call_id !== $sess->call_id){
				continue;
			}
			Logger::debug("recycling session");
			$sess->incoming($msg);
			return true;
		}
	}
	
	function del_session($sess){
		parent::del_session($sess);
		Logger::debug("finally delete session");
	}
}
