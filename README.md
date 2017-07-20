# phpvoip

模块可以承担两种角色，一是用户(UAC)在引擎内部的代理人，也即 UAS。二是自身作为 UAC，也即终端应用。

UAS 是 Registrar，或者 SipChannel 外线，或者是 SIP Trunk 中继，它们不发起会话，也不接受会话，只做原样转发。

UAC 也称终端应用，例如自动总台，座席分配器等。它们既可作为会话的接收者，也可以主动发起会话。


## 引擎处理 INVITE 的逻辑

引擎从网络收到 INVITE 后:

* 引擎通过 callin() 找到会话发起者的代理模块，代理模块返回 callee。
* 引擎调用路由模块执行路由查找，做地址转换或者不转换，替换 from 和/或 to。
* 引擎通过 callout() 找到会话的接收者代理模块，代理模块返回 caller。
* 引擎将管理由模块返回的 callee 和 caller 会话，这两个会话将脱离模块的控制。
* callee 会话与会话发起者进行通信，caller 会话与会话接收者进行通信。


## 模块处理 callin/callout INVITE

模块应该在 callin() 中返回一个 callee 会话，如果满足下列条件:

* 对于 SipRegistrar，检查 from 是否在自己处注册，且与注册信息相符。
* 对于 SipChannel，检查网络地址是否为自己的上级(server)。
* 对于终端应用，检查 from 是否为自己绑定的号码。

模块应该在 callout() 中返回一个 caller 会话，如果满足下列条件:

* 对于 SipRegistrar，检查 to 是否在自己处注册。
* 对于 SipChannel，检查 from 为自己绑定的号码。
* 对于终端应用，检查 to 是否为自己绑定的号码。


## 终端应用发起和接受会话

发起 INVITE 的流程(待定):

* 模块(mod_a)创建一条 INVITE 消息给引擎。
* 引擎收到 INVITE 后，执行与网络收到 INVITE 相同的流程。
* 模块在 callin() 方法返回一个 local callee 会话给引擎管理，同时，创建一个对应的 local caller 会话自己管理。
* 引擎接着执行后续的逻辑。

接受 INVITE 的流程:

* 模块在 callout() 方法中发现自己是会话接受者。
* 模块创建一个 local caller 会话返回给引擎管理，同时，创建一个对应的 local callee 会话自己管理。


## 路由模块工作流程

路由模块的 route() 方法传入一条 INVITE 消息，根据路由表配置做可能地址转换，或者不转换，返回新的 INVITE 消息。转换逻辑如下：

```
input:
	uri, from, to, contact
output:
	uri_new, from_new, to_new, contact
```

如果 INVITE 需要进行路由寻址，只修改 uri/from/to。

如果 INVITE 能直接送达，则 uri/from/to 保持不变。

无法如何，contact 一直保持不变。

#### 静态路由表示例

```
in_from   in_to   out_from    out_to
--------|-------|-----------|--------
*         2005    2005        1001
*         221     221         231
```

## 出局线路处理逻辑

经过路由模块处理后的 INVITE，将被交给指定的出局线路进行 callout() 处理。由于 from 可能经过转换，所以出局线路需要根据自己的类型，对 from 和 contact 进行再次必要的转换。

* 如果出局的线路是需要进行类似 NAT 转换的（使用统一的对外地址），修改 contact 为自身地址。
* 如果出局线路是对等中继线路，根据 contact 将被路由模块修改过的 from 恢复回原来的地址。

因为对等中继所绑定的地址是虚拟地址，所以，经过它出局的 INVITE，应该恢复 from。



# REGISTER

A=>B 注册

	> REGISTER (from.tag=at, to.tag=  , seq+0, branch0, contact=A, uri=S)
	< 401      (from.tag=at, to.tag=  , seq+0, branch0, contact= )
	> REGISTER (from.tag=at, to.tag=  , seq+1, branch1, contact=A, uri=S)
	< 200      (from.tag=at, to.tag=bt, seq+1, branch1, contact= )

### 刷新

	> REGISTER (from.tag=xt, to.tag=  , seq+2, branch2, contact=A, uri=S)

或

	> REGISTER (from.tag=at, to.tag=  , seq+2, branch2, contact=A, uri=S)


# INVITE

### 正常

A=>B 创建会话

	> INVITE (from.tag=at, to.tag=  , seq+0, branch0, contact=A, uri=B)
	< 180    (from.tag=at, to.tag=bt, seq+0, branch0, contact=B)
	< 200    (from.tag=at, to.tag=bt, seq+0, branch0, contact=B)
	> ACK    (from.tag=at, to.tag=bt, seq+0, branch1, contact=A, uri=B)
	# 某些客户端会重发新的 INVITE
	> INVITE (from.tag=at, to.tag=bt, seq+1, branch2, contact=A, uri=B)
	< 200    (from.tag=at, to.tag=bt, seq+1, branch2, contact=B)
	> ACK    (from.tag=at, to.tag=bt, seq+1, branch3, contact=A, uri=B)

B=>A 发 BYE

	< BYE    (from.tag=bt, to.tag=at, seqx , branch4, contact= , uri=A)
	> 200    (from.tag=bt, to.tag=at, seqx , branch4, contact= )

A=>B 发 BYE

	> BYE    (from.tag=at, to.tag=bt, seq+2, branch4, contact= , uri=B)
	< 200    (from.tag=at, to.tag=bt, seq+2, branch4, contact= )

### 中止

A=>B 创建会话

	> INVITE (from.tag=at, to.tag=  , seq+0, branch0, contact=A, uri=B)
	< 180    (from.tag=at, to.tag=bt, seq+0, branch0, contact=B)

B=>A 发 486 Busy Here

	< 486    (from.tag=at, to.tag=bt, seq+0, branch0, contact=B)
	> ACK    (from.tag=at, to.tag=bt, seq+0, branch0, contact= , uri=B)

A=>B 发 CANCEL

	> CANCEL (from.tag=at, to.tag=  , seq+0, branch0, contact= , uri=B)
	< 200    (from.tag=at, to.tag=Ct, seq+0, branch0, contact= )
	< 487    (from.tag=at, to.tag=bt, seq+0, branch0, contact=B)
	> ACK    (from.tag=at, to.tag=bt, seq+0, branch0, contact= , uri=B)

A=>B 发 CANCEL（异常情况，B 已经成功回复了 200，但网络丢包）

	< 200    (from.tag=at, to.tag=bt, seq+0, branch0, contact=B) # 网络丢包
	> CANCEL (from.tag=at, to.tag=  , seq+0, branch0, contact= , uri=B)
	< 200    (from.tag=at, to.tag=Ct, seq+0, branch0, contact= )

ACK 是对 487 的回应，不是对 OK 的回应。OK 是对 CANCEL 的回应。

RFC 设计的缺陷：RFC 把 CANCEL 当做独立的事务，UAC 判断原事务是否已被取消，取决于是否收到 487，不取决于 OK。而 UAS 如果已经发送过 200，那么它将不会再次发送 487.

所以，如果一直未收到 487，UAC 无法知道原因，也即无法知道是因为会话已经建立，还是因为 CANCEL 网络丢包。只能等收到 UAS 重传 200 之后，才将 CANCEL 替换成 BYE。这导致无法快速地中止一个会话。

对于用户来说，CANCEL 的意图是明确的，是不可撤销的，也即一旦用户决定关闭会话，就不依赖于任何条件，要么告知对方协商关闭，要么自己独自关闭。



