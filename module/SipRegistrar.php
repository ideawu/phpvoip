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
			if($this->register($msg)){
				// 如果创建了新的 session，应该处理消息，框架不会调用。
				return parent::incoming($msg);
			}
		}
		return false;
	}
	
	function register($msg){
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

		Logger::debug("create new REGISTRAR " . $sess->brief());
		$this->add_session($sess);
				
		$sess->init();
		// 未来应该在请求外部系统返回时，调用 auth()
		$sess->auth();
		$sess->set_callback(array($this, 'sess_callback'));
		return true;
	}
	
	function sess_callback($sess){
		if($sess->is_state(SIP::COMPLETED)){
			$key = $sess->remote->username;
			$old = isset($this->onlines[$key])? $this->onlines[$key] : null;
			if($old){
				if($old->call_id === $sess->call_id){
					Logger::debug($sess->brief() . " renewed");
				}else{
					Logger::debug($sess->brief() . " registered with new call_id");
				}
				Logger::debug("offline " . $old->brief());
				unset($this->onlines[$key]);
				$this->del_session($old);
			}else{
				Logger::debug($sess->brief() . ' registered');
			}
			Logger::debug("online " . $sess->brief());
			$this->onlines[$key] = $sess;
		}

		// 客户端退出，可能是在原来的 session 基础上，也可能新建一个新的 session，
		// 所以要区分处理。如果是新建 sess，则立即删除旧 sess。否则，等 sess 自动删除。
		if($sess->is_state(SIP::CLOSING)){
			if($sess->expires <= 0){
				Logger::debug($sess->brief() . " logout");
				$key = $sess->remote->username;
				$old = isset($this->onlines[$key])? $this->onlines[$key] : null;
				if($old){
					Logger::debug("offline " . $old->brief());
					unset($this->onlines[$key]);
					if($old !== $sess){
						$this->del_session($old);
					}
				}
			}
		}
		
		if($sess->is_state(SIP::CLOSED)){
			$key = $sess->remote->username;
			$old = isset($this->onlines[$key])? $this->onlines[$key] : null;
			if($old && $old === $sess){
				Logger::debug($sess->brief() . " expired");
				Logger::debug("offline " . $old->brief());
				unset($this->onlines[$key]);
				$this->del_session($old);
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
			
			$call = new CalleeSession($msg);
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
				#Logger::debug('');
				continue;
			}
			if($msg->to->username !== $sess->remote->username){
				#Logger::debug("{$msg->to->username} {$sess->remote->username}");
				continue;
			}
		
			$uri = $msg->uri;
			$from = clone $msg->from;
			$to = clone $msg->to;

			$call = new CallerSession($uri, $from, $to);
			$call->local_ip = $sess->local_ip;
			$call->local_port = $sess->local_port;
			$call->remote_ip = $sess->remote_ip;
			$call->remote_port = $sess->remote_port;
			$call->init();
			return $call;
		}
		return null;
	}
	
	private function test($sess){
		$uri = "sip:{$sess->remote->username}@{$sess->remote->domain}";
		$from = new SipContact(1, '127.0.0.1:5070');
		$to = new SipContact($sess->remote->username, $sess->remote->domain);

		$call = new CallerSession($uri, $from, $to);
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

/*
			$call->local_sdp = <<<TEXT
v=0
o=yate 1499684812 1499684812 IN IP4 127.0.0.1
s=SIP Call
c=IN IP4 127.0.0.1
t=0 0
m=audio 28300 RTP/AVP 109 0 8 101
a=rtpmap:109 iLBC/8000
a=fmtp:109 mode=30
a=rtpmap:0 PCMU/8000
a=rtpmap:8 PCMA/8000
a=rtpmap:101 telephone-event/8000
a=ptime:30
TEXT;
			$call->init();
			$this->add_session($call);
			return;
*/