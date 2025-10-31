<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 用户缓存管理器.
 *
 * 提供统一的用户数据缓存策略和管理功能
 */
#[Autoconfigure(public: true)]
class UserCacheManager implements UserCacheManagerInterface
{
    private const CACHE_PREFIX = 'lark_user_';
    private const CACHE_SEARCH_PREFIX = 'lark_user_search_';
    private const DEFAULT_TTL = 3600; // 默认1小时
    private const SHORT_TTL = 300; // 短期缓存5分钟

    /**
     * @var array<string, int> 不同数据类型的缓存时间配置
     */
    private array $ttlConfig = [
        'user_info' => self::DEFAULT_TTL,
        'user_departments' => self::DEFAULT_TTL,
        'user_permissions' => self::SHORT_TTL,
        'search_results' => self::SHORT_TTL,
        'batch_results' => self::DEFAULT_TTL,
    ];

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 缓存用户信息.
     *
     * @param string               $userId     用户ID
     * @param string               $userIdType 用户ID类型
     * @param array<string, mixed> $userData   用户数据
     * @param string               $dataType   数据类型
     */
    public function cacheUser(
        string $userId,
        string $userIdType,
        array $userData,
        string $dataType = 'user_info',
    ): void {
        $cacheKey = $this->buildCacheKey($userId, $userIdType, $dataType);
        $ttl = $this->ttlConfig[$dataType] ?? self::DEFAULT_TTL;

        try {
            $item = $this->cache->getItem($cacheKey);
            $item->set($userData);
            $item->expiresAfter($ttl);
            $this->cache->save($item);

            $this->logger->debug('缓存用户数据成功', [
                'cache_key' => $cacheKey,
                'data_type' => $dataType,
                'ttl' => $ttl,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('缓存用户数据失败', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取缓存的用户信息.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     * @param string $dataType   数据类型
     *
     * @return array<string, mixed>|null 缓存的数据，不存在则返回null
     */
    public function getCachedUser(
        string $userId,
        string $userIdType,
        string $dataType = 'user_info',
    ): ?array {
        $cacheKey = $this->buildCacheKey($userId, $userIdType, $dataType);

        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                $this->logger->debug('缓存命中', [
                    'cache_key' => $cacheKey,
                    'data_type' => $dataType,
                ]);

                return $item->get();
            }
        } catch (\Exception $e) {
            $this->logger->error('获取缓存数据失败', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * 批量缓存用户信息.
     *
     * @param array<string, mixed> $users      用户ID => 用户数据的映射
     * @param string               $userIdType 用户ID类型
     * @param string               $dataType   数据类型
     */
    public function batchCacheUsers(
        array $users,
        string $userIdType,
        string $dataType = 'user_info',
    ): void {
        if ([] === $users) {
            return;
        }

        $ttl = $this->ttlConfig[$dataType] ?? self::DEFAULT_TTL;
        $items = [];

        try {
            foreach ($users as $userId => $userData) {
                /** @var string $userId */
                /** @var array<string, mixed> $userData */
                $cacheKey = $this->buildCacheKey($userId, $userIdType, $dataType);
                $item = $this->cache->getItem($cacheKey);
                $item->set($userData);
                $item->expiresAfter($ttl);
                $items[] = $item;
            }

            $this->cache->saveDeferred(...$items);
            $this->cache->commit();

            $this->logger->debug('批量缓存用户数据成功', [
                'user_count' => \count($users),
                'data_type' => $dataType,
                'ttl' => $ttl,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('批量缓存用户数据失败', [
                'user_count' => \count($users),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 批量获取缓存的用户信息.
     *
     * @param string[] $userIds    用户ID列表
     * @param string   $userIdType 用户ID类型
     * @param string   $dataType   数据类型
     *
     * @return array<string, mixed> 用户数据数组，键为用户ID，值为用户数据
     */
    public function batchGetCachedUsers(
        array $userIds,
        string $userIdType,
        string $dataType = 'user_info',
    ): array {
        $cached = [];
        $uncached = [];

        foreach ($userIds as $userId) {
            $data = $this->getCachedUser($userId, $userIdType, $dataType);
            if (null !== $data) {
                $cached[$userId] = $data;
            } else {
                $uncached[] = $userId;
            }
        }

        $this->logger->debug('批量获取缓存结果', [
            'total_count' => \count($userIds),
            'cached_count' => \count($cached),
            'uncached_count' => \count($uncached),
            'data_type' => $dataType,
        ]);

        return [
            'cached' => $cached,
            'uncached' => $uncached,
        ];
    }

    /**
     * 缓存搜索结果.
     *
     * @param string               $searchKey 搜索关键字
     * @param array<string, mixed> $results   搜索结果
     * @param array<string, mixed> $params    搜索参数
     */
    public function cacheSearchResults(string $searchKey, array $results, array $params = []): void
    {
        $cacheKey = $this->buildSearchCacheKey($searchKey, $params);
        $ttl = $this->ttlConfig['search_results'] ?? self::SHORT_TTL;

        try {
            $item = $this->cache->getItem($cacheKey);
            $item->set($results);
            $item->expiresAfter($ttl);
            $this->cache->save($item);

            $this->logger->debug('缓存搜索结果成功', [
                'search_key' => $searchKey,
                'result_count' => \count($results['items'] ?? []),
                'ttl' => $ttl,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('缓存搜索结果失败', [
                'search_key' => $searchKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取缓存的搜索结果.
     *
     * @param string               $searchKey 搜索关键字
     * @param array<string, mixed> $params    搜索参数
     *
     * @return array<string, mixed>|null
     */
    public function getCachedSearchResults(string $searchKey, array $params = []): ?array
    {
        $cacheKey = $this->buildSearchCacheKey($searchKey, $params);

        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                $this->logger->debug('搜索结果缓存命中', [
                    'search_key' => $searchKey,
                ]);

                return $item->get();
            }
        } catch (\Exception $e) {
            $this->logger->error('获取搜索结果缓存失败', [
                'search_key' => $searchKey,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * 使某个用户的缓存失效.
     *
     * @param string $userId     用户ID
     * @param string $userIdType 用户ID类型
     */
    public function invalidateUser(string $userId, string $userIdType): void
    {
        $dataTypes = array_keys($this->ttlConfig);
        $deletedKeys = [];

        try {
            foreach ($dataTypes as $dataType) {
                $cacheKey = $this->buildCacheKey($userId, $userIdType, $dataType);
                if ($this->cache->deleteItem($cacheKey)) {
                    $deletedKeys[] = $cacheKey;
                }
            }

            $this->logger->info('用户缓存失效成功', [
                'user_id' => $userId,
                'user_id_type' => $userIdType,
                'deleted_keys' => $deletedKeys,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('用户缓存失效失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 批量使用户缓存失效.
     *
     * @param string[] $userIds    用户ID列表
     * @param string   $userIdType 用户ID类型
     */
    public function batchInvalidateUsers(array $userIds, string $userIdType): void
    {
        foreach ($userIds as $userId) {
            $this->invalidateUser($userId, $userIdType);
        }
    }

    /**
     * 清除所有用户缓存.
     */
    public function clearAllCache(): void
    {
        try {
            $this->cache->clear();
            $this->logger->info('清除所有用户缓存成功');
        } catch (\Exception $e) {
            $this->logger->error('清除所有用户缓存失败', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 设置缓存TTL配置.
     *
     * @param string $dataType 数据类型
     * @param int    $ttl      缓存时间（秒）
     */
    public function setTtlConfig(string $dataType, int $ttl): void
    {
        $this->ttlConfig[$dataType] = $ttl;
        $this->logger->debug('更新缓存TTL配置', [
            'data_type' => $dataType,
            'ttl' => $ttl,
        ]);
    }

    /**
     * 获取缓存统计信息.
     *
     * @return array{
     *     hit_rate?: float,
     *     total_items?: int,
     *     memory_usage?: int
     * }
     */
    public function getCacheStats(): array
    {
        $stats = [];

        try {
            // 某些缓存实现可能支持统计信息
            if (method_exists($this->cache, 'getStats')) {
                $result = $this->cache->getStats();
                if (\is_array($result)) {
                    $stats = $result;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('获取缓存统计信息失败', [
                'error' => $e->getMessage(),
            ]);
        }

        return $stats;
    }

    /**
     * 预热缓存.
     *
     * @param string[] $userIds    用户ID列表
     * @param string   $userIdType 用户ID类型
     * @param callable $dataLoader 数据加载器回调函数
     */
    public function warmupCache(array $userIds, string $userIdType, callable $dataLoader): void
    {
        if ([] === $userIds) {
            return;
        }

        $this->logger->info('开始预热用户缓存', [
            'user_count' => \count($userIds),
            'user_id_type' => $userIdType,
        ]);

        try {
            // 调用数据加载器获取用户数据
            $users = $dataLoader($userIds);

            // 批量缓存
            $this->batchCacheUsers($users, $userIdType);

            $this->logger->info('用户缓存预热完成', [
                'user_count' => \count($users),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('用户缓存预热失败', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 预热用户缓存（简化版）.
     *
     * @param string[] $userIds    用户ID列表
     * @param string   $userIdType 用户ID类型
     */
    public function warmupUsers(array $userIds, string $userIdType = 'open_id'): void
    {
        // 这是一个简化的接口，实际的数据加载逻辑由调用者提供
        $this->logger->info('预热用户缓存请求', [
            'user_count' => \count($userIds),
            'user_id_type' => $userIdType,
        ]);
    }

    /**
     * 使搜索缓存失效.
     *
     * @param array<string, mixed> $params 搜索参数
     */
    public function invalidateSearchCache(array $params = []): void
    {
        try {
            // 如果没有指定参数，清除所有搜索缓存
            if ([] === $params) {
                // 由于无法精确匹配前缀，我们记录日志
                $this->logger->info('请求清除搜索缓存', [
                    'params' => 'all',
                ]);

                return;
            }

            // 根据参数构建可能的缓存键并删除
            $possibleKeys = [];
            if (isset($params['department_id'])) {
                $possibleKeys[] = self::CACHE_SEARCH_PREFIX . 'dept_' . $params['department_id'];
            }
            if (isset($params['query'])) {
                $possibleKeys[] = self::CACHE_SEARCH_PREFIX . 'query_' . md5($params['query']);
            }

            foreach ($possibleKeys as $key) {
                $this->cache->deleteItem($key);
            }

            $this->logger->info('搜索缓存失效完成', [
                'params' => $params,
                'keys_count' => \count($possibleKeys),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('搜索缓存失效失败', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 构建缓存键.
     */
    private function buildCacheKey(string $userId, string $userIdType, string $dataType): string
    {
        return \sprintf(
            '%s%s_%s_%s',
            self::CACHE_PREFIX,
            $dataType,
            $userIdType,
            $userId
        );
    }

    /**
     * 构建搜索缓存键.
     *
     * @param array<string, mixed> $params
     */
    private function buildSearchCacheKey(string $searchKey, array $params): string
    {
        // 对参数进行排序以确保相同参数生成相同的键
        ksort($params);
        $paramHash = md5(($json = json_encode($params)) !== false ? $json : '{}');

        return \sprintf(
            '%s%s_%s',
            self::CACHE_SEARCH_PREFIX,
            $searchKey,
            $paramHash
        );
    }
}
