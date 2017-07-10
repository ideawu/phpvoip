<?php
class SipMessage
{
	public $src_ip;
	public $src_port;
	public $dst_ip;
	public $dst_port;
	
	public $uri;
	
	public $method;
	public $code;
	public $reason;
	
	public $call_id;
	public $branch;
	public $cseq;
	public $cseq_method;
	public $from; // Contact
	public $to; // Contact
	public $content_length = 0;

	public $via = null;
	public $contact = null; // 网络层地址, 但RFC中似乎并没有这样说？
	
	public $expires = null;
	public $auth;
	
	// 未详细解析的 header, 每个元素是 pair [key, val]
	public $headers = array();
	public $content = '';

	private static $code_reasons = array(
		100 => 'Trying',
		180 => 'Ringing',
		200 => 'OK',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		481 => 'Call/Transaction Does Not Exist',
		486 => 'Busy Here',
	);
	
	// 返回简洁描述
	function brief(){
		if($this->is_request()){
			$cmd = $this->method;
			$src = $this->from->address();
			$dst = $this->to->address();
		}else{
			$cmd = $this->code;
			if($this->code == 200){
				$cmd .= ' OK';
			}
			$src = $this->to->address();
			$dst = $this->from->address();
		}
		$ret = sprintf('%-8s %3d %s=>%s', $cmd, $this->cseq, $src, $dst);
		return $ret;
	}
	
	function is_request(){
		return $this->code == 0;
	}
	
	function is_response(){
		return !$this->is_request();
	}
	
	function add_header($key, $val){
		$this->headers[] = array($key, $val);
	}
	
	function get_header($key){
		foreach($this->headers as $v){
			if($key === $v[0]){
				return $v[1];
			}
		}
		return null;
	}
	
	function get_headers($key){
		$ret = array();
		foreach($this->headers as $v){
			if($key === $v[0]){
				$ret[] = $v[1];
			}
		}
		return $ret;
	}
	
	function encode(){
		if($this->code > 0 && strlen($this->reason) == 0){
			if(isset(self::$code_reasons[$this->code])){
				$this->reason = self::$code_reasons[$this->code];
			}
		}
		
		$headers = array();
		if($this->is_request()){
			$headers[] = "{$this->method} {$this->uri} SIP/2.0";
		}else{
			$headers[] = "SIP/2.0 {$this->code} {$this->reason}";
		}
		$headers[] = "From: " . $this->from->encode();
		$headers[] = "To: " . $this->to->encode();
		$headers[] = "Call-ID: {$this->call_id}";
		$headers[] = "CSeq: {$this->cseq} " . ($this->cseq_method? $this->cseq_method : $this->method);
		
		if($this->via){
			$headers[] = "Via: {$this->via}";
		}else{
			if($this->is_request()){
				$headers[] = "Via: SIP/2.0/UDP {$this->src_ip}:{$this->src_port};rport;branch={$this->branch}";
			}else{
				$headers[] = "Via: SIP/2.0/UDP {$this->dst_ip}:{$this->dst_port};rport;branch={$this->branch}";
				//;received={$this->src_ip}";
			}
		}
		if($this->contact){
			$headers[] = "Contact: " . $this->contact->encode();
		}

		foreach($this->headers as $v){
			$headers[] = "{$v[0]}: {$v[1]}";
		}
		
		$this->content_length = strlen($this->content);
		if($this->expires !== null){
			$headers[] = "Expires: {$this->expires}";
		}
		$headers[] = "User-Agent: phpvoip";
		$headers[] = "Content-Length: " . $this->content_length;
		
		$ret = join("\r\n", $headers) . "\r\n\r\n{$this->content}";
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
		
		$this->content = substr($buf, $pos, $this->content_length);
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
			$this->from = SipContact::from_str($val);
		}else if($key == 'To'){
			$this->to = SipContact::from_str($val);
		}else if($key == 'Contact'){
			// TODO: support contact list
			$ps = explode(',', $val);
			$this->contact = SipContact::from_str($ps[0]);
		}else if($key == 'Call-ID'){
			$this->call_id = $val;
		}else if($key == 'CSeq'){
			$ps = explode(' ', $val);
			$this->cseq = intval($ps[0]);
			$this->cseq_method = $ps[1];
		}else if($key == 'Via'){
			$ret = $this->parse_via($val);
			$this->via_ip = $ret['ip'];
			$this->via_port = $ret['port'];
			if(isset($ret['tags']['branch'])){
				$this->branch = $ret['tags']['branch'];
			}
			$this->via = $val;
		}else if($key == 'Expires'){
			$this->expires = intval($val);
		}else if($key == 'Content-Length'){
			$this->content_length = intval($val);
		}else if($key == 'WWW-Authenticate'){
			$this->auth = $val;
		}else if($key == 'Authorization'){
			$this->auth = $val;
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
