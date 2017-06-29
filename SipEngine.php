<?php
class SipEngine
{
	private $link;
	public $local_ip;
	public $local_port;
	
	private $modules = array();
	private $router;
	
	private function __construct(){
		$this->time = microtime(1);
		
		$this->router = new SipRouter();
		$this->add_module($this->router, INT_MAX);
		
		$mod = new SipRobotModule();
		$this->add_module($mod, -1);
	}
	
	static function create($local_ip='127.0.0.1', $local_port=0){
		$ret = new SipEngine();
		$ret->link = SipLink::listen($local_ip, $local_port);
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
		$mod->engine = $this;
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
		$msg = $this->link->recv();
		foreach($this->modules as $mi){
			$module = $mi['module'];
			$ret = $module->incoming($msg);
			if($ret === true){
				return;
			}
		}
		
		if($msg->method == 'INVITE'){
			if($this->proc_invite($msg) == true){
				return;
			}
		}
		
		Logger::debug("drop msg");
	}
	
	private function proc_invite($msg){
		// 1, Router 先处理
		// TODO:

		// 2, 否则，各模块处理
		$callee = null;
		$caller = $null;
		foreach($this->modules as $mi){
			$module = $mi['module'];
			$callee = $module->callin($msg);
			if($callee){
				Logger::debug("callin {$callee->call_id}");
				break;
			}
		}
		if(!$callee){
			// Unauthorized request/Not Found
			return;
		}
		
		// TODO: 在此路由转换
		
		foreach($this->modules as $mi){
			$module = $mi['module'];
			$caller = $module->callout($msg);
			if($caller){
				Logger::debug("callout {$caller->call_id}");
				break;
			}
		}
		if(!$caller){
			// 404
			return;
		}
		
		// 创建路由记录
		$this->router->add_route($callee, $caller);
	}
	
	private $time = 0;

	function proc_send(){
		$old_time = $this->time;
		$this->time = microtime(1);
		$timespan = max(0, $this->time - $old_time);

		foreach($this->modules as $mi){
			$module = $mi['module'];
			$msgs = $module->outgoing($time, $timespan);
			foreach($msgs as $msg){
				// TODO: 对于模块消息，不通过 socket 发送
				$this->link->send($msg);
			}
		}
	}
}
