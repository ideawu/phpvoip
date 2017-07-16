<?php
class SipUri
{
	public $scheme = 'sip';
	public $username;
	public $password;
	public $domain; // 可包含 port
	public $parameters = array();

	function __construct($username='', $domain=''){
		$this->username = $username;
		$this->domain = $domain;
	}

	function equals($dst){
		return ($this->username === $dst->username) && ($this->domain == $dst->domain);
	}
	
	function get_parameter($k){
		return $this->parameters[$k];
	}
	
	function set_parameter($k, $v){
		$this->parameters[$k] = $v;
	}

	function del_parameter($k){
		unset($this->parameters[$k]);
	}

	function encode(){
		$ret = '';
		$ret .= "{$this->scheme}:";
		if($this->username){
			$ret .= "{$this->username}@";
		}
		$ret .= "{$this->domain}";
		foreach($this->parameters as $k=>$v){
			$ret .= ";{$k}";
			if(strlen($v)){
				$ret .= "={$v}";
			}
		}
		return $ret;
	}

	function decode($str){
		$ps = explode(';', $str);
		$addr = $ps[0];
		$this->parse_addr($addr);
		
		$ps = array_slice($ps, 1);
		foreach($ps as $p){
			$p = trim($p);
			if(strlen($p) == 0){
				continue;
			}
			$kv = explode('=', $p);
			$k = $kv[0];
			$v = isset($kv[1])? $kv[1] : '';
			$this->set_parameter($k, $v);
		}
	}

	private function parse_addr($str){
		$ps = explode(':', $str, 2);
		$this->scheme = $ps[0];
		if(count($ps) != 2){
			return;
		}
		
		$ps = explode('@', $ps[1]);
		if(count($ps) == 2){
			$ts = explode(':', $ps[0]);
			if(count($ts) == 1){
				$this->username = $ts[0];
				$this->password = null;
			}else{
				$this->username = $ts[0];
				$this->password = $ts[1];
			}
			$this->domain = $ps[1];
		}else{
			$this->username = null;
			$this->domain = $ps[0];
		}
	}
}
