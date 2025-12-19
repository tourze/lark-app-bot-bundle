<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncErrorHandler;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncResultCollector;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncStrategyManager;
use Tourze\LarkAppBotBundle\Service\User\Sync\UserDataProcessor;
use Tourze\LarkAppBotBundle\Service\User\Sync\UserEventDispatcher;

/**
 * 用户数据同步服务（重构版）.
 *
 * 负责协调各个组件完成用户数据的同步、更新和一致性维护
 */
#[Autoconfigure(public: true)]
#[WithMonologChannel(channel: 'lark_app_bot')]
final class UserSyncServiceRefactored implements UserSyncServiceInterface
{
    public function __construct(
        private readonly UserServiceInterface $userService,
        private readonly SyncStrategyManager $strategyManager,
        private readonly UserDataProcessor $dataProcessor,
        private readonly SyncResultCollector $resultCollector,
        private readonly UserEventDispatcher $eventDispatcher,
        private readonly SyncErrorHandler $errorHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 同步单个用户数据.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     * @param bool   $force      是否强制同步（忽略同步间隔）
     *
     * @return array<mixed> 同步后的用户数据
     * @throws ApiException
     */
    public function syncUser(string $userId, string $userIdType = 'open_id', bool $force = false): array
    {
        // 检查是否需要同步
        if (!$this->strategyManager->needsSync($userId, $userIdType, $force)) {
            $this->errorHandler->logSyncSkipped($userId, $userIdType);

            // 返回缓存的数据
            $cachedData = $this->dataProcessor->getCachedUserData($userId, $userIdType);
            if (null !== $cachedData) {
                return $cachedData;
            }
        }

        $this->errorHandler->logSyncStart($userId, $userIdType, $force);

        try {
            // 获取最新的用户数据
            $userData = $this->userService->getUser($userId, $userIdType);

            // 获取旧数据用于比较
            $oldData = $this->dataProcessor->getCachedUserData($userId, $userIdType);

            // 处理用户数据
            $processedData = $this->dataProcessor->processUserData($userId, $userIdType, $userData);

            // 记录同步时间
            $this->strategyManager->recordSyncTime($userId, $userIdType);

            // 触发用户更新事件
            if ($this->eventDispatcher->shouldDispatchUpdateEvent($oldData, $userData)) {
                $this->eventDispatcher->dispatchUserUpdatedEvent($userId, $userIdType, $oldData ?? [], $userData);
            }

            $this->errorHandler->logSyncSuccess($userId, $userData);

            return $processedData;
        } catch (\Exception $e) {
            $this->errorHandler->handleSingleUserSyncError($userId, $e);
            throw $this->errorHandler->wrapException($e, '用户数据同步失败');
        }
    }

    /**
     * 同步部门下的所有用户.
     *
     * @param string $departmentId 部门ID
     * @param bool   $includeChild 是否包含子部门
     * @param bool   $force        是否强制同步
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} 同步结果
     */
    public function syncDepartmentUsers(
        string $departmentId,
        bool $includeChild = true,
        bool $force = false,
    ): array {
        $this->logger->info('开始同步部门用户', [
            'department_id' => $departmentId,
            'include_child' => $includeChild,
        ]);

        try {
            $allUserIds = $this->collectDepartmentUserIds($departmentId);

            return $this->batchSyncUsers($allUserIds, 'user_id', $force);
        } catch (\Exception $e) {
            return $this->errorHandler->handleDepartmentSyncError($departmentId, $e);
        }
    }

    /**
     * 批量同步用户数据.
     *
     * @param array<mixed> $userIds    用户ID列表
     * @param string       $userIdType 用户ID类型
     * @param bool         $force      是否强制同步
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} 同步结果统计
     */
    public function batchSyncUsers(array $userIds, string $userIdType = 'open_id', bool $force = false): array
    {
        if ([] === $userIds) {
            return $this->resultCollector->createEmptyResult();
        }

        $this->resultCollector->logBatchSyncStart($userIds, $userIdType, $force);
        $result = $this->performBatchSync($userIds, $userIdType, $force);
        $this->resultCollector->logBatchSyncComplete($userIds, $result);

        // 触发批量同步完成事件
        $this->eventDispatcher->dispatchBatchSyncCompletedEvent($userIds, $userIdType, $result);

        return $result;
    }

    /**
     * 清除同步时间记录.
     */
    public function clearSyncHistory(): void
    {
        $this->strategyManager->clearSyncHistory();
        $this->logger->info('清除同步时间记录');
    }

    /**
     * 收集部门用户ID.
     *
     * @return string[]
     */
    private function collectDepartmentUserIds(string $departmentId): array
    {
        $allUserIds = [];
        $pageToken = null;

        do {
            $result = $this->userService->searchUsers([
                'department_id' => $departmentId,
                'page_token' => $pageToken,
                'page_size' => 100,
            ]);

            $pageUserIds = $this->extractUserIdsFromPageResult($result);
            $allUserIds = [...$allUserIds, ...$pageUserIds];

            $pageToken = $result['page_token'] ?? null;
        } while ($result['has_more'] ?? false);

        return $allUserIds;
    }

    /**
     * 从单页结果中提取用户ID.
     *
     * @param array<mixed> $pageResult 单页查询结果
     *
     * @return string[] 用户ID列表
     */
    private function extractUserIdsFromPageResult(array $pageResult): array
    {
        $userIds = [];
        $items = $pageResult['items'] ?? [];

        if (!is_iterable($items)) {
            return $userIds;
        }

        foreach ($items as $item) {
            $userId = $this->extractUserIdFromPageItem($item);
            if (null !== $userId) {
                $userIds[] = $userId;
            }
        }

        return $userIds;
    }

    /**
     * 从单个项目中提取用户ID.
     *
     * @param mixed $item 单个项目数据
     *
     * @return string|null 用户ID，提取失败返回null
     */
    private function extractUserIdFromPageItem(mixed $item): ?string
    {
        if (!is_array($item)) {
            return null;
        }

        $user = $item['user'] ?? [];
        if (!is_array($user) || !isset($user['user_id'])) {
            return null;
        }

        $userId = $user['user_id'];
        if (!is_scalar($userId)) {
            return null;
        }

        return (string) $userId;
    }

    /**
     * 执行批量同步.
     *
     * @param string[] $userIds
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    private function performBatchSync(array $userIds, string $userIdType, bool $force): array
    {
        $result = $this->resultCollector->initializeResult();
        $batches = $this->resultCollector->createBatches($userIds);

        foreach ($batches as $batch) {
            $result = $this->processSyncBatch($batch, $userIdType, $force, $result);
        }

        return $result;
    }

    /**
     * 处理同步批次.
     *
     * @param string[]                                                                         $batch
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    private function processSyncBatch(array $batch, string $userIdType, bool $force, array $result): array
    {
        $syncFilter = $this->strategyManager->filterUsersToSync($batch, $userIdType, $force);

        // 添加跳过的用户
        $result = $this->resultCollector->addSkipped($result, $syncFilter['skipped']);

        if ([] === $syncFilter['toSync']) {
            return $result;
        }

        return $this->syncBatchUsers($syncFilter['toSync'], $userIdType, $result);
    }

    /**
     * 同步批次用户.
     *
     * @param string[]                                                                         $userIds
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    private function syncBatchUsers(array $userIds, string $userIdType, array $result): array
    {
        try {
            $users = $this->userService->batchGetUsers($userIds, $userIdType);

            foreach ($users as $userId => $userData) {
                $result = $this->processSingleUserInBatch($userId, $userIdType, $userData, $result);
            }

            // 处理未返回的用户
            $missingUserIds = $this->errorHandler->handleMissingUsers($userIds, $users, $userIdType);
            $result = $this->resultCollector->addBatchFailures($result, $missingUserIds);

            // 批量记录同步时间
            $this->strategyManager->batchRecordSyncTime(array_keys($users), $userIdType);
        } catch (\Exception $e) {
            $this->errorHandler->handleBatchSyncError($userIds, $e);
            $result = $this->resultCollector->addBatchFailures($result, $userIds);
        }

        return $result;
    }

    /**
     * 处理批次中的单个用户.
     *
     * @param array<mixed>                                                                     $userData
     * @param array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} $result
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
     */
    private function processSingleUserInBatch(string $userId, string $userIdType, array $userData, array $result): array
    {
        try {
            $oldData = $this->dataProcessor->getCachedUserData($userId, $userIdType);
            $processedData = $this->dataProcessor->processUserData($userId, $userIdType, $userData);

            if ($this->eventDispatcher->shouldDispatchUpdateEvent($oldData, $userData)) {
                $this->eventDispatcher->dispatchUserUpdatedEvent($userId, $userIdType, $oldData ?? [], $userData);
            }

            $result = $this->resultCollector->addSuccess($result, $userId, $processedData);
        } catch (\Exception $e) {
            $this->errorHandler->handleSingleUserSyncError($userId, $e);
            $result = $this->resultCollector->addFailure($result, $userId);
        }

        return $result;
    }
}
