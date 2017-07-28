<?php
class SipRegistrar extends SipModule
{
	private $users = array();
	private $onlines = array();

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
			if($this->new_register($msg)){
				// 如果创建了新的 session，应该处理消息，框架不会调用。
				return parent::incoming($msg);
			}
		}
		return false;
	}

	function outgoing($time, $timespan){
		$msgs = parent::outgoing($time, $timespan);
		foreach($msgs as $msg){
			$msg->add_header('Server', 'phpvoip-registrar');
		}
		return $msgs;
	}
		
	private function new_register($msg){
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

		$sess = new RegistrarSession($msg);
		$sess->local_ip = $local_ip;
		$sess->local_port = $local_port;
		$sess->remote_ip = $msg->src_ip;
		$sess->remote_port = $msg->src_port;

		$sess->username = $username;
		$sess->password = $password;
		$sess->domain = "{$sess->local_ip}:{$sess->local_port}";

		Logger::debug("create new " . $sess->brief());
		$this->add_session($sess);
				
		$sess->init();
		// 未来应该在请求外部系统返回时，调用 auth()
		$sess->auth();
		$sess->set_callback(array($this, 'sess_callback'));
		return true;
	}
	
	function sess_callback($sess){
		if($sess->is_state(SIP::COMPLETED)){
			$old = $this->get_online($sess->remote->username);
			if($old){
				if($old->call_id === $sess->call_id){
					Logger::debug($sess->brief() . " renewed");
				}else{
					Logger::debug($sess->brief() . " registered with new call_id");
				}
				$this->offline($old);
				$this->del_session($old);
			}else{
				Logger::debug($sess->brief() . ' registered');
			}
			$this->online($sess);
		}

		// 客户端退出，可能是在原来的 session 基础上，也可能新建一个新的 session，
		// 所以要区分处理。如果是新建 sess，则立即删除旧 sess。否则，等 sess 自动删除。
		if($sess->is_state(SIP::CLOSING)){
			if($sess->expires <= 0){
				Logger::debug($sess->brief() . " logout");
				$old = $this->get_online($sess->remote->username);
				if($old){
					$this->offline($old);
					if($old !== $sess){
						$this->del_session($old);
					}
				}
			}
		}
		
		if($sess->is_state(SIP::CLOSED)){
			$old = $this->get_online($sess->remote->username);
			if($old && $old === $sess){
				Logger::debug($sess->brief() . " expired");
				$this->offline($old);
				$this->del_session($old);
			}
		}
	}
	
	private function get_online($username){
		return isset($this->onlines[$username])? $this->onlines[$username] : null;
	}
	
	private function online($sess){
		Logger::debug("online  " . $sess->brief());
		$this->onlines[$sess->remote->username] = $sess;
	}
	
	private function offline($sess){
		Logger::debug("offline " . $sess->brief());
		unset($this->onlines[$sess->remote->username]);
	}
	
	function callin($msg){
		if(!isset($this->onlines[$msg->from->username])){
			return null;
		}
		$sess = $this->onlines[$msg->from->username];
		if(!$sess->is_state(SIP::COMPLETED)){
			return null;
		}
		if($msg->src_ip !== $sess->remote_ip || $msg->src_port !== $sess->remote_port){
			return null;
		}

		$call = new CalleeSession($msg);
		$call->local_ip = $sess->local_ip;
		$call->local_port = $sess->local_port;
		$call->remote_ip = $sess->remote_ip;
		$call->remote_port = $sess->remote_port;
		$call->init();
		return $call;
	}
	
	function callout($msg){
		if(!isset($this->onlines[$msg->to->username])){
			return null;
		}
		$sess = $this->onlines[$msg->to->username];
		if(!$sess->is_state(SIP::COMPLETED)){
			return null;
		}

		$from = new SipContact($msg->from->username, $sess->domain);
		$to = new SipContact($msg->to->username, $sess->domain);

		$call = new CallerSession($from, $to);
		$call->local_ip = $sess->local_ip;
		$call->local_port = $sess->local_port;
		$call->remote_ip = $sess->remote_ip;
		$call->remote_port = $sess->remote_port;
		$call->init();
		return $call;
	}
	
	private function test($sess){
		$from = new SipContact(1, '127.0.0.1:5070');
		$to = new SipContact($sess->remote->username, $sess->remote->domain);

		$call = new CallerSession($from, $to);
		$call->local_ip = $sess->local_ip;
		$call->local_port = $sess->local_port;
		$call->remote_ip = $sess->remote_ip;
		$call->remote_port = $sess->remote_port;
		$call->init();
		
		$this->add_session($call);
		$call->set_callback(array($this, 'test_cb'));
	}
	
	function test_cb($sess){
		if($sess->is_state(SIP::RINGING)){
			$sess->close();
		}
	}
}
