# 最佳实践

本文档总结了使用飞书应用机器人Bundle的最佳实践和推荐模式。

## 目录

1. [架构设计](#架构设计)
2. [安全性](#安全性)
3. [性能优化](#性能优化)
4. [错误处理](#错误处理)
5. [测试策略](#测试策略)
6. [监控和日志](#监控和日志)
7. [代码组织](#代码组织)
8. [常见模式](#常见模式)

## 架构设计

### 1. 服务层设计

**推荐**：创建专门的服务层来封装飞书API调用

```php
namespace App\Service\Lark;

use Tourze\Component\Lark\AppBot\Message\MessageService;
use Tourze\Component\Lark\AppBot\User\UserService;

class NotificationService
{
    public function __construct(
        private MessageService $messageService,
        private UserService $userService,
        private LoggerInterface $logger
    ) {}
    
    /**
     * 发送通知给用户
     * 
     * @param string $email 用户邮箱
     * @param string $title 通知标题
     * @param string $content 通知内容
     * @param array $options 额外选项
     * @return bool 是否发送成功
     */
    public function notifyUser(
        string $email, 
        string $title, 
        string $content, 
        array $options = []
    ): bool {
        try {
            // 1. 获取用户信息
            $user = $this->userService->getUserInfo(['email' => $email]);
            if (!$user) {
                $this->logger->warning('User not found', ['email' => $email]);
                return false;
            }
            
            // 2. 构建消息
            $message = $this->buildNotificationMessage($title, $content, $options);
            
            // 3. 发送消息
            $result = $this->messageService->sendToUser(
                ['open_id' => $user['open_id']],
                $message['type'],
                $message['content']
            );
            
            // 4. 记录结果
            if ($result) {
                $this->logger->info('Notification sent', [
                    'user' => $email,
                    'message_id' => $result['message_id'] ?? null,
                ]);
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send notification', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    private function buildNotificationMessage(
        string $title, 
        string $content, 
        array $options
    ): array {
        // 根据选项决定消息类型
        if ($options['urgent'] ?? false) {
            return [
                'type' => 'card',
                'content' => $this->buildUrgentCard($title, $content),
            ];
        }
        
        return [
            'type' => 'text',
            'content' => ['text' => sprintf("%s\n\n%s", $title, $content)],
        ];
    }
}
```

### 2. 事件驱动架构

**推荐**：使用事件系统解耦业务逻辑

```php
// 定义领域事件
namespace App\Event;

class UserJoinedTeamEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly string $teamId,
        public readonly \DateTimeImmutable $joinedAt
    ) {}
}

// 创建事件监听器
namespace App\EventListener;

use App\Event\UserJoinedTeamEvent;
use App\Service\Lark\TeamNotificationService;

class TeamEventListener
{
    public function __construct(
        private TeamNotificationService $notificationService
    ) {}
    
    public function onUserJoinedTeam(UserJoinedTeamEvent $event): void
    {
        // 发送欢迎消息
        $this->notificationService->sendWelcomeMessage(
            $event->userId,
            $event->teamId
        );
        
        // 通知团队成员
        $this->notificationService->notifyTeamMembers(
            $event->teamId,
            sprintf('新成员加入：%s', $event->userId)
        );
    }
}
```

### 3. 命令模式处理

**推荐**：使用命令模式处理复杂的消息交互

```php
namespace App\Command\Lark;

interface CommandInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function execute(array $args, array $context): array;
}

class HelpCommand implements CommandInterface
{
    public function getName(): string
    {
        return '/help';
    }
    
    public function getDescription(): string
    {
        return '显示帮助信息';
    }
    
    public function execute(array $args, array $context): array
    {
        return [
            'type' => 'text',
            'content' => [
                'text' => "可用命令：\n/help - 显示帮助\n/task - 任务管理\n/report - 生成报告"
            ],
        ];
    }
}

class CommandRegistry
{
    private array $commands = [];
    
    public function register(CommandInterface $command): void
    {
        $this->commands[$command->getName()] = $command;
    }
    
    public function execute(string $commandName, array $args, array $context): ?array
    {
        if (!isset($this->commands[$commandName])) {
            return null;
        }
        
        return $this->commands[$commandName]->execute($args, $context);
    }
}
```

## 安全性

### 1. Webhook签名验证

**必须**：始终验证Webhook请求的签名

```php
namespace App\Security;

use Tourze\Component\Lark\AppBot\Exception\SecurityException;

class WebhookSecurityService
{
    public function __construct(
        private string $verificationToken,
        private string $encryptKey
    ) {}
    
    public function verifyRequest(Request $request): void
    {
        $timestamp = $request->headers->get('X-Lark-Request-Timestamp');
        $nonce = $request->headers->get('X-Lark-Request-Nonce');
        $signature = $request->headers->get('X-Lark-Signature');
        
        // 1. 验证时间戳（防重放攻击）
        if (abs(time() - (int)$timestamp) > 300) {
            throw new SecurityException('Request timestamp is too old');
        }
        
        // 2. 验证签名
        $content = $request->getContent();
        $expectedSignature = $this->calculateSignature($timestamp, $nonce, $content);
        
        if (!hash_equals($expectedSignature, $signature)) {
            throw new SecurityException('Invalid signature');
        }
    }
    
    private function calculateSignature(
        string $timestamp, 
        string $nonce, 
        string $content
    ): string {
        $data = $timestamp . $nonce . $this->encryptKey . $content;
        return hash('sha256', $data);
    }
}
```

### 2. 权限控制

**推荐**：实现细粒度的权限控制

```php
namespace App\Security\Lark;

class PermissionService
{
    private array $permissions = [
        'send_notification' => ['admin', 'manager'],
        'manage_group' => ['admin'],
        'view_reports' => ['admin', 'manager', 'viewer'],
    ];
    
    public function __construct(
        private UserService $userService
    ) {}
    
    public function can(string $openId, string $permission): bool
    {
        $user = $this->userService->getUserInfo(['open_id' => $openId]);
        if (!$user) {
            return false;
        }
        
        $userRole = $this->getUserRole($user);
        
        return in_array($userRole, $this->permissions[$permission] ?? [], true);
    }
    
    public function requirePermission(string $openId, string $permission): void
    {
        if (!$this->can($openId, $permission)) {
            throw new AccessDeniedException(
                sprintf('User %s does not have permission %s', $openId, $permission)
            );
        }
    }
    
    private function getUserRole(array $user): string
    {
        // 从用户信息或其他来源获取角色
        return $user['custom_attrs']['role'] ?? 'viewer';
    }
}
```

### 3. 敏感信息保护

**必须**：不要在日志中记录敏感信息

```php
namespace App\Logger;

class SensitiveDataProcessor
{
    private array $sensitiveFields = [
        'password',
        'token',
        'secret',
        'mobile',
        'email',
        'id_card',
    ];
    
    public function process(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->process($value);
            } elseif ($this->isSensitive($key)) {
                $data[$key] = $this->mask($value);
            }
        }
        
        return $data;
    }
    
    private function isSensitive(string $key): bool
    {
        $lowerKey = strtolower($key);
        foreach ($this->sensitiveFields as $field) {
            if (str_contains($lowerKey, $field)) {
                return true;
            }
        }
        return false;
    }
    
    private function mask(mixed $value): string
    {
        if (!is_string($value)) {
            return '***';
        }
        
        $length = strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        
        return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
    }
}
```

## 性能优化

### 1. 批量操作

**推荐**：使用批量API减少请求次数

```php
namespace App\Service\Lark;

class BulkOperationService
{
    private const BATCH_SIZE = 50;
    
    public function __construct(
        private UserService $userService,
        private MessageService $messageService
    ) {}
    
    /**
     * 批量发送消息给多个用户
     */
    public function sendBulkMessages(array $userEmails, array $message): array
    {
        $results = ['success' => [], 'failed' => []];
        
        // 批量获取用户信息
        foreach (array_chunk($userEmails, self::BATCH_SIZE) as $chunk) {
            $users = $this->batchGetUsersByEmail($chunk);
            
            // 并发发送消息
            $promises = [];
            foreach ($users as $email => $user) {
                if ($user) {
                    $promises[$email] = $this->sendMessageAsync($user, $message);
                } else {
                    $results['failed'][$email] = 'User not found';
                }
            }
            
            // 等待所有请求完成
            foreach ($promises as $email => $promise) {
                try {
                    $result = $promise->wait();
                    $results['success'][$email] = $result;
                } catch (\Exception $e) {
                    $results['failed'][$email] = $e->getMessage();
                }
            }
        }
        
        return $results;
    }
    
    private function batchGetUsersByEmail(array $emails): array
    {
        $userIds = array_map(fn($email) => ['email' => $email], $emails);
        $users = $this->userService->batchGetUsers($userIds);
        
        // 构建email => user映射
        $userMap = [];
        foreach ($emails as $index => $email) {
            $userMap[$email] = $users[$index] ?? null;
        }
        
        return $userMap;
    }
}
```

### 2. 缓存策略

**推荐**：合理使用缓存减少API调用

```php
namespace App\Service\Lark;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CachedUserService
{
    private const CACHE_TTL = 3600; // 1小时
    
    public function __construct(
        private UserService $userService,
        private TagAwareCacheInterface $cache
    ) {}
    
    public function getUserInfo(array $userId): ?array
    {
        $cacheKey = $this->buildCacheKey('user_info', $userId);
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($userId) {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag(['lark_users']);
            
            return $this->userService->getUserInfo($userId);
        });
    }
    
    public function getUserDepartments(string $openId): array
    {
        $cacheKey = sprintf('user_departments_%s', $openId);
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($openId) {
            $item->expiresAfter(self::CACHE_TTL * 2); // 部门信息变动较少
            $item->tag(['lark_users', 'lark_departments']);
            
            return $this->userService->getUserDepartments($openId);
        });
    }
    
    public function invalidateUserCache(?string $openId = null): void
    {
        if ($openId) {
            // 清除特定用户缓存
            $this->cache->delete(sprintf('user_info_%s', $openId));
            $this->cache->delete(sprintf('user_departments_%s', $openId));
        } else {
            // 清除所有用户缓存
            $this->cache->invalidateTags(['lark_users']);
        }
    }
    
    private function buildCacheKey(string $prefix, array $params): string
    {
        ksort($params);
        return sprintf('%s_%s', $prefix, md5(json_encode($params)));
    }
}
```

### 3. 异步处理

**推荐**：对非实时要求的操作使用异步处理

```php
namespace App\MessageHandler;

use App\Message\ProcessLarkWebhook;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessLarkWebhookHandler
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger
    ) {}
    
    public function __invoke(ProcessLarkWebhook $message): void
    {
        $startTime = microtime(true);
        
        try {
            // 解析事件类型
            $eventType = $message->getEventType();
            $eventData = $message->getEventData();
            
            // 分发事件
            $event = $this->createEvent($eventType, $eventData);
            $this->eventDispatcher->dispatch($event);
            
            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->info('Webhook processed', [
                'event_type' => $eventType,
                'duration_ms' => $duration,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to process webhook', [
                'event_type' => $message->getEventType(),
                'error' => $e->getMessage(),
            ]);
            
            // 重新抛出异常以触发重试
            throw $e;
        }
    }
}
```

## 错误处理

### 1. 优雅降级

**推荐**：实现优雅降级策略

```php
namespace App\Service\Lark;

class ResilientMessageService
{
    public function __construct(
        private MessageService $messageService,
        private FallbackService $fallbackService,
        private LoggerInterface $logger
    ) {}
    
    public function sendCriticalNotification(
        string $userId, 
        string $message
    ): bool {
        try {
            // 尝试通过飞书发送
            $result = $this->messageService->sendToUser(
                ['open_id' => $userId],
                'text',
                ['text' => $message]
            );
            
            if ($result) {
                return true;
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to send via Lark', [
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
        
        // 降级到备用通道
        try {
            return $this->fallbackService->notify($userId, $message);
        } catch (\Exception $e) {
            $this->logger->error('All notification channels failed', [
                'user' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
```

### 2. 重试机制

**推荐**：实现智能重试机制

```php
namespace App\Service;

use Tourze\Component\Lark\AppBot\Exception\RateLimitException;
use Tourze\Component\Lark\AppBot\Exception\ApiException;

class RetryableService
{
    private array $retryableExceptions = [
        RateLimitException::class,
        ApiException::class,
    ];
    
    private array $retryDelays = [1, 2, 4, 8, 16]; // 指数退避
    
    public function executeWithRetry(callable $operation, array $context = []): mixed
    {
        $lastException = null;
        
        foreach ($this->retryDelays as $attempt => $delay) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;
                
                // 检查是否应该重试
                if (!$this->shouldRetry($e, $attempt)) {
                    throw $e;
                }
                
                // 记录重试
                $this->logger->info('Retrying operation', [
                    'attempt' => $attempt + 1,
                    'delay' => $delay,
                    'error' => $e->getMessage(),
                    'context' => $context,
                ]);
                
                // 等待
                sleep($delay);
            }
        }
        
        throw $lastException;
    }
    
    private function shouldRetry(\Exception $e, int $attempt): bool
    {
        // 检查是否是可重试的异常
        foreach ($this->retryableExceptions as $retryableClass) {
            if ($e instanceof $retryableClass) {
                return true;
            }
        }
        
        // 检查HTTP状态码
        if ($e instanceof ApiException) {
            $statusCode = $e->getStatusCode();
            // 5xx 错误和 429 (Too Many Requests) 可重试
            return $statusCode >= 500 || $statusCode === 429;
        }
        
        return false;
    }
}
```

### 3. 断路器模式

**推荐**：使用断路器防止雪崩效应

```php
namespace App\Service;

class CircuitBreakerService
{
    private array $states = []; // 服务状态
    private array $failures = []; // 失败计数
    private array $lastFailureTime = []; // 最后失败时间
    
    private const FAILURE_THRESHOLD = 5;
    private const TIMEOUT = 60; // 60秒后尝试恢复
    
    public function call(string $service, callable $operation): mixed
    {
        $state = $this->getState($service);
        
        if ($state === 'open') {
            // 断路器打开，检查是否可以尝试恢复
            if ($this->canAttemptReset($service)) {
                $state = 'half-open';
                $this->setState($service, $state);
            } else {
                throw new CircuitBreakerOpenException(
                    sprintf('Service %s is unavailable', $service)
                );
            }
        }
        
        try {
            $result = $operation();
            
            // 成功，重置失败计数
            if ($state === 'half-open') {
                $this->setState($service, 'closed');
            }
            $this->resetFailures($service);
            
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure($service);
            
            // 检查是否需要打开断路器
            if ($this->failures[$service] >= self::FAILURE_THRESHOLD) {
                $this->setState($service, 'open');
            }
            
            throw $e;
        }
    }
}
```

## 测试策略

### 1. 单元测试

**推荐**：模拟外部依赖

```php
namespace App\Tests\Service\Lark;

use App\Service\Lark\NotificationService;
use PHPUnit\Framework\TestCase;
use Tourze\Component\Lark\AppBot\Message\MessageService;
use Tourze\Component\Lark\AppBot\User\UserService;

class NotificationServiceTest extends TestCase
{
    private NotificationService $service;
    private MessageService $messageService;
    private UserService $userService;
    
    protected function setUp(): void
    {
        $this->messageService = $this->createMock(MessageService::class);
        $this->userService = $this->createMock(UserService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->service = new NotificationService(
            $this->messageService,
            $this->userService,
            $this->logger
        );
    }
    
    public function testNotifyUserSuccess(): void
    {
        // Arrange
        $email = 'user@example.com';
        $user = ['open_id' => 'open_123', 'email' => $email];
        
        $this->userService
            ->expects($this->once())
            ->method('getUserInfo')
            ->with(['email' => $email])
            ->willReturn($user);
        
        $this->messageService
            ->expects($this->once())
            ->method('sendToUser')
            ->with(
                ['open_id' => 'open_123'],
                'text',
                $this->anything()
            )
            ->willReturn(['message_id' => 'msg_123']);
        
        // Act
        $result = $this->service->notifyUser($email, 'Test', 'Content');
        
        // Assert
        $this->assertTrue($result);
    }
    
    public function testNotifyUserWhenUserNotFound(): void
    {
        // Arrange
        $this->userService
            ->expects($this->once())
            ->method('getUserInfo')
            ->willReturn(null);
        
        $this->messageService
            ->expects($this->never())
            ->method('sendToUser');
        
        // Act
        $result = $this->service->notifyUser('nonexistent@example.com', 'Test', 'Content');
        
        // Assert
        $this->assertFalse($result);
    }
}
```

### 2. 集成测试

**推荐**：使用测试容器

```php
namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tourze\Component\Lark\AppBot\Message\MessageService;

class LarkIntegrationTest extends KernelTestCase
{
    private MessageService $messageService;
    
    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        
        $this->messageService = $container->get(MessageService::class);
    }
    
    public function testMessageServiceIntegration(): void
    {
        // 使用测试环境配置
        $testUserId = $_ENV['LARK_TEST_USER_ID'] ?? null;
        
        if (!$testUserId) {
            $this->markTestSkipped('LARK_TEST_USER_ID not configured');
        }
        
        $result = $this->messageService->sendToUser(
            ['open_id' => $testUserId],
            'text',
            ['text' => 'Integration test message']
        );
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('message_id', $result);
    }
}
```

### 3. 功能测试

**推荐**：测试完整的业务流程

```php
namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemoryTransport;

class WebhookTest extends WebTestCase
{
    public function testWebhookMessageEvent(): void
    {
        $client = static::createClient();
        
        // 准备测试数据
        $webhookData = [
            'schema' => '2.0',
            'header' => [
                'event_id' => 'test_event_123',
                'event_type' => 'im.message.receive_v1',
                'app_id' => 'test_app',
                'tenant_key' => 'test_tenant',
                'create_time' => '1234567890000',
                'token' => 'test_token',
            ],
            'event' => [
                'message' => [
                    'message_id' => 'om_test_123',
                    'content' => '{"text":"test message"}',
                    'message_type' => 'text',
                ],
            ],
        ];
        
        // 发送Webhook请求
        $client->request(
            'POST',
            '/lark/webhook',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_LARK_REQUEST_TIMESTAMP' => (string)time(),
                'HTTP_X_LARK_REQUEST_NONCE' => 'test_nonce',
                'HTTP_X_LARK_SIGNATURE' => 'test_signature',
            ],
            json_encode($webhookData)
        );
        
        $this->assertResponseIsSuccessful();
        
        // 验证消息被处理
        $transport = self::getContainer()->get('messenger.transport.async');
        $this->assertInstanceOf(InMemoryTransport::class, $transport);
        $this->assertCount(1, $transport->getSent());
    }
}
```

## 监控和日志

### 1. 结构化日志

**推荐**：使用结构化日志便于查询和分析

```php
namespace App\Logger;

use Monolog\Processor\ProcessorInterface;

class LarkContextProcessor implements ProcessorInterface
{
    public function __invoke(array $record): array
    {
        // 添加通用上下文
        $record['extra']['service'] = 'lark';
        $record['extra']['environment'] = $_ENV['APP_ENV'] ?? 'prod';
        
        // 添加请求相关信息
        if (isset($GLOBALS['lark_request_context'])) {
            $context = $GLOBALS['lark_request_context'];
            $record['extra']['event_id'] = $context['event_id'] ?? null;
            $record['extra']['event_type'] = $context['event_type'] ?? null;
            $record['extra']['tenant_key'] = $context['tenant_key'] ?? null;
        }
        
        // 添加性能指标
        $record['extra']['memory_usage'] = memory_get_usage(true);
        $record['extra']['peak_memory'] = memory_get_peak_usage(true);
        
        return $record;
    }
}
```

### 2. 指标收集

**推荐**：收集关键业务指标

```php
namespace App\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

class LarkMetricsCollector
{
    private CollectorRegistry $registry;
    private array $counters = [];
    private array $histograms = [];
    
    public function __construct()
    {
        $this->registry = new CollectorRegistry(new Redis());
        $this->initializeMetrics();
    }
    
    private function initializeMetrics(): void
    {
        // 消息发送计数器
        $this->counters['messages_sent'] = $this->registry->getOrRegisterCounter(
            'lark',
            'messages_sent_total',
            'Total number of messages sent',
            ['type', 'status']
        );
        
        // API响应时间直方图
        $this->histograms['api_duration'] = $this->registry->getOrRegisterHistogram(
            'lark',
            'api_request_duration_seconds',
            'API request duration',
            ['endpoint', 'method'],
            [0.01, 0.05, 0.1, 0.5, 1, 2, 5]
        );
    }
    
    public function recordMessageSent(string $type, bool $success): void
    {
        $this->counters['messages_sent']->inc([
            'type' => $type,
            'status' => $success ? 'success' : 'failure',
        ]);
    }
    
    public function recordApiCall(
        string $endpoint, 
        string $method, 
        float $duration
    ): void {
        $this->histograms['api_duration']->observe(
            $duration,
            [$endpoint, $method]
        );
    }
}
```

### 3. 健康检查

**推荐**：实现全面的健康检查

```php
namespace App\Health;

use Tourze\Component\Lark\AppBot\Authentication\TokenManager;
use Tourze\Component\Lark\AppBot\Client\LarkClient;

class LarkHealthCheck
{
    public function __construct(
        private TokenManager $tokenManager,
        private LarkClient $client,
        private CacheInterface $cache
    ) {}
    
    public function check(): array
    {
        $checks = [
            'token' => $this->checkToken(),
            'api' => $this->checkApi(),
            'cache' => $this->checkCache(),
            'dependencies' => $this->checkDependencies(),
        ];
        
        $overallStatus = 'healthy';
        foreach ($checks as $check) {
            if ($check['status'] !== 'healthy') {
                $overallStatus = 'unhealthy';
                break;
            }
        }
        
        return [
            'status' => $overallStatus,
            'checks' => $checks,
            'timestamp' => time(),
        ];
    }
    
    private function checkToken(): array
    {
        try {
            $token = $this->tokenManager->getToken();
            $expiry = $this->tokenManager->getTokenExpiry();
            $remainingTime = $expiry - time();
            
            return [
                'status' => $token && $remainingTime > 300 ? 'healthy' : 'degraded',
                'token_expires_in' => $remainingTime,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
    
    private function checkApi(): array
    {
        try {
            $start = microtime(true);
            $response = $this->client->request('GET', '/open-apis/auth/v3/app_info');
            $duration = (microtime(true) - $start) * 1000;
            
            return [
                'status' => $response->getStatusCode() === 200 ? 'healthy' : 'unhealthy',
                'response_time_ms' => $duration,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
}
```

## 代码组织

### 1. 领域驱动设计

**推荐**：按业务领域组织代码

```
src/
├── Domain/
│   ├── Notification/
│   │   ├── Entity/
│   │   │   └── Notification.php
│   │   ├── Repository/
│   │   │   └── NotificationRepository.php
│   │   ├── Service/
│   │   │   └── NotificationService.php
│   │   └── Event/
│   │       └── NotificationSentEvent.php
│   ├── Team/
│   │   ├── Entity/
│   │   ├── Repository/
│   │   └── Service/
│   └── Task/
│       ├── Entity/
│       ├── Repository/
│       └── Service/
├── Infrastructure/
│   ├── Lark/
│   │   ├── Adapter/
│   │   │   ├── MessageAdapter.php
│   │   │   └── UserAdapter.php
│   │   └── Service/
│   │       └── LarkApiService.php
│   └── Persistence/
│       └── Doctrine/
└── Application/
    ├── Command/
    ├── Query/
    └── EventHandler/
```

### 2. 适配器模式

**推荐**：使用适配器隔离外部依赖

```php
namespace App\Infrastructure\Lark\Adapter;

use App\Domain\Notification\Model\Message;
use Tourze\Component\Lark\AppBot\Message\MessageService;

class MessageAdapter implements MessageAdapterInterface
{
    public function __construct(
        private MessageService $messageService
    ) {}
    
    public function send(Message $message): bool
    {
        try {
            $larkMessage = $this->convertToLarkFormat($message);
            
            $result = $this->messageService->sendToUser(
                $larkMessage['receiver'],
                $larkMessage['type'],
                $larkMessage['content']
            );
            
            return $result !== null;
        } catch (\Exception $e) {
            // 记录错误但不抛出，保持领域层清洁
            $this->logger->error('Failed to send message via Lark', [
                'error' => $e->getMessage(),
                'message_id' => $message->getId(),
            ]);
            
            return false;
        }
    }
    
    private function convertToLarkFormat(Message $message): array
    {
        // 转换领域模型到飞书API格式
        return [
            'receiver' => ['open_id' => $message->getRecipientId()],
            'type' => $this->mapMessageType($message->getType()),
            'content' => $this->buildContent($message),
        ];
    }
}
```

## 常见模式

### 1. 工厂模式创建消息

```php
namespace App\Factory;

use App\Domain\Notification\Model\Message;

class MessageFactory
{
    private array $builders = [];
    
    public function registerBuilder(string $type, MessageBuilderInterface $builder): void
    {
        $this->builders[$type] = $builder;
    }
    
    public function create(string $type, array $data): Message
    {
        if (!isset($this->builders[$type])) {
            throw new \InvalidArgumentException(
                sprintf('Unknown message type: %s', $type)
            );
        }
        
        return $this->builders[$type]->build($data);
    }
}
```

### 2. 责任链模式处理命令

```php
namespace App\Handler;

abstract class CommandHandler
{
    private ?CommandHandler $next = null;
    
    public function setNext(CommandHandler $handler): CommandHandler
    {
        $this->next = $handler;
        return $handler;
    }
    
    public function handle(Command $command): ?Response
    {
        if ($this->canHandle($command)) {
            return $this->doHandle($command);
        }
        
        if ($this->next) {
            return $this->next->handle($command);
        }
        
        return null;
    }
    
    abstract protected function canHandle(Command $command): bool;
    abstract protected function doHandle(Command $command): Response;
}
```

### 3. 观察者模式处理事件

```php
namespace App\Observer;

class MessageSentObserver
{
    private array $subscribers = [];
    
    public function subscribe(callable $callback): void
    {
        $this->subscribers[] = $callback;
    }
    
    public function notify(MessageSentEvent $event): void
    {
        foreach ($this->subscribers as $subscriber) {
            try {
                $subscriber($event);
            } catch (\Exception $e) {
                $this->logger->error('Subscriber error', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
```

## 总结

遵循这些最佳实践可以帮助您：

1. **提高代码质量**：通过分层架构和设计模式
2. **增强安全性**：通过适当的验证和权限控制
3. **优化性能**：通过缓存、批量操作和异步处理
4. **改善可维护性**：通过清晰的代码组织和测试策略
5. **提升可靠性**：通过错误处理和监控

记住，这些是建议而非规则，根据您的具体需求进行调整。