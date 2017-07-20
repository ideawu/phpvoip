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
Caller 是否发出 ACK，取决于 Callee，Caller 收到 OK 后就立即回复 ACK。
*/

class SipDialog
{
	// 在 caller 连接之前，callee 的状态一直是 TRYING
	// 对于终端应用来说，它的 caller 创建后立即连接完毕
	public $callee;
	public $caller;
	
	public $sessions = array();
	
	function __construct($sess1, $sess2){
		$this->callee = $sess1;
		$this->caller = $sess2;
		$this->add_session($sess1);
		$this->add_session($sess2);
		$this->caller->local_sdp = $this->callee->remote_sdp;
	}
	
	function sess_callback($sess){
		$caller = $this->caller;
		$callee = $this->callee;
		
		if($sess === $caller){
			if($sess->is_state(SIP::RINGING)){
				Logger::debug("caller ringing, callee ringing");
				$callee->ringing();
			}
			if($sess->is_state(SIP::COMPLETING)){
				Logger::debug("caller completing, callee completing");
				$callee->local_sdp = $caller->remote_sdp;
				$callee->completing();
			}
			if($sess->is_state(SIP::COMPLETED)){
				Logger::debug("caller " . $sess->state_text());
			}
			if($sess->is_state(SIP::CLOSING)){
				if($callee && !$callee->is_state(SIP::CLOSING)){
					Logger::debug("caller " . $sess->state_text() . ", closing callee");
					$callee->close();
				}
			}
			if($sess->is_state(SIP::CLOSED)){
				$this->del_session($caller);
				if($callee && !$callee->is_state(SIP::CLOSING)){
					Logger::debug("caller " . $sess->state_text() . ", closing callee");
					$callee->close();
				}else{
					Logger::debug("caller " . $sess->state_text());
				}
			}
		}
		
		if($sess === $callee){
			if($sess->is_state(SIP::COMPLETED)){
				Logger::debug("callee " . $sess->state_text());
			}
			if($sess->is_state(SIP::CLOSING)){
				if($caller && !$caller->is_state(SIP::CLOSING)){
					Logger::debug("callee " . $sess->state_text() . ", closing caller");
					$caller->close();
				}
			}
			if($sess->is_state(SIP::CLOSED)){
				$this->del_session($callee);
				if($caller && !$caller->is_state(SIP::CLOSING)){
					Logger::debug("callee " . $sess->state_text() . ", closing caller");
					$caller->close();
				}else{
					Logger::debug("callee " . $sess->state_text());
				}
			}
		}
	}
	
	function add_session($sess){
		$this->sessions[$sess->id] = $sess;
		$sess->set_callback(array($this, 'sess_callback'));
	}
	
	function del_session($sess){
		unset($this->sessions[$sess->id]);
		if($sess === $this->callee){
			$this->callee = null;
		}else if($sess === $this->caller){
			$this->caller = null;
		}
	}
}
