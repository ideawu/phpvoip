<?php
include_once(dirname(__FILE__) . '/net/UdpLink.php');
include_once(dirname(__FILE__) . '/net/SipLink.php');

include_once(dirname(__FILE__) . '/SipEngine.php');
include_once(dirname(__FILE__) . '/SipMessage.php');
include_once(dirname(__FILE__) . '/SipContact.php');
include_once(dirname(__FILE__) . '/SipModule.php');
include_once(dirname(__FILE__) . '/SipSession.php');
include_once(dirname(__FILE__) . '/SipRouter.php');

include_once(dirname(__FILE__) . '/session/SipTransaction.php');
include_once(dirname(__FILE__) . '/session/RegisterSession.php');
include_once(dirname(__FILE__) . '/session/RegistrarSession.php');
include_once(dirname(__FILE__) . '/session/CalleeSession.php');
include_once(dirname(__FILE__) . '/session/CallerSession.php');
include_once(dirname(__FILE__) . '/session/LocalCaller.php');
include_once(dirname(__FILE__) . '/session/LocalCallee.php');
include_once(dirname(__FILE__) . '/session/NoopCallerSession.php');

include_once(dirname(__FILE__) . '/module/SipDialog.php');
include_once(dirname(__FILE__) . '/module/SipMixer.php');
include_once(dirname(__FILE__) . '/module/SipChannel.php');
include_once(dirname(__FILE__) . '/module/SipRegistrar.php');
include_once(dirname(__FILE__) . '/module/SipRobotModule.php');


class SIP
{
	// role
	const NOOP        = 0;
	const REGISTER    = 1;
	const REGISTRAR   = 2;
	const CALLER      = 3;
	const CALLEE      = 4;
	const CLOSER      = 5;

	// state
	const NONE       = 0;
	const CLOSED     = -1;
	const CLOSING    = 1;
	const TRYING     = 100;
	const RINGING    = 180;
	const COMPLETING = 199;
	const COMPLETED  = 200;
	const AUTHING    = 401;

	private static $call_id_prefix = 'c';
	private static $tag_prefix = 't';
	private static $branch_prefix = 'z9hG4bK_';
	
	static function state_text($state){
		if($state == self::NONE){
			return 'NONE';
		}else if($state == self::CLOSING){
			return 'CLOSING';
		}else if($state == self::CLOSED){
			return 'CLOSED';
		}else if($state == self::TRYING){
			return 'TRYING';
		}else if($state == self::RINGING){
			return 'RINGING';
		}else if($state == self::COMPLETING){
			return 'COMPLETING';
		}else if($state == self::COMPLETED){
			return 'COMPLETED';
		}else if($state == self::AUTHING){
			return 'AUTHING';
		}else{
			return 'UNKNOWN';
		}
	}

	static function token(){
		static $seq = -1;
		if($seq == -1){
			$seq = mt_rand();
		}
		$seq = ($seq + 1) % 1000;
		$num = sprintf('%03d', $seq);
		$time = substr(sprintf('%.3f', microtime(1)), 2);
		return $time.'_'.$num;
	}
	
	static function long_token(){
		return md5(mt_rand() . microtime(1));
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

	static function new_cseq(){
		return mt_rand(100, 1000);
	}
	
	static function encode_www_auth($auth){
		$scheme = $auth['scheme'];
		$arr = array();
		foreach($auth as $k=>$v){
			if($k != 'scheme'){
				$arr[] = "$k=\"$v\"";
			}
		}
		return "$scheme " . join(', ', $arr);
	}

	static function decode_www_auth($str){
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
	
	static function www_auth($username, $password, $uri, $method, $auth){
		$scheme = $auth['scheme'];
		$realm = $auth['realm'];
		$nonce = $auth['nonce'];
		if($scheme == 'Digest'){
			$ha1 = md5($username .':'. $realm .':'. $password);
		    $ha2 = md5($method .':'. $uri);
			if(isset($auth['qpop']) && $auth['qpop'] == 'auth'){
				//MD5(HA1:nonce:nonceCount:cnonce:qop:HA2)
			}else{
				$res = md5($ha1 .':'. $nonce .':'. $ha2);
			}
			$auth['uri'] = $uri;
			$auth['username'] = $username;
			$auth['response'] = $res;
			return $auth;
		}
		return array();
	}
	
	static function guess_local_ip($remote_ip){
		$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if(!@socket_connect($sock, $remote_ip, 8888)){
			socket_close($sock);
			return false;
		}
		if(!socket_getsockname($sock, $ip, $port)){
			socket_close($sock);
			return false;
		}
		socket_close($sock);
		return $ip;
	}
}
