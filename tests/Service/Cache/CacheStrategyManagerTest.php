<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Exception\InvalidCacheStrategyException;
use Tourze\LarkAppBotBundle\Service\Cache\CacheStrategyManager;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CacheStrategyManager::class)]
#[RunTestsInSeparateProcesses]
final class CacheStrategyManagerTest extends AbstractIntegrationTestCase
{
    private CacheStrategyManager $manager;

    public function testGetStrategyStats(): void
    {
        $stats = $this->manager->getStrategyStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('strategies', $stats);
        $this->assertArrayHasKey('cache_stats', $stats);
    }

    public function testHasStrategyForUser(): void
    {
        // 测试默认的用户策略存在
        $this->assertTrue($this->manager->has(CacheStrategyManager::STRATEGY_USER, 'test_key'));
    }

    public function testInvalidStrategyThrowsException(): void
    {
        $this->expectException(InvalidCacheStrategyException::class);
        $this->manager->has('invalid_strategy', 'test_key');
    }

    public function testSet(): void
    {
        $result = $this->manager->set(CacheStrategyManager::STRATEGY_USER, 'test_key', 'test_value', 3600);
        $this->assertTrue($result);
    }

    public function testGet(): void
    {
        // 先设置一个值
        $this->manager->set(CacheStrategyManager::STRATEGY_USER, 'test_key', 'test_value');

        // 然后获取它 - get方法需要callback参数
        $result = $this->manager->get(CacheStrategyManager::STRATEGY_USER, 'test_key', fn () => 'default_value');
        $this->assertSame('test_value', $result);
    }

    public function testGetWithDefault(): void
    {
        // 获取一个不存在的值，应该返回默认值 - get方法需要callback参数
        $result = $this->manager->get(CacheStrategyManager::STRATEGY_USER, 'nonexistent_key', fn () => 'default_value');
        $this->assertSame('default_value', $result);
    }

    public function testHas(): void
    {
        // 测试不存在的键
        $this->assertFalse($this->manager->has(CacheStrategyManager::STRATEGY_USER, 'nonexistent_key'));

        // 设置一个键
        $this->manager->set(CacheStrategyManager::STRATEGY_USER, 'test_key', 'test_value');

        // 测试存在的键
        $this->assertTrue($this->manager->has(CacheStrategyManager::STRATEGY_USER, 'test_key'));
    }

    public function testDelete(): void
    {
        // 设置一个键
        $this->manager->set(CacheStrategyManager::STRATEGY_USER, 'test_key', 'test_value');

        // 确认存在
        $this->assertTrue($this->manager->has(CacheStrategyManager::STRATEGY_USER, 'test_key'));

        // 删除它
        $this->assertTrue($this->manager->delete(CacheStrategyManager::STRATEGY_USER, 'test_key'));

        // 确认不存在
        $this->assertFalse($this->manager->has(CacheStrategyManager::STRATEGY_USER, 'test_key'));
    }

    public function testClearStrategy(): void
    {
        $this->assertTrue($this->manager->clearStrategy(CacheStrategyManager::STRATEGY_USER));
    }

    public function testAddStrategy(): void
    {
        $this->manager->addStrategy('custom', ['ttl' => 7200]);

        // 验证策略已添加 - 使用has方法测试具体的key
        $this->assertTrue($this->manager->has('custom', 'test_key'));
    }

    public function testDeleteMultiple(): void
    {
        // 设置多个键
        $this->manager->set(CacheStrategyManager::STRATEGY_USER, 'test_key1', 'value1');
        $this->manager->set(CacheStrategyManager::STRATEGY_USER, 'test_key2', 'value2');

        // 批量删除
        $this->assertTrue($this->manager->deleteMultiple(CacheStrategyManager::STRATEGY_USER, ['test_key1', 'test_key2']));
    }

    public function testDeleteMultipleWithEmptyKeys(): void
    {
        // 空的键数组应该直接返回 true
        $this->assertTrue($this->manager->deleteMultiple(CacheStrategyManager::STRATEGY_USER, []));
    }

    public function testWarmup(): void
    {
        $data = [
            'user_test_key1' => 'value1',
            'user_test_key2' => 'value2',
        ];

        $this->manager->warmup(CacheStrategyManager::STRATEGY_USER, $data, 3600);

        // 验证数据已预热 - get方法需要callback参数
        $this->assertSame('value1', $this->manager->get(CacheStrategyManager::STRATEGY_USER, 'user_test_key1', fn () => 'default'));
        $this->assertSame('value2', $this->manager->get(CacheStrategyManager::STRATEGY_USER, 'user_test_key2', fn () => 'default'));
    }

    public function testOptimizeStrategy(): void
    {
        $this->manager->optimizeStrategy(CacheStrategyManager::STRATEGY_USER, [
            'access_frequency' => 150,
            'avg_size' => 50,
        ]);

        // 验证优化操作没有抛出异常
        $this->expectNotToPerformAssertions();
    }

    protected function onSetUp(): void
    {
        // 使用真实的Symfony服务容器获取服务
        $this->manager = self::getService(CacheStrategyManager::class);
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要Mock服务，全部使用真实实现
    }
}
