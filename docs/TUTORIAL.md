# 使用教程

本教程将通过实际示例，一步步教您如何使用飞书应用机器人Bundle。

## 目录

1. [创建第一个机器人](#创建第一个机器人)
2. [处理消息](#处理消息)
3. [发送不同类型的消息](#发送不同类型的消息)
4. [群组管理](#群组管理)
5. [用户交互](#用户交互)
6. [高级技巧](#高级技巧)

## 创建第一个机器人

### 步骤1：创建Symfony项目

```bash
composer create-project symfony/skeleton my-lark-bot
cd my-lark-bot
```

### 步骤2：安装Bundle

```bash
composer require tourze/lark-app-bot-bundle
```

### 步骤3：配置应用

创建 `config/packages/lark_app_bot.yaml`：

```yaml
lark_app_bot:
    app_id: '%env(LARK_APP_ID)%'
    app_secret: '%env(LARK_APP_SECRET)%'
    webhook:
        verification_token: '%env(LARK_VERIFICATION_TOKEN)%'
```

在 `.env` 中添加：

```env
LARK_APP_ID=your_app_id
LARK_APP_SECRET=your_app_secret
LARK_VERIFICATION_TOKEN=your_verification_token
```

### 步骤4：创建第一个监听器

创建 `src/EventListener/WelcomeListener.php`：

```php
<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\Component\Lark\AppBot\Event\MessageEvent;
use Tourze\Component\Lark\AppBot\Message\MessageService;

class WelcomeListener implements EventSubscriberInterface
{
    public function __construct(
        private MessageService $messageService
    ) {}
    
    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => 'onMessage',
        ];
    }
    
    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();
        $content = json_decode($message['content'], true);
        
        if (($content['text'] ?? '') === 'hello') {
            $this->messageService->reply(
                $message['message_id'],
                'text',
                ['text' => '你好！我是飞书机器人。']
            );
        }
    }
}
```

### 步骤5：测试机器人

1. 启动应用：`symfony server:start`
2. 配置飞书Webhook：`https://your-domain.com/lark/webhook`
3. 在飞书中向机器人发送 "hello"

## 处理消息

### 理解消息结构

飞书消息事件包含以下关键信息：

```php
$message = [
    'message_id' => 'om_xxx',
    'content' => '{"text":"消息内容"}',
    'message_type' => 'text',
    'chat_id' => 'oc_xxx',
    'chat_type' => 'group', // 或 'p2p'
    'sender' => [
        'sender_id' => ['open_id' => 'open_xxx'],
        'sender_type' => 'user',
    ],
];
```

### 创建智能消息处理器

```php
namespace App\Service;

use Tourze\Component\Lark\AppBot\Message\MessageService;

class MessageProcessor
{
    private array $handlers = [];
    
    public function __construct(
        private MessageService $messageService
    ) {}
    
    public function registerHandler(string $pattern, callable $handler): void
    {
        $this->handlers[$pattern] = $handler;
    }
    
    public function process(array $message): void
    {
        $content = json_decode($message['content'], true);
        $text = $content['text'] ?? '';
        
        foreach ($this->handlers as $pattern => $handler) {
            if (preg_match($pattern, $text, $matches)) {
                $handler($message, $matches);
                return;
            }
        }
        
        // 默认处理
        $this->handleDefault($message);
    }
    
    private function handleDefault(array $message): void
    {
        $this->messageService->reply(
            $message['message_id'],
            'text',
            ['text' => '我不太明白您的意思，请输入"帮助"查看可用命令。']
        );
    }
}
```

### 注册处理器

```php
// 在服务配置中
$processor->registerHandler('/^帮助$/', function($message) {
    // 发送帮助信息
});

$processor->registerHandler('/^查询 (.+)$/', function($message, $matches) {
    $query = $matches[1];
    // 处理查询
});

$processor->registerHandler('/^@(.+) (.+)$/', function($message, $matches) {
    $user = $matches[1];
    $content = $matches[2];
    // 处理@某人
});
```

## 发送不同类型的消息

### 1. 文本消息

最简单的消息类型：

```php
$messageService->sendToUser(
    ['open_id' => 'open_xxx'],
    'text',
    ['text' => '这是一条文本消息']
);
```

### 2. 富文本消息

支持格式化的消息：

```php
use Tourze\Component\Lark\AppBot\Message\Builder\RichTextBuilder;

$richText = $richTextBuilder
    ->addText('标题', ['bold' => true, 'text_size' => 'large'])
    ->newLine()
    ->addText('这是一段普通文本')
    ->addText('，这是', [])
    ->addText('红色文本', ['text_color' => 'red'])
    ->newLine()
    ->addLink('点击访问', 'https://example.com')
    ->newLine()
    ->addMention('all') // @所有人
    ->build();

$messageService->sendToGroup('oc_xxx', 'post', $richText['content']);
```

### 3. 卡片消息

最灵活的消息类型：

```php
use Tourze\Component\Lark\AppBot\Message\Builder\CardMessageBuilder;

// 简单卡片
$card = $cardBuilder
    ->setTitle('任务提醒')
    ->setThemeColor('blue')
    ->addMarkdown('您有一个新任务需要处理')
    ->addButton('primary', '查看任务', ['url' => 'https://task.example.com'])
    ->build();

// 复杂卡片
$card = $cardBuilder
    ->setTitle('项目周报')
    ->setThemeColor('green')
    ->addMarkdown('## 本周完成情况')
    ->addFields([
        ['短文本' => '5个任务', 'is_short' => true],
        ['长文本' => '2个Bug修复', 'is_short' => true],
    ])
    ->addDivider()
    ->addMarkdown('### 下周计划')
    ->addMarkdown('- 完成用户系统重构\n- 优化数据库查询')
    ->addNote('更新时间：' . date('Y-m-d H:i:s'))
    ->addActions([
        ['tag' => 'button', 'text' => '查看详情', 'type' => 'primary'],
        ['tag' => 'button', 'text' => '导出报告', 'type' => 'default'],
    ])
    ->build();
```

### 4. 图片消息

```php
$messageService->sendToUser(
    ['open_id' => 'open_xxx'],
    'image',
    ['image_key' => 'img_xxx'] // 需要先上传图片获取image_key
);
```

### 5. 分享群名片

```php
$messageService->sendToUser(
    ['open_id' => 'open_xxx'],
    'share_chat',
    ['chat_id' => 'oc_xxx']
);
```

## 群组管理

### 创建群组

```php
use Tourze\Component\Lark\AppBot\Group\GroupService;

$group = $groupService->createGroup([
    'name' => '项目讨论组',
    'description' => '用于项目相关讨论',
    'chat_mode' => 'group', // 群聊模式
    'member_id_list' => [
        ['open_id' => 'open_xxx1'],
        ['open_id' => 'open_xxx2'],
    ]
]);

$chatId = $group['chat_id'];
```

### 管理群成员

```php
// 添加成员
$groupService->addMembers($chatId, [
    ['open_id' => 'open_xxx3'],
    ['email' => 'newuser@example.com'],
]);

// 移除成员
$groupService->removeMembers($chatId, [
    ['open_id' => 'open_xxx1'],
]);

// 获取成员列表
$members = $groupService->getMembers($chatId);
foreach ($members as $member) {
    echo $member['name'] . ' (' . $member['open_id'] . ')' . PHP_EOL;
}
```

### 更新群信息

```php
$groupService->updateGroup($chatId, [
    'name' => '新的群名称',
    'description' => '新的群描述',
    'add_member_permission' => 'only_owner', // 仅群主可添加成员
]);
```

### 解散群组

```php
$groupService->dissolveGroup($chatId);
```

## 用户交互

### 获取用户信息

```php
use Tourze\Component\Lark\AppBot\User\UserService;

// 通过open_id获取
$user = $userService->getUserInfo(['open_id' => 'open_xxx']);

// 通过邮箱获取
$user = $userService->getUserInfo(['email' => 'user@example.com']);

// 批量获取
$users = $userService->batchGetUsers([
    ['open_id' => 'open_xxx1'],
    ['open_id' => 'open_xxx2'],
]);
```

### 用户属性

```php
$user = [
    'open_id' => 'open_xxx',
    'user_id' => 'user_xxx',
    'name' => '张三',
    'en_name' => 'Zhang San',
    'email' => 'zhangsan@example.com',
    'mobile' => '+8613800138000',
    'avatar' => [
        'avatar_72' => 'https://...',
        'avatar_240' => 'https://...',
    ],
    'status' => [
        'is_activated' => true,
        'is_frozen' => false,
    ],
];
```

### 交互式组件

处理卡片按钮点击：

```php
namespace App\EventListener;

use Tourze\Component\Lark\AppBot\Event\CardActionEvent;

class CardActionListener implements EventSubscriberInterface
{
    public function onCardAction(CardActionEvent $event): void
    {
        $action = $event->getAction();
        
        switch ($action['value']['action'] ?? '') {
            case 'approve':
                $this->handleApprove($event);
                break;
            case 'reject':
                $this->handleReject($event);
                break;
        }
    }
    
    private function handleApprove(CardActionEvent $event): void
    {
        // 更新卡片
        $event->updateCard([
            'config' => ['wide_screen_mode' => true],
            'elements' => [
                [
                    'tag' => 'div',
                    'text' => [
                        'tag' => 'plain_text',
                        'content' => '✅ 已批准',
                    ],
                ],
            ],
        ]);
    }
}
```

## 高级技巧

### 1. 消息模板系统

创建可复用的消息模板：

```php
namespace App\Message\Template;

class TaskNotificationTemplate
{
    public function render(array $data): array
    {
        return [
            'msg_type' => 'card',
            'content' => json_encode([
                'config' => ['wide_screen_mode' => true],
                'header' => [
                    'title' => ['tag' => 'plain_text', 'content' => $data['title']],
                    'template' => $this->getTemplateColor($data['priority']),
                ],
                'elements' => $this->buildElements($data),
            ]),
        ];
    }
    
    private function getTemplateColor(string $priority): string
    {
        return match ($priority) {
            'high' => 'red',
            'medium' => 'orange',
            'low' => 'blue',
            default => 'grey',
        };
    }
}
```

### 2. 消息队列处理

异步处理消息：

```php
// Message类
namespace App\Message;

class SendLarkNotification
{
    public function __construct(
        public string $userId,
        public string $template,
        public array $data
    ) {}
}

// Handler类
namespace App\MessageHandler;

#[AsMessageHandler]
class SendLarkNotificationHandler
{
    public function __invoke(SendLarkNotification $message): void
    {
        $this->notificationService->send(
            $message->userId,
            $message->template,
            $message->data
        );
    }
}
```

### 3. 定时任务

使用Symfony的定时任务发送定期报告：

```php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:send-daily-report',
    description: 'Send daily report to Lark groups'
)]
class SendDailyReportCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $groups = $this->groupRepository->findActiveGroups();
        
        foreach ($groups as $group) {
            $report = $this->reportService->generateDailyReport($group);
            $this->messageService->sendToGroup(
                $group->getChatId(),
                'card',
                $report
            );
        }
        
        return Command::SUCCESS;
    }
}
```

配置cron：
```bash
0 9 * * * /usr/bin/php /path/to/bin/console app:send-daily-report
```

### 4. 错误处理最佳实践

```php
class SafeMessageService
{
    private const MAX_RETRIES = 3;
    
    public function safeSend(array $userId, string $type, array $content): bool
    {
        $retries = 0;
        
        while ($retries < self::MAX_RETRIES) {
            try {
                $result = $this->messageService->sendToUser($userId, $type, $content);
                
                if ($result) {
                    return true;
                }
            } catch (RateLimitException $e) {
                sleep($e->getRetryAfter() ?? 60);
            } catch (ApiException $e) {
                $this->logger->error('API error', [
                    'error' => $e->getMessage(),
                    'retry' => $retries,
                ]);
                
                if ($retries === self::MAX_RETRIES - 1) {
                    // 最后一次重试失败，使用备用方案
                    $this->notifyViaEmail($userId, $content);
                }
            }
            
            $retries++;
        }
        
        return false;
    }
}
```

### 5. 性能优化

批量操作示例：

```php
class BulkNotificationService
{
    public function notifyUsers(array $userIds, string $message): array
    {
        // 批量获取用户信息
        $users = [];
        foreach (array_chunk($userIds, 50) as $chunk) {
            $batchUsers = $this->userService->batchGetUsers(
                array_map(fn($id) => ['open_id' => $id], $chunk)
            );
            $users = array_merge($users, $batchUsers);
        }
        
        // 并行发送消息
        $promises = [];
        foreach ($users as $user) {
            if ($user) {
                $promises[] = $this->sendAsync($user['open_id'], $message);
            }
        }
        
        // 等待所有请求完成
        $results = [];
        foreach ($promises as $promise) {
            try {
                $results[] = $promise->wait();
            } catch (\Exception $e) {
                $results[] = ['error' => $e->getMessage()];
            }
        }
        
        return $results;
    }
}
```

## 下一步

- 查看[API参考文档](API_REFERENCE.md)了解所有可用方法
- 阅读[最佳实践](BEST_PRACTICES.md)优化您的代码
- 参考[示例项目](../examples/)获取更多灵感

恭喜！您已经掌握了飞书应用机器人Bundle的基本和高级用法。继续探索，创建更强大的机器人应用！