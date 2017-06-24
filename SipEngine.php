<?php
class SipEngine
{
	private $link;
	public $local_ip;
	public $local_port;
	
	private $modules = array();
	private $sessions = array();
	
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
	
	function add_module($mod){
		$this->modules[] = $mod;
	}

	function loop(){
		$read = array($this->link->sock);
		$write = array();
		$except = array();
	
		$ret = @socket_select($read, $write, $except, 0, 100*1000);
		if($ret === false){
			Logger::error(socket_strerror(socket_last_error()));
			return false;
		}
		
		if($read){
			$this->proc_recv();
		}
		$this->proc_send();
	}
	
	private function proc_recv(){
		$link = $this->link;
		$buf = $link->recvfrom($ip, $port);
		$msg = new SipMessage();
		if($msg->decode($buf) <= 0){
			Logger::error("bad SIP packet");
			return;
		}
		Logger::debug("recv " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason) . ' ' . $msg->from);
		#echo '  < ' . str_replace("\n", "\n  < ", trim($buf)) . "\n\n";

		foreach($this->modules as $module){
			$ret = $module->incoming($msg);
			if($ret === true){
				return;
			}
		}
		
		// 注：如果是重传的 INVITE，则应该被 incoming 发现并处理，不会走到此处逻辑。
		if($msg->method == 'INVITE'){
			foreach($this->modules as $module){
				$sess1 = $module->callin($msg);
				if($sess1 === true){
					break;
				}
			}
			foreach($this->modules as $module){
				$sess2 = $module->callout($msg);
				if($sess2 === true){
					break;
				}
			}
			// TODO: new conference = sess1 + sess2
			if(!$sess1){
				// forbidden client
			}
			if($sess1 && !$sess2){
				// 404
			}
		}
		
		// TODO
		Logger::debug("ignore " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason) . ' ' . $msg->from);
	}
	
	private $time = 0;

	function proc_send(){
		$old_time = $this->time;
		$this->time = microtime(1);
		$timespan = max(0, $this->time - $old_time);

		$link = $this->link;
		foreach($this->modules as $module){
			$msgs = $module->outgoing($time, $timespan);
			foreach($msgs as $msg){
				// TODO: 如果绑的是 0.0.0.0，需要自动选一个 src_ip
				$msg->src_ip = $this->local_ip;
				$msg->src_port = $this->local_port;

				$buf = $msg->encode();
				$link->sendto($buf, $msg->dst_ip, $msg->dst_port);
				Logger::debug("send " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason) . ' ' . $msg->from);
				#echo '  > ' . str_replace("\n", "\n  > ", trim($buf)) . "\n\n";
			}
		}
	}
}
