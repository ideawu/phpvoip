<?php
class SipConferenceModule extends SipModule
{
	function create_conference($in_sess, $out_sess){
		// TODO: 应该在 $out_sess 连接之后，才让 $in_sess 连接，
		// 在那之前，$in_sess 定期回复 Trying
		$this->add_session($in_sess);
		$this->add_session($out_sess);
	}
	
	function incoming($msg){
		parent::incoming($msg);
		
		foreach($this->sessions as $sess){
			if($msg->call_id === $sess->call_id && $msg->from_tag === $sess->from_tag && $msg->to_tag === $sess->to_tag){
				$sess->on_recv_msg($msg);
				return true;
			}
		}
		return false;
	}
}

