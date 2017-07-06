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
			if(isset($this->users[$username])){
				// TODO: 验证 contact
				$password = $this->users[$username];
				
				$local_ip = $this->engine->local_ip;
				$local_port = $this->engine->local_port;
				if($local_ip === '0.0.0.0'){
					$local_ip = SIP::guess_local_ip($this->remote_ip);
				}

				$sess = new SipRegistrarSession($msg);
				$sess->local_ip = $local_ip;
				$sess->local_port = $local_port;
				$sess->remote_ip = $msg->src_ip;
				$sess->remote_port = $msg->src_port;

				$sess->call_id = $msg->call_id;
				$sess->local = clone $msg->from;
				$sess->remote = clone $msg->to;
				$sess->contact = clone $msg->contact;
				$sess->remote_branch = $msg->branch;
				$sess->remote_cseq = $msg->cseq;

				$sess->username = $username;
				$sess->password = $password;
				$sess->trying();
				
				$sess->set_callback(array($this, 'sess_callback'));
				$this->add_session($sess);
				return true;
			}
			
			// response 401 Unauthorized
		}
		return false;
	}
	
	function sess_callback($sess){
		
	}
	
	function callin($msg){
		return null;
	}
	
	function callout($msg){
		return null;
	}
}
