<?php
/*
如果做地址转换，则类似 NAT。如果不做地址转换，则是纯路由器。
*/
class SipRouter extends SipModule
{
	// 记录 session 间的关系。mappings?
	protected $dialogs = array();
	
	// 针对 INVITE 消息，如果做地址转换，则直接修改 $msg 对象。
	function rewrite($msg){
		// TESTING: 收到 *->2005 时，转换成 2005->1001
		// TESTING: 收到 *->221 时，转换成 2005->1001
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
