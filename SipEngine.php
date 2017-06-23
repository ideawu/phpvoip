<?php
class SipEngine
{
	private $link;
	public $local_ip;
	public $local_port;
	
	private $agents = array();
	private $sessions = array();
	
	private $time = 0;
	
	private function __construct(){
		$this->time = microtime(1);
	}
	
	static function create($local_ip='127.0.0.1', $local_port=0){
		$ret = new SipEngine();
		$ret->link = UdpLink::listen($local_ip, $local_port);
		$ret->local_ip = $ret->link->local_ip;
		$ret->local_port = $ret->link->local_port;
		return $ret;
	}
	
	// username format: username[@domain]
	function register($username, $password, $proxy_ip, $proxy_port=5060){
		$agent = new SipAgent();
		$agent->local_ip = $this->local_ip;
		$agent->local_port = $this->local_port;
		$agent->register($username, $password, $proxy_ip, $proxy_port);
		$this->agents[] = $agent;
	}

	function loop(){
		$old_time = $this->time;
		$this->time = microtime(1);
		$timespan = max(0, $this->time - $old_time);

		$read = array($this->link->sock);
		$write = array();
		$except = array();
	
		$ret = @socket_select($read, $write, $except, 0, 100*1000);
		if($ret === false){
			Logger::error(socket_strerror(socket_last_error()));
			return false;
		}
		
		if($read){
			$this->on_recv();
		}
		$this->to_send($time, $timespan);
	}
	
	private function on_recv(){
		$link = $this->link;
		$buf = $link->recvfrom($ip, $port);
		$msg = new SipMessage();
		if($msg->decode($buf) <= 0){
			Logger::error("bad SIP packet");
			return;
		}
		Logger::debug("recv " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason) . ' ' . $msg->from);
		#echo '  < ' . str_replace("\n", "\n  < ", trim($buf)) . "\n\n";
		
		foreach($this->agents as $agent){
			$ret = $agent->incoming($msg);
			if($ret === true){
				return;
			}
		}
		
		// TODO
		Logger::debug("ignore " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason) . ' ' . $msg->from);
	}
	
	function to_send($time, $timespan){
		$link = $this->link;
		foreach($this->agents as $agent){
			$msgs = $agent->outgoing($time, $timespan);
			foreach($msgs as $msg){
				$buf = $msg->encode();
				$link->sendto($buf, $agent->proxy_ip, $agent->proxy_port);
				Logger::debug("send " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason) . ' ' . $msg->from);
				#echo '  > ' . str_replace("\n", "\n  > ", trim($buf)) . "\n\n";
			}
		}
	}
}
