<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User\EventHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Event\UserEvent;
use Tourze\LarkAppBotBundle\Service\User\EventHandler\UserEventHandler;
use Tourze\LarkAppBotBundle\Service\User\UserCacheManager;
use Tourze\LarkAppBotBundle\Service\User\UserTracker;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserEventHandler::class)]
#[RunTestsInSeparateProcesses]
final class UserEventHandlerTest extends AbstractIntegrationTestCase
{
    private UserEventHandler $handler;

    private UserCacheManager $cacheManager;

    private UserTracker $userTracker;

    private LoggerInterface $logger;

    public function testHandleUserCreated(): void
    {
        $user = [
            'user_id' => 'user_123',
            'open_id' => 'open_123',
            'name' => 'Test User'];
        $context = ['source' => 'api'];

        $event = new UserEvent(UserEvent::USER_CREATED, $user, $context);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('处理用户创建事件', [
                'user_id' => 'user_123',
                'user_name' => 'Test User'])
        ;

        $this->cacheManager->expects($this->once())
            ->method('warmupUsers')
            ->with(['open_123'])
        ;

        $this->userTracker->expects($this->once())
            ->method('trackActivity')
            ->with('open_123', 'open_id', 'user_created', self::anything())
        ;

        $this->handler->handleUserCreated($event);
    }

    public function testHandleUserUpdated(): void
    {
        $user = [
            'user_id' => 'user_123',
            'open_id' => 'open_123',
            'name' => 'Updated User',
            'department_ids' => ['dept_1', 'dept_2'],
        ];
        $context = [
            'changes' => [
                'name' => ['old' => 'Test User', 'new' => 'Updated User'],
                'department_ids' => ['old' => ['dept_1'], 'new' => ['dept_1', 'dept_2']],
            ],
        ];

        $event = new UserEvent(UserEvent::USER_UPDATED, $user, $context);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('处理用户更新事件', [
                'user_id' => 'user_123',
                'user_name' => 'Updated User',
                'changes' => ['name', 'department_ids'],
            ])
        ;

        $this->cacheManager->expects($this->once())
            ->method('invalidateUser')
            ->with('open_123', 'open_id')
        ;

        $this->cacheManager->expects($this->exactly(2))
            ->method('invalidateSearchCache')
        ;

        $this->userTracker->expects($this->once())
            ->method('trackActivity')
            ->with('open_123', 'open_id', 'user_updated', self::anything())
        ;

        $this->handler->handleUserUpdated($event);
    }

    public function testHandleUserDeleted(): void
    {
        $user = [
            'user_id' => 'user_123',
            'open_id' => 'open_123',
            'union_id' => 'union_123',
            'email' => 'user@example.com',
            'mobile' => '+1234567890',
            'name' => 'Deleted User',
            'department_ids' => ['dept_1'],
        ];
        $context = ['reason' => 'account_closure'];

        $event = new UserEvent(UserEvent::USER_DELETED, $user, $context);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('处理用户删除事件', [
                'user_id' => 'user_123',
                'user_name' => 'Deleted User'])
        ;

        // Expect cache invalidation for all identifiers
        $this->cacheManager->expects($this->exactly(5))
            ->method('invalidateUser')
        ;

        $this->cacheManager->expects($this->once())
            ->method('invalidateSearchCache')
            ->with(['department_id' => 'dept_1'])
        ;

        $this->userTracker->expects($this->once())
            ->method('trackActivity')
            ->with('open_123', 'open_id', 'user_deleted', self::anything())
        ;

        $this->handler->handleUserDeleted($event);
    }

    public function testHandleUserActivity(): void
    {
        $user = [
            'user_id' => 'user_123',
            'open_id' => 'open_123'];
        $context = [
            'activity_type' => 'login',
            'activity_data' => [
                'timestamp' => 1234567890,
                'ip' => '192.168.1.1'],
        ];

        $event = new UserEvent(UserEvent::USER_ACTIVITY, $user, $context);

        $this->userTracker->expects($this->once())
            ->method('trackActivity')
            ->with('open_123', 'open_id', 'login', $context['activity_data'])
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('用户登录', self::anything())
        ;

        $this->handler->handleUserActivity($event);
    }

    public function testHandleUserDataLoaded(): void
    {
        $user = ['user_id' => 'user_123'];
        $context = [
            'full_data' => [
                'profile' => ['name' => 'Test User'],
                'permissions' => ['read', 'write'],
            ],
        ];

        $event = new UserEvent(UserEvent::USER_DATA_LOADED, $user, $context);

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('用户数据已加载', [
                'user_id' => 'user_123',
                'data_keys' => ['profile', 'permissions'],
            ])
        ;

        $this->handler->handleUserDataLoaded($event);
    }

    public function testHandleUserDataUpdated(): void
    {
        $user = [
            'user_id' => 'user_123',
            'open_id' => 'open_123'];
        $context = [
            'custom_data' => ['preference' => 'dark_mode'],
            'old_custom_data' => ['preference' => 'light_mode', 'language' => 'en'],
        ];

        $event = new UserEvent(UserEvent::USER_DATA_UPDATED, $user, $context);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('用户自定义数据已更新', self::callback(function ($data) {
                return 'user_123' === $data['user_id'] && $data['updated_keys'] === ['preference'] && array_values($data['removed_keys']) === ['language'];
            }))
        ;

        $this->cacheManager->expects($this->once())
            ->method('invalidateUser')
            ->with('open_123', 'open_id')
        ;

        $this->handler->handleUserDataUpdated($event);
    }

    public function testHandleUserDataDeleted(): void
    {
        $user = [
            'user_id' => 'user_123',
            'open_id' => 'open_123'];

        $event = new UserEvent(UserEvent::USER_DATA_DELETED, $user, []);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('用户数据已删除', ['user_id' => 'user_123'])
        ;

        $this->cacheManager->expects($this->once())
            ->method('invalidateUser')
            ->with('open_123', 'open_id')
        ;

        $this->handler->handleUserDataDeleted($event);
    }

    public function testHandleUserDataImported(): void
    {
        $user = [
            'user_id' => 'user_123',
            'open_id' => 'open_123'];
        $context = [
            'import_data' => [
                'export_time' => '2023-01-01T00:00:00Z',
                'profile' => ['name' => 'Imported User'],
            ],
        ];

        $event = new UserEvent(UserEvent::USER_DATA_IMPORTED, $user, $context);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('用户数据已导入', [
                'user_id' => 'user_123',
                'import_time' => '2023-01-01T00:00:00Z',
                'data_keys' => ['export_time', 'profile'],
            ])
        ;

        $this->cacheManager->expects($this->once())
            ->method('warmupUsers')
            ->with(['open_123'])
        ;

        $this->handler->handleUserDataImported($event);
    }

    protected function onSetUp(): void
    {
        // 从容器获取服务实例，符合集成测试的架构模式
        $this->handler = self::getService(UserEventHandler::class);

        // 创建 mock 对象用于测试隔离
        $this->cacheManager = self::createMock(UserCacheManager::class);
        $this->userTracker = self::createMock(UserTracker::class);
        $this->logger = self::createMock(LoggerInterface::class);
    }
}
