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
		
		$sess = SipSession::register($username, $password);
		$sess->uri = "sip:{$this->domain}";
		$sess->from = $this->from;
		$sess->to = $this->from;
		
		$this->sessions[] = $sess;
	}
	
	function unregister(){
	}
	
	// 返回要发送的报文列表
	function outgoing($time, $timespan){
		$ret = array();
		foreach($this->sessions as $index=>$sess){
			if($sess->state == SIP::CLOSED){
				unset($this->sessions[$index]);
				Logger::debug("del session");
				continue;
			}
			
			$sess->timers[0] -= $timespan;
			if($sess->timers[0] <= 0){
				array_shift($sess->timers);
				if(count($sess->timers[0]) == 0){
					if($sess->state == SIP::CLOSING){
						//
					}else{
						// transaction timeout
						Logger::debug("transaction timeout");
					}
					$sess->state = SIP::CLOSED;
					unset($this->sessions[$index]);
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
			if($msg->call_id == $sess->call_id && $msg->from_tag == $sess->from_tag){
				if(!$sess->to_tag || $msg->to_tag == $sess->to_tag){
					$sess->on_recv($msg);
					return;
				}
			}
		}
		if($msg->method == 'INVITE'){
			Logger::debug("new session");
			$sess = SipSession::oncall($msg);
			$this->sessions[] = $sess;
		}else if($msg->method == 'REGISTER'){
			//
		}else{
			Logger::debug("ignore " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason) . ' ' . $msg->from);
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



