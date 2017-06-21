<?php
class SipMessage
{
	public $ip;
	public $port;
	public $username;
	
	public $method;
	public $uri;
	
	public $code;
	public $reason;

	public $via = null;
	public $contact = null;
	
	public $call_id;
	public $branch;
	public $cseq;
	public $from;
	public $to;
	public $from_tag;
	public $to_tag;
	public $content_length = 0;
	
	// 未详细解析的 header, 每个元素是 pair [key, val]
	public $headers = array();
	public $body = '';
	
	function is_request(){
		return (bool)$this->method;
	}
	
	function is_response(){
		return !$this->is_request();
	}
	
	function encode(){
		$headers = array();
		if($this->method){
			$headers[] = "{$this->method} {$this->uri} SIP/2.0";
		}else{
			$headers[] = "SIP/2.0 {$this->code} {$this->reason}";
		}
		$tag = $this->from_tag? ";tag={$this->from_tag}" : '';
		$headers[] = "From: {$this->from}{$tag}";
		$tag = $this->to_tag? ";tag={$this->to_tag}" : '';
		$headers[] = "To: {$this->from}{$tag}";
		$headers[] = "Call-ID: {$this->call_id}";
		$headers[] = "CSeq: {$this->cseq} {$this->method}";
		
		if($this->via){
			$headers[] = "Via: {$this->via}";
		}else{
			$headers[] = "Via: SIP/2.0/UDP {$this->ip}:{$this->port};rport;branch={$this->branch}";
		}
		if($this->contact){
			$headers[] = "Contact: {$this->contact}";
		}else{
			$headers[] = "Contact: <sip:{$this->username}@{$this->ip}:{$this->port}>";
		}
		
		foreach($this->headers as $v){
			$headers[] = "{$v[0]}: {$v[1]}";
		}
		
		$this->content_length = strlen($this->body);
		$headers[] = "User-Agent: phpvoip";
		$headers[] = "Content-Length: " . $this->content_length;
		
		$ret = join("\r\n", $headers) . "\r\n\r\n{$this->body}";
		return $ret;
	}
	
	// 返回解析的字节数，支持流式解析
	function decode($buf){
		$sp_len = 4;
		$pos = strpos($buf, "\r\n\r\n");
		if($pos === false){
			$sp_len = 2;
			$pos = strpos($buf, "\n\n");
			if($pos === false){
				return 0;
			}
		}
		$header = substr($buf, 0, $pos);
		$pos += $sp_len;
		
		$lines = explode("\n", $header);
		for($i=0; $i<count($lines); $i++){
			if($i == 0){
				$ps = explode(' ', trim($lines[0]), 3);
				if(count($ps) != 3){
					return false;
				}
				if($ps[0] == 'SIP/2.0'){
					// is response
					$this->code = intval($ps[1]);
					$this->reason = $ps[2];
				}else{
					$this->method = $ps[0];
					$this->uri = $ps[1];
				}
				continue;
			}
			
			$line = $lines[$i];
			// multi-line value
			for($j=$i+1; $j<count($lines); $j++){
				$next = $lines[$j];
				if($next[0] == ' ' || $next[0] == "\t"){
					$i = $j;
					$next = trim($next);
					$line .= ' ' . $next;
				}else{
					break;
				}
			}
			$this->parse_header_line($line);
		}
		
		$this->body = substr($buf, $pos, $this->content_length);
		return $pos + $this->content_length;
	}
	
	private function parse_header_line($line){
		$ps = explode(':', $line, 2);
		if(count($ps) != 2){
			// bad header line
			return;
		}
		$key = trim($ps[0]);
		$val = trim($ps[1]);
		// TODO: case insensitive
		if($key == 'From'){
			$ret = SIP::parse_uri($val);
			$this->from = $ret['contact'];
			if(isset($ret['tags']['tag'])){
				$this->from_tag = $ret['tags']['tag'];
			}		
		}else if($key == 'To'){
			$ret = SIP::parse_uri($val);
			$this->to = $ret['contact'];
			if(isset($ret['tags']['tag'])){
				$this->to_tag = $ret['tags']['tag'];
			}		
		}else if($key == 'Call-ID'){
			$this->call_id = $val;
		}else if($key == 'CSeq'){
			$this->cseq = intval($val);
		}else if($key == 'Via'){
			$ret = $this->parse_via($val);
			$this->ip = $ret['ip'];
			$this->port = $ret['port'];
			if(isset($ret['tags']['branch'])){
				$this->branch = $ret['tags']['branch'];
			}
			$this->via = $val;
		}else if($key == 'Contact'){
			// TODO: support contact list
			$ps = explode(',', $val);
			$this->contact = trim($ps[0]);
		}else if($key == 'Content-Length'){
			$this->content_length = intval($val);
		}else{
			$this->headers[] = array($key, $val);
		}
	}
	
	private function parse_via($str){
		$ret = array(
			'ip' => '',
			'port' => 0,
			'tags' => array(),
		);
		
		$attrs = explode(';', $str);
		$p_a = explode(' ', $attrs[0], 2);
		if(isset($p_a[1])){
			$i_p = explode(':', $p_a[1]);
			$ret['ip'] = $i_p[0];
			$ret['port'] = isset($i_p[1])? intval($i_p[1]) : 0;
		}
		
		for($i=1; $i<count($attrs); $i++){
			$p = $attrs[$i];
			$kv = explode('=', $p);
			$ret['tags'][$kv[0]] = isset($kv[1])? $kv[1] : null;
		}
		
		if(isset($ret['tags']['rport']) && $ret['tags']['rport']){
			$ret['port'] = intval($ret['tags']['rport']);
		}
		
		return $ret;
	}
}
