<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Service\Cache\CacheStats;

/**
 * @internal
 */
#[CoversClass(CacheStats::class)]
final class CacheStatsTest extends TestCase
{
    private CacheStats $cacheStats;

    protected function setUp(): void
    {
        // 直接创建 CacheStats 实例进行测试
        $this->cacheStats = new CacheStats();
    }

    public function testInitialStats(): void
    {
        $stats = $this->cacheStats->getStats();

        $this->assertSame(0, $stats['requests']);
        $this->assertSame(0, $stats['hits']);
        $this->assertSame(0, $stats['misses']);
        $this->assertSame(0.0, $stats['hit_rate']);
        $this->assertIsArray($stats['level_stats']);
    }

    public function testIncrementRequests(): void
    {
        $this->cacheStats->incrementRequests();
        $this->cacheStats->incrementRequests();

        $stats = $this->cacheStats->getStats();
        $this->assertSame(2, $stats['requests']);
    }

    public function testIncrementHit(): void
    {
        $this->cacheStats->incrementHit('memory');
        $this->cacheStats->incrementHit('local');

        $stats = $this->cacheStats->getStats();
        $this->assertIsArray($stats);
        $this->assertSame(2, $stats['hits']);
        $this->assertIsArray($stats['level_stats']);
        $this->assertIsArray($stats['level_stats']['memory']);
        $this->assertSame(1, $stats['level_stats']['memory']['hits']);
        $this->assertIsArray($stats['level_stats']['local']);
        $this->assertSame(1, $stats['level_stats']['local']['hits']);
    }

    public function testIncrementMiss(): void
    {
        $this->cacheStats->incrementMiss('memory');
        $this->cacheStats->incrementMiss('memory');

        $stats = $this->cacheStats->getStats();
        $this->assertIsArray($stats);
        $this->assertSame(2, $stats['misses']);
        $this->assertIsArray($stats['level_stats']);
        $this->assertIsArray($stats['level_stats']['memory']);
        $this->assertSame(2, $stats['level_stats']['memory']['misses']);
    }

    public function testHitRate(): void
    {
        $this->cacheStats->incrementRequests();
        $this->cacheStats->incrementRequests();
        $this->cacheStats->incrementHit('memory');

        $stats = $this->cacheStats->getStats();
        $this->assertSame(0.5, $stats['hit_rate']);
    }

    public function testReset(): void
    {
        $this->cacheStats->incrementRequests();
        $this->cacheStats->incrementHit('memory');
        $this->cacheStats->incrementMiss('local');

        $this->cacheStats->reset();

        $stats = $this->cacheStats->getStats();
        $this->assertIsArray($stats);
        $this->assertSame(0, $stats['requests']);
        $this->assertSame(0, $stats['hits']);
        $this->assertSame(0, $stats['misses']);
        $this->assertSame(0.0, $stats['hit_rate']);
        $this->assertIsArray($stats['level_stats']);
        $this->assertIsArray($stats['level_stats']['memory']);
        $this->assertSame(0, $stats['level_stats']['memory']['hits']);
        $this->assertSame(0, $stats['level_stats']['memory']['misses']);
    }
}
