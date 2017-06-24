<?php
include_once(dirname(__FILE__) . '/SipEngine.php');

include_once(dirname(__FILE__) . '/SipMessage.php');

include_once(dirname(__FILE__) . '/SipSession.php');
include_once(dirname(__FILE__) . '/SipRegistrarSession.php');
include_once(dirname(__FILE__) . '/SipRegisterSession.php');
include_once(dirname(__FILE__) . '/SipCallerSession.php');
include_once(dirname(__FILE__) . '/SipCalleeSession.php');

include_once(dirname(__FILE__) . '/SipModule.php');
include_once(dirname(__FILE__) . '/SipRegistrarModule.php');
include_once(dirname(__FILE__) . '/SipRegisterModule.php');


class SIP
{
	// role
	const REGISTER    = 1;
	const REGISTRAR   = 2;
	const CALLER      = 3;
	const CALLEE      = 4;

	// state
	const CLOSED      = 0;
	const CLOSING     = 1;

	const REGISTERING = 1001;
	const PROCEEDING  = 1002;
	const AUTHING     = 1003;
	const REG_REFRESH = 1004;
	const REGISTERED  = 200;

	const CALLING     = 3001;
	const ACCEPTING   = 3002;
	const ESTABLISHED = 201;

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

	static function parse_address($str){
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
	
	static function parse_www_auth($str){
		$ret = array(
			'scheme' => '',
		);
		$kvs = explode(',', $str);
		foreach($kvs as $n=>$kv){
			$kv = trim($kv);
			if($n == 0){
				$ps = explode(' ', $kv, 2);
				$ret['scheme'] = $ps[0];
				$kv = $ps[1];
			}
			list($k, $v) = explode('=', $kv);
			$v = trim($v, '"');
			$ret[$k] = $v;
		}
		return $ret;
	}
}
