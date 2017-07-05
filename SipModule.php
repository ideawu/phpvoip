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
		if(!$this->domain){
			$this->domain = $this->engine->local_ip;
		}
	}

	function incoming($msg){
		$sess = $this->find_session_for_msg($msg);
		if(!$sess){
			return false;
		}
		if($msg->is_request() && !$sess->remote_cseq){
			Logger::debug("init remote_cseq={$msg->cseq}");
			$sess->remote_cseq = $msg->cseq;
		}
		if($msg->is_request() && $msg->cseq == $sess->remote_cseq + 1){
			$sess->remote_cseq ++;
			Logger::debug("set remote_cseq {$msg->cseq}");
		}
		if($msg->code >= 180 && !$sess->remote->tag() && $msg->to->tag()){
			Logger::debug("set remote.tag=" . $msg->to->tag());
			$sess->remote->set_tag($msg->to->tag());
		}
		if(!$sess->remote_allow){
			$str = $msg->get_header('Allow');
			if($str){
				$sess->remote_allow = preg_split('/[, ]+/', trim($str));
			}
		}

		$trans = $this->find_transaction_for_msg($msg, $sess);
		if(!$trans){
			if($msg->is_request()){
				Logger::debug("create new response");
				$trans = $sess->new_response($msg->branch);
				$trans->trying();
			}else{
				return false;
			}
		}

		// Logger::debug($sess->role_name() . " process msg");
		$s1 = $sess->is_state(SIP::COMPLETED);
		$sess->incoming($msg, $trans);
		$s2 = $sess->is_state(SIP::COMPLETED);
		if(!$s1 && $s2){
			$this->complete_session($sess);
		}

		return true;
	}
	
	private function find_session_for_msg($msg){
		#echo "{$msg->call_id}\n";
		foreach($this->sessions as $sess){
			#echo "    {$sess->call_id}\n";
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
				if(!$msg->to->equals($sess->local)){
					Logger::debug("to: " . $msg->to->encode() . " != local: " . $sess->local->encode());
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
				if($sess->remote->tag()){
					if($msg->to->tag() !== $sess->remote->tag()){
						Logger::debug("to: " . $msg->to->encode() . " != remote: " . $sess->remote->encode());
						continue;
					}
				}
			}
			
			return $sess;
		}
		return false;
	}
	
	private function find_transaction_for_msg($msg, $sess){
		foreach($sess->transactions as $trans){
			if($msg->is_request()){
				// 观察到 Yate Client 会在连接成功之后，再发送新的 INVITE，
				// fromtag, totag(不为空), callid 相同，branch, cseq 不同。
				// uri 也不同。
				if($msg->cseq !== $trans->cseq){
					#Logger::debug("cseq: {$msg->cseq} != cseq: {$trans->cseq}");
					continue;
				}
				if($trans->local_tag){
					if($msg->to->tag() !== $trans->local_tag){
						Logger::debug("to.tag: ". $msg->to->tag() . " != local_tag: {$trans->local_tag}");
						continue;
					}
				}
			}else{
				if($msg->cseq !== $trans->cseq){
					#Logger::debug("cseq: {$msg->cseq} != cseq: {$trans->cseq}");
					continue;
				}
				if($trans->remote_tag){
					if($msg->to->tag() !== $trans->remote_tag){
						Logger::debug("to.tag: " . $msg->to->tag() . " != remote_tag: {$trans->remote_tag}");
						continue;
					}
				}
				if($msg->branch !== $trans->branch){
					Logger::debug("branch: {$msg->branch} != branch: {$trans->branch}");
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
			$s1 = $sess->is_state(SIP::COMPLETED);
			$msgs = $this->proc_trans($sess, $time, $timespan);
			$ret += $msgs;
			$s2 = $sess->is_state(SIP::COMPLETED);
			if(!$s1 && $s2){
				$this->complete_session($sess);
			}
			if($sess->is_state(SIP::CLOSED)){
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
						#Logger::debug($sess->role_name() . ' ' . SIP::state_text($trans->state) . " transaction timeout");
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
		if($msg->is_request()){
			$msg->from = $sess->local;
			$msg->to = $sess->remote;
		}else{
			$msg->from = $sess->remote;
			$msg->to = $sess->local;
		}
		$msg->contact = $sess->contact;
		$msg->branch = $trans->branch;
		$msg->cseq = $trans->cseq;
	}
	
	function add_session($sess){
		Logger::debug("NEW " . $sess->brief());
		$sess->module = $this;
		$this->sessions[] = $sess;
	}

	function del_session($sess){
		Logger::debug("DEL " . $sess->brief());
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
		Logger::debug("COMPLETE " . $sess->brief());
	}
}
