<?php
abstract class SipSession
{
	public $role;
	public $state = 0;
	public $timers;

	public $local_ip;
	public $local_port;
	public $proxy_ip;
	public $proxy_port;

	protected $expires = 60;
	protected static $reg_timers = array(0, 0.5, 1, 2, 4, 2);
	protected static $call_timers = array(0, 0.5, 1, 2, 4, 2);
	protected static $refresh_timers = array(10, 2);
	protected static $closing_timers = array(0, 5);
	protected static $call_id_prefix = 'call_';
	protected static $tag_prefix = 'tag_';
	protected static $branch_prefix = 'z9hG4bK_';
	
	public $call_id; // session id
	public $branch;  // transaction id
	public $cseq;    // command/transaction seq
	
	public $uri;
	
	public $from;
	public $from_tag; // session id
	public $to;
	public $to_tag;   // session id
	
	protected $auth;
	
	function __construct(){
	}
	
	abstract function incoming($msg);
	abstract function outgoing();
	
	function get_msg_to_send(){
		$msg = $this->outgoing();;
		if($msg){
			$msg->src_ip = $this->local_ip;
			$msg->src_port = $this->local_port;
			$msg->dst_ip = $this->proxy_ip;
			$msg->dst_port = $this->proxy_port;

			$msg->uri = $this->uri;
			$msg->call_id = $this->call_id;
			$msg->branch = $this->branch;
			$msg->cseq = $this->cseq;
			$msg->from = $this->from;
			$msg->from_tag = $this->from_tag;
			$msg->to = $this->to;
			$msg->contact = $this->contact;
			// 重发的请求不需要带 to_tag
			if($msg->is_response()){
				$msg->to_tag = $this->to_tag;
			}
		}
		return $msg;
	}
	
	function on_recv_msg($msg){
		if($msg->is_request()){
			$this->uri = $msg->uri; // will uri be updated during session?
			$this->branch = $msg->branch;
			$this->cseq = $msg->cseq;
		}else{
			if($msg->cseq !== $this->cseq){
				Logger::debug("drop msg, msg.cseq: {$msg->cseq} != sess.cseq: {$this->cseq}");
				return;
			}
			if($msg->branch !== $this->branch){
				Logger::debug("drop msg, msg.branch: {$msg->branch} != sess.branch: {$this->branch}");
				return;
			}
			if($this->state == SIP::ESTABLISHED){
				if($msg->to_tag !== $this->to_tag){
					Logger::debug("drop msg, msg.to_tag: {$msg->to_tag} != sess.cseq: {$this->to_tag}");
					return;
				}
			}
			$this->to_tag = $msg->to_tag;
		}
		
		$this->incoming($msg);
		if($msg->is_response() && $msg->code >= 200){
			$this->cseq ++;
		}
	}
}
