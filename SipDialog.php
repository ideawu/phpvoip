<?php
/*
Dialog 只记录 session 之间的关系，不负责创建和销毁 session。
*/
/*
                        Proxy
   User A         (Callee + Caller)        User B
     |               |         |              |
     |   INVITE      |         |              |
     |-------------->|         |              |
     |               |    ~    |   INVITE     |
     |    (100)      |         |------------->|
     |<--------------|         |    (100)     |
     |               |         |<-------------|
     |               |    ~    |              |
     |               |         |    180       |
     |     180       |         |<-------------|
     |<--------------|         |              |
     |               |    ~    |    OK        |
     |     OK        |         |<-------------|
     |<--------------|         |              |
     |     ACK       |         |              |
     |-------------->|    ~    |    ACK       |
     |               |         |------------->|
*/

class SipDialog
{
	// 在 caller 连接之前，callee 的状态一直是 TRYING
	// 对于终端应用来说，它的 caller 创建后立即连接完毕
	public $callee;
	public $caller;
	
	private $sessions = array();
	
	function __construct($sess1, $sess2){
		$this->callee = $sess1;
		$this->caller = $sess2;
		$this->add_session($sess1);
		$this->add_session($sess2);
	}
	
	function add_session($sess){
		$this->sessions[] = $sess;
		$sess->set_callback(array($this, 'sess_state_callback'));
	}
	
	function sess_state_callback($sess){
		Logger::debug("sess state = " . $sess->state());
		if($sess === $this->caller){
			if($sess->is_state(SIP::RINGING)){
				Logger::debug("caller ringing, callee ringing");
				$this->callee->ringing();
			}
			if($sess->is_state(SIP::COMPLETED)){
				Logger::debug("caller completed, completing callee");
				$this->callee->completing();
			}
		}
		if($sess->is_state(SIP::CLOSED)){
			if($sess === $this->callee){
				Logger::debug("callee closed");
				$this->del_session($this->callee);
				if($this->caller){
					Logger::debug("closing caller");
					$this->caller->close();
				}
			}else if($sess === $this->caller){
				Logger::debug("caller closed");
				$this->del_session($this->caller);
				if($this->callee){
					Logger::debug("closing callee");
					$this->callee->close();
				}
			}
		}
	}
	
	function del_session($sess){
		foreach($this->sessions as $index=>$tmp){
			if($tmp === $sess){
				unset($this->sessions[$index]);
				if($sess === $this->callee){
					$this->callee = null;
				}else if($sess === $this->caller){
					$this->caller = null;
				}
				break;
			}
		}
	}
}
