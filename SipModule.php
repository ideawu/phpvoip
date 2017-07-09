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
	
	function incoming($msg){
		$sess = $this->find_session($msg);
		if(!$sess){
			return false;
		}

		if(!$sess->remote_allow){
			$str = $msg->get_header('Allow');
			if($str){
				$sess->remote_allow = preg_split('/[, ]+/', trim($str));
			}
		}

		$trans = $sess->trans;
		/*
		消息只能是下列情形之一：
		1. 新请求: seq+1, call_id, from tag, to tag
		2. 交互中的请求: seq, call_id, from tag, to tag(重传不验证), branch
		3. 响应: seq, call_id, from tag, to tag(本端设置之后), branch
		
		只有处理某些状态，才能允许某些新请求。
		*/
		if($msg->is_request()){
			if($msg->cseq <= 0 || $msg->cseq > $sess->remote_cseq + 1){
				Logger::debug("Invalid Cseq");
				throw new Exception("Invalid Cseq", 500);
			}else if($msg->cseq < $sess->remote_cseq){
				Logger::debug("drop msg with old cseq");
				return true;
			}else if(!$sess->remote_cseq || $msg->cseq == $sess->remote_cseq + 1){
				$ret = $sess->on_new_request($msg);
				if(!$ret){
					Logger::debug("new request not acceptable");
					return true;
				}
			}else{
				// request during a transaction 和下面的代码重复
				if($msg->cseq !== $trans->cseq){
					Logger::debug("cseq: {$msg->cseq} != cseq: {$trans->cseq}");
					return true;
				}
				if($msg->branch !== $trans->branch){
					Logger::debug("Invalid branch");
					throw new Exception("Invalid branch", 500);
				}
				if($trans->to->tag()){
					if($msg->to->tag() !== $trans->to->tag()){
						Logger::debug("to.tag: " . $msg->to->tag() . " != remote.tag: " . $trans->to->tag());
						return true;
					}
				}
			}
		}else{
			if($msg->cseq !== $trans->cseq){
				Logger::debug("cseq: {$msg->cseq} != cseq: {$trans->cseq}");
				return true;
			}
			if($msg->branch !== $trans->branch){
				Logger::debug("branch: {$msg->branch} != branch: {$trans->branch}");
				return true;
			}
			if($trans->to->tag()){
				if($msg->to->tag() !== $trans->to->tag()){
					Logger::debug("to.tag: " . $msg->to->tag() . " != remote.tag: " . $trans->to->tag());
					return true;
				}
			}
		}

		// Logger::debug($sess->role_name() . " process msg");
		$s1 = $sess->is_state(SIP::COMPLETED);
		$sess->incoming($msg);
		$s2 = $sess->is_state(SIP::COMPLETED);
		if(!$s1 && $s2){
			$this->complete_session($sess);
		}
		if($sess->is_state(SIP::CLOSED)){
			$this->del_session($sess);
		}
		return true;
	}

	// 返回处理该消息的 session，没有则返回 null。子类可以继承此方法，增加功能。
	private function find_session($msg){
		foreach($this->sessions as $sess){
			if($msg->src_ip !== $sess->remote_ip || $msg->src_port !== $sess->remote_port){
				continue;
			}
			if($msg->call_id !== $sess->call_id){
				continue;
			}
			
			if($msg->is_request()){
				if(!$msg->from->equals($sess->remote)){
					Logger::debug("from: " . $msg->from->encode() . " != remote: " . $sess->remote->encode());
					continue;
				}
				if($msg->to->username !== $sess->local->username){
					Logger::debug("to: " . $msg->to->encode() . " != remote: " . $sess->local->encode());
					continue;
				}
			}else{
				if(!$msg->from->equals($sess->local)){
					Logger::debug("from: " . $msg->from->encode() . " != local: " . $sess->local->encode());
					continue;
				}
				// 发起会话或注册时，request.to.tag 为空，需要设为 response.to.tag
				// 若此种情况，只验证 username，不验证 tag
				if($msg->to->username !== $sess->remote->username){
					Logger::debug("to: " . $msg->to->encode() . " != remote: " . $sess->remote->encode());
					continue;
				}
			}
			
			return $sess;
		}
		return null;
	}
	
	/*
	该方法定期（很小间隔时间）被系统调用。更新 dialog 中 session 的定时器，
	如果定时器有触发，则调用 session 的 outgoing() 方法获取要发送的消息。
	*/
	function outgoing($time, $timespan){
		$ret = array();
		foreach($this->sessions as $index=>$sess){
			$s1 = $sess->is_state(SIP::COMPLETED);
			$msg = $this->proc_trans($sess, $time, $timespan);
			$s2 = $sess->is_state(SIP::COMPLETED);
			if(!$s1 && $s2){
				$this->complete_session($sess);
			}
			if($sess->is_state(SIP::CLOSED)){
				$this->del_session($sess);
			}
			if($msg){
				$this->before_sess_send_msg($sess, $msg);
				$ret[] = $msg;
			}
		}
		return $ret;
	}
	
	private function proc_trans($sess, $time, $timespan){
		$trans = $sess->trans;
		
		if(!$trans->timers){
			if($trans->state == SIP::FIN_WAIT || $trans->state == SIP::CLOSE_WAIT){
				Logger::debug($sess->role_name() . ' ' . SIP::state_text($trans->state) . " close transaction gracefully");
			}else{
				Logger::debug($sess->role_name() . ' ' . SIP::state_text($trans->state) . " transaction timeout");
			}
			$sess->terminate();
			return;
		}
		
		$trans->timers[0] -= $timespan;
		if($trans->timers[0] <= 0){
			array_shift($trans->timers);
			if($trans->timers){
				$msg = $sess->outgoing();
				return $msg;
			}
		}
	}
	
	private function before_sess_send_msg($sess, $msg){
		$trans = $sess->trans;

		$msg->src_ip = $sess->local_ip;
		$msg->src_port = $sess->local_port;
		$msg->dst_ip = $sess->remote_ip;
		$msg->dst_port = $sess->remote_port;

		$msg->uri = $sess->uri;
		$msg->call_id = $sess->call_id;
		$msg->contact = $sess->contact;
		
		if($msg->is_request()){
			$msg->from = $sess->local;
			$msg->to = $sess->remote;
		}else{
			$msg->from = $sess->remote;
			$msg->to = $sess->local;
		}
		
		$msg->branch = $trans->branch;
		$msg->cseq = $trans->cseq;
	}
	
	function add_session($sess){
		#Logger::debug("NEW " . $sess->brief());
		$sess->module = $this;
		$this->sessions[] = $sess;
	}

	function del_session($sess){
		#Logger::debug("DEL " . $sess->brief());
		foreach($this->sessions as $index=>$tmp){
			if($tmp !== $sess){
				continue;
			}
			$sess->module = null;
			unset($this->sessions[$index]);
			break;
		}
	}
	
	function complete_session($sess){
		#Logger::debug("COMPLETE " . $sess->brief());
	}
}
