<?php
/*
Dialog 只记录 session 之间的关系，不负责创建和销毁 session。
*/
/*
   User A        Proxy 1         User B
     |              |              |
     |   INVITE     |              |
     |------------->|              |
     |              |   INVITE     |
     |    (100)     |------------->|
     |<-------------|    (100)     |
     |              |<-------------|
     |              |              |
     |              |    180       |
     |     180      |<-------------|
     |<-------------|              |
     |              |    OK        |
     |     OK       |<-------------|
     |<-------------|              |
     |     ACK      |              |
     |------------->|    ACK       |
     |              |------------->|
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
		$this->callee->ringing();
		$this->add_session($sess1);
		$this->add_session($sess2);
	}
	
	function add_session($sess){
		$this->sessions[] = $sess;
	}
	
	function del_session($sess){
		foreach($this->sessions as $index=>$tmp){
			if($tmp === $sess){
				unset($this->sessions[$index]);

				if($sess === $this->callee){
					$this->callee = null;
					if($this->caller){
						Logger::debug("del callee, close caller");
						$this->caller->close();
					}
				}else if($sess === $this->caller){
					$this->caller = null;
					if($this->callee){
						Logger::debug("del caller, close callee");
						$this->callee->close();
					}
				}
				break;
			}
		}
	}
	
	function complete_session($sess){
		foreach($this->sessions as $index=>$tmp){
			if($tmp === $sess){
				if($sess === $this->caller){
					if($this->callee){
						Logger::debug("caller completed, completing callee");
						$this->callee->completing();
					}
				}
				break;
			}
		}
	}
}
