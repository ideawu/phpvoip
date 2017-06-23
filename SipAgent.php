<?php
class SipAgent
{
	public $local_ip;
	public $local_port;
	public $proxy_ip;
	public $proxy_port;
	public $username;
	public $password;
	public $domain;
	
	public $uri;
	public $from;
	public $contact;
	
	private $sessions = array();
	
	function register($username, $password, $proxy_ip, $proxy_port){
		$ps = explode('@', $username);
		if(count($ps) > 1){
			$username = $ps[0];
			$domain = $ps[1];
		}else{
			$domain = $proxy_ip;
		}
	
		$this->username = $username;
		$this->password = $password;
		$this->domain = $domain;
		$this->proxy_ip = $proxy_ip;
		$this->proxy_port = $proxy_port;
		$this->uri = "sip:{$this->username}@{$this->domain}";
		$this->from = "\"{$this->username}\" <{$this->uri}>";
		$this->contact = $this->from;

		$sess = SipSession::register($this->username, $this->password);
		$sess->uri = "sip:{$this->domain}";
		$sess->from = $this->from;
		$sess->to = $this->from;
		
		$this->sessions[] = $sess;
	}
	
	function oncall($msg){
		$sess = SipSession::oncall($msg);
		$this->sessions[] = $sess;
		Logger::debug("NEW session, {$sess->call_id} {$sess->from_tag} {$sess->to_tag}");
	}
	
	function incoming($msg){
		foreach($this->sessions as $sess){
			if($msg->call_id === $sess->call_id && $msg->from_tag === $sess->from_tag){
				$sess->on_recv($msg);
				return true;
			}
		}
		
		if($msg->method == 'INVITE'){
			// WTF?
			if($this->uri === $msg->uri || strpos($msg->uri, "sip:{$this->username}@") === 0){
				$this->oncall($msg);
				return true;
			}else{
			}
		}
	}
	
	function outgoing($time, $timespan){
		$ret = array();
		foreach($this->sessions as $index=>$sess){
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
				}else{
					// re/transmission timeout
					$msg = $sess->to_send();
					if($msg){
						$msg->ip = $this->local_ip;
						$msg->port = $this->local_port;
						$msg->username = $this->username;
						$msg->password = $this->password;
						$msg->contact = $this->contact;
						$ret[] = $msg;
					}
				}
			}

			if($sess->state == SIP::CLOSED){
				unset($this->sessions[$index]);
				Logger::debug("DEL session, {$sess->call_id} {$sess->from_tag} {$sess->to_tag}");
				continue;
			}
		}
		return $ret;
	}
}
