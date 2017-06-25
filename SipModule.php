<?php
abstract class SipModule
{
	/*
	此模块管理的会话列表，如果是 Registrar，则是收到的注册的客户端列表；
	如果是 Register，则是向上级注册的通道列表；如果是终端应用模块，则是
	本模块收到的 INVITE 会话（也即 CalleeSession）列表.
	
	对于终端应用模块，它会在 callout() 返回一个 CallerSession，同时创建
	一个 CalleeSession 自己保留，就像无端的节点所要做的那样。
	*/
	protected $sessions = array();
	
	/*
	系统收到 INVITE 时调用，如果 INVITE 是由此模块负责的号码(from)发出的，
	则返回一个新创建的 CalleeSession，接收此号码(from)的 INVITE。
	*/
	function callin($msg){
	}
	
	/*
	系统收到 INVITE 时调用，如果 INVITE 是由此模块负责的号码(to)接收的，
	则返回一个新创建的 CallerSession，向号码(to)发起 INVITE。
	*/
	function callout($msg){
	}

	/*
	系统收到任意 msg 时调用，如果此消息被本模块处理，应返回 true。
	模块一般检测其管理的 Session 是否是此消息的处理者，如是则交给 Session 处理。
	
	注：收到 INVITE 创建 Session 的操作应由 callin/callout 方法处理，但应该
	处理重传的 INVITE。
	*/
	function incoming($msg){
		return false;
	}
	
	/*
	该方法定期（很小间隔时间）被系统调用，如果有需要发送出去的一个或者多个 msg，
	放在一个数组中返回。
	*/
	function outgoing($time, $timespan){
		$ret = array();
		foreach($this->sessions as $index=>$sess){
			$sess->timers[0] -= $timespan;
			if($sess->timers[0] <= 0){
				array_shift($sess->timers);
				if(count($sess->timers[0]) == 0){
					if($sess->state == SIP::CLOSING){
						Logger::debug("CLOSING " . $sess->role_name() . " session, call_id: {$sess->call_id}");
					}else{
						// transaction timeout
						Logger::debug("transaction timeout");
					}
					$sess->state = SIP::CLOSED;
				}else{
					// re/transmission timer trigger
					$msg = $sess->get_msg_to_send();
					if($msg){
						$ret[] = $msg;
					}
				}
			}

			if($sess->state == SIP::CLOSED){
				$this->del_session($sess);
			}
		}
		return $ret;
	}
	
	protected function add_session($sess){
		Logger::debug("NEW " . $sess->role_name() . " session, call_id: {$sess->call_id}");
		$this->sessions[] = $sess;
	}

	protected function del_session($sess){
		Logger::debug("DEL " . $sess->role_name() . " session, call_id: {$sess->call_id}");
		foreach($this->sessions as $index=>$tmp){
			if($tmp === $sess){
				unset($this->sessions[$index]);
			}
		}
	}
}
