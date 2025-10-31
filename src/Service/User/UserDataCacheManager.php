<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\User;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 用户数据缓存管理器.
 */
#[Autoconfigure(public: true)]
class UserDataCacheManager
{
    private const CACHE_PREFIX = 'lark_user_data_';
    private const CACHE_TTL = 7200; // 2小时

    /**
     * @var array<string, array<string, mixed>> 内存中的用户数据缓存
     */
    private array $memoryCache = [];

    /**
     * @var array<string, bool> 数据修改标记
     */
    private array $dirtyFlags = [];

    public function __construct(
        private readonly ?CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $userId, string $userIdType): ?array
    {
        $cacheKey = $this->getCacheKey($userId, $userIdType);

        // 检查内存缓存
        if (isset($this->memoryCache[$cacheKey])) {
            $this->logger->debug('从内存缓存获取用户数据', [
                'user_id' => $userId,
                'user_id_type' => $userIdType,
            ]);

            return $this->memoryCache[$cacheKey];
        }

        // 检查持久化缓存
        if (null !== $this->cache) {
            $cachedData = $this->getFromPersistentCache($cacheKey);
            if (null !== $cachedData) {
                $this->memoryCache[$cacheKey] = $cachedData;
                $this->logger->debug('从持久化缓存获取用户数据', [
                    'user_id' => $userId,
                    'user_id_type' => $userIdType,
                ]);

                return $cachedData;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $userData
     */
    public function set(string $userId, string $userIdType, array $userData): void
    {
        if (null === $this->cache) {
            return;
        }

        $cacheKey = $this->getCacheKey($userId, $userIdType);
        $this->memoryCache[$cacheKey] = $userData;
        $this->dirtyFlags[$cacheKey] = true;

        if ($this->saveToPersistentCache($cacheKey, $userData)) {
            // If persistent save succeeded, clear dirty flag
            $this->dirtyFlags[$cacheKey] = false;
        }
    }

    public function delete(string $userId, string $userIdType): void
    {
        $cacheKey = $this->getCacheKey($userId, $userIdType);

        // 删除内存缓存
        unset($this->memoryCache[$cacheKey], $this->dirtyFlags[$cacheKey]);

        // 删除持久化缓存
        if (null !== $this->cache) {
            try {
                $this->cache->deleteItem($cacheKey);
            } catch (\Exception $e) {
                $this->logger->error('删除用户数据缓存失败', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getDirtyData(): array
    {
        $dirtyData = [];

        foreach ($this->dirtyFlags as $cacheKey => $isDirty) {
            if ($isDirty && isset($this->memoryCache[$cacheKey])) {
                $dirtyData[$cacheKey] = $this->memoryCache[$cacheKey];
            }
        }

        return $dirtyData;
    }

    public function persistDirtyData(): void
    {
        if (null === $this->cache) {
            return;
        }

        $count = 0;
        foreach ($this->dirtyFlags as $cacheKey => $isDirty) {
            if ($isDirty && isset($this->memoryCache[$cacheKey])) {
                if ($this->saveToPersistentCache($cacheKey, $this->memoryCache[$cacheKey])) {
                    $this->dirtyFlags[$cacheKey] = false;
                    ++$count;
                }
            }
        }

        if ($count > 0) {
            $this->logger->info('持久化修改数据完成', [
                'persisted_count' => $count,
            ]);
        }
    }

    public function cleanMemoryCache(int $maxAge = 3600): void
    {
        $now = time();
        $cleanedCount = 0;

        foreach ($this->memoryCache as $cacheKey => $userData) {
            $lastAccess = $userData['metadata']['last_access'] ?? $userData['metadata']['last_sync'] ?? 0;
            if ($now - $lastAccess > $maxAge && !($this->dirtyFlags[$cacheKey] ?? false)) {
                unset($this->memoryCache[$cacheKey], $this->dirtyFlags[$cacheKey]);
                ++$cleanedCount;
            }
        }

        if ($cleanedCount > 0) {
            $this->logger->info('清理内存缓存完成', [
                'cleaned_count' => $cleanedCount,
                'max_age' => $maxAge,
            ]);
        }
    }

    public function getCacheKey(string $userId, string $userIdType): string
    {
        return self::CACHE_PREFIX . $userIdType . '_' . $userId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getFromPersistentCache(string $key): ?array
    {
        if (null === $this->cache) {
            return null;
        }

        try {
            $item = $this->cache->getItem($key);
            if ($item->isHit()) {
                $data = $item->get();
                // 更新最后访问时间
                if (\is_array($data) && isset($data['metadata'])) {
                    $data['metadata']['last_access'] = time();
                }

                return $data;
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
     * @param array<string, mixed> $data
     */
    private function saveToPersistentCache(string $key, array $data): bool
    {
        if (null === $this->cache) {
            return false;
        }

        try {
            $item = $this->cache->getItem($key);
            $item->set($data);
            $item->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('保存到缓存失败', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
