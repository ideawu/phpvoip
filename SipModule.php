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

		$trans = $this->find_trans($sess, $msg);
		if(!$trans){
			Logger::debug("drop invalid session msg");
			return true;
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
			$this->engine->recycle_session($sess);
		}
		return true;
	}
	
	private function find_trans($sess, $msg){
		$trans = $sess->trans;
		
		if($msg->cseq === $trans->cseq && $msg->branch === $trans->branch){
			// transaction msg
			if($trans->to->tag() && $msg->to->tag() !== $trans->to->tag()){
				Logger::debug("to.tag: " . $msg->to->tag() . " != to.tag: " . $trans->to->tag());
				return null;
			}
			return $trans;
		}
		#ACK: new branch, seq
		#REQ: new branch, seq + 1
		if($msg->is_request()){
			if($sess->local->tag() && $msg->to->tag() !== $sess->local->tag()){
				Logger::debug("to.tag: " . $msg->to->tag() . " != local.tag: " . $sess->local->tag());
				return null;
			}

			if($msg->method === 'ACK' && $msg->cseq === $trans->cseq){
				Logger::debug("recv ACK in new transaction with old cseq");
				$ret = $sess->on_new_request($msg);
				if(!$ret){
					Logger::debug("new request not acceptable, drop msg");
					return null;
				}
				return $trans;
			}else if(!$sess->remote_cseq || $sess->remote_cseq + 1 === $msg->cseq){
				Logger::debug("recv new request with new cseq");
				$ret = $sess->on_new_request($msg);
				if(!$ret){
					Logger::debug("new request not acceptable, drop msg");
					return null;
				}
				return $trans;
			}else{
				Logger::debug("drop msg");
			}
		}
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
				$this->engine->recycle_session($sess);
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
		// Logger::debug(json_encode($trans->timers));
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
		// TESTING:
		if($msg->method == 'BYE'){
			$msg->uri = "sip:{$msg->to->username}@{$msg->to->domain}:53919;ob";
			$msg->contact = '';
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
