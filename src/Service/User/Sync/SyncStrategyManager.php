<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User\Sync;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 同步策略管理器.
 *
 * 负责判断同步时机和策略
 */
#[Autoconfigure(public: true)]
class SyncStrategyManager
{
    private const SYNC_INTERVAL = 300; // 5分钟同步间隔

    /**
     * @var array<string, int> 记录上次同步时间
     */
    private array $lastSyncTime = [];

    /**
     * 批量检查需要同步的用户.
     *
     * @param string[] $userIds
     *
     * @return array{toSync: string[], skipped: string[]}
     */
    public function filterUsersToSync(array $userIds, string $userIdType, bool $force = false): array
    {
        $toSync = [];
        $skipped = [];

        foreach ($userIds as $userId) {
            if ($this->needsSync($userId, $userIdType, $force)) {
                $toSync[] = $userId;
            } else {
                $skipped[] = $userId;
            }
        }

        return [
            'toSync' => $toSync,
            'skipped' => $skipped,
        ];
    }

    /**
     * 检查是否需要同步.
     */
    public function needsSync(string $userId, string $userIdType, bool $force = false): bool
    {
        if ($force) {
            return true;
        }

        $syncKey = $this->getSyncKey($userId, $userIdType);

        return $this->isTimeToSync($syncKey);
    }

    /**
     * 记录同步时间.
     */
    public function recordSyncTime(string $userId, string $userIdType): void
    {
        $syncKey = $this->getSyncKey($userId, $userIdType);
        $this->lastSyncTime[$syncKey] = time();
    }

    /**
     * 批量记录同步时间.
     *
     * @param string[] $userIds
     */
    public function batchRecordSyncTime(array $userIds, string $userIdType): void
    {
        $currentTime = time();
        foreach ($userIds as $userId) {
            $syncKey = $this->getSyncKey($userId, $userIdType);
            $this->lastSyncTime[$syncKey] = $currentTime;
        }
    }

    /**
     * 清除同步历史.
     */
    public function clearSyncHistory(): void
    {
        $this->lastSyncTime = [];
    }

    /**
     * 获取同步键.
     */
    private function getSyncKey(string $userId, string $userIdType): string
    {
        return \sprintf('%s_%s', $userIdType, $userId);
    }

    /**
     * 检查是否到了同步时间.
     */
    private function isTimeToSync(string $syncKey): bool
    {
        if (!isset($this->lastSyncTime[$syncKey])) {
            return true;
        }

        $elapsed = time() - $this->lastSyncTime[$syncKey];

        return $elapsed >= self::SYNC_INTERVAL;
    }
}
