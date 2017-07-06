<?php
class SipRegistrarSession extends SipSession
{
	public $username;
	public $password;
	public $remote_branch;

	function __construct(){
		parent::__construct();
		$this->role = SIP::REGISTRAR;
		$this->set_state(SIP::NONE);
	}
	
	function init(){
		$this->set_state(SIP::TRYING);
		$new = $this->new_response($this->remote_branch);
		$new->trying();
	}

	function new_response($branch){
		// 先清除当前的所有 transaction
		if(!$this->is_state(SIP::COMPLETED)){
			$this->transactions = array();
		}
		return parent::new_response($branch);
	}
	
	function incoming($msg, $trans){
	}
	
	function outgoing($trans){
		if($trans->state == SIP::TRYING){
			if(strlen($this->username) > 0 || strlen($this->password) > 0){
				$trans->state = SIP::AUTHING;
			}

			$msg = new SipMessage();
			$msg->code = 100;
			$msg->cseq_method = 'REGISTER';
			return $msg;
		}else if($trans->state == SIP::AUTHING){
			$msg = new SipMessage();
			$msg->code = 401;
			$msg->cseq_method = 'REGISTER';
			
			
			return $msg;
		}
	}
}
