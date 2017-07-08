<?php
class SipRegistrar extends SipModule
{
	private $users = array();

	// 添加一个用户配置
	function add_user($username, $password){
		$this->users[$username] = $password;
	}
	
	function incoming($msg){
		$ret = parent::incoming($msg);
		if($ret){
			return $ret;
		}
		
		// 新的 REGISTER
		if($msg->method === 'REGISTER'){
			$username = $msg->from->username;
			if(!isset($this->users[$username])){
				return false;
			}
			if($username !== $msg->contact->username){
				Logger::debug("username: {$username} != contact: {$msg->contact->username}");
				return false;
			}
			
			$password = $this->users[$username];
				
			$local_ip = $this->engine->local_ip;
			$local_port = $this->engine->local_port;
			if($local_ip === '0.0.0.0'){
				$local_ip = SIP::guess_local_ip($msg->src_ip);
			}

			$sess = new SipRegistrarSession();
			$sess->local_ip = $local_ip;
			$sess->local_port = $local_port;
			$sess->remote_ip = $msg->src_ip;
			$sess->remote_port = $msg->src_port;

			$sess->uri = $msg->uri;
			$sess->call_id = $msg->call_id;
			$sess->local = clone $msg->to;
			$sess->remote = clone $msg->from;
			$sess->contact = clone $msg->contact;
			$sess->remote_cseq = $msg->cseq;
			$sess->remote_branch = $msg->branch;

			$sess->username = $username;
			$sess->password = $password;

			$this->add_session($sess);
				
			$sess->init();
			// 未来应该在请求外部系统返回时，调用 auth()
			$sess->auth();
			$sess->set_callback(array($this, 'sess_callback'));
			return true;
		}
		return false;
	}
	
	function sess_callback($sess){
		Logger::debug($sess->brief() . " state = " . $sess->state_text());
		// 将同用户不同 call_id 的会话清除，处理逻辑1
		// 将同用户同 call_id 的会话清除，处理逻辑2
		if($sess->is_state(SIP::COMPLETED)){
			foreach($this->sessions as $index=>$tmp){
				if($tmp === $sess){
					continue;
				}
				if($tmp->remote->username !== $sess->remote->username){
					continue;
				}

				if($tmp->call_id === $sess->call_id){
					Logger::debug("REGISTRAR " . $sess->remote->address() . " renewed");
				}else{
					Logger::debug("REGISTRAR " . $sess->remote->address() . " with new call_id");
				}
				
				Logger::debug('    del ' . $sess->remote->encode());
				unset($this->sessions[$index]);
			}
		}
		if($sess->is_state(SIP::CLOSED)){
			foreach($this->sessions as $index=>$tmp){
				if($tmp === $sess){
					continue;
				}
				if($tmp->remote->username !== $sess->remote->username){
					continue;
				}
				Logger::debug('    del ' . $sess->remote->encode());
				unset($this->sessions[$index]);
			}
		}
	}
	
	function callin($msg){
		foreach($this->sessions as $sess){
			if(!$sess->is_state(SIP::COMPLETED)){
				continue;
			}
			if($msg->src_ip !== $sess->remote_ip || $msg->src_port !== $sess->remote_port){
				continue;
			}
			if($msg->from->username !== $sess->remote->username){
				continue;
			}
			
			$call = new SipCalleeSession($msg);
			$call->local_ip = $sess->local_ip;
			$call->local_port = $sess->local_port;
			$call->remote_ip = $sess->remote_ip;
			$call->remote_port = $sess->remote_port;
			$call->init();
			return $call;
		}
		return null;
	}
	
	function callout($msg){
		foreach($this->sessions as $sess){
			if(!$sess->is_state(SIP::COMPLETED)){
				continue;
			}
			if($msg->src_ip !== $sess->remote_ip || $msg->src_port !== $sess->remote_port){
				continue;
			}
			if($msg->to->username !== $sess->remote->username){
				continue;
			}
		
			$uri = $msg->uri;
			$from = clone $msg->from;
			$to = clone $msg->to;

			$call = new SipCallerSession($uri, $from, $to);
			$call->local_ip = $sess->local_ip;
			$call->local_port = $sess->local_port;
			$call->remote_ip = $sess->remote_ip;
			$call->remote_port = $sess->remote_port;
			$call->init();
			return $call;
		}
		return null;
	}
}
