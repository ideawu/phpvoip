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
		return $ret;
	}
	
	function send($msg){
		$buf = $msg->encode();
		$this->udp->sendto($buf, $msg->dst_ip, $msg->dst_port);
		Logger::debug("send " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason) . " to {$msg->dst_ip}:{$msg->dst_port}");
		#echo '  > ' . str_replace("\n", "\n  > ", trim($buf)) . "\n\n";
	}
	
	function recv(){
		$buf = $this->udp->recvfrom($ip, $port);
		// 模拟丢包
		// static $i = 0;
		// if($i++%2 == 0){
		// 	echo "drop OK for BYE\n";
		// 	return;
		// }
			
		$msg = new SipMessage();
		$msg->src_ip = $ip;
		$msg->src_port = $port;
		if($msg->decode($buf) <= 0){
			Logger::error("bad SIP packet");
			return;
		}
		Logger::debug("recv " . ($msg->is_request()? $msg->method.' '.$msg->uri : $msg->code.' '.$msg->reason) . " from {$msg->src_ip}:{$msg->src_port}");
		#echo '  < ' . str_replace("\n", "\n  < ", trim($buf)) . "\n\n";
		return $msg;
	}
}
