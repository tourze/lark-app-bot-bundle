<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\LarkAppBotBundle\Event\UserEvent;

/**
 * 用户状态追踪器.
 *
 * 追踪用户的在线状态、活动记录和行为分析
 */
#[Autoconfigure(public: true)]
class UserTracker
{
    private const CACHE_PREFIX = 'lark_user_tracker_';
    private const ONLINE_TIMEOUT = 300; // 5分钟无活动视为离线
    private const ACTIVITY_RETENTION = 86400; // 活动记录保留24小时

    /**
     * @var array<string, int|array<string, mixed>> 内存中的用户活动缓存
     */
    private array $memoryCache = [];

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 记录用户活动.
     *
     * @param string               $userId     用户ID
     * @param string               $userIdType 用户ID类型
     * @param string               $activity   活动类型
     * @param array<string, mixed> $context    活动上下文
     */
    public function trackActivity(
        string $userId,
        string $userIdType,
        string $activity,
        array $context = [],
    ): void {
        $timestamp = time();
        $activityData = [
            'user_id' => $userId,
            'user_id_type' => $userIdType,
            'activity' => $activity,
            'context' => $context,
            'timestamp' => $timestamp,
            'datetime' => date('Y-m-d H:i:s', $timestamp),
        ];

        // 更新最后活动时间
        $this->updateLastActivity($userId, $userIdType, $timestamp);

        // 记录活动历史
        $this->recordActivity($userId, $userIdType, $activityData);

        // 更新活动统计
        $this->updateActivityStats($userId, $userIdType, $activity);

        // 触发活动事件
        $this->dispatchActivityEvent($userId, $userIdType, $activityData);

        $this->logger->debug('记录用户活动', [
            'user_id' => $userId,
            'activity' => $activity,
        ]);
    }

    /**
     * 获取用户在线状态.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     *
     * @return array{
     *     is_online: bool,
     *     last_activity_time?: int,
     *     last_activity_datetime?: string,
     *     status?: string
     * }
     */
    public function getUserStatus(string $userId, string $userIdType): array
    {
        $lastActivity = $this->getLastActivity($userId, $userIdType);

        if (null === $lastActivity) {
            return [
                'is_online' => false,
                'status' => 'unknown',
            ];
        }

        $isOnline = (time() - $lastActivity) <= self::ONLINE_TIMEOUT;

        return [
            'is_online' => $isOnline,
            'last_activity_time' => $lastActivity,
            'last_activity_datetime' => date('Y-m-d H:i:s', $lastActivity),
            'status' => $isOnline ? 'online' : 'offline',
        ];
    }

    /**
     * 批量获取用户在线状态.
     *
     * @param string[] $userIds    用户ID列表
     * @param string   $userIdType 用户ID类型
     *
     * @return array<string, array<string, mixed>> 用户ID => 状态信息的映射
     */
    public function batchGetUserStatus(array $userIds, string $userIdType): array
    {
        $statuses = [];

        foreach ($userIds as $userId) {
            $statuses[$userId] = $this->getUserStatus($userId, $userIdType);
        }

        return $statuses;
    }

    /**
     * 获取用户活动历史.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     * @param int    $limit      返回的记录数
     * @param int    $offset     偏移量
     *
     * @return array<string, mixed>
     */
    public function getUserActivityHistory(
        string $userId,
        string $userIdType,
        int $limit = 20,
        int $offset = 0,
    ): array {
        $cacheKey = $this->getActivityHistoryCacheKey($userId, $userIdType);

        try {
            $item = $this->cache->getItem($cacheKey);
            if (!$item->isHit()) {
                return [
                    'activities' => [],
                    'total' => 0,
                ];
            }

            $allActivities = $item->get() ?? [];
            \assert(\is_array($allActivities));

            // 清理过期的活动记录
            $allActivities = $this->cleanExpiredActivities($allActivities);

            // 按时间倒序排序
            usort($allActivities, function ($a, $b) {
                \assert(\is_int($a['timestamp']) && \is_int($b['timestamp']));

                return $b['timestamp'] - $a['timestamp'];
            });

            // 分页返回
            $activities = \array_slice($allActivities, $offset, $limit);

            return [
                'activities' => $activities,
                'total' => \count($allActivities),
            ];
        } catch (\Exception $e) {
            $this->logger->error('获取用户活动历史失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'activities' => [],
                'total' => 0,
            ];
        }
    }

    /**
     * 获取用户活动统计.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     * @param int    $period     统计周期（秒）
     *
     * @return array<string, mixed>
     */
    public function getUserActivityStats(
        string $userId,
        string $userIdType,
        int $period = 86400,
    ): array {
        $history = $this->getUserActivityHistory($userId, $userIdType, 1000, 0);
        $activities = $history['activities'];

        if ([] === $activities) {
            return [
                'total_activities' => 0,
                'activities_by_type' => [],
                'activity_trend' => [],
            ];
        }

        $cutoffTime = time() - $period;
        $recentActivities = array_filter(
            $activities,
            function ($activity) use ($cutoffTime) {
                \assert(\is_array($activity) && \is_int($activity['timestamp']));

                return $activity['timestamp'] >= $cutoffTime;
            }
        );

        // 按类型统计
        $activitiesByType = [];
        $hourlyDistribution = array_fill(0, 24, 0);

        foreach ($recentActivities as $activity) {
            $type = $activity['activity'];
            $activitiesByType[$type] = ($activitiesByType[$type] ?? 0) + 1;

            // 统计小时分布
            $hour = (int) date('H', $activity['timestamp']);
            ++$hourlyDistribution[$hour];
        }

        // 找出最活跃的小时
        $mostActiveHour = array_search(max($hourlyDistribution), $hourlyDistribution, true);

        // 生成活动趋势（按小时）
        $trend = $this->generateActivityTrend($recentActivities, $period);

        return [
            'total_activities' => \count($recentActivities),
            'activities_by_type' => $activitiesByType,
            'most_active_hour' => $mostActiveHour,
            'activity_trend' => $trend,
        ];
    }

    /**
     * 获取在线用户列表.
     *
     * @param string[] $userIds    用户ID列表（可选，不传则返回所有）
     * @param string   $userIdType 用户ID类型
     *
     * @return string[] 在线用户ID列表
     */
    public function getOnlineUsers(array $userIds = [], string $userIdType = 'open_id'): array
    {
        if ([] === $userIds) {
            // 从缓存中获取所有追踪的用户
            $userIds = $this->getAllTrackedUsers($userIdType);
        }

        $onlineUsers = [];
        $statuses = $this->batchGetUserStatus($userIds, $userIdType);

        foreach ($statuses as $userId => $status) {
            if (isset($status['is_online']) && true === $status['is_online']) {
                $onlineUsers[] = $userId;
            }
        }

        return $onlineUsers;
    }

    /**
     * 清除用户的所有追踪数据.
     */
    public function clearUserTracking(string $userId, string $userIdType): void
    {
        $cacheKeys = [
            $this->getLastActivityCacheKey($userId, $userIdType),
            $this->getActivityHistoryCacheKey($userId, $userIdType),
            $this->getStatsCacheKey($userId, $userIdType),
        ];

        foreach ($cacheKeys as $key) {
            try {
                $this->cache->deleteItem($key);
                unset($this->memoryCache[$key]);
            } catch (\Exception $e) {
                $this->logger->error('清除用户追踪数据失败', [
                    'cache_key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('清除用户追踪数据', [
            'user_id' => $userId,
            'user_id_type' => $userIdType,
        ]);
    }

    /**
     * 更新最后活动时间.
     */
    private function updateLastActivity(string $userId, string $userIdType, int $timestamp): void
    {
        $cacheKey = $this->getLastActivityCacheKey($userId, $userIdType);

        try {
            $item = $this->cache->getItem($cacheKey);
            $item->set($timestamp);
            $item->expiresAfter(self::ACTIVITY_RETENTION);
            $this->cache->save($item);

            // 更新内存缓存
            $this->memoryCache[$cacheKey] = $timestamp;
        } catch (\Exception $e) {
            $this->logger->error('更新最后活动时间失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取最后活动时间.
     */
    private function getLastActivity(string $userId, string $userIdType): ?int
    {
        $cacheKey = $this->getLastActivityCacheKey($userId, $userIdType);

        // 先检查内存缓存
        if (isset($this->memoryCache[$cacheKey])) {
            $value = $this->memoryCache[$cacheKey];

            return \is_int($value) ? $value : null;
        }

        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                $timestamp = $item->get();
                if (is_scalar($timestamp)) {
                    $timestampInt = (int) $timestamp;
                    $this->memoryCache[$cacheKey] = $timestampInt;

                    return $timestampInt;
                }

                return null;
            }
        } catch (\Exception $e) {
            $this->logger->error('获取最后活动时间失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * 记录活动到历史.
     */
    /**
     * @param array<string, mixed> $activityData
     */
    private function recordActivity(string $userId, string $userIdType, array $activityData): void
    {
        $cacheKey = $this->getActivityHistoryCacheKey($userId, $userIdType);

        try {
            $item = $this->cache->getItem($cacheKey);
            $activities = $item->isHit() ? ($item->get() ?? []) : [];

            // 添加新活动
            $activities[] = $activityData;

            // 清理过期活动
            $activities = $this->cleanExpiredActivities($activities);

            // 限制活动记录数量
            if (\count($activities) > 1000) {
                $activities = \array_slice($activities, -1000);
            }

            $item->set($activities);
            $item->expiresAfter(self::ACTIVITY_RETENTION);
            $this->cache->save($item);
        } catch (\Exception $e) {
            $this->logger->error('记录活动历史失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 更新活动统计.
     */
    private function updateActivityStats(string $userId, string $userIdType, string $activity): void
    {
        $cacheKey = $this->getStatsCacheKey($userId, $userIdType);

        try {
            $item = $this->cache->getItem($cacheKey);
            $stats = $item->isHit() ? ($item->get() ?? []) : [];

            // 更新总计数
            $stats['total'] = ($stats['total'] ?? 0) + 1;

            // 更新分类计数
            $stats['by_type'][$activity] = ($stats['by_type'][$activity] ?? 0) + 1;

            // 更新日期统计
            $date = date('Y-m-d');
            $stats['by_date'][$date] = ($stats['by_date'][$date] ?? 0) + 1;

            $item->set($stats);
            $item->expiresAfter(self::ACTIVITY_RETENTION);
            $this->cache->save($item);
        } catch (\Exception $e) {
            $this->logger->error('更新活动统计失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 清理过期的活动记录.
     */
    /**
     * @param array<int, array<string, mixed>> $activities
     *
     * @return array<int, array<string, mixed>>
     */
    private function cleanExpiredActivities(array $activities): array
    {
        $cutoffTime = time() - self::ACTIVITY_RETENTION;

        return array_filter(
            $activities,
            fn ($activity) => ($activity['timestamp'] ?? 0) > $cutoffTime
        );
    }

    /**
     * 生成活动趋势数据.
     *
     * @param array<int, array<string, mixed>> $activities
     *
     * @return array<int, array<string, mixed>>
     */
    private function generateActivityTrend(array $activities, int $period): array
    {
        $trend = [];
        $interval = 3600; // 按小时统计

        // 计算时间段
        $endTime = time();
        $startTime = $endTime - $period;

        for ($time = $startTime; $time <= $endTime; $time += $interval) {
            $hourStart = $time;
            $hourEnd = $time + $interval;
            $count = 0;

            foreach ($activities as $activity) {
                $timestamp = $activity['timestamp'];
                if ($timestamp >= $hourStart && $timestamp < $hourEnd) {
                    ++$count;
                }
            }

            $trend[] = [
                'time' => $hourStart,
                'datetime' => date('Y-m-d H:i:s', $hourStart),
                'count' => $count,
            ];
        }

        return $trend;
    }

    /**
     * 获取所有追踪的用户.
     *
     * @return array<string>
     */
    private function getAllTrackedUsers(string $userIdType): array
    {
        // 这是一个简化的实现，实际应用中可能需要维护一个用户列表
        return [];
    }

    /**
     * 触发活动事件.
     *
     * @param array<string, mixed> $activityData
     */
    private function dispatchActivityEvent(string $userId, string $userIdType, array $activityData): void
    {
        $event = new UserEvent(UserEvent::USER_ACTIVITY, [
            'user_id' => $userId,
            'user_id_type' => $userIdType,
        ], [
            'action' => 'activity',
            'activity' => $activityData,
        ]);

        $this->eventDispatcher->dispatch($event, UserEvent::USER_ACTIVITY);
    }

    /**
     * 获取最后活动缓存键.
     */
    private function getLastActivityCacheKey(string $userId, string $userIdType): string
    {
        return \sprintf('%slast_%s_%s', self::CACHE_PREFIX, $userIdType, $userId);
    }

    /**
     * 获取活动历史缓存键.
     */
    private function getActivityHistoryCacheKey(string $userId, string $userIdType): string
    {
        return \sprintf('%shistory_%s_%s', self::CACHE_PREFIX, $userIdType, $userId);
    }

    /**
     * 获取统计缓存键.
     */
    private function getStatsCacheKey(string $userId, string $userIdType): string
    {
        return \sprintf('%sstats_%s_%s', self::CACHE_PREFIX, $userIdType, $userId);
    }
}
