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
		return false;
	}

	function callin($msg){
	}
	function callout($msg){
	}
}

