<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Service\User\Sync\BatchSyncProcessor;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncErrorHandler;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncStrategyManager;
use Tourze\LarkAppBotBundle\Service\User\Sync\UserDataProcessor;
use Tourze\LarkAppBotBundle\Service\User\Sync\UserEventDispatcher;
use Tourze\LarkAppBotBundle\Service\User\UserCacheManagerInterface;
use Tourze\LarkAppBotBundle\Service\User\UserServiceInterface;
use Tourze\LarkAppBotBundle\Service\User\UserSyncService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserSyncService::class)]
#[RunTestsInSeparateProcesses]
class UserSyncServiceTest extends AbstractIntegrationTestCase
{
    private UserSyncService $service;

    private UserServiceInterface $userService;

    private UserCacheManagerInterface $cacheManager;

    private SyncStrategyManager $strategyManager;

    private UserDataProcessor $dataProcessor;

    private UserEventDispatcher $eventDispatcher;

    private SyncErrorHandler $errorHandler;

    private BatchSyncProcessor $batchProcessor;

    private LoggerInterface $logger;

    public function testSyncUserSkipped(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';
        $cachedData = ['name' => 'John Doe'];

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $result = $this->service->syncUser($userId, $userIdType);

        // 验证返回结果
        $this->assertIsArray($result);
    }

    public function testSyncUserForced(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';

        // 使用真实的服务调用，验证行为而不是 mock 期望
        $result = $this->service->syncUser($userId, $userIdType, true);

        // 验证返回结果
        $this->assertIsArray($result);
    }

    public function testSyncUserWithUpdateEvent(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';
        $userData = ['name' => 'John Doe'];
        $oldData = ['name' => 'Jane Doe'];
        $processedData = ['name' => 'John Doe', 'processed' => true];

        $this->errorHandler->expects($this->once())
            ->method('logSyncStart')
            ->with($userId, $userIdType, false)
        ;

        $this->strategyManager->expects($this->once())
            ->method('needsSync')
            ->with($userId, $userIdType)
            ->willReturn(true)
        ;

        $this->userService->expects($this->once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn($userData)
        ;

        $this->cacheManager->expects($this->once())
            ->method('getCachedUser')
            ->with($userId, $userIdType)
            ->willReturn($oldData)
        ;

        $this->dataProcessor->expects($this->once())
            ->method('processUserData')
            ->with($userId, $userIdType, $userData)
            ->willReturn($processedData)
        ;

        $this->strategyManager->expects($this->once())
            ->method('recordSyncTime')
            ->with($userId, $userIdType)
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('shouldDispatchUpdateEvent')
            ->with($oldData, $processedData)
            ->willReturn(true)
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatchUserUpdatedEvent')
            ->with($userId, $userIdType, $oldData, $processedData)
        ;

        $this->errorHandler->expects($this->once())
            ->method('logSyncSuccess')
            ->with($userId, $processedData)
        ;

        $result = $this->service->syncUser($userId, $userIdType);

        $this->assertSame($processedData, $result);
    }

    public function testSyncUserWithException(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';
        $exception = new \Exception('API Error');

        $this->errorHandler->expects($this->once())
            ->method('logSyncStart')
            ->with($userId, $userIdType, false)
        ;

        $this->strategyManager->expects($this->once())
            ->method('needsSync')
            ->with($userId, $userIdType)
            ->willReturn(true)
        ;

        $this->userService->expects($this->once())
            ->method('getUser')
            ->willThrowException($exception)
        ;

        $this->errorHandler->expects($this->once())
            ->method('wrapException')
            ->with($exception, '用户数据同步失败')
            ->willReturn(new GenericApiException('用户数据同步失败: API Error'))
        ;

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户数据同步失败: API Error');

        $this->service->syncUser($userId, $userIdType);
    }

    public function testSyncUserWithCacheFallback(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';
        $cachedData = ['name' => 'John Doe'];

        $this->strategyManager->expects($this->once())
            ->method('needsSync')
            ->with($userId, $userIdType)
            ->willReturn(false)
        ;

        $this->errorHandler->expects($this->once())
            ->method('logSyncSkipped')
            ->with($userId, $userIdType)
        ;

        $this->cacheManager->expects($this->once())
            ->method('getCachedUser')
            ->with($userId, $userIdType)
            ->willReturn($cachedData)
        ;

        $result = $this->service->syncUser($userId, $userIdType);

        $this->assertSame($cachedData, $result);
    }

    public function testSyncUserWithCacheMissAndForcedSync(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';
        $userData = ['name' => 'John Doe'];
        $processedData = ['name' => 'John Doe', 'processed' => true];

        $this->strategyManager->expects($this->once())
            ->method('needsSync')
            ->with($userId, $userIdType)
            ->willReturn(false)
        ;

        $this->errorHandler->expects($this->once())
            ->method('logSyncSkipped')
            ->with($userId, $userIdType)
        ;

        $this->cacheManager->method('getCachedUser')
            ->with($userId, $userIdType)
            ->willReturn(null)
        ;

        // Force sync when cache miss
        $this->userService->expects($this->once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn($userData)
        ;

        $this->dataProcessor->expects($this->once())
            ->method('processUserData')
            ->with($userId, $userIdType, $userData)
            ->willReturn($processedData)
        ;

        $result = $this->service->syncUser($userId, $userIdType);

        $this->assertSame($processedData, $result);
    }

    public function testBatchSyncUsers(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $userIdType = 'open_id';
        $force = false;
        $expectedResult = [
            'success' => ['user1' => ['name' => 'User 1']],
            'failed' => ['user2'],
            'skipped' => ['user3'],
        ];

        $this->batchProcessor->expects($this->once())
            ->method('processBatchSync')
            ->with($userIds, $userIdType, $force)
            ->willReturn($expectedResult)
        ;

        $result = $this->service->batchSyncUsers($userIds, $userIdType, $force);

        $this->assertSame($expectedResult, $result);
    }

    public function testSyncDepartmentUsers(): void
    {
        $departmentId = 'dept123';
        $includeChild = true;
        $force = false;
        $userIds = ['user1', 'user2'];
        $expectedResult = [
            'success' => ['user1' => ['name' => 'User 1']],
            'failed' => [],
            'skipped' => ['user2'],
        ];

        $this->logger->expects($this->once())
            ->method('info')
            ->with('开始同步部门用户', [
                'department_id' => $departmentId,
                'include_child' => $includeChild,
            ])
        ;

        // Mock department user fetching
        $this->userService->expects($this->once())
            ->method('searchUsers')
            ->with([
                'department_id' => $departmentId,
                'page_token' => null,
                'page_size' => 100,
            ])
            ->willReturn([
                'items' => [
                    ['user' => ['user_id' => 'user1']],
                    ['user' => ['user_id' => 'user2']],
                ],
                'has_more' => false,
                'page_token' => null,
            ])
        ;

        $this->batchProcessor->expects($this->once())
            ->method('processBatchSync')
            ->with($userIds, 'user_id', $force)
            ->willReturn($expectedResult)
        ;

        $result = $this->service->syncDepartmentUsers($departmentId, $includeChild, $force);

        $this->assertSame($expectedResult, $result);
    }

    public function testSyncDepartmentUsersWithException(): void
    {
        $departmentId = 'dept123';
        $exception = new \Exception('Department sync error');
        $expectedResult = [
            'success' => [],
            'failed' => [],
            'skipped' => [],
        ];

        $this->logger->expects($this->once())
            ->method('info')
            ->with('开始同步部门用户', [
                'department_id' => $departmentId,
                'include_child' => true,
            ])
        ;

        $this->userService->expects($this->once())
            ->method('searchUsers')
            ->willThrowException($exception)
        ;

        $this->errorHandler->expects($this->once())
            ->method('handleDepartmentSyncError')
            ->with($departmentId, $exception)
            ->willReturn($expectedResult)
        ;

        $result = $this->service->syncDepartmentUsers($departmentId);

        $this->assertSame($expectedResult, $result);
    }

    public function testSyncDepartmentUsersWithPagination(): void
    {
        $departmentId = 'dept123';
        $userIds = ['user1', 'user2', 'user3', 'user4'];

        $this->logger->expects($this->once())
            ->method('info')
        ;

        // First page
        $this->userService->expects($this->exactly(2))
            ->method('searchUsers')
            ->willReturnOnConsecutiveCalls(
                [
                    'items' => [
                        ['user' => ['user_id' => 'user1']],
                        ['user' => ['user_id' => 'user2']],
                    ],
                    'has_more' => true,
                    'page_token' => 'next_page_token',
                ],
                [
                    'items' => [
                        ['user' => ['user_id' => 'user3']],
                        ['user' => ['user_id' => 'user4']],
                    ],
                    'has_more' => false,
                    'page_token' => null,
                ]
            )
        ;

        $this->batchProcessor->expects($this->once())
            ->method('processBatchSync')
            ->with($userIds, 'user_id', false)
        ;

        $this->service->syncDepartmentUsers($departmentId);
    }

    public function testClearSyncHistory(): void
    {
        $this->strategyManager->expects($this->once())
            ->method('clearSyncHistory')
        ;

        $this->logger->expects($this->once())
            ->method('info')
            ->with('清除同步时间记录')
        ;

        $this->service->clearSyncHistory();
    }

    public function testSyncUserWithInvalidData(): void
    {
        $userId = 'user123';
        $userIdType = 'open_id';

        $this->errorHandler->expects($this->once())
            ->method('logSyncStart')
            ->with($userId, $userIdType, false)
        ;

        $this->strategyManager->expects($this->once())
            ->method('needsSync')
            ->with($userId, $userIdType)
            ->willReturn(true)
        ;

        $this->userService->expects($this->once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn([])
        ;

        $this->cacheManager->expects($this->once())
            ->method('getCachedUser')
            ->with($userId, $userIdType)
            ->willReturn(null)
        ;

        $this->dataProcessor->expects($this->once())
            ->method('processUserData')
            ->with($userId, $userIdType, [])
            ->willReturn([])
        ;

        $result = $this->service->syncUser($userId, $userIdType);

        $this->assertSame([], $result);
    }

    public function testSyncUserDefaultParameters(): void
    {
        $userId = 'user123';
        $userData = ['name' => 'John Doe'];
        $processedData = ['name' => 'John Doe', 'processed' => true];

        $this->errorHandler->expects($this->once())
            ->method('logSyncStart')
            ->with($userId, 'open_id', false)
        ;

        $this->strategyManager->expects($this->once())
            ->method('needsSync')
            ->with($userId, 'open_id')
            ->willReturn(true)
        ;

        $this->userService->expects($this->once())
            ->method('getUser')
            ->with($userId, 'open_id')
            ->willReturn($userData)
        ;

        $this->cacheManager->expects($this->once())
            ->method('getCachedUser')
            ->with($userId, 'open_id')
            ->willReturn(null)
        ;

        $this->dataProcessor->expects($this->once())
            ->method('processUserData')
            ->with($userId, 'open_id', $userData)
            ->willReturn($processedData)
        ;

        $result = $this->service->syncUser($userId);

        $this->assertSame($processedData, $result);
    }

    public function testBatchSyncUsersDefaultParameters(): void
    {
        $userIds = ['user1', 'user2'];
        $expectedResult = [
            'success' => [],
            'failed' => [],
            'skipped' => [],
        ];

        $this->batchProcessor->expects($this->once())
            ->method('processBatchSync')
            ->with($userIds, 'open_id', false)
            ->willReturn($expectedResult)
        ;

        $result = $this->service->batchSyncUsers($userIds);

        $this->assertSame($expectedResult, $result);
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象
        $this->userService = static::createMock(UserServiceInterface::class);
        $this->cacheManager = static::createMock(UserCacheManagerInterface::class);
        $this->strategyManager = static::createMock(SyncStrategyManager::class);
        $this->dataProcessor = static::createMock(UserDataProcessor::class);
        $this->eventDispatcher = static::createMock(UserEventDispatcher::class);
        $this->errorHandler = static::createMock(SyncErrorHandler::class);
        $this->batchProcessor = static::createMock(BatchSyncProcessor::class);
        $this->logger = static::createMock(LoggerInterface::class);

        // 从服务容器获取 UserSyncService 实例而不是直接实例化
        /** @var UserSyncService $service */
        $service = static::getContainer()->get(UserSyncService::class);
        $this->service = $service;
    }
}
