<?php
class SipRouter
{
	/*
	输入一条 INVITE 消息，根据路由表配置，然后返回新的 INVITE 消息。新的消息
	如果能直接送达，则直接返回。否则修改 uri, from, to，但保持 contact 不变。
	*/
	function route($msg){
		$msg = new SipMessage();
		$msg->method = 'INVITE';
		$msg->call_id = SIP::new_call_id();
		$msg->from->set_tag(SIP::new_tag());
		$msg->branch = SIP::new_branch();
		$msg->cseq = SIP::new_cseq();
		$msg->contact = clone $msg->contact;

		// TESTING: 收到 *->2005 时，转换成 2005->1001
		$f2 = '2005';
		$t2 = '1001';
		if($msg->to->username === $f2){
			$f1 = $msg->from->username;
			$t1 = $msg->to->username;
			Logger::debug("rewrite {$f1}->{$t1} => {$f2}->{$t2}");

			$msg->uri = "sip:{$t2}@{$this->domain}";
			$msg->from = new SipContact($f2, $this->domain);
			$msg->to = new SipContact($t2, $this->domain);
			return $msg;
		}
		
		// TESTING: 收到 *->221 时，转换成 221->231
		$f2 = '221';
		$t2 = '231';
		if($msg->to->username === $f2){
			$f1 = $msg->from->username;
			$t1 = $msg->to->username;
			Logger::debug("rewrite {$f1}->{$t1} => {$f2}->{$t2}");
			
			$msg->uri = "sip:{$t2}@{$this->domain}";
			$msg->from = new SipContact($f2, $this->domain);
			$msg->to = new SipContact($t2, $this->domain);
			return $msg;
		}
		
		$msg->uri = clone $msg->uri;
		$msg->from = clone $msg->from;
		$msg->to = clone $msg->to;
		return $msg;
	}
}
