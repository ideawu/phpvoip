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
include_once(dirname(__FILE__) . '/session/SipRegisterSession.php');
include_once(dirname(__FILE__) . '/session/SipRegistrarSession.php');
include_once(dirname(__FILE__) . '/session/SipBaseCallSession.php');
include_once(dirname(__FILE__) . '/session/SipCallerSession.php');
include_once(dirname(__FILE__) . '/session/SipCalleeSession.php');
include_once(dirname(__FILE__) . '/session/SipNoopCallerSession.php');

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

	// state
	const NONE        = 0;
	const CLOSED      = -1;
	const CLOSING     = 1;
	const CALLING     = 5;
	const TRYING      = 100;
	const RINGING     = 180;
	const COMPLETING  = 199; // 
	const COMPLETED   = 200;
	
	const KEEPALIVE   = 19; //
	const FIN_WAIT    = 20;  // 主动关闭
	const CLOSE_WAIT  = 21;  // 被动关闭
	const AUTHING     = 401;

	private static $call_id_prefix = 'c';
	private static $tag_prefix = 't';
	private static $branch_prefix = 'z9hG4bK_';
	
	static function state_text($state){
		if($state == self::TRYING){
			return 'TRYING';
		}else if($state == self::RINGING){
			return 'RINGING';
		}else if($state == self::COMPLETED){
			return 'COMPLETED';
		}else if($state == self::AUTHING){
			return 'AUTHING';
		}else if($state == self::CLOSING){
			return 'CLOSED';
		}else if($state == self::CLOSED){
			return 'CLOSING';
		}else if($state == self::FIN_WAIT){
			return 'FIN_WAIT';
		}else if($state == self::CLOSE_WAIT){
			return 'CLOSE_WAIT';
		}else if($state == self::CALLING){
			return 'CALLING';
		}else if($state == self::KEEPALIVE){
			return 'KEEPALIVE';
		}else if($state == self::COMPLETING){
			return 'COMPLETING';
		}else{
			return 'NONE';
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
