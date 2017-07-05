<?php
/*
这是一个终端应用的例子。终端应用和远程终端代理类似，在 callout() 中返回一个 Session。
常见的终端应用，如 IVR，录音。这些终端应用可作为模块，也可能被做成真正的远程终端。
*/
class SipRobotModule extends SipModule
{
	function callin($msg){
		return null;
	}
	
	function callout($msg){
		$sess = new SipNoopCallerSession();
		return $sess;
	}
}
