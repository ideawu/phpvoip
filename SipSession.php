<?php
abstract class SipSession
{
	public $id;
	public $role;
	private $state = 0;

	public $local_ip;
	public $local_port;
	public $remote_ip;
	public $remote_port;

	public $call_id;
	public $local;
	public $remote;
	public $contact;
	public $local_cseq;
	public $remote_cseq;
	public $local_branch;
	// public $remote_branch;
	public $remote_allow = array();
	
	protected $trans;
	protected $transactions = array();

	private $callback;
	private static $id_incr = 0;
	
	function __construct(){
		$this->set_state(SIP::NONE);
		$this->id = self::$id_incr ++;
		$this->local_cseq = SIP::new_cseq();
		$this->local_branch = SIP::new_branch();
		$this->trans = new SipTransaction();
		$this->transactions = array($this->trans);
	}
	
	abstract function init();
	abstract function close();
	
	function set_callback($callback){
		$this->callback = $callback;
	}
	
	function state(){
		return $this->state;
	}
	
	function is_state($state){
		return $this->state === $state;
	}
	
	function set_state($new){
		if($new === SIP::CLOSED){
			#debug_print_backtrace();
		}
		$old = $this->state;
		$this->state = $new;
		if($old !== $new && $this->callback){
			Logger::debug($this->brief() . " state=" . $this->state_text());
			call_user_func($this->callback, $this);
		}
	}
	
	function role_name(){
		if($this->role == SIP::REGISTER){
			return 'REGISTER';
		}else if($this->role == SIP::REGISTRAR){
			return 'REGISTRAR';
		}else if($this->role == SIP::CALLER){
			return 'CALLER';
		}else if($this->role == SIP::CALLEE){
			return 'CALLEE';
		}else{
			return 'NOOP';
		}
	}
	
	function state_text(){
		return SIP::state_text($this->state);
	}
	
	function brief(){
		if($this->role == SIP::CALLEE){
			$src = $this->remote->username;
			$dst = $this->local->username;
		}else{
			$src = $this->local->username;
			$dst = $this->remote->username;
		}
		$ret = sprintf('%9s[%d] %-4s=>%-4s', $this->role_name(), $this->id, $src, $dst);
		return $ret;
	}

	
	protected function new_request($method, $uri=null){
		$this->local_cseq ++;
		$this->local_branch = SIP::new_branch();
		
		$new = new SipTransaction();
		$new->uri = $uri? $uri : new SipUri($this->remote->username, "{$this->remote_ip}:{$this->remote_port}");
		$new->method = $method;
		$new->cseq = $this->local_cseq;
		$new->branch = $this->local_branch;
		
		$this->transactions[] = $new;
		return $new;
	}

	protected function bye(){
		$this->set_state(SIP::CLOSING);
		Logger::debug($this->role_name() . " send BYE to close session");
		$this->transactions = array();
		$new = $this->new_request('BYE'); // 发送 BYE, 直到收到 200
		$new->timers = array(0, 1, 2, 2, 2, 2);
		$this->trans = $new;
	}
	
	protected function keepalive(){
		$this->transactions = array();
		if(in_array('INFO', $this->remote_allow)){
			$new = $this->new_request('INFO');
		}else{
			$new = $this->new_request('OPTIONS');
		}
		$new->timers = array(10, 2, 1, 1, 1);
		$this->trans = $new;
	}
	
	function complete(){
		$this->set_state(SIP::COMPLETED);
	}

	function terminate(){
		$this->set_state(SIP::CLOSED);
	}
	
	function add_transaction($trans){
		$this->transactions[] = $trans;
	}
	
	function del_transaction($trans){
		foreach($this->transactions as $index=>$tmp){
			if($tmp === $trans){
				unset($this->transactions[$index]);
				break;
			}
		}
	}
	
	function match_sess($msg){
		if($msg->src_ip !== $this->remote_ip || $msg->src_port !== $this->remote_port){
			return false;
		}
		if($msg->call_id != $this->call_id){
			#Logger::debug($this->role_name() . " {$msg->call_id} != {$this->call_id}");
			return false;
		}
		
		if($msg->is_request()){
			if($msg->from->username != $this->remote->username){
				#Logger::debug("");
				return false;
			}
			if($msg->to->username != $this->local->username){
				#Logger::debug("");
				return false;
			}
			if($msg->from->tag() !== $this->remote->tag()){
				#Logger::debug($msg->from->tag() . " != " . $this->remote->tag());
				return false;
			}
		}else{
			if($msg->from->username != $this->local->username){
				return false;
			}
			if($msg->to->username != $this->remote->username){
				return false;
			}
			if($msg->from->tag() !== $this->local->tag()){
				return false;
			}
			if($this->remote->tag()){
				if($msg->to->tag() !== $this->remote->tag()){
					Logger::debug($msg->to->tag() . " != " . $this->remote->tag());
					return false;
				}
			}
		}
		return true;
	}
	
	private function match_trans($msg, $trans){
		if($msg->is_response()){
			if($msg->cseq !== $trans->cseq){
				return false;
			}
			if($msg->cseq_method !== $trans->method){
				return false;
			}
			if($msg->branch !== $trans->branch){
				Logger::debug("{$msg->branch} != {$trans->branch}");
				return false;
			}
			return true;
		}else{
			// ACK 特殊处理
			if($msg->method === 'ACK'){
				if($msg->cseq !== $trans->cseq){
					#Logger::debug("");
					return false;
				}
				if($msg->uri && $trans->uri && !$msg->uri->equals($trans->uri)){
					Logger::debug($msg->uri->encode() . " != " . $trans->uri->encode());
					return false;
				}
				if($msg->to->tag() !== $this->local->tag()){
					Logger::debug($msg->to->tag() . " != " . $this->local->tag());
					return false;
				}
				return true;
			}
			// CANCEL 不带 to.tag，重传的 INVITE，可能不带 to.tag
			if($msg->cseq === $trans->cseq && $msg->branch === $trans->branch && $msg->uri->equals($trans->uri)){
				#Logger::debug("recv transaction request " . $msg->method);
				return true;
			}
			return false;
		}
	}

	function proc_incoming($msg){
		if(!$this->remote_allow && $msg->allow){
			$this->remote_allow = $msg->allow;
		}
		foreach($this->transactions as $trans){
			if($this->match_trans($msg, $trans)){
				return $this->incoming($msg, $trans);
			}
		}
		#Logger::debug("{$msg->cseq} {$this->remote_cseq} " . $msg->to->tag() . ' ' . $this->local->tag());
		// 新请求或者 BYE
		if((!$this->remote_cseq || $msg->cseq > $this->remote_cseq) && $msg->to->tag() === $this->local->tag()){
			Logger::debug("create new trans for " . $msg->method);
			$this->remote_cseq = $msg->cseq;
			
			$trans = new SipTransaction();
			$trans->uri = clone $msg->uri;
			$trans->method = $msg->method;
			$trans->cseq = $msg->cseq;
			$trans->branch = $msg->branch;
			$this->transactions[] = $trans;
			return $this->incoming($msg, $trans);
		}
		return false;
	}

	function proc_outgoing($time, $timespan){
		$ret = array();
		foreach($this->transactions as $trans){
			if(!$trans->timers){
				$this->del_transaction($trans);
				continue;
			}
			
			$trans->timers[0] -= $timespan;
			if($trans->timers[0] <= 0){
				array_shift($trans->timers);
				if(count($trans->timers) > 0){
					$msg = $this->outgoing($trans);
					if($msg){
						$ret[] = $msg;
					}
				}
			}
		}
		if(!$this->transactions){
			Logger::debug($this->role_name() . " terminated for empty transactions");
			$this->terminate();
		}
		return $ret;
	}
		
	protected function incoming($msg, $trans){
		if($msg->code === 481 || $msg->code === 486 || $msg->code === 487 || $msg->code === 603){
			Logger::debug("recv {$msg->code} for {$trans->method}, closing");
			$this->set_state(SIP::CLOSING);
			$this->remote->set_tag($msg->to->tag());
			
			$trans->method = 'ACK';
			$trans->timers = array(0, 0);
			$this->transactions = array($trans);
			return true;
		}
		if($msg->method === 'ACK'){
			if($trans->code >= 300){
				Logger::debug("recv ACK for {$trans->code}, terminate");
				$this->terminate();
				return true;
			}
		}

		if($msg->is_response() && $msg->cseq_method === 'CANCEL'){
			Logger::debug("recv {$msg->code} for {$msg->cseq_method}, do nothing");
			return true;
		}
		
		if($msg->method === 'BYE'){
			$this->transactions = array();
			// if($this->is_state(SIP::TRYING) || $this->is_state(SIP::RINGING)){
			if($this->trans->code > 0 && $this->trans->code < 200){
				$this->trans->code = 487; // Request Terminated
				$this->trans->timers = array(0, 0);
				$this->transactions[] = $this->trans;
			}
			$this->set_state(SIP::CLOSING);

			// response OK
			$trans->code = 200;
			$trans->timers = array(0, 0);
			$this->transactions[] = $trans;
			return true;
		}
		if($msg->is_response() && $msg->cseq_method === 'BYE'){
			if($msg->code >= 200){
				Logger::debug("recv 200 for BYE, terminate");
				$this->terminate();
				return true;
			}
			#Logger::debug("recv {$msg->code} for {$msg->cseq_method}, do nothing");
			return true;
		}

		if($msg->method === 'INFO' || $msg->method === 'OPTIONS'){
			$trans->code = 200;
			$trans->timers = array(0, 0);
			return true;
		}		
		// keepalive
		if($msg->is_response() && ($trans->method === 'INFO' || $trans->method === 'OPTIONS')){
			if($msg->code >= 200){
				Logger::debug("keepalive updated");
				$this->del_transaction($trans);
				$this->keepalive();
				return true;
			}
			#Logger::debug("recv {$msg->code} for {$msg->cseq_method}, do nothing");
			return true;
		}

		return false;
	}
	
	protected function outgoing($trans){
		$msg = new SipMessage();
		$msg->src_ip = $this->local_ip;
		$msg->src_port = $this->local_port;
		$msg->dst_ip = $this->remote_ip;
		$msg->dst_port = $this->remote_port;

		if($trans->code){
			$msg->code = $trans->code;
			$msg->cseq_method = $trans->method;
		}else{
			$msg->method = $trans->method;
		}
		if($msg->is_request()){
			$msg->from = clone $this->local;
			$msg->to = clone $this->remote;
		}else{
			$msg->from = clone $this->remote;
			$msg->to = clone $this->local;
		}
		if($trans->to_tag){
			$msg->to->set_tag($trans->to_tag);
		}
		if($msg->code === 200 || ($msg->is_request() && ($msg->method === 'INVITE' || $msg->method === 'REGISTER'))){
			$msg->contact = clone $this->contact;
		}
		$msg->call_id = $this->call_id;
		$msg->uri = $trans->uri? (clone $trans->uri) : null;
		$msg->branch = $trans->branch;
		$msg->cseq = $trans->cseq;
		$msg->expires = $trans->expires;
		$msg->auth = $trans->auth;
		$msg->content = $trans->content;
		$msg->content_type = $trans->content_type;
		
		return $msg;
	}
}
