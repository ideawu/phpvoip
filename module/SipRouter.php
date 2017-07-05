<?php
/*
如果做地址转换，则类似 NAT。如果不做地址转换，则是纯路由器。
*/
class SipRouter extends SipModule
{
	// 记录 session 间的关系。mappings?
	protected $dialogs = array();
	
	// 针对 INVITE 消息，如果做地址转换，则返回地址转换后的 msg。
	function rewrite($msg){
		return;
		// TESTING: 收到 *->2005 时，转换成 2005->1001
		$f2 = '2005';
		$t2 = '1001';
		if($msg->to->username === $f2){
			$f1 = $msg->from->username;
			$t1 = $msg->to->username;
			Logger::debug("rewrite {$f1}->{$t1} => {$f2}->{$t2}");
			
			$msg = new SipMessage();
			$msg->method = 'INVITE';
			$msg->uri = "sip:{$t2}@{$local_ip}";
			$msg->from = new SipContact($f2, $this->domain);
			$msg->from->set_tag(SIP::new_tag());
			$msg->to = new SipContact($t2, $this->domain);
			$msg->call_id = SIP::new_call_id();
			$msg->cseq = SIP::new_cseq();
			$msg->contact = new SipContact($f2, $this->domain);
			$msg->branch = SIP::new_branch();
			return $msg;
		}
		
		// TESTING: 收到 *->221 时，转换成 221->231
		$f2 = '221';
		$t2 = '231';
		if($msg->to->username === $f2){
			$f1 = $msg->from->username;
			$t1 = $msg->to->username;
			Logger::debug("rewrite {$f1}->{$t1} => {$f2}->{$t2}");
			
			$msg = new SipMessage();
			$msg->method = 'INVITE';
			$msg->uri = "sip:{$t2}@{$local_ip}";
			$msg->from = new SipContact($f2, $this->domain);
			$msg->from->set_tag(SIP::new_tag());
			$msg->to = new SipContact($t2, $this->domain);
			$msg->call_id = SIP::new_call_id();
			$msg->cseq = SIP::new_cseq();
			$msg->contact = new SipContact($f2, $this->domain);
			$msg->branch = SIP::new_branch();
			return $msg;
		}
	}
	
	function callin($msg){
		return null;
	}
	
	function callout($msg){
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
		foreach($this->dialogs as $index=>$dia){
			if(!$dia->sessions){
				Logger::debug("delete dialog");
				unset($this->dialogs[$index]);
				break;
			}
		}
	}
	
	function complete_session($sess){
		parent::complete_session($sess);
	}
}
