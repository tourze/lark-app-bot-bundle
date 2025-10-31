<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Service\User\Sync\BatchSyncProcessor;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncErrorHandler;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncStrategyManager;
use Tourze\LarkAppBotBundle\Service\User\Sync\UserDataProcessor;
use Tourze\LarkAppBotBundle\Service\User\UserServiceInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(BatchSyncProcessor::class)]
#[RunTestsInSeparateProcesses]
final class BatchSyncProcessorTest extends AbstractIntegrationTestCase
{
    private UserServiceInterface $userService;

    private UserDataProcessor $dataProcessor;

    private SyncStrategyManager $strategyManager;

    private SyncErrorHandler $errorHandler;

    private LoggerInterface $logger;

    private BatchSyncProcessor $processor;

    public function testProcessBatchSyncWithEmptyUserIds(): void
    {
        $result = $this->processor->processBatchSync([], 'user_id', false);

        $this->assertSame([], $result['success']);
        $this->assertSame([], $result['failed']);
        $this->assertSame([], $result['skipped']);
    }

    public function testProcessBatchSyncSuccess(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $userIdType = 'user_id';
        $force = false;

        $this->logger->expects($this->exactly(2))
            ->method('info')
        ;

        $this->strategyManager->expects($this->once())
            ->method('filterUsersToSync')
            ->with($userIds, $userIdType, $force)
            ->willReturn([
                'toSync' => $userIds,
                'skipped' => [],
            ])
        ;

        $userData = [
            'user1' => ['name' => 'User 1', 'email' => 'user1@example.com'],
            'user2' => ['name' => 'User 2', 'email' => 'user2@example.com'],
            'user3' => ['name' => 'User 3', 'email' => 'user3@example.com'],
        ];

        $this->userService->expects($this->once())
            ->method('batchGetUsers')
            ->with($userIds, $userIdType)
            ->willReturn($userData)
        ;

        $processedData = [
            'user1' => ['processed' => true, 'name' => 'User 1'],
            'user2' => ['processed' => true, 'name' => 'User 2'],
            'user3' => ['processed' => true, 'name' => 'User 3'],
        ];

        $this->dataProcessor->expects($this->exactly(3))
            ->method('processUserData')
            ->willReturnCallback(function ($userId) use ($processedData) {
                return $processedData[$userId];
            })
        ;

        $this->strategyManager->expects($this->exactly(3))
            ->method('recordSyncTime')
        ;

        $result = $this->processor->processBatchSync($userIds, $userIdType, $force);

        $this->assertIsArray($result);
        $this->assertCount(3, $result['success']);
        $this->assertSame($processedData['user1'], $result['success']['user1']);
        $this->assertSame($processedData['user2'], $result['success']['user2']);
        $this->assertSame($processedData['user3'], $result['success']['user3']);
        $this->assertEmpty($result['failed']);
        $this->assertEmpty($result['skipped']);
    }

    public function testProcessBatchSyncWithSkippedUsers(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $userIdType = 'user_id';
        $force = false;

        $this->strategyManager->expects($this->once())
            ->method('filterUsersToSync')
            ->with($userIds, $userIdType, $force)
            ->willReturn([
                'toSync' => ['user1', 'user2'],
                'skipped' => ['user3'],
            ])
        ;

        $userData = [
            'user1' => ['name' => 'User 1'],
            'user2' => ['name' => 'User 2'],
        ];

        $this->userService->expects($this->once())
            ->method('batchGetUsers')
            ->with(['user1', 'user2'], $userIdType)
            ->willReturn($userData)
        ;

        $this->dataProcessor->expects($this->exactly(2))
            ->method('processUserData')
            ->willReturn(['processed' => true])
        ;

        $this->strategyManager->expects($this->exactly(2))
            ->method('recordSyncTime')
        ;

        $result = $this->processor->processBatchSync($userIds, $userIdType, $force);

        $this->assertIsArray($result);
        $this->assertCount(2, $result['success']);
        $this->assertEmpty($result['failed']);
        $this->assertSame(['user3'], $result['skipped']);
    }

    public function testProcessBatchSyncWithMissingUsers(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $userIdType = 'user_id';

        $this->strategyManager->expects($this->once())
            ->method('filterUsersToSync')
            ->willReturn([
                'toSync' => $userIds,
                'skipped' => [],
            ])
        ;

        // 只返回部分用户，模拟某些用户已被删除
        $userData = [
            'user1' => ['name' => 'User 1'],
            'user2' => ['name' => 'User 2'],
            // user3 缺失
        ];

        $this->userService->expects($this->once())
            ->method('batchGetUsers')
            ->willReturn($userData)
        ;

        $this->dataProcessor->expects($this->exactly(2))
            ->method('processUserData')
            ->willReturn(['processed' => true])
        ;

        $this->dataProcessor->expects($this->once())
            ->method('handleDeletedUser')
            ->with('user3', $userIdType)
        ;

        $result = $this->processor->processBatchSync($userIds, $userIdType, false);

        $this->assertIsArray($result);
        $this->assertCount(2, $result['success']);
        $this->assertSame(['user3'], $result['failed']);
        $this->assertEmpty($result['skipped']);
    }

    public function testProcessBatchSyncWithUserServiceException(): void
    {
        $userIds = ['user1', 'user2'];
        $userIdType = 'user_id';

        $this->strategyManager->expects($this->once())
            ->method('filterUsersToSync')
            ->willReturn([
                'toSync' => $userIds,
                'skipped' => [],
            ])
        ;

        $exception = new \RuntimeException('Service unavailable');
        $this->userService->expects($this->once())
            ->method('batchGetUsers')
            ->willThrowException($exception)
        ;

        $this->errorHandler->expects($this->once())
            ->method('handleBatchSyncErrorWithFailedRef')
            ->with($userIds, $exception, self::anything())
        ;

        $result = $this->processor->processBatchSync($userIds, $userIdType, false);

        $this->assertEmpty($result['success']);
        $this->assertEmpty($result['skipped']);
    }

    public function testProcessBatchSyncWithDataProcessorException(): void
    {
        $userIds = ['user1', 'user2'];
        $userIdType = 'user_id';

        $this->strategyManager->expects($this->once())
            ->method('filterUsersToSync')
            ->willReturn([
                'toSync' => $userIds,
                'skipped' => [],
            ])
        ;

        $userData = [
            'user1' => ['name' => 'User 1'],
            'user2' => ['name' => 'User 2'],
        ];

        $this->userService->expects($this->once())
            ->method('batchGetUsers')
            ->willReturn($userData)
        ;

        $exception = new \InvalidArgumentException('Invalid user data');
        $this->dataProcessor->expects($this->exactly(2))
            ->method('processUserData')
            ->willReturnCallback(function ($userId) use ($exception) {
                if ('user1' === $userId) {
                    return ['processed' => true];
                }
                if ('user2' === $userId) {
                    throw $exception;
                }

                return [];
            })
        ;

        $this->strategyManager->expects($this->once())
            ->method('recordSyncTime')
            ->with('user1', $userIdType)
        ;

        $this->errorHandler->expects($this->once())
            ->method('handleSingleUserError')
            ->with('user2', $exception, self::anything())
        ;

        $result = $this->processor->processBatchSync($userIds, $userIdType, false);

        $this->assertIsArray($result);
        $this->assertCount(1, $result['success']);
        $this->assertArrayHasKey('user1', $result['success']);
        $this->assertEmpty($result['skipped']);
    }

    public function testProcessBatchSyncWithForceFlag(): void
    {
        $userIds = ['user1'];
        $userIdType = 'user_id';
        $force = true;

        $this->strategyManager->expects($this->once())
            ->method('filterUsersToSync')
            ->with($userIds, $userIdType, $force)
            ->willReturn([
                'toSync' => $userIds,
                'skipped' => [],
            ])
        ;

        $this->userService->expects($this->once())
            ->method('batchGetUsers')
            ->willReturn(['user1' => ['name' => 'User 1']])
        ;

        $this->dataProcessor->expects($this->once())
            ->method('processUserData')
            ->willReturn(['processed' => true])
        ;

        $result = $this->processor->processBatchSync($userIds, $userIdType, $force);

        $this->assertIsArray($result);
        $this->assertCount(1, $result['success']);
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象
        $this->userService = self::createMock(UserServiceInterface::class);
        $this->dataProcessor = self::createMock(UserDataProcessor::class);
        $this->strategyManager = self::createMock(SyncStrategyManager::class);
        $this->errorHandler = self::createMock(SyncErrorHandler::class);
        $this->logger = self::createMock(LoggerInterface::class);

        // @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass (需要直接实例化以避免容器服务已初始化的问题)
        $this->processor = new BatchSyncProcessor(
            $this->userService,
            $this->dataProcessor,
            $this->strategyManager,
            $this->errorHandler,
            $this->logger
        );
    }
}
