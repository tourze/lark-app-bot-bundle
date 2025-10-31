# 集成指南

本指南将帮助您将飞书应用机器人Bundle集成到您的Symfony应用中。

## 目录

1. [准备工作](#准备工作)
2. [安装](#安装)
3. [配置](#配置)
4. [基本使用](#基本使用)
5. [高级功能](#高级功能)
6. [部署](#部署)
7. [故障排除](#故障排除)

## 准备工作

### 1. 创建飞书应用

1. 登录[飞书开放平台](https://open.feishu.cn/)
2. 创建新应用或选择现有应用
3. 记录以下信息：
   - App ID
   - App Secret
   - Verification Token（可选）
   - Encrypt Key（可选）

### 2. 配置应用权限

在"权限管理"中添加以下权限：

- **消息相关**
  - `im:message`: 发送消息
  - `im:message.group_at_msg:readonly`: 接收群组@消息
  - `im:message.p2p_msg:readonly`: 接收私聊消息

- **群组相关**
  - `im:chat`: 群组管理
  - `im:chat:readonly`: 读取群组信息
  - `im:chat.member:readonly`: 读取群成员

- **通讯录相关**
  - `contact:user.base:readonly`: 读取用户基本信息
  - `contact:user.email:readonly`: 读取用户邮箱
  - `contact:user.phone:readonly`: 读取用户手机号

### 3. 配置事件订阅

在"事件订阅"中配置：

1. 请求地址：`https://your-domain.com/lark/webhook`
2. 添加需要的事件：
   - 接收消息 v2.0
   - 群配置修改 v1.0
   - 用户进群 v1.0
   - 用户退群 v1.0

## 安装

### 使用Composer安装

```bash
composer require tourze/lark-app-bot-bundle
```

### 注册Bundle

在 `config/bundles.php` 中添加：

```php
return [
    // ...
    Tourze\Component\Lark\AppBot\LarkAppBotBundle::class => ['all' => true],
];
```

## 配置

### 1. 环境变量

在 `.env` 文件中添加：

```env
# 飞书应用凭据
LARK_APP_ID=cli_xxxxxxxxxxxxx
LARK_APP_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxx

# Webhook配置（可选）
LARK_VERIFICATION_TOKEN=xxxxxxxxxx
LARK_ENCRYPT_KEY=xxxxxxxxxx

# API域名（中国：https://open.feishu.cn，国际：https://open.larksuite.com）
LARK_API_DOMAIN=https://open.feishu.cn
```

### 2. Bundle配置

创建 `config/packages/lark_app_bot.yaml`：

```yaml
lark_app_bot:
    # 应用凭据
    app_id: '%env(LARK_APP_ID)%'
    app_secret: '%env(LARK_APP_SECRET)%'
    api_domain: '%env(LARK_API_DOMAIN)%'
    
    # Webhook配置
    webhook:
        verification_token: '%env(LARK_VERIFICATION_TOKEN)%'
        encrypt_key: '%env(LARK_ENCRYPT_KEY)%'
        enabled_events:
            - 'im.message.receive_v1'
            - 'im.chat.member.user.added_v1'
            - 'im.chat.member.user.deleted_v1'
    
    # 缓存配置
    cache:
        pool: 'cache.app'
        ttl: 3600
    
    # Token提供者
    token_provider: 'file_cache'
    
    # 速率限制
    rate_limit:
        max_requests: 100
        time_window: 60
    
    # 日志
    logger: 'monolog.logger.lark'
```

### 3. 路由配置

在 `config/routes.yaml` 中添加：

```yaml
lark_app_bot:
    resource: '@LarkAppBotBundle/Resources/config/routes.yaml'
```

或手动配置：

```yaml
lark_webhook:
    path: /lark/webhook
    controller: Tourze\Component\Lark\AppBot\Event\Webhook\WebhookController::handle
    methods: [POST]
```

### 4. 日志配置（可选）

在 `config/packages/monolog.yaml` 中添加专用通道：

```yaml
monolog:
    channels:
        - lark
    
    handlers:
        lark:
            type: rotating_file
            path: '%kernel.logs_dir%/lark.log'
            level: debug
            channels: [lark]
            max_files: 7
```

## 基本使用

### 1. 发送消息

#### 发送文本消息

```php
use Tourze\Component\Lark\AppBot\Message\MessageService;

class NotificationService
{
    public function __construct(
        private MessageService $messageService
    ) {}
    
    public function sendWelcomeMessage(string $openId): void
    {
        $this->messageService->sendToUser(
            ['open_id' => $openId],
            'text',
            ['text' => '欢迎使用我们的服务！']
        );
    }
}
```

#### 发送富文本消息

```php
use Tourze\Component\Lark\AppBot\Message\Builder\RichTextBuilder;

class RichMessageService
{
    public function __construct(
        private MessageService $messageService,
        private RichTextBuilder $richTextBuilder
    ) {}
    
    public function sendFormattedMessage(string $chatId): void
    {
        $message = $this->richTextBuilder
            ->addText('重要通知', ['bold' => true])
            ->newLine()
            ->addText('会议时间：')
            ->addText('2024年1月15日 14:00', ['italic' => true])
            ->newLine()
            ->addLink('加入会议', 'https://meeting.example.com')
            ->build();
        
        $this->messageService->sendToGroup($chatId, 'post', $message['content']);
    }
}
```

#### 发送卡片消息

```php
use Tourze\Component\Lark\AppBot\Message\Builder\CardMessageBuilder;

class CardMessageService
{
    public function __construct(
        private MessageService $messageService,
        private CardMessageBuilder $cardBuilder
    ) {}
    
    public function sendApprovalCard(string $openId, array $approvalData): void
    {
        $card = $this->cardBuilder
            ->setTitle('审批请求')
            ->setThemeColor('blue')
            ->addMarkdown(sprintf(
                "**申请人**: %s\n**类型**: %s\n**金额**: ¥%.2f",
                $approvalData['applicant'],
                $approvalData['type'],
                $approvalData['amount']
            ))
            ->addDivider()
            ->addButton('primary', '批准', [
                'action' => 'approve',
                'approval_id' => $approvalData['id']
            ])
            ->addButton('danger', '拒绝', [
                'action' => 'reject',
                'approval_id' => $approvalData['id']
            ])
            ->build();
        
        $this->messageService->sendToUser(
            ['open_id' => $openId],
            'interactive',
            $card
        );
    }
}
```

### 2. 处理消息事件

创建事件监听器：

```php
namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\Component\Lark\AppBot\Event\MessageEvent;
use Tourze\Component\Lark\AppBot\Message\MessageService;

class MessageEventListener implements EventSubscriberInterface
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
        $text = $content['text'] ?? '';
        
        // 响应特定命令
        if (str_starts_with($text, '/help')) {
            $this->sendHelpMessage($message);
        } elseif (str_starts_with($text, '/status')) {
            $this->sendStatusMessage($message);
        }
    }
    
    private function sendHelpMessage(array $message): void
    {
        $helpText = "可用命令：\n"
            . "/help - 显示帮助信息\n"
            . "/status - 查看系统状态\n"
            . "/task - 创建任务";
        
        $this->messageService->reply(
            $message['message_id'],
            'text',
            ['text' => $helpText]
        );
    }
}
```

注册监听器：

```yaml
# config/services.yaml
services:
    App\EventListener\MessageEventListener:
        tags:
            - { name: kernel.event_subscriber }
```

### 3. 群组管理

```php
use Tourze\Component\Lark\AppBot\Group\GroupService;

class TeamManagementService
{
    public function __construct(
        private GroupService $groupService
    ) {}
    
    public function createProjectGroup(string $projectName, array $memberOpenIds): string
    {
        // 创建群组
        $result = $this->groupService->createGroup([
            'name' => sprintf('项目：%s', $projectName),
            'description' => sprintf('%s 项目讨论组', $projectName),
            'chat_mode' => 'private',
            'member_id_list' => array_map(
                fn($openId) => ['open_id' => $openId],
                $memberOpenIds
            )
        ]);
        
        return $result['chat_id'];
    }
    
    public function addProjectMember(string $chatId, string $memberOpenId): void
    {
        $this->groupService->addMembers($chatId, [
            ['open_id' => $memberOpenId]
        ]);
    }
}
```

### 4. 用户信息获取

```php
use Tourze\Component\Lark\AppBot\User\UserService;

class UserInfoService
{
    public function __construct(
        private UserService $userService
    ) {}
    
    public function getUserProfile(string $email): ?array
    {
        $user = $this->userService->getUserInfo(['email' => $email]);
        
        if (!$user) {
            return null;
        }
        
        return [
            'name' => $user['name'],
            'email' => $user['email'],
            'department' => $this->getUserDepartmentName($user['open_id']),
            'avatar' => $user['avatar']['avatar_72'] ?? null,
        ];
    }
    
    private function getUserDepartmentName(string $openId): ?string
    {
        $departments = $this->userService->getUserDepartments($openId);
        
        return $departments[0]['name'] ?? null;
    }
}
```

## 高级功能

### 1. 自定义消息模板

创建自定义模板：

```php
namespace App\Message\Template;

use Tourze\Component\Lark\AppBot\Message\Template\AbstractMessageTemplate;

class TaskNotificationTemplate extends AbstractMessageTemplate
{
    public function getName(): string
    {
        return 'task_notification';
    }
    
    public function getRequiredFields(): array
    {
        return ['task_title', 'assignee', 'due_date'];
    }
    
    public function render(array $data): array
    {
        return [
            'msg_type' => 'card',
            'content' => json_encode([
                'config' => [
                    'wide_screen_mode' => true,
                ],
                'header' => [
                    'title' => [
                        'tag' => 'plain_text',
                        'content' => '新任务分配',
                    ],
                    'template' => 'blue',
                ],
                'elements' => [
                    [
                        'tag' => 'div',
                        'fields' => [
                            [
                                'is_short' => true,
                                'text' => [
                                    'tag' => 'lark_md',
                                    'content' => sprintf('**任务**: %s', $data['task_title']),
                                ],
                            ],
                            [
                                'is_short' => true,
                                'text' => [
                                    'tag' => 'lark_md',
                                    'content' => sprintf('**负责人**: %s', $data['assignee']),
                                ],
                            ],
                        ],
                    ],
                    [
                        'tag' => 'div',
                        'text' => [
                            'tag' => 'lark_md',
                            'content' => sprintf('**截止日期**: %s', $data['due_date']),
                        ],
                    ],
                    [
                        'tag' => 'action',
                        'actions' => [
                            [
                                'tag' => 'button',
                                'text' => [
                                    'tag' => 'plain_text',
                                    'content' => '查看详情',
                                ],
                                'type' => 'primary',
                                'url' => $data['task_url'] ?? '',
                            ],
                        ],
                    ],
                ],
            ]),
        ];
    }
}
```

注册模板：

```yaml
# config/services.yaml
services:
    App\Message\Template\TaskNotificationTemplate:
        tags:
            - { name: 'lark.message_template' }
```

使用模板：

```php
$this->messageService->sendTemplateToUser(
    ['open_id' => $openId],
    'task_notification',
    [
        'task_title' => '完成项目文档',
        'assignee' => '张三',
        'due_date' => '2024-01-20',
        'task_url' => 'https://task.example.com/123',
    ]
);
```

### 2. 批量操作优化

```php
use Tourze\Component\Lark\AppBot\User\UserService;

class BatchOperationService
{
    public function __construct(
        private UserService $userService,
        private MessageService $messageService
    ) {}
    
    public function sendBulkNotification(array $emails, string $message): array
    {
        // 批量获取用户信息
        $users = [];
        foreach (array_chunk($emails, 50) as $chunk) {
            $batchResult = $this->userService->batchGetUsers(
                array_map(fn($email) => ['email' => $email], $chunk)
            );
            $users = array_merge($users, $batchResult);
        }
        
        // 批量发送消息
        $results = [];
        foreach ($users as $user) {
            if ($user) {
                $results[$user['email']] = $this->messageService->sendToUser(
                    ['open_id' => $user['open_id']],
                    'text',
                    ['text' => $message]
                );
            }
        }
        
        return $results;
    }
}
```

### 3. 异步消息处理

配置Messenger：

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    multiplier: 2
        
        routing:
            'App\Message\SendLarkMessage': async
```

创建消息类：

```php
namespace App\Message;

class SendLarkMessage
{
    public function __construct(
        public readonly array $userId,
        public readonly string $msgType,
        public readonly array $content
    ) {}
}
```

创建处理器：

```php
namespace App\MessageHandler;

use App\Message\SendLarkMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Tourze\Component\Lark\AppBot\Message\MessageService;

#[AsMessageHandler]
class SendLarkMessageHandler
{
    public function __construct(
        private MessageService $messageService
    ) {}
    
    public function __invoke(SendLarkMessage $message): void
    {
        $this->messageService->sendToUser(
            $message->userId,
            $message->msgType,
            $message->content
        );
    }
}
```

使用异步发送：

```php
use Symfony\Component\Messenger\MessageBusInterface;

class AsyncNotificationService
{
    public function __construct(
        private MessageBusInterface $bus
    ) {}
    
    public function notifyUserAsync(string $openId, string $text): void
    {
        $this->bus->dispatch(new SendLarkMessage(
            ['open_id' => $openId],
            'text',
            ['text' => $text]
        ));
    }
}
```

### 4. 错误处理和重试

```php
use Tourze\Component\Lark\AppBot\Exception\ApiException;
use Tourze\Component\Lark\AppBot\Exception\RateLimitException;

class RobustMessageService
{
    public function __construct(
        private MessageService $messageService,
        private LoggerInterface $logger
    ) {}
    
    public function sendWithRetry(array $userId, string $msgType, array $content, int $maxRetries = 3): ?array
    {
        $attempt = 0;
        $lastException = null;
        
        while ($attempt < $maxRetries) {
            try {
                return $this->messageService->sendToUser($userId, $msgType, $content);
            } catch (RateLimitException $e) {
                // 速率限制，等待后重试
                $waitTime = $e->getRetryAfter() ?? 60;
                $this->logger->warning('Rate limit hit, waiting {seconds} seconds', [
                    'seconds' => $waitTime,
                    'attempt' => $attempt + 1,
                ]);
                sleep($waitTime);
            } catch (ApiException $e) {
                // API错误，记录并重试
                $this->logger->error('API error: {message}', [
                    'message' => $e->getMessage(),
                    'attempt' => $attempt + 1,
                ]);
                $lastException = $e;
                
                // 指数退避
                sleep(pow(2, $attempt));
            }
            
            $attempt++;
        }
        
        // 所有重试失败
        if ($lastException) {
            $this->logger->critical('Failed to send message after {attempts} attempts', [
                'attempts' => $maxRetries,
                'user_id' => $userId,
                'error' => $lastException->getMessage(),
            ]);
        }
        
        return null;
    }
}
```

## 部署

### 1. 生产环境配置

```yaml
# config/packages/prod/lark_app_bot.yaml
lark_app_bot:
    # 使用Redis缓存Token
    token_provider: 'redis'
    
    # 生产环境缓存配置
    cache:
        pool: 'cache.redis'
        ttl: 7200
    
    # 更严格的速率限制
    rate_limit:
        max_requests: 50
        time_window: 60
```

### 2. 性能优化

```yaml
# config/packages/prod/cache.yaml
framework:
    cache:
        pools:
            cache.lark:
                adapter: cache.adapter.redis
                default_lifetime: 3600
                provider: '%env(REDIS_DSN)%'
                tags: true
```

### 3. 监控和日志

```yaml
# config/packages/prod/monolog.yaml
monolog:
    handlers:
        lark_error:
            type: fingers_crossed
            action_level: error
            handler: lark_error_file
            
        lark_error_file:
            type: rotating_file
            path: '%kernel.logs_dir%/lark_error.log'
            level: error
            max_files: 10
            
        lark_api:
            type: rotating_file
            path: '%kernel.logs_dir%/lark_api.log'
            level: info
            channels: [lark]
            max_files: 7
```

### 4. 健康检查

创建健康检查控制器：

```php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\Component\Lark\AppBot\Authentication\TokenManager;

class HealthCheckController extends AbstractController
{
    #[Route('/health/lark', name: 'health_check_lark')]
    public function checkLark(TokenManager $tokenManager): JsonResponse
    {
        try {
            $token = $tokenManager->getToken();
            $status = $token ? 'healthy' : 'unhealthy';
            
            return new JsonResponse([
                'service' => 'lark',
                'status' => $status,
                'timestamp' => time(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'service' => 'lark',
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => time(),
            ], 503);
        }
    }
}
```

## 故障排除

### 1. 常见问题

#### Token获取失败

**症状**：
```
Authentication failed: Unable to get access token
```

**解决方案**：
1. 检查App ID和App Secret是否正确
2. 确认应用已启用
3. 检查网络连接
4. 运行诊断命令：`bin/console lark:config:check --test-api`

#### Webhook验证失败

**症状**：
```
Challenge verification failed
```

**解决方案**：
1. 确认Verification Token配置正确
2. 检查路由配置
3. 查看Webhook控制器日志

#### 消息发送失败

**症状**：
```
Failed to send message: [99991401] No permission
```

**解决方案**：
1. 检查应用权限配置
2. 确认用户/群组ID正确
3. 验证消息格式

### 2. 调试技巧

#### 启用详细日志

```yaml
# config/packages/dev/monolog.yaml
monolog:
    handlers:
        lark_debug:
            type: stream
            path: '%kernel.logs_dir%/lark_debug.log'
            level: debug
            channels: [lark]
```

#### 使用调试命令

```bash
# 检查配置
bin/console lark:config:check

# 测试API
bin/console lark:debug api --endpoint=/open-apis/auth/v3/app_info

# 诊断错误
bin/console lark:debug error --error-code=99991663

# 调试消息
bin/console lark:debug message --interactive
```

#### 监听所有事件

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\Component\Lark\AppBot\Event\LarkEvent;

class DebugEventListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LarkEvent::class => ['onAnyEvent', 1000],
        ];
    }
    
    public function onAnyEvent(LarkEvent $event): void
    {
        dump([
            'event_class' => get_class($event),
            'event_data' => $event->getData(),
            'timestamp' => time(),
        ]);
    }
}
```

### 3. 性能分析

```php
use Tourze\Component\Lark\AppBot\Developer\PerformanceProfiler;

class PerformanceAnalysisService
{
    public function __construct(
        private PerformanceProfiler $profiler
    ) {}
    
    public function analyzeApiPerformance(): array
    {
        $this->profiler->startProfiling();
        
        // 执行API操作
        
        $profile = $this->profiler->stopProfiling();
        
        return [
            'avg_response_time' => $profile['avg_response_time'],
            'slow_requests' => $profile['slow_requests'],
            'error_rate' => $profile['error_rate'],
        ];
    }
}
```

## 下一步

- 查看[API参考文档](API_REFERENCE.md)了解所有可用方法
- 学习[最佳实践](BEST_PRACTICES.md)
- 查看[示例项目](../examples/)
- 加入[开发者社区](https://github.com/tourze/lark-app-bot-bundle/discussions)