<?php
class SipConferenceModule extends SipModule
{
	function create_conference($sess1, $sess2){
		$this->add_session($sess1);
		//$this->add_session($sess1);
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
