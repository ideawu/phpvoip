<?php
class SipRegistrarSession extends SipSession
{
	public $username;
	public $password;
	public $remote_branch;
	private $expires = 60; // 似乎某个 UAC 不支持少于60
	
	private $auth = array(
		'scheme' => 'Digest',
		'realm' => 'phpvoip',
		'algorithm' => 'MD5',
	);

	function __construct($msg){
		parent::__construct();
		$this->role = SIP::REGISTRAR;
		$this->set_state(SIP::NONE);

		$this->uri = $msg->uri;
		$this->call_id = $msg->call_id;
		$this->local = clone $msg->to;
		$this->remote = clone $msg->from;
		$this->contact = clone $msg->contact;
		$this->remote_cseq = $msg->cseq;
	
		$this->remote_branch = $msg->branch;
		$this->auth['nonce'] = SIP::long_token();
	}
	
	function init(){
		$this->set_state(SIP::TRYING);
		$this->new_response($this->remote_branch);
		$this->trans->trying();
	}
	
	function auth(){
		$this->set_state(SIP::AUTHING);
		$this->new_response($this->remote_branch);
		$this->trans->auth();
	}
	
	function on_new_request($msg){
		// 其它状态下，禁止接收新请求。
		if($this->is_state(SIP::AUTHING) && $msg->method === 'REGISTER'){
			parent::on_new_request($msg);
			$this->trans->auth();
			return true;
		}
		return false;
	}
	
	function incoming($msg){
		$trans = $this->trans;
		if($trans->state == SIP::AUTHING || $trans->state == SIP::COMPLETING){
			if($msg->method == 'REGISTER'){
				if(!$msg->auth){
					Logger::debug("recv duplicated REGISTER without auth info");
					$trans->nowait();
					return true;
				}

				// 账号密码验证
				$in_auth = SIP::decode_www_auth($msg->auth);
				$my_auth = SIP::www_auth($this->username, $this->password, $this->uri, 'REGISTER', $this->auth);
				if($in_auth['response'] !== $my_auth['response']){
					Logger::debug("auth failed");
					$trans->nowait();
					return true;
				}
				if($msg->expires <= 0){
					Logger::debug($this->remote->address() . " client logout, onclose");
					$this->expires = 0;
					$this->onclose($msg);
					return true;
				}
				if($this->is_completed()){
					Logger::debug("duplicated successful login");
					$trans->nowait();
					return true;
				}

				$this->local->set_tag(SIP::new_tag());
				$this->complete();
				$this->trans->completing();
				$this->trans->timers = array(0, $this->expires);
			}
		}
	}
	
	function outgoing(){
		$trans = $this->trans;
		if($trans->state == SIP::TRYING){
			$msg = new SipMessage();
			$msg->code = 100;
			$msg->cseq_method = 'REGISTER';
			return $msg;
		}else if($trans->state == SIP::AUTHING){
			$trans->to->del_tag();
			
			$msg = new SipMessage();
			$msg->code = 401;
			$msg->cseq_method = 'REGISTER';

			$str = SIP::encode_www_auth($this->auth);
			$msg->add_header('WWW-Authenticate', $str);
			return $msg;
		}else if($trans->state == SIP::COMPLETING){
			static $i=0;
			if($i++%2 == 0){
				Logger::debug("manually drop msg");
				return;
			}
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->cseq_method = 'REGISTER';
			$msg->expires = $this->expires;
			return $msg;
		// }else if($trans->state == SIP::KEEPALIVE){
		// 	Logger::debug($this->remote->address() . " session expires, terminate");
		// 	$this->terminate();
		}else if($trans->state == SIP::CLOSE_WAIT){
			$this->terminate();
			$msg = new SipMessage();
			$msg->code = 200;
			$msg->cseq_method = 'REGISTER';
			$msg->expires = $this->expires;
			return $msg;
		}
	}
}
