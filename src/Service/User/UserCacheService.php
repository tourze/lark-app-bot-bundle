<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 用户缓存管理服务.
 *
 * 负责用户数据的缓存存储、获取和清理
 */
#[Autoconfigure(public: true)]
final class UserCacheService
{
    private const CACHE_PREFIX = 'lark_user_';
    private const CACHE_TTL = 3600; // 1小时

    public function __construct(
        private readonly ?CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 从缓存获取用户信息.
     *
     * @return array<string, mixed>|null
     */
    public function getUser(string $userId, string $userIdType): ?array
    {
        if (null === $this->cache) {
            return null;
        }

        $cacheKey = $this->getUserCacheKey($userId, $userIdType);
        $cachedUser = $this->getFromCache($cacheKey);

        if (null !== $cachedUser) {
            $this->logger->debug('从缓存获取用户信息', [
                'user_id' => $userId,
                'user_id_type' => $userIdType,
            ]);
        }

        return $cachedUser;
    }

    /**
     * 批量获取缓存用户.
     *
     * @param string[] $userIds
     *
     * @return array{cached: array<string, array>, uncached: string[]}
     */
    /**
     * @param array<string> $userIds
     *
     * @return array<string, mixed>
     */
    public function batchGetUsers(array $userIds, string $userIdType): array
    {
        if (null === $this->cache) {
            return ['cached' => [], 'uncached' => $userIds];
        }

        $cached = [];
        $uncached = [];

        foreach ($userIds as $userId) {
            $cacheKey = $this->getUserCacheKey($userId, $userIdType);
            $cachedUser = $this->getFromCache($cacheKey);

            if (null !== $cachedUser) {
                $cached[$userId] = $cachedUser;
            } else {
                $uncached[] = $userId;
            }
        }

        return ['cached' => $cached, 'uncached' => $uncached];
    }

    /**
     * 批量缓存用户信息.
     *
     * @param array<string, array> $users 用户ID => 用户信息的映射
     */
    /**
     * @param array<string, mixed> $users
     */
    public function batchCacheUsers(array $users, string $userIdType): void
    {
        if (null === $this->cache) {
            return;
        }

        foreach ($users as $userId => $user) {
            $this->cacheUser($userId, $userIdType, $user);
        }
    }

    /**
     * 缓存用户信息.
     */
    /**
     * @param array<string, mixed> $user
     */
    public function cacheUser(string $userId, string $userIdType, array $user): void
    {
        if (null === $this->cache) {
            return;
        }

        $cacheKey = $this->getUserCacheKey($userId, $userIdType);
        $this->saveToCache($cacheKey, $user);
    }

    /**
     * 清除单个用户缓存.
     */
    public function clearUserCache(string $userId, string $userIdType): void
    {
        if (null === $this->cache) {
            return;
        }

        $cacheKey = $this->getUserCacheKey($userId, $userIdType);
        try {
            $this->cache->deleteItem($cacheKey);
            $this->logger->debug('清除用户缓存成功', [
                'user_id' => $userId,
                'user_id_type' => $userIdType,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('清除用户缓存失败', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 清除所有用户缓存.
     */
    public function clearAllCache(): void
    {
        if (null === $this->cache) {
            return;
        }

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
     * 检查缓存是否可用.
     */
    public function isAvailable(): bool
    {
        return null !== $this->cache;
    }

    /**
     * 获取用户缓存键.
     */
    private function getUserCacheKey(string $userId, string $userIdType): string
    {
        return self::CACHE_PREFIX . $userIdType . '_' . $userId;
    }

    /**
     * 从缓存获取数据.
     */
    /**
     * @return array<string, mixed>|null
     */
    private function getFromCache(string $key): ?array
    {
        if (null === $this->cache) {
            return null;
        }

        try {
            $item = $this->cache->getItem($key);
            if ($item->isHit()) {
                return $item->get();
            }
        } catch (\Exception $e) {
            $this->logger->error('从缓存读取失败', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * 保存数据到缓存.
     */
    /**
     * @param array<string, mixed> $data
     */
    private function saveToCache(string $key, array $data): void
    {
        if (null === $this->cache) {
            return;
        }

        try {
            $item = $this->cache->getItem($key);
            $item->set($data);
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);
        } catch (\Exception $e) {
            $this->logger->error('保存到缓存失败', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
