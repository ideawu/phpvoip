<?php
class SipRouter extends SipModule
{
	// 记录 session 间的关系。
	protected $dialogs = array();
	
	function callin($msg){
		return null;
	}
	
	function callout($msg){
		return null;
	}
	
	function add_route($callee, $caller){
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
		// TODO: 找出所属的 dialog，做后续处理
	}
}
