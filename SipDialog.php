<?php
/*
Dialog 只记录 session 之间的关系，不负责创建和销毁 session。
*/
class SipDialog
{
	public $callee;
	public $caller;
	// 在 caller 连接之前，callee 的状态一直是 TRING
	// 对于终端应用来说，它的 caller 创建后立即连接完毕
	
	function del_session($sess){
		if($sess === $this->callee){
			$this->callee = null;
			if($this->caller){
				$this->caller->closing();
			}
		}else if($sess === $this->caller){
			$this->caller = null;
			if($this->callee){
				$this->callee->closing();
			}
		}
	}
}