<?php
abstract class SipModule
{
	// 指向引擎
	public $engine;
	
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

	function incoming($msg){
		$sess = $this->find_session_for_msg($msg);
		if(!$sess){
			return false;
		}
		
		$trans = $this->find_transaction_for_msg($msg, $sess);
		if(!$trans){
			return false;
		}
		
		// TODO:
		if(!$sess->remote_allow){
			$str = $msg->get_header('Allow');
			if($str){
				$sess->remote_allow = preg_split('/[, ]+/', trim($str));
			}
		}

		// Logger::debug($sess->role_name() . " process msg");
		$s1 = $sess->state == SIP::COMPLETED;
		$sess->incoming($msg, $trans);
		$s2 = $sess->state == SIP::COMPLETED;
		if(!$s1 && $s2){
			$this->complete_session($sess);
		}

		return true;
	}
	
	private function find_session_for_msg($msg){
		foreach($this->sessions as $sess){
			if($msg->src_ip !== $sess->remote_ip || $msg->src_port !== $sess->remote_port){
				continue;
			}
			if($msg->call_id !== $sess->call_id){
				continue;
			}
			
			if($msg->is_request()){
				if($msg->from_tag !== $sess->remote_tag){
					Logger::debug("from_tag: {$msg->from_tag} != remote_tag: {$sess->remote_tag}");
					continue;
				}
			
				// TODO: 验证 address 时，不能简单的文本比较，要解析之后比较
				if($msg->from !== $sess->remote){
					Logger::debug("from: {$msg->from} != remote: {$sess->remote}");
					continue;
				}
				if($msg->to !== $sess->local){
					Logger::debug("to: {$msg->to} != local: {$sess->local}");
					continue;
				}

			}else{
				if($msg->from_tag !== $sess->local_tag){
					Logger::debug("from_tag: {$msg->from_tag} != local_tag: {$sess->local_tag}");
					continue;
				}
	
				// TODO: 验证 address 时，不能简单的文本比较，要解析之后比较
				if($msg->from !== $sess->local){
					Logger::debug("from: {$msg->from} != local: {$sess->local}");
					continue;
				}
				if($msg->to !== $sess->remote){
					Logger::debug("to: {$msg->to} != remote: {$sess->remote}");
					continue;
				}
			}
			
			return $sess;
		}
		return false;
	}
	
	private function find_transaction_for_msg($msg, $sess){
		foreach($sess->transactions as $trans){
			if($msg->is_request()){
				if($trans->local_tag){
					if($msg->to_tag !== $trans->local_tag){
						Logger::debug("to_tag: {$msg->to_tag} != local_tag: {$trans->local_tag}");
						continue;
					}
				}
			}else{
				if($trans->remote_tag){
					if($msg->to_tag !== $trans->remote_tag){
						Logger::debug("to_tag: {$msg->to_tag} != remote_tag: {$trans->remote_tag}");
						continue;
					}
				}

				if($msg->branch !== $trans->branch){
					Logger::debug("branch: {$msg->branch} != branch: {$trans->branch}");
					continue;
				}
				if($msg->cseq !== $trans->cseq){
					Logger::debug("cseq: {$msg->cseq} != cseq: {$trans->cseq}");
					continue;
				}
			}
			return $trans;
		}
		return false;
	}
	
	/*
	该方法定期（很小间隔时间）被系统调用。更新 dialog 中 session 的定时器，
	如果定时器有触发，则调用 session 的 outgoing() 方法获取要发送的消息。
	*/
	function outgoing($time, $timespan){
		$ret = array();
		foreach($this->sessions as $index=>$sess){
			$s1 = $sess->state == SIP::COMPLETED;
			$msgs = $this->proc_trans($sess, $time, $timespan);
			$ret += $msgs;
			$s2 = $sess->state == SIP::COMPLETED;
			if(!$s1 && $s2){
				$this->complete_session($sess);
			}
			if($sess->state == SIP::CLOSED){
				$this->del_session($sess);
			}
		}
		return $ret;
	}
	
	private function proc_trans($sess, $time, $timespan){
		$ret = array();
		foreach($sess->transactions as $trans){
			$trans->timers[0] -= $timespan;
			if($trans->timers[0] <= 0){
				array_shift($trans->timers);
				if(count($trans->timers) == 0){
					if($trans->state == SIP::FIN_WAIT || $trans->state == SIP::CLOSE_WAIT){
						Logger::debug($sess->role_name() . ' ' . SIP::state_text($trans->state) . " close transaction gracefully");
					}else{
						// transaction timeout
						Logger::debug($sess->role_name() . ' ' . SIP::state_text($trans->state) . " transaction timeout");
					}
				}else{
					$msg = $sess->outgoing($trans);
					if($msg){
						$this->before_sess_send_msg($sess, $trans, $msg);
						$ret[] = $msg;
					}
				}
			}
			if(!$trans->timers){
				$sess->del_transaction($trans);
			}
		}
		if(!$sess->transactions){
			$sess->terminate();
		}
		return $ret;
	}
	
	private function before_sess_send_msg($sess, $trans, $msg){
		$msg->src_ip = $sess->local_ip;
		$msg->src_port = $sess->local_port;
		$msg->dst_ip = $sess->remote_ip;
		$msg->dst_port = $sess->remote_port;

		$msg->uri = $sess->uri;
		$msg->call_id = $sess->call_id;
		$msg->branch = $trans->branch;
		$msg->cseq = $trans->cseq;
		if($msg->is_request()){
			$msg->from = $sess->local;
			$msg->from_tag = $trans->local_tag;
			$msg->to = $sess->remote;
			$msg->to_tag = $trans->remote_tag;
		}else{
			$msg->from = $sess->remote;
			$msg->from_tag = $trans->remote_tag;
			$msg->to = $sess->local;
			$msg->to_tag = $trans->local_tag;
		}
		$msg->contact = $sess->contact;
	}
	
	function add_session($sess){
		Logger::debug("NEW session " . $sess->role_name() . ", {$sess->local} => {$sess->remote}");
		$sess->module = $this;
		$this->sessions[] = $sess;
	}

	function del_session($sess){
		Logger::debug("DEL session " . $sess->role_name() . ", {$sess->local} => {$sess->remote}");
		foreach($this->sessions as $index=>$tmp){
			if($tmp !== $sess){
				continue;
			}
			$sess->module = null;
			unset($this->sessions[$index]);
			// // 如果存在于 dialog 中，dialog 也要删除
			// foreach($this->dialogs as $dialog){
			// 	$dialog->del_session($sess);
			// }
			break;
		}
	}
	
	function complete_session($sess){
		Logger::debug("UP session " . $sess->role_name() . ", {$sess->local} => {$sess->remote}");
	}
}
