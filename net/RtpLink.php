<?php
class RtpLink
{
	private $udp;
	public $sock;
	public $local_ip;
	public $local_port;
	
	static function listen($ip='127.0.0.1', $port=0){
		$ret = new RtpLink();
		$link = UdpLink::listen($ip, $port);
		$ret->udp = $link;
		$ret->sock = $link->sock;
		$ret->local_ip = $link->local_ip;
		$ret->local_port = $link->local_port;
		return $ret;
	}
	
	function set_nonblock(){
		$this->udp->set_nonblock();
	}
	
	function set_block(){
		$this->udp->set_block();
	}
	
	function send($msg){
		Logger::debug("send " . $msg->brief() . " -> '{$msg->dst_ip}:{$msg->dst_port}'");
		$buf = $msg->encode();
		$this->udp->sendto($buf, $msg->dst_ip, $msg->dst_port);
	}
	
	function recv(){
		$buf = $this->udp->recvfrom($ip, $port);
		if(!$buf){
			return null;
		}
		$buf = ltrim($buf);
		if(strlen($buf) == 0){
			return null;
		}
			
		$msg = new RtpMessage();
		$msg->src_ip = $ip;
		$msg->src_port = $port;
		
		if($msg->decode($buf) <= 0){
			Logger::error("bad RTP packet: " . json_encode($buf));
			return;
		}
		Logger::debug("recv " . $msg->brief() . " <- '{$msg->src_ip}:{$msg->src_port}'");
		
		return $msg;
	}
}
