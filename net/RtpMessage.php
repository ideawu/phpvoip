<?php
class RtpMessage
{
	public $src_ip;
	public $src_port;
	public $dst_ip;
	public $dst_port;
	
	public $buf;

	function brief(){
		return strlen($this->buf) . ' byte(s)';
	}
	
	function encode(){
		return $this->buf;
	}
	
	function decode($buf){
		$this->buf = $buf;
		return strlen($this->buf);
	}
}
