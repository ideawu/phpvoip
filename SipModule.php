<?php
abstract class SipModule
{
	// 指向引擎
	public $engine;
	public $domain;
	
	// session 存储在 sessions 列表中。
	protected $sessions = array();
	
	/*
	呼入处理：针对此条 INVITE 消息，如果是由此模块发出的，或者由此模块管理的 UA 发出的，
	则创建一个 callee 返回。
	*/
	abstract function callin($msg);
	
	/*
	外呼处理：针对此条 INVITE 消息，如果是由此模块接收的，或者由此模块管理的 UA 接收的，
	则创建一个 caller 返回。
	*/
	abstract function callout($msg);
	
	function init(){
	}
	
	/*
	找出 session：
		call_id, from username, from tag, to username
	找出 trans：
		 
	1. 交互中的请求: to tag(若trans设置), branch, seq
	2. 交互中的响应: to tag(若trans设置), branch, seq
	3. 新请求:
		ACK: to tag(若sess设置), new branch, seq
		REQ: to tag(若sess设置), new branch, seq + 1
	*/
	function incoming($msg){
		foreach($this->sessions as $sess){
			if($sess->match_sess($msg)){
				$ret = $sess->proc_incoming($msg);
				// if($sess->is_state(SIP::CLOSED)){
				// 	$this->del_session($sess);
				// }
				if(!$ret){
					Logger::debug("no matching transaction, send 481");
					throw new Exception("Call/Transaction Does Not Exist", 481);
				}else{
					return true;
				}
			}
		}
		return false;
	}
	
	/*
	该方法定期（很小间隔时间）被系统调用。更新 dialog 中 session 的定时器，
	如果定时器有触发，则调用 session 的 outgoing() 方法获取要发送的消息。
	*/
	function outgoing($time, $timespan){
		$ret = array();
		foreach($this->sessions as $sess){
			$s1 = $sess->is_state(SIP::COMPLETED);
			$msgs = $sess->proc_outgoing($time, $timespan);
			$s2 = $sess->is_state(SIP::COMPLETED);
			if(!$s1 && $s2){
				$this->complete_session($sess);
			}
			if($sess->is_state(SIP::CLOSED)){
				$this->del_session($sess);
			}
			$ret = array_merge($ret, $msgs);
		}
		return $ret;
	}
	
	function add_session($sess){
		#Logger::debug("NEW " . $sess->brief());
		$sess->module = $this;
		$this->sessions[$sess->id] = $sess;
	}

	function del_session($sess){
		#Logger::debug("DEL " . $sess->brief());
		unset($this->sessions[$sess->id]);
	}
	
	function complete_session($sess){
		#Logger::debug("COMPLETE " . $sess->brief());
	}
}
