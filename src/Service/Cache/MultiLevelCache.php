<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\InvalidCacheStrategyException;

/**
 * 多级缓存实现
 * 提供内存、本地文件和分布式缓存的多级缓存机制.
 */
#[Autoconfigure(public: true)]
final class MultiLevelCache implements CacheItemPoolInterface
{
    private ChainAdapter $chainAdapter;

    private LoggerInterface $logger;

    private CacheStats $stats;

    /** @var CacheLevel[] */
    private array $cacheLevels = [];

    /**
     * 缓存级别配置.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $levelConfigs = [
        'memory' => [
            'enabled' => true,
            'max_items' => 1000,
            'default_lifetime' => 300, // 5分钟
        ],
        'local' => [
            'enabled' => true,
            'default_lifetime' => 3600, // 1小时
        ],
        'distributed' => [
            'enabled' => false,
            'default_lifetime' => 86400, // 24小时
        ],
    ];

    /**
     * @param array<string, array<string, mixed>> $configs
     */
    public function __construct(
        CacheItemPoolInterface $localCache,
        LoggerInterface $logger,
        ?CacheItemPoolInterface $distributedCache = null,
        array $configs = [],
    ) {
        $this->logger = $logger;
        $this->stats = new CacheStats();

        // 合并配置
        $this->levelConfigs = array_merge($this->levelConfigs, $configs);

        // 初始化缓存层级
        $this->initializeCacheLevels($localCache, $distributedCache);

        // 构建缓存链
        $this->buildCacheChain();
    }

    /**
     * @param array<string> $keys
     *
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        $items = [];
        $missingKeys = $keys;

        foreach ($this->cacheLevels as $level) {
            if ([] === $missingKeys || !$level->isEnabled()) {
                continue;
            }

            $foundItems = $this->getItemsFromLevel($level, $missingKeys);
            $items = array_merge($items, $foundItems);
            $missingKeys = array_diff($missingKeys, array_keys($foundItems));
        }

        $this->stats->incrementRequests();

        return $items;
    }

    public function getItem(string $key): CacheItemInterface
    {
        $this->stats->incrementRequests();

        foreach ($this->cacheLevels as $level) {
            if (!$level->isEnabled()) {
                continue;
            }

            $item = $level->getItem($key);
            if ($item->isHit()) {
                $this->stats->incrementHit($level->getName());
                $this->logger->debug('Cache hit', ['level' => $level->getName(), 'key' => $key]);
                $this->backfillPreviousLevels($level, $item);

                return $item;
            }

            $this->stats->incrementMiss($level->getName());
        }

        $this->logger->debug('Cache miss at all levels', ['key' => $key]);

        return $this->chainAdapter->getItem($key);
    }

    public function save(CacheItemInterface $item): bool
    {
        $results = [];

        foreach ($this->cacheLevels as $level) {
            if ($level->isEnabled()) {
                $results[] = $level->save($item);
            }
        }

        $success = [] === $results || !\in_array(false, $results, true);

        $this->logger->debug('Saved item to all cache levels', [
            'key' => $item->getKey(),
            'success' => $success,
        ]);

        return $success;
    }

    public function hasItem(string $key): bool
    {
        return $this->chainAdapter->hasItem($key);
    }

    public function clear(): bool
    {
        $results = [];

        foreach ($this->cacheLevels as $level) {
            if ($level->isEnabled()) {
                $results[] = $level->clear();
            }
        }

        $success = [] === $results || !\in_array(false, $results, true);
        $this->stats->reset();

        return $success;
    }

    public function deleteItem(string $key): bool
    {
        $results = [];

        foreach ($this->cacheLevels as $level) {
            if ($level->isEnabled()) {
                $results[] = $level->deleteItem($key);
            }
        }

        $this->logger->debug('Deleted item from all cache levels', ['key' => $key]);

        return [] === $results || !\in_array(false, $results, true);
    }

    public function deleteItems(array $keys): bool
    {
        $results = [];

        foreach ($this->cacheLevels as $level) {
            if ($level->isEnabled()) {
                $results[] = $level->deleteItems($keys);
            }
        }

        $this->logger->debug('Deleted items from all cache levels', ['keys' => $keys]);

        return [] === $results || !\in_array(false, $results, true);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->chainAdapter->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->chainAdapter->commit();
    }

    /**
     * 获取缓存统计信息.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return $this->stats->getStats();
    }

    /**
     * 设置缓存级别配置.
     *
     * @param array<string, mixed> $config
     */
    public function setLevelConfig(string $level, array $config): void
    {
        if (isset($this->levelConfigs[$level])) {
            $this->levelConfigs[$level] = array_merge($this->levelConfigs[$level], $config);
            $this->buildCacheChain();
        }
    }

    /**
     * 预热缓存.
     *
     * @param array<string, mixed> $data 键值对数据
     */
    public function warmup(array $data, int $ttl = 3600): void
    {
        foreach ($data as $key => $value) {
            $item = $this->chainAdapter->getItem($key);
            $item->set($value);
            $item->expiresAfter($ttl);
            $this->save($item);
        }

        $this->logger->info('Cache warmed up', ['items' => \count($data)]);
    }

    /**
     * 初始化缓存层级.
     */
    private function initializeCacheLevels(
        CacheItemPoolInterface $localCache,
        ?CacheItemPoolInterface $distributedCache,
    ): void {
        // 内存缓存
        if (true === $this->levelConfigs['memory']['enabled']) {
            /** @var int $defaultLifetime */
            $defaultLifetime = $this->levelConfigs['memory']['default_lifetime'] ?? 0;
            /** @var float $maxItems */
            $maxItems = $this->levelConfigs['memory']['max_items'] ?? 0.0;
            $memoryCache = new ArrayAdapter(
                $defaultLifetime,
                false,
                $maxItems
            );
            $this->cacheLevels['memory'] = new CacheLevel($memoryCache, 'memory', $this->levelConfigs['memory']);
        }

        // 本地缓存
        if (true === $this->levelConfigs['local']['enabled']) {
            $this->cacheLevels['local'] = new CacheLevel($localCache, 'local', $this->levelConfigs['local']);
        }

        // 分布式缓存
        if (true === $this->levelConfigs['distributed']['enabled'] && null !== $distributedCache) {
            $this->cacheLevels['distributed'] = new CacheLevel($distributedCache, 'distributed', $this->levelConfigs['distributed']);
        }
    }

    /**
     * 构建缓存链.
     */
    private function buildCacheChain(): void
    {
        $adapters = [];

        foreach ($this->cacheLevels as $level) {
            if ($level->isEnabled()) {
                // Note: We need to access the underlying cache for ChainAdapter
                // This is a simplified approach - in production, you might want a different strategy
                $adapters[] = $this->getAdapterFromLevel($level);
            }
        }

        $this->chainAdapter = new ChainAdapter($adapters);
    }

    private function getAdapterFromLevel(CacheLevel $level): AdapterInterface
    {
        // This is a workaround to get the underlying adapter
        // In a real implementation, you might want to store the adapter reference directly
        $reflection = new \ReflectionProperty($level, 'cache');
        $reflection->setAccessible(true);
        $cache = $reflection->getValue($level);

        if (!$cache instanceof AdapterInterface) {
            throw new InvalidCacheStrategyException('Cache level must use an AdapterInterface implementation');
        }

        return $cache;
    }

    /**
     * @param array<string> $keys
     *
     * @return array<string, CacheItemInterface>
     */
    private function getItemsFromLevel(CacheLevel $level, array $keys): array
    {
        $items = [];
        $levelItems = $level->getItems($keys);

        foreach ($levelItems as $key => $item) {
            if ($item->isHit()) {
                $items[$key] = $item;
                $this->stats->incrementHit($level->getName());
                $this->backfillPreviousLevels($level, $item);
            } else {
                $this->stats->incrementMiss($level->getName());
            }
        }

        return $items;
    }

    /**
     * 回填缓存到更高优先级的层级.
     */
    private function backfillPreviousLevels(CacheLevel $currentLevel, CacheItemInterface $item): void
    {
        $levelNames = array_keys($this->cacheLevels);
        $currentIndex = array_search($currentLevel->getName(), $levelNames, true);

        if (false === $currentIndex) {
            return;
        }

        // 回填到更高优先级的层级（索引更小的层级）
        for ($i = 0; $i < $currentIndex; ++$i) {
            $levelName = $levelNames[$i];
            if (isset($this->cacheLevels[$levelName]) && $this->cacheLevels[$levelName]->isEnabled()) {
                $this->cacheLevels[$levelName]->save($item);
            }
        }
    }
}
