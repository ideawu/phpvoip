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
		$this->add_module($this->router, INT_MAX); // 路由模块必须放在所有模块的前面
		
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
		$offset = count($this->modules);
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
	
		$ret = @socket_select($read, $write, $except, 0, 20*1000);
		// 如下代码实现引擎慢速响应
		$pause = 0.3;
		static $stime = 0;
		$ts = microtime(1) - $stime;
		$sleep = min($pause, max(0, $pause - $ts));
		usleep($sleep * 1000 * 1000);
		#Logger::debug(sprintf("sleep %.3f %.3f %.3f", $sleep, $ts, $stime));
		$stime = microtime(1);
		
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
			Logger::debug("403 Forbidden");
			// TODO: send error response
			return;
		}
		
		foreach($this->modules as $mi){
			$module = $mi['module'];
			$caller = $module->callout($msg);
			if($caller){
				Logger::debug("callout {$caller->call_id}");
				break;
			}
		}
		if(!$caller){
			Logger::debug("404 Not Found");
			return;
		}
		
		// 创建路由记录
		$this->router->add_route($callee, $caller);
		return true;
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
