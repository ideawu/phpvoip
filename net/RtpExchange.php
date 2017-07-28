<?php
class RtpExchange
{
	public $link;

	static function listen(){
		$local_ip = '0.0.0.0';
		$local_port = 0;
		
		$ret = new RtpExchange();
		$ret->link = RtpLink::listen($local_ip, $local_port);
		$ret->link->set_nonblock();
		return $ret;
	}
	
	function sdp($remote_ip){
		$ip = $this->link->local_ip;
		if($ip === '0.0.0.0'){
			$ip = SIP::guess_local_ip($remote_ip);
		}
		$port = $this->link->local_port;
		$ret = <<<TEXT
v=0
o=phpvoip 1499684812 1499684812 IN IP4 {$ip}
s=SIP Call
c=IN IP4 {$ip}
t=0 0
m=audio {$port} RTP/AVP 109 0 8 101
a=rtpmap:109 iLBC/8000
a=fmtp:109 mode=30
a=rtpmap:0 PCMU/8000
a=rtpmap:8 PCMA/8000
a=rtpmap:101 telephone-event/8000
a=ptime:30
TEXT;
		return $ret;
	}
	
	function create_room($id){
		
	}
	
	function add_member($room_id, $ip, $port){
		if(!$ip || !$port){
			return;
		}
		Logger::debug("$room_id, $ip, $port");
		
	}
	
	function del_member($room_id, $ip, $port){
		if(!$ip || !$port){
			return;
		}
		
	}
	
	function recv(){
		while(1){
			$msg = $this->link->recv();
			if(!$msg){
				break;
			}
			Logger::debug("");
		}
	}
}
