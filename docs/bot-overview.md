# Bot overview
发布时间为2025-03-31 00:00:00

> **官方文档：** [机器人概述 - 飞书开放平台](https://open.feishu.cn/document/client-docs/bot-v3/bot-overview)

## What is a Bot
- 是基于对话与用户交互的应用，是向用户传递信息的常见渠道。
- 可集成飞书日历、审批、文档等应用，以及主流第三方业务系统和自定义企业系统。
- 功能包括发送消息（如监控警报、待办提醒等各类通知）和执行自动化操作（如创建和管理群组）。

## Application scenarios
### Chat Scenario
- **典型用例**：营销部门在私聊和群聊中使用周报机器人推送销售数据、接收和回复消息，将URL转换为视觉卡片。
- **功能**
    - 推送消息：推送业务信息、事件通知等。
    - 接收和回复消息：通过集成事件订阅功能，实时接收对话消息并及时响应。
    - 多样消息格式展示：使用飞书卡片和链接预览等功能，包含文本、图像等多种格式。

### Group Management Scenario
- **功能**
    - 自动创建群组和添加成员：与其他业务平台关联自动创建群聊并邀请相关负责人。
    - 管理和维护各种群组配置：管理群组公告、对话标签、群组菜单等。

### AI Scenario
- **功能**：使用飞书机器人托管AI机器人（如在飞书发布Coze Bot），可自定义AI机器人交互，例如订阅用户进入与机器人聊天的事件以自动发送介绍，配置流式消息等多种功能实现多样化信息显示。

## Features
- **嵌入式体验**：在飞书对话消息中处理内容传递、监控和响应，将企业系统集成到飞书。
- **低开发成本**：只需服务器端开发，开发后企业内其他成员可轻松使用。
- **支持各种消息类型**：可发送文本、图像、文件等，还可发送交互卡片消息。
- **丰富的服务器端能力**：利用丰富的服务器端能力实现各种工作流自动化。
- **与外部用户交互**：飞书认证的企业可使用自建应用启用机器人和外部共享功能。

## Types of bots
|Capability|Application bot|Custom bot|
|----|----|----|
|Add to external groups|✅|✅|
|Push messages to groups|✅|✅|
|Configure open link interactions|✅|✅|
|Configure card interactions|✅|❌|
|Respond to messages @ bot users|✅|❌|
|Send direct messages to users|✅|❌|
|Create, manage, and retrieve group information|✅|❌|
|Access address book, manage cloud documents, and other various open capabilities|✅|❌|

### Application bot
- **使用场景**：可与企业业务系统结合，如集成企业数据监控仪表盘，报警时创建故障处理组并推送报警通知，成员可通过卡片上的交互按钮快速响应。
- **开发方法**：在开发者控制台创建应用并启用机器人功能，应用配置变更需发布并经企业管理员批准。
- **使用方法**：支持使用应用身份调用飞书开放平台的服务器API，如发送消息、获取对话历史等。
- **使用限制**：可在应用范围内与用户发起一对一聊天，支持创建或加入指定群组、管理群组，支持外部群组和与外部用户的一对一聊天。

### Custom bot
- **使用场景**：仅支持向群组单向推送消息，不支持与用户消息交互，一般用于临时向群组推送固定内容的场景。
- **开发方法**：在飞书群组的群组设置中添加自定义机器人。
- **使用方法**：使用webhook URL在当前群聊中单向推送消息。
- **使用限制**：只能在已加入的群组中使用，不支持一对一聊天或跨群组使用，只能在群组中单向推送消息，无法获取用户或企业的详细信息，仅支持为飞书卡片交互模块配置打开链接交互。

## Basic concepts
- **Application Capability**：包括机器人、Web应用、小部件等，机器人应用是启用了机器人功能的应用。
- **Webhook**：在飞书群聊中，自定义机器人Webhook是接收HTTP请求的URL，用于通过向该URL发送请求向群组推送消息。
- **Server-side API**：开放平台提供的各种用于管理或查看企业和用户资源数据的服务器端开放接口，机器人应用可使用这些接口实现各种功能。
- **Message Types**：机器人发送消息时，消息内容包括多种类型，不同类型的机器人支持的消息类型范围不同。
- **Bot Menu**：机器人应用的一项功能，通过为机器人配置自定义菜单，可将应用的常用入口固定在机器人的聊天输入框中，用户可通过菜单上的交互按钮快速操作。

## Bot API and Events
|Open Capabilities|Documentation|Description|
|----|----|----|
|API|Get bot information|获取机器人应用的基本信息，包括应用状态、应用名称、open_id等|
|API|Send message|机器人可使用此API向指定对话发送消息|
|Event|Receive message|订阅此事件后，机器人将根据启用的权限接收不同的用户消息，通过订阅此事件并使用消息API（如回复消息），可自动接收和响应用户消息|
|Events|Bot custom menu events|用户在飞书客户端与机器人的一对一聊天中点击自定义菜单时触发的事件|

## FAQs
For frequently asked questions related to bots, see [Bot FAQ](https://open.feishu.cn/document/uAjLw4CM/ukTMukTMukTM/reference/im-v1/guide/faq#5995f6a6).
