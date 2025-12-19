<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User\Sync;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;

/**
 * 同步错误处理器.
 *
 * 负责同步过程中的错误处理和重试逻辑
 */
#[Autoconfigure(public: true)]
final class SyncErrorHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserDataProcessor $dataProcessor,
        private readonly UserEventDispatcher $eventDispatcher,
    ) {
    }

    /**
     * 处理单个用户同步错误.
     *
     * @param string[] $failed
     *
     * @return string[]
     */
    public function handleSingleUserError(string $userId, \Exception $e, array $failed): array
    {
        $this->logger->error('同步单个用户失败', [
            'user_id' => $userId,
            'error' => $e->getMessage(),
            'error_type' => $e::class,
        ]);
        $failed[] = $userId;

        return $failed;
    }

    /**
     * 处理批量同步错误（三个参数版本，适配 BatchSyncProcessor）.
     *
     * @param string[] $userIds
     * @param string[] $failed
     *
     * @return string[]
     */
    public function handleBatchSyncErrorWithFailedRef(array $userIds, \Exception $e, array $failed): array
    {
        $this->logger->error('批量获取用户数据失败', [
            'user_ids' => $userIds,
            'error' => $e->getMessage(),
            'error_type' => $e::class,
        ]);

        return array_merge($failed, $userIds);
    }

    /**
     * 处理部门用户同步错误.
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    public function handleDepartmentSyncError(string $departmentId, \Exception $e): array
    {
        $this->logger->error('同步部门用户失败', [
            'department_id' => $departmentId,
            'error' => $e->getMessage(),
            'error_type' => $e::class,
        ]);

        return [
            'success' => [],
            'failed' => [],
            'skipped' => [],
        ];
    }

    /**
     * 处理未返回的用户.
     *
     * @param string[]                    $requestedUserIds
     * @param array<string, array<mixed>> $returnedUsers
     *
     * @return string[]
     */
    public function handleMissingUsers(
        array $requestedUserIds,
        array $returnedUsers,
        string $userIdType,
    ): array {
        $missingUserIds = [];

        foreach ($requestedUserIds as $userId) {
            if (!isset($returnedUsers[$userId])) {
                $missingUserIds[] = $userId;
                $this->handleMissingUser($userId, $userIdType);
            }
        }

        return $missingUserIds;
    }

    /**
     * 安全执行同步操作.
     */
    public function safeExecute(callable $operation, string $context = ''): mixed
    {
        try {
            return $operation();
        } catch (\Exception $e) {
            $this->logger->error('同步操作执行失败', [
                'context' => $context,
                'error' => $e->getMessage(),
                'error_type' => $e::class,
            ]);

            // 根据错误类型决定是否重新抛出
            if ($e instanceof ApiException) {
                throw $e;
            }

            return null;
        }
    }

    /**
     * 包装异常为 ApiException.
     */
    public function wrapException(\Exception $e, string $message): ApiException
    {
        return new GenericApiException($message . ': ' . $e->getMessage(), 0, $e);
    }

    /**
     * 记录同步成功日志.
     *
     * @param array<mixed> $userData
     */
    public function logSyncSuccess(string $userId, array $userData): void
    {
        $this->logger->info('用户数据同步完成', [
            'user_id' => $userId,
            'user_name' => $userData['name'] ?? '',
        ]);
    }

    /**
     * 记录同步开始日志.
     */
    public function logSyncStart(string $userId, string $userIdType, bool $force): void
    {
        $this->logger->info('开始同步用户数据', [
            'user_id' => $userId,
            'user_id_type' => $userIdType,
            'force' => $force,
        ]);
    }

    /**
     * 记录跳过同步日志.
     */
    public function logSyncSkipped(string $userId, string $userIdType): void
    {
        $this->logger->debug('用户数据无需同步', [
            'user_id' => $userId,
            'user_id_type' => $userIdType,
        ]);
    }

    /**
     * 处理单个用户同步错误（UserSyncServiceRefactored 需要的方法）.
     */
    public function handleSingleUserSyncError(string $userId, \Exception $e): void
    {
        $this->logger->error('同步单个用户失败', [
            'user_id' => $userId,
            'error' => $e->getMessage(),
            'error_type' => $e::class,
        ]);
    }

    /**
     * 处理批量同步错误（两个参数版本，适配 UserSyncServiceRefactored）.
     *
     * @param string[] $userIds
     */
    public function handleBatchSyncError(array $userIds, \Exception $e): void
    {
        $this->logger->error('批量获取用户数据失败', [
            'user_ids' => $userIds,
            'error' => $e->getMessage(),
            'error_type' => $e::class,
        ]);
    }

    /**
     * 处理单个缺失的用户.
     */
    private function handleMissingUser(string $userId, string $userIdType): void
    {
        // 处理已删除的用户
        $this->dataProcessor->handleDeletedUser($userId, $userIdType);

        // 触发删除事件
        $this->eventDispatcher->dispatchUserDeletedEvent($userId, $userIdType);
    }
}
