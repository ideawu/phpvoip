<?php
class SipContact
{
	public $dispname;
	public $scheme = 'sip';
	public $username;
	// public $password;
	public $domain; // 可包含 port
	public $parameters = array();
	
	private $tag;
	
	function __construct($username='', $domain=''){
		$this->username = $username;
		$this->domain = $domain;
	}
	
	static function from_str($str){
		$ret = new SipContact();
		$ret->decode($str);
		return $ret;
	}
	
	function equals($dst){
		return ($this->username === $dst->username) && ($this->tag == $dst->tag);
	}
	
	function address(){
		return "{$this->username}@{$this->domain}";
	}
	
	function set_tag($tag){
		$this->set_parameter('tag', $tag);
	}
	
	function tag(){
		return $this->tag;
	}
	
	function set_parameter($k, $v){
		$this->parameters[$k] = $v;
		if($k == 'tag'){
			$this->tag = $v;
		}
	}
	
	function encode(){
		$ret = '';
		if($this->dispname){
			$ret .= "\"{$this->dispname}\" ";
		}
		$ret .= "<{$this->scheme}:{$this->username}";
		if(strlen($this->password) > 0){
			$ret .= ":{$this->password}";
		}
		$ret .= "@{$this->domain}>";
		foreach($this->parameters as $k=>$v){
			$ret .= ";{$k}={$v}";
		}
		return $ret;
	}
	
	function decode($str){
		// TODO: <> 里可能有分号
		$ps = explode(';', $str);
		$this->parse_address($ps[0]);
		
		for($i=1; $i<count($ps); $i++){
			$p = $ps[$i];
			$kv = explode('=', $p);
			$k = $kv[0];
			$v = isset($kv[1])? $kv[1] : '';
			$this->set_parameter($k, $v);
		}
	}

	private function parse_address($str){
		$pos = strpos($str, '<');
		if(count($pos) !== false){
			$this->dispname = trim(substr($str, 0, $pos), '" ');
			$uri = substr($str, $pos);
		}else{
			$uri = $str;
		}
		$uri = trim($uri, '<>');
		
		$ps = preg_split('/[@:]/', $uri);
		if(count($ps) == 3){
			$this->scheme = $ps[0];
			$this->username = $ps[1];
			$this->domain = $ps[2];
		}else if(count($ps) == 4){
			$this->scheme = $ps[0];
			$this->username = $ps[1];
			$this->password = $ps[1];
			$this->domain = $ps[2];
		}
	}
}
