<?php
class SipRouter
{
	public $domain;
	// 经过预处理的路由表
	private $table = array(
		//array(in_from, in_to, out_from, out_to),
		// TESTING: 收到 *->2005 时，转换成 2005->1001
		// TESTING: 收到 *->221 时，转换成 221->231
		array('*', '2005', '2005', '1001'),
		array('*', '221', '221', '231'),
	);
	
	/*
	输入一条 INVITE 消息，根据路由表配置，然后返回新的 INVITE 消息。新的消息
	如果能直接送达，则直接返回。否则修改 uri, from, to，但保持 contact 不变。
	*/
	function route($msg){
		$ret = new SipMessage();
		$ret->method = 'INVITE';
		$ret->uri = $msg->uri;
		$ret->from = clone $msg->from;
		$ret->to = clone $msg->to;
		$ret->contact = clone $msg->contact;
		$ret->call_id = SIP::new_call_id();
		$ret->from->set_tag(SIP::new_tag());
		$ret->branch = SIP::new_branch();
		$ret->cseq = SIP::new_cseq();
		
		// 路由表处理
		$from = $msg->from->username;
		$to = $msg->to->username;
		foreach($this->table as $item){
			if($this->match_route($from, $to, $item)){
				list($in_from, $in_to, $out_from, $out_to) = $item;
				Logger::debug("rewrite {$from}->{$to} => {$out_from}->{$out_to}");
				$ret->uri = "sip:{$out_to}@{$this->domain}";
				$ret->from = new SipContact($out_from, $this->domain);
				$ret->to = new SipContact($out_to, $this->domain);
				break;
			}
		}
		
		return $ret;
	}
	
	private function match_route($from, $to, $route_item){
		list($in_from, $in_to, $out_from, $out_to) = $route_item;
		if(!$this->match_address($from, $in_from)){
			return false;
		}
		if(!$this->match_address($to, $in_to)){
			return false;
		}
		return true;
	}
	
	private function match_address($in, $conf){
		if($conf === '*'){ // all
			return true;
		}
		$ps = explode(',', $conf); // or
		if(count($ps) > 1){
			return in_array($in, $ps, true);
		}
		
		$ps = explode('-', $conf); // range
		if(count($ps) == 2){
			$min = intval($ps[0]);
			$max = intval($ps[0]);
			$in = intval($in);
			return ($min<=$in) && ($in<=$max);
		}
		
		return $in === $conf; // equals
	}
}
