<?php
class SipRegistrar extends SipModule
{
	function incoming($msg){
		$ret = parent::incoming($msg);
		if($ret){
			return $ret;
		}
		
		// 新的 REGISTER
		if($msg->method === 'REGISTER'){
			
		}
	}
	
	function callin($msg){
	}
	
	function callout($msg){
	}
}
