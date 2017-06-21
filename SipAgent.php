<?php
class SipAgent
{
	public $proxy_ip;
	public $proxy_port;
	public $domain = '';
	public $username;
	public $password;
	
	private $from;
	
	private $reg_sess = null;
	private $sessions = array();
	
	function __construct(){
	}
	
	function register($username, $password){
		if(!$this->domain){
			$this->domain = $this->proxy_ip;
		}
		
		$this->username = $username;
		$this->password = $password;
		$this->from = "\"{$this->username}\" <sip:{$this->username}@{$this->domain}>";
		
		$sess = SipSession::register();
		$sess->uri = "sip:{$this->domain}";
		$sess->from = $this->from;
		$sess->to = $this->from;
		
		$sess->username = $this->username;
		$sess->password = $this->password;
		
		$this->reg_sess = $sess;
		$this->sessions[] = $sess;
	}
	
	function unregister(){
		
	}
	
	// 返回要发送的报文列表
	function outgoing($time, $timespan){
		$ret = array();
		foreach($this->sessions as $sess){
			$sess->timers[0] -= $timespan;
			if($sess->timers[0] <= 0){
				array_shift($sess->timers);
				if(count($sess->timers[0]) == 0){
					// transaction timeout
					Logger::debug("transaction timeout");
				}else{
					// re/transmission timeout
					$msg = $sess->to_send();
					if($msg){
						$ret[] = $msg;
					}
				}
			}
		}
		return $ret;
	}
	
	// 当有收到消息时，调用一次
	function incomming($msg){
		foreach($this->sessions as $sess){
			if($sess->call_id != $msg->call_id){
				continue;
			}
			if($sess->from_tag != $msg->from_tag){
				continue;
			}
			
			if($msg->is_response()){
				if($msg->cseq != $sess->cseq){
					continue;
				}
				if($msg->branch != $sess->branch){
					continue;
				}
			}
			
			$sess->on_recv($msg);
			break;
		}
	}
}




/*
# prepare to send
state REGISTERING:
	send REGISTER
state CALLING:
	send INVITE
state ACCEPTING:
	send OK

# process received
recv REGISTER:
	=> REGISTERED
	send OK
recv OK:
	case REGISTERING:
		=> REGISTERED
	case CALLING || ESTABLISHED:
		=> ESTABLISHED
		send ACK
recv INVITE:
	case NONE:
		=> ACCEPTING
		send OK
	case ESTABLISHED:
		drop
recv ACK:
	case ACCEPTING:
		=> COMPLETED
*/



