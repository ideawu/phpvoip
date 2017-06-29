<?php
/*
这是一个终端应用的例子。终端应用和远程终端类似，能处理 INVITE 等，但不涉及网络传输。
此例子收到 INVITE 后建议会话，并定期发送固定的语音。
常见的终端应用，如 IVR，录音。这些终端应用可作为模块，也可能被做成真正的远程终端。
*/
class SipRobotModule extends SipModule
{
	function callin($msg){
	}
	
	function callout($msg){
		$sess = new SipCalleeSession($sess);
		#$this->add_session($sess);

		$out = new SipCallerSession();
		// TODO:
		return $out;
	}
}
