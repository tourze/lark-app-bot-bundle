# 快速入门
最后更新于 2025-03-31

> **官方文档：** [快速入门 - 飞书开放平台](https://open.feishu.cn/document/develop-robots/quick-start)

本文将指导您快速创建一个飞书机器人应用，实现基本的消息收发功能。

## 前提条件

在开始之前，请确保：
- 您已拥有飞书企业版账号且具有管理员权限
- 了解基本的 Web 开发知识
- 具备服务器端开发环境

## 步骤一：创建机器人应用

1. **登录开发者后台**
   - 访问 [飞书开放平台开发者后台](https://open.feishu.cn/app)
   - 使用企业账号登录

2. **创建应用**
   - 点击"创建企业自建应用"
   - 填写应用名称和描述
   - 选择应用图标
   - 点击"确定创建"

3. **开启机器人能力**
   - 在应用详情页，点击左侧导航栏的"应用功能" > "机器人"
   - 开启机器人能力
   - 配置机器人名称和描述

## 步骤二：配置机器人

### 获取应用凭证

在"凭证与基础信息"页面获取：
- **App ID**：应用的唯一标识
- **App Secret**：应用密钥

### 配置事件回调

1. **设置请求网址**
   - 在"事件订阅"页面，设置请求网址（Request URL）
   - 确保您的服务器能够接收 POST 请求

2. **验证请求网址**
   ```json
   {
     "challenge": "ajls384kdjx98XX",
     "token": "v1.5gGG6D8EJy0b6Jw+pDo4X+0hf4HCBwNAh6VdQ4MDU5J",
     "type": "url_verification"
   }
   ```
   
   服务器需要响应：
   ```json
   {
     "challenge": "ajls384kdjx98XX"
   }
   ```

3. **订阅事件**
   - 添加"接收消息"事件
   - 保存配置

### 申请权限

在"权限管理"页面申请以下权限：
- **以应用的身份发送消息**
- **获取与发送单聊、群组消息**
- **读取用户发给机器人的单聊消息**

## 步骤三：开发机器人

### 获取访问令牌

```bash
curl -X POST \
  https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal \
  -H 'Content-Type: application/json' \
  -d '{
    "app_id": "your_app_id",
    "app_secret": "your_app_secret"
  }'
```

### 发送消息

```bash
curl -X POST \
  https://open.feishu.cn/open-apis/im/v1/messages \
  -H 'Authorization: Bearer your_tenant_access_token' \
  -H 'Content-Type: application/json' \
  -d '{
    "receive_id": "user_open_id",
    "msg_type": "text",
    "content": "{\"text\":\"Hello, World!\"}"
  }'
```

### 接收消息事件

当用户向机器人发送消息时，您的服务器将收到事件回调：

```json
{
  "schema": "2.0",
  "header": {
    "event_id": "xxx",
    "event_type": "im.message.receive_v1",
    "create_time": "1608725989000",
    "token": "xxx",
    "app_id": "xxx",
    "tenant_key": "xxx"
  },
  "event": {
    "sender": {
      "sender_id": {
        "union_id": "xxx",
        "user_id": "xxx",
        "open_id": "xxx"
      },
      "sender_type": "user",
      "tenant_key": "xxx"
    },
    "message": {
      "message_id": "xxx",
      "root_id": "xxx",
      "parent_id": "xxx",
      "create_time": "1608725989000",
      "chat_id": "xxx",
      "chat_type": "p2p",
      "message_type": "text",
      "content": "{\"text\":\"用户发送的消息内容\"}"
    }
  }
}
```

## 步骤四：发布应用

1. **创建版本**
   - 在应用详情页，点击"版本管理与发布"
   - 点击"创建版本"
   - 填写版本说明

2. **发布应用**
   - 设置应用可用范围（推荐先设置为"部分成员可用"进行测试）
   - 提交审核
   - 等待管理员审核通过

## 步骤五：测试机器人

1. **添加机器人**
   - 在飞书客户端中搜索您的机器人名称
   - 发起单聊或将机器人添加到群组

2. **发送消息**
   - 向机器人发送消息测试
   - 验证机器人能够正确响应

## 常见问题

### Q: 机器人收不到消息？
A: 请检查：
- 事件订阅配置是否正确
- 权限是否申请并审核通过
- 服务器是否正常响应回调请求

### Q: 发送消息失败？
A: 请检查：
- 访问令牌是否有效
- 消息格式是否正确
- 接收者ID是否正确

### Q: 如何在群组中使用机器人？
A: 
- 将机器人添加到群组
- 用户可以@机器人或直接发送消息
- 机器人需要订阅相应的群消息事件

## 下一步

完成快速入门后，您可以：
- 学习更多[消息类型](./message-content-description-0.md)
- 配置[机器人菜单](https://open.feishu.cn/document/client-docs/bot-v3/bot-customized-menu)
- 集成更多飞书开放平台能力

## 相关链接

- [机器人概述](./bot-overview.md)
- [获取机器人信息](./obtain-bot-info.md)
- [飞书开放平台 API 文档](https://open.feishu.cn/document/server-docs/api-call-guide/calling-process/overview)