<?php
abstract class SipModule
{
	// 记录 session 间的关系。
	protected $dialogs = array();
	
	// session 存储在 sessions 列表中。
	protected $sessions = array();

	function incoming($msg){
		foreach($this->sessions as $sess){
			//外线：判断 call_id + from_tag + from + to + src.ip:port
			if($sess->role == SIP::REGISTER){
				if($msg->call_id !== $sess->call_id){
					continue;
				}
				if($msg->from_tag !== $sess->from_tag){
					continue;
				}
				if($msg->from !== $sess->from || $msg->to !== $sess->to){
					continue;
				}
				if($msg->src_ip !== $sess->remote_ip || $msg->src_port !== $sess->remote_port){
					continue;
				}
			}else{
				continue;
			}
			
			if($this->before_sess_recv_msg($sess, $msg) !== false){
				$sess->incoming($msg);
			}
			return true;
		}
		return false;
	}
	
	private function before_sess_recv_msg($sess, $msg){
		if($sess->state == SIP::ESTABLISHED){
			if($msg->to_tag !== $sess->to_tag){
				Logger::debug("drop msg, msg.to_tag: {$msg->to_tag} != sess.cseq: {$sess->to_tag}");
				return false;
			}
		}
		if($msg->is_request()){
			$sess->uri = $msg->uri; // will uri be updated during session?
			$sess->branch = $msg->branch;
			$sess->cseq = $msg->cseq;
		}else{
			if($msg->cseq !== $sess->cseq){
				Logger::debug("drop msg, msg.cseq: {$msg->cseq} != sess.cseq: {$sess->cseq}");
				return false;
			}
			if($msg->branch !== $sess->branch){
				Logger::debug("drop msg, msg.branch: {$msg->branch} != sess.branch: {$sess->branch}");
				return false;
			}
			if($msg->code >= 200){
				$sess->to_tag = $msg->to_tag;
				$sess->cseq ++;
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
			$sess->timers[0] -= $timespan;
			if($sess->timers[0] <= 0){
				array_shift($sess->timers);
				if(count($sess->timers) == 0){
					if($sess->state == SIP::CLOSING){
						Logger::debug("CLOSING " . $sess->role_name() . " session, call_id: {$sess->call_id}");
					}else{
						// transaction timeout
						Logger::debug("transaction timeout");
					}
					$sess->state = SIP::CLOSED;
				}else{
					// re/transmission timer trigger
					$msg = $sess->outgoing();
					if($msg){
						$this->before_sess_send_msg($sess, $msg);
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
	
	private function before_sess_send_msg($sess, $msg){
		$msg->src_ip = $sess->local_ip;
		$msg->src_port = $sess->local_port;
		$msg->dst_ip = $sess->remote_ip;
		$msg->dst_port = $sess->remote_port;

		$msg->uri = $sess->uri;
		$msg->call_id = $sess->call_id;
		$msg->branch = $sess->branch;
		$msg->cseq = $sess->cseq;
		$msg->from = $sess->from;
		$msg->from_tag = $sess->from_tag;
		$msg->to = $sess->to;
		$msg->contact = $sess->contact;
		// // 重发的请求不需要带 to_tag?
		// if($msg->is_response()){
		// 	$msg->to_tag = $$sess->to_tag;
		// }
		$msg->to_tag = $sess->to_tag;
	}
	
	protected function add_session($sess){
		Logger::debug("NEW " . $sess->role_name() . " session, call_id: {$sess->call_id}");
		$this->sessions[] = $sess;
	}

	protected function del_session($sess){
		Logger::debug("DEL " . $sess->role_name() . " session, call_id: {$sess->call_id}");
		foreach($this->sessions as $index=>$tmp){
			if($tmp !== $sess){
				continue;
			}
			unset($this->sessions[$index]);
			// 如果存在于 dialog 中，dialog 也要删除
			foreach($this->dialogs as $dialog){
				$dialog->del_session($sess);
			}
			break;
		}
	}
}
