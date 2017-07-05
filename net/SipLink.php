<?php
class SipLink
{
	private $udp;
	public $sock;
	public $local_ip;
	public $local_port;
	
	static function listen($ip='127.0.0.1', $port=0){
		$ret = new SipLink();
		$link = UdpLink::listen($ip, $port);
		$ret->udp = $link;
		$ret->sock = $link->sock;
		$ret->local_ip = $link->local_ip;
		$ret->local_port = $link->local_port;
		$ret->udp->set_nonblock();
		return $ret;
	}
	
	function send($msg){
		// 模拟丢包
		// static $i = 0;
		// if($i++%2 == 0){
		// 	echo "drop OK for BYE\n";
		// 	return null;
		// }
		if(!$msg->src_ip || $msg->src_ip === '0.0.0.0'){
			if($this->local_ip === '0.0.0.0'){
				$msg->src_ip = SIP::guess_local_ip($msg->dst_ip);
				Logger::info("Guest local ip {$msg->src_ip} to send to {$msg->dst_ip}");
			}else{
				$msg->src_ip = $this->local_ip;
			}
		}
		if(!$msg->src_port){
			$msg->src_port = $this->local_port;
		}
		
		$buf = $msg->encode();
		$this->udp->sendto($buf, $msg->dst_ip, $msg->dst_port);
		Logger::debug("send " . $msg->brief() . " to '{$msg->dst_ip}:{$msg->dst_port}'");
		#echo '  > ' . str_replace("\n", "\n  > ", trim($buf)) . "\n\n";
	}
	
	function recv(){
		$buf = $this->udp->recvfrom($ip, $port);
		if(!$buf){
			return null;
		}
		// 模拟丢包
		// static $i = 0;
		// if($i++%2 == 0){
		// 	echo "drop OK for BYE\n";
		// 	return null;
		// }
			
		$msg = new SipMessage();
		$msg->src_ip = $ip;
		$msg->src_port = $port;
		
		// TODO:
		if($this->local_ip === '0.0.0.0'){
			$msg->dst_ip = SIP::guess_local_ip($msg->src_ip);
			Logger::info("Guest local ip {$msg->dst_ip} for recvfrom {$msg->src_ip}");
		}else{
			$msg->dst_ip = $this->local_ip;
		}
		$msg->dst_port = $this->local_port;

		if($msg->decode($buf) <= 0){
			Logger::error("bad SIP packet");
			return;
		}
		Logger::debug("recv " . $msg->brief() . " from '{$msg->src_ip}:{$msg->src_port}'");
		#echo '  < ' . str_replace("\n", "\n  < ", trim($buf)) . "\n\n";
		return $msg;
	}
}
