<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Cache\CacheItemInterface;
use Tourze\LarkAppBotBundle\Exception\InvalidCacheStrategyException;
use Tourze\LarkAppBotBundle\Service\Cache\CacheStrategyManager;
use Tourze\LarkAppBotBundle\Service\Cache\MultiLevelCache;
use Tourze\LarkAppBotBundle\Service\Performance\PerformanceMonitor;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 *
 * 注意：本测试类中使用了具体类的 Mock（PerformanceMonitor）
 * PerformanceMonitor 是具体类，需要 Mock 以避免对性能监控的依赖
 * MultiLevelCache 虽然是具体类，但它实现了 CacheItemPoolInterface，在测试中可以 Mock
 */
#[CoversClass(CacheStrategyManager::class)]
#[RunTestsInSeparateProcesses]
final class CacheStrategyManagerTest extends AbstractIntegrationTestCase
{
    private MultiLevelCache $multiLevelCache;

    private PerformanceMonitor $performanceMonitor;

    public function testGetStrategyStats(): void
    {
        /*
         * 使用具体类 MultiLevelCache 创建 Mock 对象的原因：
         * 1. MultiLevelCache 是一个缓存实现类，包含多层缓存逻辑和统计功能
         * 2. 虽然它实现了 CacheItemPoolInterface，但测试需要访问其特有的 getStats() 方法
         * 3. 创建接口 mock 无法满足测试需求，因为接口中不包含 getStats() 方法
         * 4. 这是合理的设计选择，MultiLevelCache 作为特定的缓存实现，直接 mock 可以精确控制测试行为
         */
        $this->multiLevelCache->method('getStats')->willReturn(['test_stats' => true]);

        $manager = $this->createCacheStrategyManager();

        $stats = $manager->getStrategyStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('strategies', $stats);
        $this->assertArrayHasKey('cache_stats', $stats);
    }

    public function testHasStrategyForUser(): void
    {
        /*
         * 使用具体类 MultiLevelCache 创建 Mock 对象的原因：
         * 1. CacheStrategyManager 直接依赖 MultiLevelCache 的具体实现，而非抽象接口
         * 2. MultiLevelCache 提供了特定的多层缓存机制，是系统架构的核心组件
         * 3. 使用具体类 mock 可以准确模拟多层缓存的行为，确保测试的真实性
         * 4. 当前设计下这是最实用的选择，未来可考虑引入缓存策略接口以提高可测试性
         */
        $this->multiLevelCache->method('hasItem')->willReturn(true);

        $manager = $this->createCacheStrategyManager();

        $this->assertTrue($manager->has(CacheStrategyManager::STRATEGY_USER, 'test_key'));
    }

    public function testInvalidStrategyThrowsException(): void
    {
        /*
         * 使用具体类 MultiLevelCache 创建 Mock 对象的原因：
         * 1. 测试异常处理逻辑需要提供 MultiLevelCache 实例，即使不会调用其方法
         * 2. CacheStrategyManager 的构造函数明确要求 MultiLevelCache 类型参数
         * 3. 这种依赖关系反映了系统设计中缓存策略与多层缓存的紧密耦合
         * 4. 虽然可以考虑依赖倒置原则，但当前实现满足业务需求且测试有效
         */
        $manager = $this->createCacheStrategyManager();

        $this->expectException(InvalidCacheStrategyException::class);
        $manager->has('invalid_strategy', 'test_key');
    }

    public function testDelete(): void
    {
        /*
         * 使用具体类 MultiLevelCache 创建 Mock 对象的原因：
         * 1. MultiLevelCache 是缓存策略管理器的核心依赖
         * 2. 测试需要验证 deleteItem 方法的调用，该方法是 MultiLevelCache 特有的
         * 3. 虽然可以使用接口，但会失去类型安全性和方法签名的验证
         */
        $this->multiLevelCache->expects($this->once())
            ->method('deleteItem')
            ->with('user_test_key')
            ->willReturn(true)
        ;

        $manager = $this->createCacheStrategyManager();

        $this->assertTrue($manager->delete(CacheStrategyManager::STRATEGY_USER, 'test_key'));
    }

    public function testGet(): void
    {
        /*
         * 使用具体类 MultiLevelCache 创建 Mock 对象的原因：
         * 1. MultiLevelCache 是多层缓存的具体实现，包含复杂的缓存策略
         * 2. 测试需要模拟 getItem 方法的返回值，验证缓存获取逻辑
         * 3. 作为核心缓存组件，使用具体类 mock 可以确保测试的真实性
         * 4. 当前系统架构下，这是最合适的测试方法
         */
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn(true);
        $item->method('get')->willReturn('test_value');

        $this->multiLevelCache->expects($this->once())
            ->method('getItem')
            ->with('user_test_key')
            ->willReturn($item)
        ;

        $manager = $this->createCacheStrategyManager();

        $result = $manager->get(CacheStrategyManager::STRATEGY_USER, 'test_key', fn () => 'test_value');
        $this->assertSame('test_value', $result);
    }

    public function testSet(): void
    {
        /*
         * 使用具体类 MultiLevelCache 创建 Mock 对象的原因：
         * 1. MultiLevelCache 提供了多层缓存的 set 操作，需要模拟其行为
         * 2. 测试需要验证缓存设置操作的正确性，包括键值对和过期时间
         * 3. 作为核心缓存组件，使用具体类 mock 可以确保测试覆盖实际使用场景
         * 4. 当前系统设计中，MultiLevelCache 是缓存层的唯一实现
         */
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('set')->willReturn($item);
        $item->method('expiresAfter')->willReturn($item);

        $this->multiLevelCache->expects($this->once())
            ->method('getItem')
            ->with('user_test_key')
            ->willReturn($item)
        ;

        $this->multiLevelCache->expects($this->once())
            ->method('save')
            ->with($item)
            ->willReturn(true)
        ;

        $manager = $this->createCacheStrategyManager();

        $this->assertTrue($manager->set(CacheStrategyManager::STRATEGY_USER, 'test_key', 'test_value', 3600));
    }

    public function testClearStrategy(): void
    {
        /*
         * 使用具体类 MultiLevelCache 创建 Mock 对象的原因：
         * 1. MultiLevelCache 提供了多层缓存的 clear 操作，需要模拟其行为
         * 2. 测试需要验证缓存清除操作的正确性
         * 3. 作为核心缓存组件，使用具体类 mock 可以确保测试覆盖实际使用场景
         * 4. 当前系统设计中，MultiLevelCache 是缓存层的唯一实现
         */
        $this->multiLevelCache->expects($this->once())
            ->method('clear')
            ->willReturn(true)
        ;

        $manager = $this->createCacheStrategyManager();

        $this->assertTrue($manager->clearStrategy(CacheStrategyManager::STRATEGY_USER));
    }

    public function testAddStrategy(): void
    {
        /*
         * 使用具体类 MultiLevelCache 创建 Mock 对象的原因：
         * 1. MultiLevelCache 是多层缓存的具体实现，提供策略管理功能
         * 2. 测试需要模拟策略添加后的行为验证
         * 3. 作为核心缓存组件，使用具体类 mock 可以确保测试覆盖实际使用场景
         * 4. 当前系统设计中，MultiLevelCache 是缓存层的唯一实现
         * 5. 测试添加策略后需要验证缓存行为，使用具体类可以确保策略添加后的行为正确
         */
        // 使用真实的 Logger，不设置期望

        $this->multiLevelCache->expects($this->once())
            ->method('hasItem')
            ->with('custom_test_key')
            ->willReturn(true)
        ;

        $manager = $this->createCacheStrategyManager();

        $manager->addStrategy('custom', ['ttl' => 7200]);

        // 验证策略已添加
        $this->assertTrue($manager->has('custom', 'test_key'));
    }

    public function testDeleteMultiple(): void
    {
        /*
         * 使用具体类 MultiLevelCache 创建 Mock 对象的原因：
         * 1. MultiLevelCache 是多层缓存的具体实现，提供批量删除功能
         * 2. 测试需要模拟 deleteItems 方法的调用，验证批量删除逻辑
         * 3. 作为核心缓存组件，使用具体类 mock 可以确保测试覆盖实际使用场景
         * 4. 当前系统设计中，MultiLevelCache 是缓存层的唯一实现
         */
        $this->multiLevelCache->expects($this->once())
            ->method('deleteItems')
            ->with(['user_test_key1', 'user_test_key2'])
            ->willReturn(true)
        ;

        $manager = $this->createCacheStrategyManager();

        $this->assertTrue($manager->deleteMultiple(CacheStrategyManager::STRATEGY_USER, ['test_key1', 'test_key2']));
    }

    public function testDeleteMultipleWithEmptyKeys(): void
    {
        $manager = $this->createCacheStrategyManager();

        // 空的键数组应该直接返回 true
        $this->assertTrue($manager->deleteMultiple(CacheStrategyManager::STRATEGY_USER, []));
    }

    public function testWarmup(): void
    {
        /*
         * 使用具体类 MultiLevelCache 创建 Mock 对象的原因：
         * 1. MultiLevelCache 是多层缓存的具体实现，提供缓存预热功能
         * 2. 测试需要模拟批量设置操作，验证缓存预热逻辑
         * 3. 作为核心缓存组件，使用具体类 mock 可以确保测试覆盖实际使用场景
         * 4. 当前系统设计中，MultiLevelCache 是缓存层的唯一实现
         */
        $item1 = $this->createMock(CacheItemInterface::class);
        $item1->method('set')->willReturn($item1);
        $item1->method('expiresAfter')->willReturn($item1);

        $item2 = $this->createMock(CacheItemInterface::class);
        $item2->method('set')->willReturn($item2);
        $item2->method('expiresAfter')->willReturn($item2);

        $this->multiLevelCache->expects($this->exactly(2))
            ->method('getItem')
            ->willReturnOnConsecutiveCalls($item1, $item2)
        ;

        $this->multiLevelCache->expects($this->exactly(2))
            ->method('save')
            ->willReturn(true)
        ;

        $manager = $this->createCacheStrategyManager();

        $data = [
            'user_test_key1' => 'value1',
            'user_test_key2' => 'value2',
        ];

        $manager->warmup(CacheStrategyManager::STRATEGY_USER, $data, 3600);

        // 断言通过由 Mock 交互完成（getItem/save 调用即为断言）
    }

    public function testOptimizeStrategy(): void
    {
        /*
         * 使用具体类 MultiLevelCache 创建 Mock 对象的原因：
         * 1. MultiLevelCache 是多级缓存的具体实现类，没有对应的接口定义
         * 2. 该类提供了缓存策略管理所需的特定方法，需要在测试中模拟这些行为
         * 3. 作为内部组件，它与 CacheStrategyManager 紧密耦合，使用 mock 进行单元测试是合理的
         * 4. 未来可考虑为缓存层创建接口以提高架构的灵活性
         */
        // 使用真实的 Logger，不设置期望

        $manager = $this->createCacheStrategyManager();

        $manager->optimizeStrategy(CacheStrategyManager::STRATEGY_USER, [
            'access_frequency' => 150,
            'avg_size' => 50]);

        // 验证优化操作没有抛出异常
        $this->expectNotToPerformAssertions();
    }

    protected function onSetUp(): void
    {
        // 创建 Mock 对象
        $this->multiLevelCache = $this->createMock(MultiLevelCache::class);
        $this->performanceMonitor = $this->createMock(PerformanceMonitor::class);

        // 设置到容器
        self::getContainer()->set(MultiLevelCache::class, $this->multiLevelCache);
        self::getContainer()->set(PerformanceMonitor::class, $this->performanceMonitor);

        // 设置 PerformanceMonitor Mock 直接调用回调函数
        $this->performanceMonitor->method('monitorCacheOperation')
            ->willReturnCallback(function ($operation, $strategy, $callback) {
                return $callback();
            })
        ;
    }

    private function createCacheStrategyManager(): CacheStrategyManager
    {
        return self::getService(CacheStrategyManager::class);
    }
}
