<?php
class UdpLink
{
	public $sock = null;
	public $local_ip;
	public $local_port;

	static function listen($ip='127.0.0.1', $port=0){
		$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if(!socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1)){
			return null;
		}
		if(!socket_bind($sock, $ip, $port)){
			return null;
		}
		if(!socket_getsockname($sock, $ip, $port)){
			return null;
		}

		$ret = new UdpLink();
		$ret->sock = $sock;
		$ret->local_ip = $ip;
		$ret->local_port = $port;
		Logger::debug("listen at {$ip}:{$port}");
		return $ret;
	}
	
	function set_nonblock(){
		return socket_set_nonblock($this->sock);
	}

	// function connect($ip, $port){
	// 	$sock = $this->sock;
	// 	if(!@socket_connect($sock, $ip, $port)){
	// 		return false;
	// 	}
	//
	// 	$this->remote_ip = $ip;
	// 	$this->remote_port = $port;
	// 	Logger::debug("connect to {$ip}:{$port}");
	// 	return true;
	// }

	function close(){
		if($this->sock){
			@socket_shutdown($this->sock);
			socket_close($this->sock);
			$this->sock = null;
		}
	}
	
	function send($buf){
		$ret = @socket_write($this->sock, $buf);
		return $ret;
	}
	
	function recv(){
		$ret = @socket_read($this->sock, 8192);
		return $ret;
	}
	
	function sendto($buf, $ip, $port){
		$ret = @socket_sendto($this->sock, $buf, strlen($buf), 0, $ip, $port);
		return $ret;
	}
	
	function recvfrom(&$ip, &$port){
		$buf = null;
		$ret = @socket_recvfrom($this->sock, $buf, 8192, 0, $ip, $port);
		if(!$ret){
			return false;
		}
		return $buf;
	}
}
