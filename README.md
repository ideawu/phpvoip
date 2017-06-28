# phpvoip

SipEngine
	Module[]

Module:
	SipClient
		连接别的 Server
	SipRelay
		SIP 中继
	SipRegistrar

引擎从网络收到消息后
	找出处理模块，如果没有，则 404 或丢弃。

引擎从模块收到消息后
	找出处理模块，如果没有，通过网络发送出去。

模块判断消息来源
	中继：判断 src.ip:port
	外线：判断 call_id + from_tag + from + to + src.ip:port
	注册中心：判断 src.ip:port + from 是否与某一注册者相同
	终端：判断 src.ip:port + from
模块判断消息目的
	中继：判断 dst.ip:port
	外线：判断 dst.ip:port + from
	注册中心：判断 dst.ip:port + to 是否与某一注册者相同
	终端：判断 dst.ip:port + to
如来源和目的无误，且是 INVITE，模块创建 dialog


// 处理从网络或者其它模块收到的消息
Module.incoming($msg);

// 返回要发送的消息，由引擎发给其它模块或者网络
Module.outgoing();


