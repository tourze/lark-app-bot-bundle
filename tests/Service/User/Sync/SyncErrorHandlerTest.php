<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncErrorHandler;
use Tourze\LarkAppBotBundle\Service\User\Sync\UserDataProcessor;
use Tourze\LarkAppBotBundle\Service\User\Sync\UserEventDispatcher;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(SyncErrorHandler::class)]
#[RunTestsInSeparateProcesses]
final class SyncErrorHandlerTest extends AbstractIntegrationTestCase
{
    private LoggerInterface $logger;

    private UserDataProcessor $dataProcessor;

    private UserEventDispatcher $eventDispatcher;

    private SyncErrorHandler $handler;

    public function testHandleSingleUserError(): void
    {
        $userId = 'user123';
        $exception = new \RuntimeException('User sync failed');
        $failed = ['existing_user'];

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                '同步单个用户失败',
                [
                    'user_id' => $userId,
                    'error' => 'User sync failed',
                    'error_type' => \RuntimeException::class]
            )
        ;

        $this->handler->handleSingleUserError($userId, $exception, $failed);

        $this->assertContains($userId, $failed);
        $this->assertContains('existing_user', $failed);
    }

    public function testHandleBatchSyncErrorWithFailedRef(): void
    {
        $userIds = ['user1', 'user2', 'user3'];
        $exception = new \InvalidArgumentException('Invalid batch request');
        $failed = ['existing_failed_user'];

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                '批量获取用户数据失败',
                [
                    'user_ids' => $userIds,
                    'error' => 'Invalid batch request',
                    'error_type' => \InvalidArgumentException::class]
            )
        ;

        $this->handler->handleBatchSyncErrorWithFailedRef($userIds, $exception, $failed);

        $this->assertContains('existing_failed_user', $failed);
        $this->assertContains('user1', $failed);
        $this->assertContains('user2', $failed);
        $this->assertContains('user3', $failed);
        $this->assertIsArray($failed);
        $this->assertCount(4, $failed);
    }

    public function testHandleDepartmentSyncError(): void
    {
        $departmentId = 'dept123';
        $exception = new \Exception('Department not found');

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                '同步部门用户失败',
                [
                    'department_id' => $departmentId,
                    'error' => 'Department not found',
                    'error_type' => \Exception::class]
            )
        ;

        $result = $this->handler->handleDepartmentSyncError($departmentId, $exception);

        $this->assertSame([], $result['success']);
        $this->assertSame([], $result['failed']);
        $this->assertSame([], $result['skipped']);
    }

    public function testHandleMissingUsers(): void
    {
        $requestedUserIds = ['user1', 'user2', 'user3', 'user4'];
        $returnedUsers = [
            'user1' => ['name' => 'User 1'],
            'user3' => ['name' => 'User 3'],
        ];
        $userIdType = 'user_id';

        $this->dataProcessor->expects($this->exactly(2))
            ->method('handleDeletedUser')
        ;

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatchUserDeletedEvent')
        ;

        $missingUserIds = $this->handler->handleMissingUsers(
            $requestedUserIds,
            $returnedUsers,
            $userIdType
        );

        $this->assertSame(['user2', 'user4'], $missingUserIds);
    }

    public function testHandleMissingUsersWithNoMissing(): void
    {
        $requestedUserIds = ['user1', 'user2'];
        $returnedUsers = [
            'user1' => ['name' => 'User 1'],
            'user2' => ['name' => 'User 2'],
        ];
        $userIdType = 'user_id';

        $this->dataProcessor->expects($this->never())
            ->method('handleDeletedUser')
        ;

        $this->eventDispatcher->expects($this->never())
            ->method('dispatchUserDeletedEvent')
        ;

        $missingUserIds = $this->handler->handleMissingUsers(
            $requestedUserIds,
            $returnedUsers,
            $userIdType
        );

        $this->assertSame([], $missingUserIds);
    }

    public function testSafeExecuteWithSuccess(): void
    {
        $expectedResult = 'success_result';
        $operation = fn () => $expectedResult;

        $this->logger->expects($this->never())
            ->method('error')
        ;

        $result = $this->handler->safeExecute($operation, 'test_context');

        $this->assertSame($expectedResult, $result);
    }

    public function testSafeExecuteWithGeneralException(): void
    {
        $exception = new \RuntimeException('Operation failed');
        $operation = fn () => throw $exception;

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                '同步操作执行失败',
                [
                    'context' => 'test_context',
                    'error' => 'Operation failed',
                    'error_type' => \RuntimeException::class]
            )
        ;

        $result = $this->handler->safeExecute($operation, 'test_context');

        $this->assertNull($result);
    }

    public function testSafeExecuteWithApiException(): void
    {
        $exception = new GenericApiException('API error');
        $operation = fn () => throw $exception;

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                '同步操作执行失败',
                [
                    'context' => 'test_context',
                    'error' => 'API error',
                    'error_type' => ApiException::class]
            )
        ;

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('API error');

        $this->handler->safeExecute($operation, 'test_context');
    }

    public function testSafeExecuteWithEmptyContext(): void
    {
        $exception = new \Exception('Some error');
        $operation = fn () => throw $exception;

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                '同步操作执行失败',
                [
                    'context' => '',
                    'error' => 'Some error',
                    'error_type' => \Exception::class]
            )
        ;

        $result = $this->handler->safeExecute($operation);

        $this->assertNull($result);
    }

    public function testWrapException(): void
    {
        $originalException = new \RuntimeException('Original error');
        $message = 'Wrapped error';

        $wrapped = $this->handler->wrapException($originalException, $message);

        $this->assertInstanceOf(ApiException::class, $wrapped);
        $this->assertSame('Wrapped error: Original error', $wrapped->getMessage());
        $this->assertSame($originalException, $wrapped->getPrevious());
    }

    public function testLogSyncSuccess(): void
    {
        $userId = 'user123';
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com'];

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                '用户数据同步完成',
                [
                    'user_id' => $userId,
                    'user_name' => 'John Doe'])
        ;

        $this->handler->logSyncSuccess($userId, $userData);
    }

    public function testLogSyncSuccessWithoutName(): void
    {
        $userId = 'user123';
        $userData = [
            'email' => 'john@example.com'];

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                '用户数据同步完成',
                [
                    'user_id' => $userId,
                    'user_name' => ''])
        ;

        $this->handler->logSyncSuccess($userId, $userData);
    }

    public function testLogSyncStart(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $force = true;

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                '开始同步用户数据',
                [
                    'user_id' => $userId,
                    'user_id_type' => $userIdType,
                    'force' => $force]
            )
        ;

        $this->handler->logSyncStart($userId, $userIdType, $force);
    }

    public function testLogSyncSkipped(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                '用户数据无需同步',
                [
                    'user_id' => $userId,
                    'user_id_type' => $userIdType]
            )
        ;

        $this->handler->logSyncSkipped($userId, $userIdType);
    }

    public function testHandleSingleUserSyncError(): void
    {
        $userId = 'user123';
        $exception = new \Exception('Sync failed');

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                '同步单个用户失败',
                [
                    'user_id' => $userId,
                    'error' => 'Sync failed',
                    'error_type' => \Exception::class]
            )
        ;

        $this->handler->handleSingleUserSyncError($userId, $exception);
    }

    public function testHandleBatchSyncError(): void
    {
        $userIds = ['user1', 'user2'];
        $exception = new \RuntimeException('Batch sync failed');

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                '批量获取用户数据失败',
                [
                    'user_ids' => $userIds,
                    'error' => 'Batch sync failed',
                    'error_type' => \RuntimeException::class]
            )
        ;

        $this->handler->handleBatchSyncError($userIds, $exception);
    }

    protected function onSetUp(): void
    {
        // 获取服务实例，不再设置 mock
        $this->handler = self::getService(SyncErrorHandler::class);
        // 创建 mock 对象
        $this->logger = self::createMock(LoggerInterface::class);
        self::getContainer()->set(LoggerInterface::class, $this->logger);
        $this->dataProcessor = self::createMock(UserDataProcessor::class);
        self::getContainer()->set(UserDataProcessor::class, $this->dataProcessor);
        $this->eventDispatcher = self::createMock(UserEventDispatcher::class);
        self::getContainer()->set(UserEventDispatcher::class, $this->eventDispatcher);
        $this->handler = self::getService(SyncErrorHandler::class);
    }
}
