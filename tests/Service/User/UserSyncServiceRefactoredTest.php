<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncErrorHandler;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncResultCollector;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncStrategyManager;
use Tourze\LarkAppBotBundle\Service\User\Sync\UserDataProcessor;
use Tourze\LarkAppBotBundle\Service\User\Sync\UserEventDispatcher;
use Tourze\LarkAppBotBundle\Service\User\UserServiceInterface;
use Tourze\LarkAppBotBundle\Service\User\UserSyncServiceRefactored;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserSyncServiceRefactored::class)]
#[RunTestsInSeparateProcesses]
final class UserSyncServiceRefactoredTest extends AbstractIntegrationTestCase
{
    private UserSyncServiceRefactored $service;

    private UserServiceInterface&MockObject $userService;

    private SyncStrategyManager&MockObject $strategyManager;

    private UserDataProcessor&MockObject $dataProcessor;

    private SyncResultCollector&MockObject $resultCollector;

    private UserEventDispatcher&MockObject $eventDispatcher;

    private SyncErrorHandler&MockObject $errorHandler;

    private LoggerInterface&MockObject $logger;

    public function testSyncUser(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $userData = ['user_id' => $userId, 'name' => 'Test User'];
        $processedData = ['user_id' => $userId, 'name' => 'Test User', 'processed' => true];

        $this->strategyManager->expects($this->once())
            ->method('needsSync')
            ->with($userId, $userIdType, false)
            ->willReturn(true)
        ;

        $this->errorHandler->expects($this->once())
            ->method('logSyncStart')
            ->with($userId, $userIdType, false)
        ;

        $this->userService->expects($this->once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn($userData)
        ;

        $this->dataProcessor->expects($this->once())
            ->method('getCachedUserData')
            ->with($userId, $userIdType)
            ->willReturn(null)
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
            ->with(null, $userData)
            ->willReturn(true)
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatchUserUpdatedEvent')
            ->with($userId, $userIdType, [], $userData)
        ;

        $this->errorHandler->expects($this->once())
            ->method('logSyncSuccess')
            ->with($userId, $userData)
        ;

        $result = $this->service->syncUser($userId, $userIdType);

        $this->assertSame($processedData, $result);
    }

    public function testSyncUserSkipsWhenNotNeeded(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $cachedData = ['user_id' => $userId, 'name' => 'Cached User'];

        $this->strategyManager->expects($this->once())
            ->method('needsSync')
            ->with($userId, $userIdType, false)
            ->willReturn(false)
        ;

        $this->errorHandler->expects($this->once())
            ->method('logSyncSkipped')
            ->with($userId, $userIdType)
        ;

        $this->dataProcessor->expects($this->once())
            ->method('getCachedUserData')
            ->with($userId, $userIdType)
            ->willReturn($cachedData)
        ;

        $result = $this->service->syncUser($userId, $userIdType);

        $this->assertSame($cachedData, $result);
    }

    public function testBatchSyncUsers(): void
    {
        $userIds = ['u_123', 'u_456'];
        $userIdType = 'user_id';
        $expectedResult = [
            'success' => ['u_123' => ['user_id' => 'u_123']],
            'failed' => ['u_456'],
            'skipped' => []];

        $this->resultCollector->expects($this->once())
            ->method('logBatchSyncStart')
            ->with($userIds, $userIdType, false)
        ;

        $this->resultCollector->expects($this->once())
            ->method('initializeResult')
            ->willReturn(['success' => [], 'failed' => [], 'skipped' => []])
        ;

        $this->resultCollector->expects($this->once())
            ->method('createBatches')
            ->with($userIds)
            ->willReturn([['u_123', 'u_456']])
        ;

        $this->strategyManager->expects($this->once())
            ->method('filterUsersToSync')
            ->with(['u_123', 'u_456'], $userIdType, false)
            ->willReturn(['toSync' => ['u_123'], 'skipped' => ['u_456']])
        ;

        $this->resultCollector->expects($this->once())
            ->method('addSkipped')
            ->with(self::anything(), ['u_456'])
        ;

        $this->userService->expects($this->once())
            ->method('batchGetUsers')
            ->with(['u_123'], $userIdType)
            ->willReturn(['u_123' => ['user_id' => 'u_123', 'name' => 'User 1']])
        ;

        $this->dataProcessor->expects($this->once())
            ->method('getCachedUserData')
            ->with('u_123', $userIdType)
            ->willReturn(null)
        ;

        $this->dataProcessor->expects($this->once())
            ->method('processUserData')
            ->with('u_123', $userIdType, ['user_id' => 'u_123', 'name' => 'User 1'])
            ->willReturn(['user_id' => 'u_123', 'name' => 'User 1', 'processed' => true])
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('shouldDispatchUpdateEvent')
            ->willReturn(false)
        ;

        $this->resultCollector->expects($this->once())
            ->method('addSuccess')
            ->with(self::anything(), 'u_123', self::anything())
        ;

        $this->errorHandler->expects($this->once())
            ->method('handleMissingUsers')
            ->with(['u_123'], ['u_123' => ['user_id' => 'u_123', 'name' => 'User 1']], $userIdType)
            ->willReturn([])
        ;

        $this->resultCollector->expects($this->once())
            ->method('addBatchFailures')
            ->with(self::anything(), [])
        ;

        $this->strategyManager->expects($this->once())
            ->method('batchRecordSyncTime')
            ->with(['u_123'], $userIdType)
        ;

        $this->resultCollector->expects($this->once())
            ->method('logBatchSyncComplete')
            ->with($userIds, self::anything())
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatchBatchSyncCompletedEvent')
            ->with($userIds, $userIdType, self::anything())
        ;

        $result = $this->service->batchSyncUsers($userIds, $userIdType);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('skipped', $result);
    }

    public function testSyncDepartmentUsers(): void
    {
        $departmentId = 'dept_123';
        $searchResult = [
            'items' => [
                ['user' => ['user_id' => 'u_123']],
                ['user' => ['user_id' => 'u_456']],
            ],
            'has_more' => false];

        $this->logger->expects($this->once())
            ->method('info')
            ->with('开始同步部门用户', [
                'department_id' => $departmentId,
                'include_child' => true])
        ;

        $this->userService->expects($this->once())
            ->method('searchUsers')
            ->with([
                'department_id' => $departmentId,
                'page_token' => null,
                'page_size' => 100])
            ->willReturn($searchResult)
        ;

        $this->resultCollector->expects($this->once())
            ->method('logBatchSyncStart')
            ->with(['u_123', 'u_456'], 'user_id', false)
        ;

        $this->resultCollector->expects($this->once())
            ->method('initializeResult')
            ->willReturn(['success' => [], 'failed' => [], 'skipped' => []])
        ;

        $this->resultCollector->expects($this->once())
            ->method('createBatches')
            ->with(['u_123', 'u_456'])
            ->willReturn([['u_123', 'u_456']])
        ;

        $this->strategyManager->expects($this->once())
            ->method('filterUsersToSync')
            ->willReturn(['toSync' => [], 'skipped' => ['u_123', 'u_456']])
        ;

        $this->resultCollector->expects($this->once())
            ->method('addSkipped')
            ->with(self::anything(), ['u_123', 'u_456'])
        ;

        $this->resultCollector->expects($this->once())
            ->method('logBatchSyncComplete')
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatchBatchSyncCompletedEvent')
        ;

        $result = $this->service->syncDepartmentUsers($departmentId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('skipped', $result);
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

    protected function onSetUp(): void
    {
        // 获取服务实例，不再设置 mock
        $this->service = self::getService(UserSyncServiceRefactored::class);
        // 创建 mock 对象
        $this->userService = self::createMock(UserServiceInterface::class);
        $this->strategyManager = self::createMock(SyncStrategyManager::class);
        $this->dataProcessor = self::createMock(UserDataProcessor::class);
        $this->resultCollector = self::createMock(SyncResultCollector::class);
        $this->eventDispatcher = self::createMock(UserEventDispatcher::class);
        $this->errorHandler = self::createMock(SyncErrorHandler::class);
        $this->logger = self::createMock(LoggerInterface::class);
        $this->service = self::getService(UserSyncServiceRefactored::class);
    }
}
