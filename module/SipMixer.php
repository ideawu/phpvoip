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
