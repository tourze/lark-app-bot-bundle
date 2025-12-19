<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * 缓存级别处理器.
 */
final class CacheLevel
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly string $name,
        private readonly array $config,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    public function getItem(string $key): CacheItemInterface
    {
        return $this->cache->getItem($key);
    }

    /**
     * @param array<string> $keys
     *
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys): iterable
    {
        return $this->cache->getItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->cache->save($item);
    }

    public function deleteItem(string $key): bool
    {
        return $this->cache->deleteItem($key);
    }

    /**
     * @param array<string> $keys
     */
    public function deleteItems(array $keys): bool
    {
        return $this->cache->deleteItems($keys);
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }

    public function hasItem(string $key): bool
    {
        return $this->cache->hasItem($key);
    }
}
