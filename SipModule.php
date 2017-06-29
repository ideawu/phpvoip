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
		foreach($this->sessions as $sess){
			if($sess->role == SIP::REGISTER){
				if($msg->src_ip !== $sess->remote_ip || $msg->src_port !== $sess->remote_port){
					continue;
				}
				if($msg->call_id !== $sess->call_id){
					continue;
				}
				if($msg->from_tag !== $sess->local_tag){
					continue;
				}
				// TODO: 需要区分收到的是请求还是响应，目前转不支持请求
				if($msg->from !== $sess->local || $msg->to !== $sess->remote){
					continue;
				}
			}else if($sess->role == SIP::REGISTRAR){
				continue;
			}else if($sess->role == SIP::CALLER){
				if($msg->src_ip !== $sess->remote_ip || $msg->src_port !== $sess->remote_port){
					continue;
				}
				if($msg->call_id !== $sess->call_id){
					continue;
				}
				if($msg->is_request()){
					if($msg->from_tag !== $sess->remote_tag || $msg->to_tag !== $sess->local_tag){
						Logger::debug("{$msg->from_tag} {$sess->local_tag} {$msg->to_tag} {$sess->remote_tag}");
						continue;
					}
					if($msg->from !== $sess->remote || $msg->to !== $sess->local){
						continue;
					}
				}else{
					if($msg->from_tag !== $sess->local_tag || ($msg->to_tag != $sess->remote_tag && $sess->remote_tag)){
						Logger::debug("{$msg->from_tag} {$sess->local_tag} {$msg->to_tag} {$sess->remote_tag}");
						continue;
					}
					if($msg->from !== $sess->local || $msg->to !== $sess->remote){
						continue;
					}
				}
			}else if($sess->role == SIP::CALLEE){
				continue;
			}

			if($this->before_sess_recv_msg($sess, $msg) !== false){
				$s1 = ($sess->state == SIP::COMPLETED || $sess->renew);
				$sess->incoming($msg);
				$s2 = ($sess->state == SIP::COMPLETED);
				if(!$s1 && $s2){
					$this->up_session($sess);
				}
			}
			return true;
		}
		return false;
	}
	
	private function before_sess_recv_msg($sess, $msg){
		if($msg->is_request()){
			$sess->uri = $msg->uri; // will uri be updated during session?
			$sess->branch = $msg->branch;
			$sess->cseq = $msg->cseq;
		}else{
			if($msg->branch !== $sess->branch){
				Logger::debug("drop msg, msg.branch: {$msg->branch} != sess.branch: {$sess->branch}");
				return false;
			}
			// 对于会话中的 OPTIONS/INFO，响应的 cseq 不会变化。
			if($msg->cseq_method == 'OPTIONS'){
				if($msg->cseq !== $sess->options_cseq){
					Logger::debug("drop msg, msg.cseq: {$msg->cseq} != sess.options_cseq: {$sess->options_cseq}");
					return false;
				}
			}else if($msg->cseq_method == 'INFO'){
				if($msg->cseq !== $sess->info_cseq){
					Logger::debug("drop msg, msg.cseq: {$msg->cseq} != sess.info_cseq: {$sess->info_cseq}");
					return false;
				}
				//$sess->close(); return true;
			}else{
				if($msg->cseq !== $sess->cseq){
					Logger::debug("drop msg, msg.cseq: {$msg->cseq} != sess.cseq: {$sess->cseq}");
					return false;
				}
			}
			
			if($msg->code >= 200){
				$sess->remote_tag = $msg->to_tag;
				$sess->cseq ++;
				if(!$sess->remote_allow){
					$str = $msg->get_header('Allow');
					$sess->remote_allow = preg_split('/[, ]+/', trim($str));
				}
			}
		}
	}
	
	/*
	该方法定期（很小间隔时间）被系统调用。更新 dialog 中 session 的定时器，
	如果定时器有触发，则调用 session 的 outgoing() 方法获取要发送的消息。
	*/
	function outgoing($time, $timespan){
		$ret = array();
		foreach($this->sessions as $index=>$sess){
			if($sess->timers){
				$sess->timers[0] -= $timespan;
				if($sess->timers[0] <= 0){
					array_shift($sess->timers);
					if(count($sess->timers) == 0){
						if($sess->state == SIP::FIN_WAIT || $sess->state == SIP::CLOSE_WAIT){
							Logger::debug("close session " . $sess->role_name() . ' gracefully');
							$sess->terminate();
						}else{
							// transaction timeout
							Logger::debug("transaction timeout");
							$sess->close();
						}
					}else{
						// re/transmission timer trigger
						$msg = $sess->outgoing();
						if($msg){
							$this->before_sess_send_msg($sess, $msg);
							$ret[] = $msg;
						}
					}
				}
			}

			if($sess->state == SIP::CLOSED){
				$this->del_session($sess);
			}
		}
		return $ret;
	}
	
	private function before_sess_send_msg($sess, $msg){
		$msg->src_ip = $sess->local_ip;
		$msg->src_port = $sess->local_port;
		$msg->dst_ip = $sess->remote_ip;
		$msg->dst_port = $sess->remote_port;

		$msg->uri = $sess->uri;
		$msg->call_id = $sess->call_id;
		$msg->branch = $sess->branch;
		$msg->cseq = $sess->cseq;
		$msg->from = $sess->local;
		$msg->from_tag = $sess->local_tag;
		$msg->to = $sess->remote;
		$msg->to_tag = $sess->remote_tag;
		$msg->contact = $sess->contact;
		if($msg->method == 'INFO'){
			$msg->cseq = $sess->info_cseq;
		}
		if($msg->method == 'OPTIONS'){
			$msg->cseq = $sess->options_cseq;
		}
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
	
	function up_session($sess){
		Logger::debug("UP session " . $sess->role_name() . ", {$sess->local} => {$sess->remote}");
	}
}
