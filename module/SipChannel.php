<?php
/*
SipChannel 将向其它 Server 注册自己。可以理解为一条外线，类似 IP 网络的 WAN 接口。
但是要注意，SipChannel 并不是数据收发的逻辑模块，除了向 Server 注册外，它并不进行
路由数据的处理。路由数据的收发将由引擎和 SipRouter 模块进行处理。

这里需要特别说明面向对象分析和代码编程实现上的容易混淆的地方。面向对象的分析方法往往将
对象模拟成有自主能动性的单元，但编程实现上，对象并没有主动执行逻辑的能力，它只是一段段
被执行的代码，是一个被动的单元，不是主动单元。

当我们在分析时说“通过 SipChannel 将数据发送出去”，但编程实现上，数据的发送过程并不
会涉及到 SipChannel 的代码调用，也就是并不是真正意义上的”通过 SipChannel“发送。如果
真要实现这样的逻辑，数据发送时应该调用 SipChannel 的某个方法，例如 send()。
*/
class SipChannel extends SipModule
{
	private $sess;
	private $username;
	private $password;
	private $remote_ip;
	private $remote_port;
	
	function __construct($username, $password, $remote_ip, $remote_port){
		$this->username = $username;
		$this->password = $password;
		$this->remote_ip = $remote_ip;
		$this->remote_port = $remote_port;
	}
	
	function init(){
		parent::init();
		
		$local_ip = $this->engine->local_ip;
		$local_port = $this->engine->local_port;
		if($local_ip === '0.0.0.0'){
			$local_ip = SIP::guess_local_ip($this->remote_ip);
		}
		$sess = new SipRegisterSession($this->username, $this->password, $this->remote_ip, $this->remote_port);
		$sess->local_ip = $local_ip;
		$sess->local_port = $local_port;
		$this->add_session($sess);
		$this->sess = $sess;
		// set_callback
	}

	function callin($msg){
		$sess = $this->sess;
		// if not register return null;
		
		if($msg->src_ip !== $sess->remote_ip || $msg->src_port !== $sess->remote_port){
			continue;
		}
		$ps1 = explode('@', $msg->to);
		$ps2 = explode('@', $sess->local);
		// 只验证 username
		if($ps1[0] !== $ps2[0]){
			Logger::debug("{$msg->to} {$sess->local}");
			continue;
		}
		// TODO: 验证 uri

		$call = new SipCalleeSession($msg);
		$call->local_ip = $sess->local_ip;
		$call->local_port = $sess->local_port;
		$call->remote_ip = $sess->remote_ip;
		$call->remote_port = $sess->remote_port;
		return $call;
	}

	function callout($msg){
		return null;
	}
}
