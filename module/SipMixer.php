<?php
class SipMixer extends SipModule
{
	// 记录 session 间的关系。mappings?
	private $dialogs = array();
	
	function callin($msg){
		return null;
	}
	
	function callout($msg){
		return null;
	}
	
	function add_dialog($callee, $caller){
		$dia = new SipDialog($callee, $caller);
		$this->dialogs[] = $dia;
		$this->add_session($callee);
		$this->add_session($caller);
		
		// P2P 直连
		// $caller->local_sdp = $callee->remote_sdp;
		// $callee->local_sdp = $caller->remote_sdp;
		// 服务器中转
		$exchange = $this->engine->exchange;
		$callee->local_sdp = $exchange->sdp($callee->remote_ip);
		$caller->local_sdp = $exchange->sdp($caller->remote_ip);
		$room_id = $callee->call_id;
		$exchange->create_room($room_id);
		$exchange->add_member($room_id, $callee->remote_ip, $callee->remote_port);
		$exchange->add_member($room_id, $caller->remote_ip, $caller->remote_port);
	}
	
	function add_session($sess){
		parent::add_session($sess);
	}
	
	function del_session($sess){
		parent::del_session($sess);
		foreach($this->dialogs as $index=>$dia){
			if(!$dia->sessions){
				Logger::debug("delete dialog");
				unset($this->dialogs[$index]);
				break;
			}
		}
	}
	
	function complete_session($sess){
		parent::complete_session($sess);
	}
}
