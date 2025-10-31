# API参考文档

## 概述

飞书应用机器人Bundle提供了完整的API封装，支持消息发送、群组管理、用户查询等功能。

## 核心服务

### MessageService

消息服务，用于发送和管理消息。

#### 方法列表

##### sendToUser(array $userId, string $msgType, array $content): ?array

发送消息给用户。

**参数：**
- `$userId`: 用户ID数组，支持 `['open_id' => 'xxx']`、`['user_id' => 'xxx']` 或 `['email' => 'xxx']`
- `$msgType`: 消息类型，支持 `text`、`rich_text`、`card` 等
- `$content`: 消息内容，格式根据消息类型而定

**返回值：**
- 成功返回包含 `message_id` 的数组
- 失败返回 `null`

**示例：**
```php
$messageService->sendToUser(
    ['open_id' => 'open_123456'],
    'text',
    ['text' => 'Hello, World!']
);
```

##### sendToGroup(string $chatId, string $msgType, array $content): ?array

发送消息到群组。

**参数：**
- `$chatId`: 群组ID
- `$msgType`: 消息类型
- `$content`: 消息内容

**示例：**
```php
$messageService->sendToGroup(
    'oc_abc123',
    'card',
    $cardBuilder->setTitle('通知')->addMarkdown('内容')->build()
);
```

##### sendTemplateToUser(array $userId, string $template, array $data): ?array

使用模板发送消息给用户。

**参数：**
- `$userId`: 用户ID数组
- `$template`: 模板名称
- `$data`: 模板数据

**支持的模板：**
- `welcome`: 欢迎消息
- `notification`: 通知消息

### GroupService

群组管理服务。

#### 方法列表

##### createGroup(array $params): array

创建群组。

**参数：**
- `name`: 群组名称（必需）
- `description`: 群组描述
- `chat_mode`: 群组模式，`private`（私有）或 `public`（公开）
- `external`: 是否允许外部用户加入
- `owner_id`: 群主ID
- `member_id_list`: 初始成员列表

**示例：**
```php
$groupService->createGroup([
    'name' => '技术讨论组',
    'description' => '技术团队内部讨论',
    'chat_mode' => 'private',
    'member_id_list' => [
        ['open_id' => 'open_123'],
        ['open_id' => 'open_456'],
    ]
]);
```

##### getGroupInfo(string $chatId): array

获取群组信息。

##### updateGroup(string $chatId, array $params): void

更新群组信息。

##### addMembers(string $chatId, array $memberList): array

添加群成员。

##### removeMembers(string $chatId, array $memberList): void

移除群成员。

##### listGroups(int $page = 1, int $pageSize = 20): array

获取群组列表。

### UserService

用户管理服务。

#### 方法列表

##### getUserInfo(array $userId): ?array

获取用户信息。

**参数：**
- `$userId`: 用户ID数组，支持 `['open_id' => 'xxx']`、`['user_id' => 'xxx']` 等

**返回值：**
包含用户详细信息的数组：
- `open_id`: Open ID
- `user_id`: 用户ID
- `name`: 姓名
- `en_name`: 英文名
- `email`: 邮箱
- `mobile`: 手机号
- `avatar`: 头像信息
- `status`: 用户状态
- `employee_type`: 员工类型

##### batchGetUsers(array $userIds): array

批量获取用户信息。

##### getUserDepartments(string $userId): array

获取用户所在部门。

##### searchUsers(string $query, array $options = []): array

搜索用户。

**参数：**
- `$query`: 搜索关键词
- `$options`: 搜索选项
  - `department_id`: 部门ID
  - `page`: 页码
  - `page_size`: 每页数量

### UserTools

用户工具类，提供便捷方法。

#### 方法列表

##### getUserByEmail(string $email): ?array

通过邮箱查找用户。

##### getUserByMobile(string $mobile): ?array

通过手机号查找用户。

##### getUserGroups(string $openId): array

获取用户所在的群组列表。

##### isUserInGroup(string $openId, string $chatId): bool

判断用户是否在群组中。

## 消息构建器

### TextMessageBuilder

文本消息构建器。

```php
$builder = new TextMessageBuilder();
$message = $builder
    ->setText('Hello @user')
    ->addMention('open_123456')
    ->build();
```

### RichTextBuilder

富文本消息构建器。

```php
$builder = new RichTextBuilder();
$message = $builder
    ->addText('重要通知', ['bold' => true])
    ->addLink('查看详情', 'https://example.com')
    ->addMention('open_123456')
    ->newLine()
    ->addTag('紧急', 'red')
    ->build();
```

### CardMessageBuilder

卡片消息构建器。

```php
$builder = new CardMessageBuilder();
$message = $builder
    ->setTitle('系统通知')
    ->setThemeColor('red')
    ->addMarkdown('**重要更新**\n系统将于今晚维护')
    ->addDivider()
    ->addButton('primary', '确认', ['action' => 'confirm'])
    ->addButton('default', '取消', ['action' => 'cancel'])
    ->build();
```

## 事件处理

### 支持的事件类型

- `im.message.receive_v1`: 接收消息
- `im.message.message_read_v1`: 消息已读
- `im.chat.member.user.added_v1`: 用户加入群组
- `im.chat.member.user.deleted_v1`: 用户退出群组
- `im.chat.member.bot.added_v1`: 机器人加入群组
- `im.chat.member.bot.deleted_v1`: 机器人退出群组
- `contact.user.created_v3`: 用户创建
- `contact.user.updated_v3`: 用户更新
- `contact.user.deleted_v3`: 用户删除
- `approval.approval_instance.v1`: 审批实例

### 事件监听器示例

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\Component\Lark\AppBot\Event\MessageEvent;

class CustomMessageListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => 'onMessage',
        ];
    }

    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();
        $chatId = $message['chat_id'];
        $content = json_decode($message['content'], true);
        
        // 处理消息
        if (str_contains($content['text'] ?? '', '/help')) {
            // 发送帮助信息
        }
    }
}
```

## 错误处理

### 常见错误代码

| 错误代码 | 说明 | 解决方案 |
|---------|------|---------|
| 99991663 | 应用未启用 | 在开放平台启用应用 |
| 99991664 | 应用审核中 | 等待审核通过 |
| 99991668 | Token无效 | 检查Token是否过期 |
| 99991400 | 参数错误 | 检查API参数 |
| 99991401 | 无权限 | 检查权限配置 |
| 9499 | 频率限制 | 降低请求频率 |
| 10003 | 用户不存在 | 检查用户ID |
| 10014 | 群组不存在 | 检查群组ID |

### 异常类型

- `ApiException`: API调用异常
- `AuthenticationException`: 认证异常
- `RateLimitException`: 频率限制异常
- `ValidationException`: 参数验证异常
- `ConfigurationException`: 配置异常

## 性能优化

### 缓存策略

- Token缓存：默认3600秒
- 用户信息缓存：300秒
- 群组信息缓存：600秒

### 批量操作

优先使用批量API减少请求次数：

```php
// 不推荐
foreach ($userIds as $userId) {
    $userInfo = $userService->getUserInfo(['open_id' => $userId]);
}

// 推荐
$users = $userService->batchGetUsers($userIds);
```

### 异步处理

对于非实时要求的操作，使用消息队列：

```php
// 配置异步消息发送
$this->messageBus->dispatch(new SendMessageCommand(
    $userId,
    $msgType,
    $content
));
```

## 安全建议

1. **验证Webhook签名**：始终验证来自飞书的Webhook请求
2. **权限最小化**：只申请必需的权限
3. **敏感信息加密**：不要在日志中记录敏感信息
4. **频率限制**：实现应用层的频率限制
5. **输入验证**：验证所有用户输入

## 调试工具

使用内置的调试命令：

```bash
# API调试
bin/console lark:debug api --endpoint=/open-apis/auth/v3/app_info

# 错误诊断
bin/console lark:debug error --error-code=99991663

# 消息调试
bin/console lark:debug message --interactive

# 性能分析
bin/console lark:debug performance
```

## 更多资源

- [飞书开放平台文档](https://open.feishu.cn/document/home/index)
- [Bundle GitHub仓库](https://github.com/tourze/lark-app-bot-bundle)
- [示例项目](../examples/)