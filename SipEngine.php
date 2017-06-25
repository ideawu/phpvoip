<?php
class SipEngine
{
	private $link;
	public $local_ip;
	public $local_port;
	
	private $modules = array();
	private $sessions = array();
	
	private $mod_conference;
	
	private function __construct(){
		$this->time = microtime(1);

		$mod = new SipConferenceModule();
		$this->add_module($mod, INT_MAX);
		$this->mod_conference = $mod;
		
		$mod = new SipRobotModule();
		$this->add_module($mod, -1);
	}
	
	static function create($local_ip='127.0.0.1', $local_port=0){
		$ret = new SipEngine();
		$ret->link = UdpLink::listen($local_ip, $local_port);
		$ret->local_ip = $ret->link->local_ip;
		$ret->local_port = $ret->link->local_port;
		return $ret;
	}
	
	function add_module($mod, $weight=0){
		$offset = 0;
		foreach($this->modules as $index=>$mi){
			if($weight > $mi['weight']){
				$offset = $index;
				break;
			}
		}
		$mi = array(
			'weight' => $weight,
			'module' => $mod,
		);
		array_splice($this->modules, $offset, 0, array($mi));
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
		Logger::debug("recv " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason) . " from {$msg->src_ip}:{$msg->src_port}");
		#echo '  < ' . str_replace("\n", "\n  < ", trim($buf)) . "\n\n";

		foreach($this->modules as $mi){
			$module = $mi['module'];
			$ret = $module->incoming($msg);
			if($ret === true){
				return;
			}
		}
		
		// 注：如果是重传的 INVITE，则应该被 incoming 发现并处理，不会走到此处逻辑。
		if($msg->method == 'INVITE'){
			foreach($this->modules as $mi){
				$module = $mi['module'];
				$sess1 = $module->callin($msg);
				if($sess1){
					Logger::debug("module " . get_class($module) . " accept callin.");
					break;
				}
			}
			if(!$sess1){
				// TODO: reply 403 forbidden
				Logger::info("403 Forbidden");
				return;
			}
			
			foreach($this->modules as $mi){
				$module = $mi['module'];
				$sess2 = $module->callout($msg);
				if($sess2){
					Logger::debug("module " . get_class($module) . " create callout.");
					break;
				}
			}
			if(!$sess2){
				// TODO: reply 404
				Logger::info("404 Not Found");
				return;
			}
			
			$this->mod_conference->create_conference($sess1, $sess2);
			return;
		}
		
		// TODO
		Logger::debug("ignore " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason));
		echo '  < ' . str_replace("\n", "\n  < ", trim($msg->encode())) . "\n\n";
	}
	
	private $time = 0;

	function proc_send(){
		$old_time = $this->time;
		$this->time = microtime(1);
		$timespan = max(0, $this->time - $old_time);

		$link = $this->link;
		foreach($this->modules as $mi){
			$module = $mi['module'];
			$msgs = $module->outgoing($time, $timespan);
			foreach($msgs as $msg){
				// TODO: 对于模块消息，不通过 socket 发送
				$buf = $msg->encode();
				$link->sendto($buf, $msg->dst_ip, $msg->dst_port);
				Logger::debug("send " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason) . " to {$msg->dst_ip}:{$msg->dst_port}");
				#echo '  > ' . str_replace("\n", "\n  > ", trim($buf)) . "\n\n";
			}
		}
	}
}
