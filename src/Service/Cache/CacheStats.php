<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Cache;

/**
 * 缓存统计信息管理器.
 */
final class CacheStats
{
    /**
     * @var array<string, int>
     */
    private array $stats = [
        'memory_hits' => 0,
        'memory_misses' => 0,
        'local_hits' => 0,
        'local_misses' => 0,
        'distributed_hits' => 0,
        'distributed_misses' => 0,
        'total_requests' => 0,
    ];

    public function incrementHit(string $level): void
    {
        ++$this->stats[$level . '_hits'];
    }

    public function incrementMiss(string $level): void
    {
        ++$this->stats[$level . '_misses'];
    }

    public function incrementRequests(): void
    {
        ++$this->stats['total_requests'];
    }

    public function reset(): void
    {
        $this->stats = [
            'memory_hits' => 0,
            'memory_misses' => 0,
            'local_hits' => 0,
            'local_misses' => 0,
            'distributed_hits' => 0,
            'distributed_misses' => 0,
            'total_requests' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $totalHits = $this->stats['memory_hits'] + $this->stats['local_hits'] + $this->stats['distributed_hits'];
        $totalMisses = $this->stats['memory_misses'] + $this->stats['local_misses'] + $this->stats['distributed_misses'];
        $totalRequests = $this->stats['total_requests'];

        $hitRate = $totalRequests > 0
            ? round($totalHits / $totalRequests, 3)
            : 0.0;

        return [
            'requests' => $totalRequests,
            'hits' => $totalHits,
            'misses' => $totalMisses,
            'hit_rate' => $hitRate,
            'level_stats' => [
                'memory' => [
                    'hits' => $this->stats['memory_hits'],
                    'misses' => $this->stats['memory_misses'],
                ],
                'local' => [
                    'hits' => $this->stats['local_hits'],
                    'misses' => $this->stats['local_misses'],
                ],
                'distributed' => [
                    'hits' => $this->stats['distributed_hits'],
                    'misses' => $this->stats['distributed_misses'],
                ],
            ],
        ];
    }
}
