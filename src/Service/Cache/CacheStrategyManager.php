<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Cache;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\InvalidCacheStrategyException;
use Tourze\LarkAppBotBundle\Service\Performance\PerformanceMonitor;

/**
 * 缓存策略管理器
 * 根据不同的数据类型和访问模式选择最优的缓存策略.
 */
#[Autoconfigure(public: true)]
final class CacheStrategyManager
{
    /**
     * 缓存策略常量.
     */
    public const STRATEGY_TOKEN = 'token';
    public const STRATEGY_USER = 'user';
    public const STRATEGY_GROUP = 'group';
    public const STRATEGY_MESSAGE = 'message';
    public const STRATEGY_MENU = 'menu';
    public const STRATEGY_CONFIG = 'config';
    public const STRATEGY_TEMP = 'temp';

    /**
     * 策略配置.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $strategies = [
        self::STRATEGY_TOKEN => [
            'ttl' => 3600, // 1小时
            'prefix' => 'token_',
            'memory_enabled' => true,
            'distributed_enabled' => false,
        ],
        self::STRATEGY_USER => [
            'ttl' => 86400, // 24小时
            'prefix' => 'user_',
            'memory_enabled' => true,
            'distributed_enabled' => true,
        ],
        self::STRATEGY_GROUP => [
            'ttl' => 43200, // 12小时
            'prefix' => 'group_',
            'memory_enabled' => true,
            'distributed_enabled' => true,
        ],
        self::STRATEGY_MESSAGE => [
            'ttl' => 300, // 5分钟
            'prefix' => 'msg_',
            'memory_enabled' => true,
            'distributed_enabled' => false,
        ],
        self::STRATEGY_MENU => [
            'ttl' => 604800, // 7天
            'prefix' => 'menu_',
            'memory_enabled' => true,
            'distributed_enabled' => true,
        ],
        self::STRATEGY_CONFIG => [
            'ttl' => 2592000, // 30天
            'prefix' => 'config_',
            'memory_enabled' => true,
            'distributed_enabled' => true,
        ],
        self::STRATEGY_TEMP => [
            'ttl' => 60, // 1分钟
            'prefix' => 'temp_',
            'memory_enabled' => true,
            'distributed_enabled' => false,
        ],
    ];

    public function __construct(
        private readonly MultiLevelCache $multiLevelCache,
        private readonly LoggerInterface $logger,
        private readonly ?PerformanceMonitor $performanceMonitor = null,
    ) {
    }

    /**
     * 删除缓存.
     */
    public function delete(string $strategy, string $key): bool
    {
        $config = $this->getStrategyConfig($strategy);
        /** @var string $prefix */
        $prefix = $config['prefix'];
        $cacheKey = $prefix . $key;

        return $this->multiLevelCache->deleteItem($cacheKey);
    }

    /**
     * 批量删除缓存.
     *
     * @param array<string> $keys
     */
    public function deleteMultiple(string $strategy, array $keys): bool
    {
        if ([] === $keys) {
            // 空键列表视为已删除
            return true;
        }
        $config = $this->getStrategyConfig($strategy);
        /** @var string $prefix */
        $prefix = $config['prefix'];
        $cacheKeys = array_map(fn ($key) => $prefix . $key, $keys);

        return $this->multiLevelCache->deleteItems($cacheKeys);
    }

    /**
     * 清空策略下的所有缓存.
     */
    public function clearStrategy(string $strategy): bool
    {
        $config = $this->getStrategyConfig($strategy);

        // PSR-6 的 clear() 方法不接受参数，需要手动删除指定前缀的项目
        // 这里我们简化处理，清空整个缓存
        return $this->multiLevelCache->clear();
    }

    /**
     * 检查缓存是否存在.
     */
    public function has(string $strategy, string $key): bool
    {
        $config = $this->getStrategyConfig($strategy);
        /** @var string $prefix */
        $prefix = $config['prefix'];
        $cacheKey = $prefix . $key;

        return $this->multiLevelCache->hasItem($cacheKey);
    }

    /**
     * 批量获取缓存.
     *
     * @param array<string> $keys
     *
     * @return array<string, mixed>
     */
    public function getMultiple(string $strategy, array $keys): array
    {
        $config = $this->getStrategyConfig($strategy);
        /** @var string $prefix */
        $prefix = $config['prefix'];
        $cacheKeys = array_map(fn ($key) => $prefix . $key, $keys);

        $items = $this->multiLevelCache->getItems($cacheKeys);
        $result = [];

        foreach ($items as $cacheKey => $item) {
            if ($item->isHit()) {
                // 移除前缀以获取原始键
                $originalKey = substr($cacheKey, \strlen($prefix));
                $result[$originalKey] = $item->get();
            }
        }

        return $result;
    }

    /**
     * 根据策略获取缓存值
     *
     * @template T
     *
     * @param callable(): T $callback
     * @param array<string> $tags
     *
     * @return T
     */
    public function get(
        string $strategy,
        string $key,
        callable $callback,
        ?int $ttl = null,
        array $tags = [],
    ): mixed {
        $config = $this->getStrategyConfig($strategy);
        /** @var string $prefix */
        $prefix = $config['prefix'];
        /** @var int $defaultTtl */
        $defaultTtl = $config['ttl'];
        $cacheKey = $prefix . $key;
        $ttl ??= $defaultTtl;

        // 配置缓存级别
        $this->configureStrategyLevels($strategy);

        // 监控缓存操作
        if (null !== $this->performanceMonitor) {
            return $this->performanceMonitor->monitorCacheOperation(
                'get',
                $strategy,
                function () use ($cacheKey, $callback, $ttl) {
                    return $this->doGet($cacheKey, $callback, $ttl, []);
                }
            );
        }

        return $this->doGet($cacheKey, $callback, $ttl, []);
    }

    /**
     * 设置缓存值
     *
     * @param array<string> $tags
     */
    public function set(
        string $strategy,
        string $key,
        mixed $value,
        ?int $ttl = null,
        array $tags = [],
    ): bool {
        $config = $this->getStrategyConfig($strategy);
        /** @var string $prefix */
        $prefix = $config['prefix'];
        /** @var int $defaultTtl */
        $defaultTtl = $config['ttl'];
        $cacheKey = $prefix . $key;
        $ttl ??= $defaultTtl;

        // 配置缓存级别
        $this->configureStrategyLevels($strategy);

        $item = $this->multiLevelCache->getItem($cacheKey);
        $item->set($value);
        $item->expiresAfter($ttl);

        // PSR-6 标准不支持标签，这里只是记录标签信息
        if ([] !== $tags && method_exists($item, 'tag')) {
            $item->tag($tags);
        }

        return $this->multiLevelCache->save($item);
    }

    /**
     * 预热缓存.
     *
     * @param array<string, mixed> $data
     */
    public function warmup(string $strategy, array $data, ?int $ttl = null): void
    {
        $config = $this->getStrategyConfig($strategy);
        /** @var string $prefix */
        $prefix = $config['prefix'];
        /** @var int $defaultTtl */
        $defaultTtl = $config['ttl'];
        $ttl ??= $defaultTtl;

        $prefixedData = [];
        foreach ($data as $key => $value) {
            $prefixedData[$prefix . $key] = $value;
        }

        // 逐项写入以兼容不同缓存层实现，便于测试替身断言
        foreach ($prefixedData as $cacheKey => $value) {
            $item = $this->multiLevelCache->getItem($cacheKey);
            $item->set($value);
            $item->expiresAfter($ttl);
            $this->multiLevelCache->save($item);
        }

        $this->logger->info('Cache strategy warmed up', [
            'strategy' => $strategy,
            'items' => \count($data),
        ]);
    }

    /**
     * 获取策略统计信息.
     *
     * @return array<string, mixed>
     */
    public function getStrategyStats(): array
    {
        return [
            'strategies' => array_keys($this->strategies),
            'cache_stats' => $this->multiLevelCache->getStats(),
        ];
    }

    /**
     * 添加或更新策略.
     *
     * @param array<string, mixed> $config
     */
    public function addStrategy(string $name, array $config): void
    {
        $this->strategies[$name] = array_merge([
            'ttl' => 3600,
            'prefix' => $name . '_',
            'memory_enabled' => true,
            'distributed_enabled' => false,
        ], $config);

        $this->logger->info('Cache strategy added/updated', [
            'strategy' => $name,
            'config' => $config,
        ]);
    }

    /**
     * 智能缓存决策
     * 根据访问频率和数据大小自动调整缓存策略.
     *
     * @param array<string, mixed> $metrics
     */
    public function optimizeStrategy(string $strategy, array $metrics): void
    {
        $config = $this->getStrategyConfig($strategy);
        /** @var int $currentTtl */
        $currentTtl = $config['ttl'];

        // 基于访问频率调整TTL
        if ($metrics['access_frequency'] > 100) {
            $config['ttl'] = min($currentTtl * 2, 86400); // 最多1天
        } elseif ($metrics['access_frequency'] < 10) {
            $config['ttl'] = max((int) ($currentTtl / 2), 60); // 最少1分钟
        }

        // 基于数据大小决定是否使用分布式缓存
        if ($metrics['avg_size'] > 1024 * 100) { // 100KB
            $config['distributed_enabled'] = true;
            $config['memory_enabled'] = false;
        }

        $this->strategies[$strategy] = $config;

        $this->logger->info('Cache strategy optimized', [
            'strategy' => $strategy,
            'metrics' => $metrics,
            'new_config' => $config,
        ]);
    }

    /**
     * 获取策略配置.
     *
     * @return array<string, mixed>
     */
    private function getStrategyConfig(string $strategy): array
    {
        if (!isset($this->strategies[$strategy])) {
            throw new InvalidCacheStrategyException("Unknown cache strategy: {$strategy}");
        }

        $config = $this->strategies[$strategy];
        \assert(\is_string($config['prefix']) && \is_int($config['ttl']));

        return $config;
    }

    /**
     * 根据策略配置缓存级别.
     */
    private function configureStrategyLevels(string $strategy): void
    {
        $config = $this->getStrategyConfig($strategy);

        $this->multiLevelCache->setLevelConfig('memory', [
            'enabled' => $config['memory_enabled'] ?? true,
        ]);

        $this->multiLevelCache->setLevelConfig('distributed', [
            'enabled' => $config['distributed_enabled'] ?? false,
        ]);
    }

    /**
     * 执行缓存获取.
     *
     * @template T
     *
     * @param callable(): T $callback
     * @param array<string> $tags
     *
     * @return T
     */
    private function doGet(string $cacheKey, callable $callback, int $ttl, array $tags): mixed
    {
        $item = $this->multiLevelCache->getItem($cacheKey);

        if ($item->isHit()) {
            $this->logger->debug('Cache hit', [
                'key' => $cacheKey,
                'tags' => $tags,
            ]);

            return $item->get();
        }

        // 缓存未命中，执行回调
        $value = $callback();

        // 保存到缓存
        $item->set($value);
        $item->expiresAfter($ttl);

        // PSR-6 标准不支持标签，这里只是记录标签信息
        if ([] !== $tags && method_exists($item, 'tag')) {
            $item->tag($tags);
        }

        $this->multiLevelCache->save($item);

        $this->logger->debug('Cache miss and saved', [
            'key' => $cacheKey,
            'ttl' => $ttl,
            'tags' => $tags,
        ]);

        return $value;
    }
}
