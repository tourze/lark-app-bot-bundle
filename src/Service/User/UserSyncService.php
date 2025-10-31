<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Service\User\Sync\BatchSyncProcessor;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncErrorHandler;
use Tourze\LarkAppBotBundle\Service\User\Sync\SyncStrategyManager;
use Tourze\LarkAppBotBundle\Service\User\Sync\UserDataProcessor;
use Tourze\LarkAppBotBundle\Service\User\Sync\UserEventDispatcher;

/**
 * 用户数据同步服务.
 *
 * 负责协调各个组件完成用户数据的同步、更新和一致性维护
 */
#[Autoconfigure(public: true)]
class UserSyncService implements UserSyncServiceInterface
{
    public function __construct(
        private readonly UserServiceInterface $userService,
        private readonly UserCacheManagerInterface $cacheManager,
        private readonly SyncStrategyManager $strategyManager,
        private readonly UserDataProcessor $dataProcessor,
        private readonly UserEventDispatcher $eventDispatcher,
        private readonly SyncErrorHandler $errorHandler,
        private readonly BatchSyncProcessor $batchProcessor,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 同步单个用户数据.
     *
     * @return array<mixed> 同步后的用户数据
     * @throws ApiException
     */
    public function syncUser(string $userId, string $userIdType = 'open_id', bool $force = false): array
    {
        if (!$this->shouldSyncUser($userId, $userIdType, $force)) {
            return $this->getCachedUserData($userId, $userIdType);
        }

        $this->errorHandler->logSyncStart($userId, $userIdType, $force);

        try {
            return $this->performUserSync($userId, $userIdType);
        } catch (\Exception $e) {
            throw $this->errorHandler->wrapException($e, '用户数据同步失败');
        }
    }

    /**
     * 批量同步用户数据.
     *
     * @param string[] $userIds 用户ID列表
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]} 同步结果统计
     */
    public function batchSyncUsers(array $userIds, string $userIdType = 'open_id', bool $force = false): array
    {
        return $this->batchProcessor->processBatchSync($userIds, $userIdType, $force);
    }

    /**
     * 同步部门下的所有用户.
     *
     * @return array{success: array<string, array<mixed>>, failed: string[], skipped: string[]}
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
            $userIds = $this->fetchDepartmentUserIds($departmentId);

            return $this->batchSyncUsers($userIds, 'user_id', $force);
        } catch (\Exception $e) {
            return $this->errorHandler->handleDepartmentSyncError($departmentId, $e);
        }
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
     * 判断是否应该同步用户.
     */
    private function shouldSyncUser(string $userId, string $userIdType, bool $force): bool
    {
        if ($force) {
            return true;
        }

        if ($this->strategyManager->needsSync($userId, $userIdType)) {
            return true;
        }

        $this->errorHandler->logSyncSkipped($userId, $userIdType);

        return false;
    }

    /**
     * 获取缓存的用户数据.
     *
     * @return array<mixed>
     */
    private function getCachedUserData(string $userId, string $userIdType): array
    {
        $cachedData = $this->cacheManager->getCachedUser($userId, $userIdType);
        if (null !== $cachedData) {
            return $cachedData;
        }

        // 如果缓存失效，强制同步
        return $this->performUserSync($userId, $userIdType);
    }

    /**
     * 执行用户同步.
     *
     * @return array<mixed>
     */
    private function performUserSync(string $userId, string $userIdType): array
    {
        $userData = $this->userService->getUser($userId, $userIdType);
        $oldData = $this->cacheManager->getCachedUser($userId, $userIdType);

        $processedData = $this->dataProcessor->processUserData($userId, $userIdType, $userData);
        $this->strategyManager->recordSyncTime($userId, $userIdType);

        $this->dispatchUpdateEventIfNeeded($userId, $userIdType, $oldData, $processedData);
        $this->errorHandler->logSyncSuccess($userId, $processedData);

        return $processedData;
    }

    /**
     * 如果需要则分发更新事件.
     *
     * @param array<mixed>|null $oldData
     * @param array<mixed>      $newData
     */
    private function dispatchUpdateEventIfNeeded(string $userId, string $userIdType, ?array $oldData, array $newData): void
    {
        if ($this->eventDispatcher->shouldDispatchUpdateEvent($oldData, $newData) && null !== $oldData) {
            $this->eventDispatcher->dispatchUserUpdatedEvent($userId, $userIdType, $oldData, $newData);
        }
    }

    /**
     * 获取部门下的所有用户ID.
     *
     * @return string[]
     */
    private function fetchDepartmentUserIds(string $departmentId): array
    {
        $allUserIds = [];
        $pageToken = null;

        do {
            $result = $this->userService->searchUsers([
                'department_id' => $departmentId,
                'page_token' => $pageToken,
                'page_size' => 100,
            ]);

            $pageUserIds = $this->extractUserIdsFromPage($result);
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
    private function extractUserIdsFromPage(array $pageResult): array
    {
        $userIds = [];
        $items = $pageResult['items'] ?? [];

        if (!is_iterable($items)) {
            return $userIds;
        }

        foreach ($items as $item) {
            $userId = $this->extractUserIdFromItem($item);
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
    private function extractUserIdFromItem(mixed $item): ?string
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
}
