<?php
include_once(dirname(__FILE__) . '/SipAgent.php');
include_once(dirname(__FILE__) . '/SipSession.php');
include_once(dirname(__FILE__) . '/SipMessage.php');

class SIP
{
	const REGISTER    = 1;
	const REGISTRAR   = 2;
	const CALLER      = 3;
	const CALLEE      = 4;

	const ESTABLISHED = 200;

	const REGISTERING = 1001;
	const PROCEEDING  = 1002;
	const AUTHING     = 1003;

	const CALLING     = 3001;
	const ACCEPTING   = 3002;

	static function token(){
		$rand = substr(sprintf('%05d', mt_rand()), 0, 5);
		$time = sprintf('%.6f', microtime(1));
		return $rand . '_' . $time;
	}
	
	static function parse_contact($str){
		// $addr = substr($kv, 1, strlen($kv) - 2);
		// $ps = explode('@', $addr, 2);
		// if(count($ps) == 2){
		// 	$p_n = $ps[0]; // protocol:username
		// 	$h_p = $ps[1]; // host:port
		// 	$ps = explode(':', $p_n);
		// 	$ret['username'] = $ps[count($ps) - 1];
		// 	$ps = explode(':', $h_p);
		// 	$ret['host'] = $ps[0];
		// 	if(isset($ps[1])){
		// 		$ret['port'] = $ps[1];
		// 	}
		// }
	}

	static function parse_uri($str){
		$ret = array(
			'contact' => '',
			'tags' => array(),
		);
		
		$attrs = explode(';', $str);
		$ret['contact'] = $attrs[0];
		for($i=1; $i<count($attrs); $i++){
			$p = $attrs[$i];
			$kv = explode('=', $p);
			$ret['tags'][$kv[0]] = isset($kv[1])? $kv[1] : null;
		}
		return $ret;
	}
}
