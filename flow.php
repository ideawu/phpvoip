<?php
caller{
	proc1{
		$out_sess->send(INVITE);
		wait{
			case $sock.recv.OK:
				goto proc2;
			case $sock.recv.TRYING:
				noop;
			case UI.CANCEL:
				goto proc_cancel;
			case $timer.trigger:
				continue;
			case $timer.timeout:
				goto end;
		}
	}
	
	proc_cancel{
		$out_sess->send(CANCEL);
		wait{
		}
	}
	
	proc2{
		$out_sess->send(ACK);
		wait{
			case $sock.recv.BYE:
				goto proc3;
		}
	}

	proc3{
		$out_sess->send(OK);
		wait{
			case BYE:
				continue;
			case $timer.timeout:
				goto end;
		}
	}
	
	end{
		$out_sess->finalize();
	}
}

// 收到 INVITE 之后，才进入 callee 流程
callee{
	proc1{
		$in_sess->send(OK);
		wait{
			case $sock.recv.INVITE:
				continue;
			case $sock.recv.CANCEL:
				goto end;
			case $sock.recv.ACK:
				goto proc2;
			case UI.CANCEL:
				goto proc3;
			case $timer.trigger:
				continue;
			case $timer.timeout:
				goto end;
		}
	}
	
	proc2{
		$in_sess->established();
		wait{
			case UI.FINISH:
				goto proc4;
		}
	}
	
	proc3{
		$in_sess->send(CANCEL);
		wait{
			case $sock.recv.OK:
				goto end;
			case $timer.trigger:
				continue;
			case $timer.timeout:
				goto end;
		}
	}
	
	proc4{
		$in_sess->send(CANCEL);
		wait{
			case $sock.recv.OK:
				goto end;
			case $timer.trigger:
				continue;
			case $timer.timeout:
				goto end;
		}
	}

	end{
		$out_sess->finalize();
	}
}
