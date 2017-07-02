# phpvoip

模块可以承担两种角色，一是用户(UAC)在引擎内部的代理人，也即 UAS。二是自身作为 UAC，也即终端应用。

UAS 是 Registrar，或者 SipChannel 外线，或者是 SIP Trunk 中继，它们不发起会话，也不接受会话，只做原样转发。

UAC 也称终端应用，例如自动总台，座席分配器等。它们既可作为会话的接收者，也可以主动发起会话。

## 引擎处理 INVITE 的逻辑

引擎从网络收到 INVITE 后:

* 引擎已经通过 callin() 找到会话发起者的代理模块，代理模块返回 callee。
* 引擎执行地址转换，将 from 做替换为新的发起者地址。
* 引擎通过 callout() 找到会话的接收者代理模块。
* 引擎将管理由模块返回的 callee 和 caller 会话，这两个会话将脱离模块的控制。
* callee 会话与会话发起者进行通信，caller 会话与会话接收者进行通信。


## 模块处理 callin/callout INVITE

模块应该在 callin() 中返回一个 callee 会话，如果满足下列条件:

* 对于 SipRegistrar，检查 from 是否在自己处注册，且与注册信息相符。
* 对于 SipChannel，检查网络地址是否为自己的上级(server)。
* 对于终端应用，检查 to 是否为自己绑定的号码。

模块应该在 callout() 中返回一个 caller 会话，如果满足下列条件:

* 对于 SipRegistrar，检查 to 是否在自己处注册。
* 对于 SipChannel，检查 from 为自己绑定的号码。
* 对于终端应用，检查 to 是否为自己绑定的号码。


## 终端应用发起和接受会话

发起 INVITE 的流程:

* 模块(mod_a)生成一个 caller 会话，此会话由模块自己管理，将发送 INVITE 给引擎。
* 引擎收到 INVITE 后，执行与网络收到 INVITE 相同的流程。
* 所以，mod_a 将在 callin() 方法返回一个 callee 会话给引擎管理。
* 引擎接着执行后续的逻辑。

接受 INVITE 的流程:

* 模块在 callout() 方法中发现自己是会话接受者。
* 模块创建一个 caller 会话返回给引擎管理，同时，创建一个对应的 callee 会话自己管理。
