<?php
include_once(dirname(__FILE__) . '/UdpLink.php');
include_once(dirname(__FILE__) . '/SipLink.php');

include_once(dirname(__FILE__) . '/SipEngine.php');
include_once(dirname(__FILE__) . '/SipModule.php');
include_once(dirname(__FILE__) . '/SipMessage.php');
include_once(dirname(__FILE__) . '/SipSession.php');
include_once(dirname(__FILE__) . '/SipTransaction.php');
include_once(dirname(__FILE__) . '/SipDialog.php');

include_once(dirname(__FILE__) . '/session/SipNullSession.php');
include_once(dirname(__FILE__) . '/session/SipRegisterSession.php');
// include_once(dirname(__FILE__) . '/session/SipRegistrarSession.php');
include_once(dirname(__FILE__) . '/session/SipBaseCallSession.php');
include_once(dirname(__FILE__) . '/session/SipCallerSession.php');
#include_once(dirname(__FILE__) . '/session/SipCalleeSession.php');

include_once(dirname(__FILE__) . '/module/SipRouter.php');
include_once(dirname(__FILE__) . '/module/SipRobotModule.php');
include_once(dirname(__FILE__) . '/module/SipRegistrarModule.php');
include_once(dirname(__FILE__) . '/module/SipRegisterModule.php');
include_once(dirname(__FILE__) . '/module/SipConferenceModule.php');


class SIP
{
	// role
	const NONE        = 0;
	const REGISTER    = 1;
	const REGISTRAR   = 2;
	const CALLER      = 3;
	const CALLEE      = 4;

	const KEEPALIVE   = 90;
	const TRYING      = 100;
	const RINGING     = 180;
	const COMPLETED   = 200;
	const COMPLETING  = 201;
	const AUTHING     = 401;
	const NOT_FOUND   = 404;

	// state
	const CLOSED      = 0;
	const FIN_WAIT    = 2; // 主动关闭
	const CLOSE_WAIT  = 3; // 被动关闭

	private static $call_id_prefix = 'c';
	private static $tag_prefix = 't';
	private static $branch_prefix = 'z9hG4bK_';

	static function token(){
		static $seq = -1;
		if($seq == -1){
			$seq = mt_rand();
		}
		$seq = ($seq + 1) % 1000;
		$num = sprintf('%03d', $seq);
		$time = substr(sprintf('%.3f', microtime(1)), 2);
		return $num .'_'. $time;
	}

	
	static function new_call_id(){
		return self::$call_id_prefix . self::token();
	}
	
	static function new_branch(){
		return self::$branch_prefix . self::token();
	}
	
	static function new_tag(){
		return self::$tag_prefix . self::token();
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

	// TODO: 解析得更细致
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
	
	static function guess_local_ip($remote_ip){
		$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if(!@socket_connect($sock, $remote_ip, 8888)){
			return false;
		}
		if(!socket_getsockname($sock, $ip, $port)){
			return false;
		}
		socket_close($sock);
		return $ip;
	}
}
