<?php
abstract class SipModule
{
	protected $sessions = array();

 	// 如果 msg 已经被处理，返回 true
	function incoming($msg){
	}
	
	protected function del_session($sess){
		foreach($this->sessions as $index=>$tmp){
			if($tmp === $sess){
				unset($this->sessions[$index]);
			}
		}
	}
	
	// 如果有需要发送出去的 msg，返回 msg 列表。
	function outgoing($time, $timespan){
		$ret = array();
		foreach($this->sessions as $index=>$sess){
			$sess->timers[0] -= $timespan;
			if($sess->timers[0] <= 0){
				array_shift($sess->timers);
				if(count($sess->timers[0]) == 0){
					if($sess->state == SIP::CLOSING){
						//
					}else{
						// transaction timeout
						Logger::debug("transaction timeout");
					}
					$sess->state = SIP::CLOSED;
				}else{
					// re/transmission timeout
					$msg = $sess->to_send();
					if($msg){
						$ret[] = $msg;
					}
				}
			}

			if($sess->state == SIP::CLOSED){
				$this->del_session($sess);
			}
		}
		return $ret;
	}

}
