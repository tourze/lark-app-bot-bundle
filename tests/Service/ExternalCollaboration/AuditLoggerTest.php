<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\ExternalCollaboration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tourze\LarkAppBotBundle\Event\GenericEvent;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\AuditLogger;
use Tourze\LarkAppBotBundle\Service\ExternalCollaboration\ExternalUserIdentifier;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AuditLogger::class)]
#[RunTestsInSeparateProcesses]
final class AuditLoggerTest extends AbstractIntegrationTestCase
{
    private AuditLogger $auditLogger;

    private LoggerInterface $logger;

    private EventDispatcherInterface $eventDispatcher;

    private ExternalUserIdentifier $userIdentifier;

    private RequestStack $requestStack;

    public function testLogBasicAuditEntry(): void
    {
        // 准备
        $userId = 'test_user_123';
        $eventType = AuditLogger::EVENT_EXTERNAL_USER_LOGIN;
        $data = ['test_key' => 'test_value'];

        $this->userIdentifier
            ->expects($this->once())
            ->method('isExternalUser')
            ->with($userId)
            ->willReturn(true)
        ;

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(self::stringContains('Audit: '), self::isArray())
        ;

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(self::isInstanceOf(GenericEvent::class))
        ;

        // AuditLogger 实例已经在 setUp 中创建好了

        // 执行
        $this->auditLogger->log($eventType, $userId, $data);

        // 验证 - 通过查询来验证日志记录
        $logs = $this->auditLogger->query(['user_id' => $userId]);
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertIsArray($logs[0]);
        $this->assertSame($eventType, $logs[0]['event_type']);
        $this->assertSame($userId, $logs[0]['user_id']);
        $this->assertTrue($logs[0]['is_external']);
        $this->assertSame($data, $logs[0]['data']);
    }

    public function testLogWithDifferentLevels(): void
    {
        // 测试不同日志级别
        $testCases = [
            ['level' => AuditLogger::LEVEL_INFO, 'method' => 'info'],
            ['level' => AuditLogger::LEVEL_WARNING, 'method' => 'warning'],
            ['level' => AuditLogger::LEVEL_ERROR, 'method' => 'error'],
            ['level' => AuditLogger::LEVEL_CRITICAL, 'method' => 'critical']];

        foreach ($testCases as $index => $testCase) {
            $userId = 'user_' . $index;

            $this->userIdentifier
                ->method('isExternalUser')
                ->willReturn(false)
            ;

            $this->logger
                ->expects($this->once())
                ->method($testCase['method'])
                ->with(self::stringContains('Audit: '), self::isArray())
            ;

            $this->eventDispatcher
                ->method('dispatch')
            ;

            $this->auditLogger->log(
                AuditLogger::EVENT_EXTERNAL_USER_ACCESS,
                $userId,
                [],
                $testCase['level']
            );
        }
    }

    public function testLogExternalUserLoginSuccess(): void
    {
        // 准备
        $userId = 'ou_external_user123';
        $details = ['login_method' => 'oauth'];

        $this->userIdentifier
            ->method('isExternalUser')
            ->willReturn(true)
        ;

        $this->logger
            ->expects($this->once())
            ->method('info')
        ;

        $this->eventDispatcher
            ->method('dispatch')
        ;

        // 执行
        $this->auditLogger->logExternalUserLogin($userId, true, $details);

        // 验证
        $logs = $this->auditLogger->query(['event_type' => AuditLogger::EVENT_EXTERNAL_USER_LOGIN]);
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertIsArray($logs[0]);
        $this->assertArrayHasKey('data', $logs[0]);
        $this->assertIsArray($logs[0]['data']);
        $this->assertTrue($logs[0]['data']['success']);
        $this->assertSame('oauth', $logs[0]['data']['login_method']);
        $this->assertSame(AuditLogger::LEVEL_INFO, $logs[0]['level']);
    }

    public function testLogExternalUserLoginFailure(): void
    {
        // 准备
        $userId = 'ou_external_user123';
        $details = ['error' => 'invalid_credentials'];

        $this->userIdentifier
            ->method('isExternalUser')
            ->willReturn(true)
        ;

        $this->logger
            ->expects($this->once())
            ->method('warning')
        ;

        $this->eventDispatcher
            ->method('dispatch')
        ;

        // AuditLogger 实例已经在 setUp 中创建好了

        // 执行
        $this->auditLogger->logExternalUserLogin($userId, false, $details);

        // 验证
        $logs = $this->auditLogger->query(['event_type' => AuditLogger::EVENT_EXTERNAL_USER_LOGIN]);
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertIsArray($logs[0]);
        $this->assertArrayHasKey('data', $logs[0]);
        $this->assertIsArray($logs[0]['data']);
        $this->assertFalse($logs[0]['data']['success']);
        $this->assertSame('invalid_credentials', $logs[0]['data']['error']);
        $this->assertSame(AuditLogger::LEVEL_WARNING, $logs[0]['level']);
    }

    public function testLogExternalUserAccessAllowed(): void
    {
        // 准备
        $userId = 'ou_external_user123';
        $resource = '/api/public/data';

        $this->userIdentifier
            ->method('isExternalUser')
            ->willReturn(true)
        ;

        $this->logger
            ->expects($this->once())
            ->method('info')
        ;

        $this->eventDispatcher
            ->method('dispatch')
        ;

        // AuditLogger 实例已经在 setUp 中创建好了

        // 执行
        $this->auditLogger->logExternalUserAccess($userId, $resource, true);

        // 验证
        $logs = $this->auditLogger->query(['event_type' => AuditLogger::EVENT_EXTERNAL_USER_ACCESS]);
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertIsArray($logs[0]);
        $this->assertArrayHasKey('data', $logs[0]);
        $this->assertIsArray($logs[0]['data']);
        $this->assertSame($resource, $logs[0]['data']['resource']);
        $this->assertTrue($logs[0]['data']['allowed']);
        $this->assertSame(AuditLogger::LEVEL_INFO, $logs[0]['level']);
    }

    public function testLogExternalUserAccessDenied(): void
    {
        // 准备
        $userId = 'ou_external_user123';
        $resource = '/api/internal/sensitive';

        $this->userIdentifier
            ->method('isExternalUser')
            ->willReturn(true)
        ;

        $this->logger
            ->expects($this->once())
            ->method('warning')
        ;

        $this->eventDispatcher
            ->method('dispatch')
        ;

        // AuditLogger 实例已经在 setUp 中创建好了

        // 执行
        $this->auditLogger->logExternalUserAccess($userId, $resource, false);

        // 验证
        $logs = $this->auditLogger->query(['event_type' => AuditLogger::EVENT_EXTERNAL_USER_DENIED]);
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertIsArray($logs[0]);
        $this->assertArrayHasKey('data', $logs[0]);
        $this->assertIsArray($logs[0]['data']);
        $this->assertSame($resource, $logs[0]['data']['resource']);
        $this->assertFalse($logs[0]['data']['allowed']);
        $this->assertSame(AuditLogger::LEVEL_WARNING, $logs[0]['level']);
    }

    public function testLogPermissionChange(): void
    {
        // 准备
        $targetUserId = 'ou_external_user123';
        $operatorId = 'internal_admin456';
        $changes = [
            'permissions' => ['read' => true, 'write' => false],
            'previous_permissions' => ['read' => false, 'write' => true]];

        $this->userIdentifier
            ->method('isExternalUser')
            ->willReturn(false)
        ;

        $this->logger
            ->expects($this->once())
            ->method('warning')
        ;

        $this->eventDispatcher
            ->method('dispatch')
        ;

        // AuditLogger 实例已经在 setUp 中创建好了

        // 执行
        $this->auditLogger->logPermissionChange($targetUserId, $operatorId, $changes);

        // 验证
        $logs = $this->auditLogger->query(['event_type' => AuditLogger::EVENT_PERMISSION_CHANGED]);
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertIsArray($logs[0]);
        $this->assertArrayHasKey('data', $logs[0]);
        $this->assertIsArray($logs[0]['data']);
        $this->assertSame($targetUserId, $logs[0]['data']['target_user_id']);
        $this->assertSame($changes, $logs[0]['data']['changes']);
        $this->assertSame(AuditLogger::LEVEL_WARNING, $logs[0]['level']);
    }

    public function testLogSecurityViolation(): void
    {
        // 准备
        $userId = 'ou_external_user123';
        $violationType = 'brute_force_attempt';
        $details = ['attempts' => 10, 'ip' => '192.168.1.100'];

        $this->userIdentifier
            ->method('isExternalUser')
            ->willReturn(true)
        ;

        $this->logger
            ->expects($this->once())
            ->method('critical')
        ;

        $this->eventDispatcher
            ->expects($this->exactly(2))  // 一次普通事件，一次关键事件
            ->method('dispatch')
            ->with(self::isInstanceOf(GenericEvent::class))
        ;

        // AuditLogger 实例已经在 setUp 中创建好了

        // 执行
        $this->auditLogger->logSecurityViolation($userId, $violationType, $details);

        // 验证
        $logs = $this->auditLogger->query(['event_type' => AuditLogger::EVENT_SECURITY_VIOLATION]);
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertIsArray($logs[0]);
        $this->assertArrayHasKey('data', $logs[0]);
        $this->assertIsArray($logs[0]['data']);
        $this->assertSame($violationType, $logs[0]['data']['violation_type']);
        $this->assertSame(10, $logs[0]['data']['attempts']);
        $this->assertSame(AuditLogger::LEVEL_CRITICAL, $logs[0]['level']);
    }

    public function testQueryWithCriteria(): void
    {
        // 准备多条日志
        $this->userIdentifier->method('isExternalUser')->willReturn(true);
        $this->logger->method('info');
        $this->eventDispatcher->method('dispatch');

        $this->auditLogger->log(AuditLogger::EVENT_EXTERNAL_USER_LOGIN, 'user1', ['type' => 'A']);
        $this->auditLogger->log(AuditLogger::EVENT_EXTERNAL_USER_ACCESS, 'user1', ['type' => 'B']);
        $this->auditLogger->log(AuditLogger::EVENT_EXTERNAL_USER_LOGIN, 'user2', ['type' => 'A']);

        // 测试按用户查询
        $logs = $this->auditLogger->query(['user_id' => 'user1']);
        $this->assertIsArray($logs);
        $this->assertCount(2, $logs);

        // 测试按事件类型查询
        $logs = $this->auditLogger->query(['event_type' => AuditLogger::EVENT_EXTERNAL_USER_LOGIN]);
        $this->assertCount(2, $logs);

        // 测试分页
        $logs = $this->auditLogger->query([], 1, 0);
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);

        $logs = $this->auditLogger->query([], 1, 1);
        $this->assertCount(1, $logs);
    }

    public function testGetUserAuditLogs(): void
    {
        // 准备
        $userId = 'test_user';
        $this->userIdentifier->method('isExternalUser')->willReturn(false);
        $this->logger->method('info');
        $this->eventDispatcher->method('dispatch');

        $this->auditLogger->log(AuditLogger::EVENT_EXTERNAL_USER_LOGIN, $userId);
        $this->auditLogger->log(AuditLogger::EVENT_EXTERNAL_USER_ACCESS, 'other_user');

        // 执行
        $logs = $this->auditLogger->getUserAuditLogs($userId);

        // 验证
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertSame($userId, $logs[0]['user_id']);
    }

    public function testGetStatistics(): void
    {
        // 准备
        $this->userIdentifier->method('isExternalUser')->willReturn(true);
        $this->logger->method('info');
        $this->logger->method('warning');
        $this->logger->method('critical');
        $this->eventDispatcher->method('dispatch');

        $this->auditLogger->log(AuditLogger::EVENT_EXTERNAL_USER_LOGIN, 'user1');
        $this->auditLogger->log(AuditLogger::EVENT_SECURITY_VIOLATION, 'user2');
        $this->auditLogger->log(AuditLogger::EVENT_PERMISSION_CHANGED, 'user3');

        // 执行
        $stats = $this->auditLogger->getStatistics();

        // 验证
        $this->assertSame(3, $stats['total_events']);
        $this->assertSame(3, $stats['external_user_events']); // 所有用户都是外部用户
        $this->assertSame(1, $stats['security_violations']);
        $this->assertSame(1, $stats['permission_changes']);
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('events_by_type', $stats);
        $this->assertArrayHasKey('events_by_level', $stats);
    }

    public function testExportAsJson(): void
    {
        // 准备
        $this->userIdentifier->method('isExternalUser')->willReturn(false);
        $this->logger->method('info');
        $this->eventDispatcher->method('dispatch');

        $this->auditLogger->log(AuditLogger::EVENT_EXTERNAL_USER_LOGIN, 'user1', ['test' => 'data']);

        // 执行
        $export = $this->auditLogger->export();

        // 验证
        $this->assertJson($export);
        $data = json_decode($export, true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertIsArray($data[0]);
        $this->assertSame('user1', $data[0]['user_id']);
    }

    public function testExportAsCsv(): void
    {
        // 准备
        $this->userIdentifier->method('isExternalUser')->willReturn(false);
        $this->logger->method('info');
        $this->eventDispatcher->method('dispatch');

        $this->auditLogger->log(AuditLogger::EVENT_EXTERNAL_USER_LOGIN, 'user1');

        // 执行
        $export = $this->auditLogger->export([], 'csv');

        // 验证
        $this->assertIsString($export);
        $this->assertStringContainsString('user_id', $export);
        $this->assertStringContainsString('user1', $export);
    }

    public function testLogWithHttpRequest(): void
    {
        // 准备 HTTP 请求
        $request = Request::create(
            '/test/path',
            'POST',
            [],
            [],
            [],
            ['HTTP_USER_AGENT' => 'Test Browser', 'REMOTE_ADDR' => '192.168.1.1']
        );
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->userIdentifier->method('isExternalUser')->willReturn(false);
        $this->logger->method('info');
        $this->eventDispatcher->method('dispatch');

        // 执行
        $this->auditLogger->log(AuditLogger::EVENT_EXTERNAL_USER_LOGIN, 'user1');

        // 验证请求信息被记录
        $logs = $this->auditLogger->query(['user_id' => 'user1']);
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        $this->assertIsArray($logs[0]);
        $this->assertSame('192.168.1.1', $logs[0]['ip_address']);
        $this->assertSame('Test Browser', $logs[0]['user_agent']);
        $this->assertArrayHasKey('environment', $logs[0]);
        $this->assertIsArray($logs[0]['environment']);
        $this->assertSame('POST', $logs[0]['environment']['request_method']);
        $this->assertSame('/test/path', $logs[0]['environment']['request_uri']);
    }

    public function testCriticalEventTriggersAdditionalEvent(): void
    {
        // 准备
        $this->userIdentifier->method('isExternalUser')->willReturn(false);
        $this->logger->method('critical');

        // 期望触发两个事件：普通审计事件 + 关键事件
        $this->eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with(self::callback(function ($event) {
                return $event instanceof GenericEvent
                       && \in_array($event->getEventType(), ['audit.log.created', 'audit.critical.event'], true);
            }))
        ;

        // 执行
        $this->auditLogger->log(
            AuditLogger::EVENT_SECURITY_VIOLATION,
            'user1',
            [],
            AuditLogger::LEVEL_CRITICAL
        );
    }

    public function testEmptyExportReturnsEmptyString(): void
    {
        // 执行 CSV 导出（没有数据）
        $export = $this->auditLogger->export([], 'csv');

        // 验证
        $this->assertSame('', $export);
    }

    protected function onSetUp(): void
    {
        // 从容器获取 AuditLogger 服务
        $auditLogger = self::getContainer()->get(AuditLogger::class);
        self::assertInstanceOf(AuditLogger::class, $auditLogger);
        $this->auditLogger = $auditLogger;

        // 创建 mock 对象用于测试
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->userIdentifier = $this->createMock(ExternalUserIdentifier::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        // 通过反射替换依赖为 Mock 版本
        $reflection = new \ReflectionClass($this->auditLogger);

        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->auditLogger, $this->logger);

        $eventDispatcherProperty = $reflection->getProperty('eventDispatcher');
        $eventDispatcherProperty->setAccessible(true);
        $eventDispatcherProperty->setValue($this->auditLogger, $this->eventDispatcher);

        $userIdentifierProperty = $reflection->getProperty('userIdentifier');
        $userIdentifierProperty->setAccessible(true);
        $userIdentifierProperty->setValue($this->auditLogger, $this->userIdentifier);

        $requestStackProperty = $reflection->getProperty('requestStack');
        $requestStackProperty->setAccessible(true);
        $requestStackProperty->setValue($this->auditLogger, $this->requestStack);
    }
}
