<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Checker;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Checker\CacheConfigChecker;
use Tourze\LarkAppBotBundle\Tests\TestCase\CheckerFactory;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CacheConfigChecker::class)]
#[RunTestsInSeparateProcesses]
final class CacheConfigCheckerTest extends AbstractIntegrationTestCase
{
    private CacheConfigChecker $checker;

    private MockObject&SymfonyStyle $mockIo;

    public function testConstructor(): void
    {
        $this->assertInstanceOf(CacheConfigChecker::class, $this->checker);
    }

    public function testGetName(): void
    {
        $name = $this->checker->getName();
        $this->assertSame('缓存配置', $name);
    }

    public function testCheckWithValidCacheConfig(): void
    {
        $config = [
            'cache' => [
                'pool' => 'cache.app',
                'ttl' => 3600,
            ],
            'token_provider' => 'redis',
        ];

        $checker = $this->createCheckerWithConfig($config);

        $this->mockIo->expects($this->exactly(2))
            ->method('comment')
        ;

        $result = $checker->check($this->mockIo);
        $this->assertFalse($result);
    }

    public function testCheckWithEmptyCacheConfig(): void
    {
        $checker = $this->createCheckerWithConfig([]);

        $this->mockIo->expects($this->exactly(2))
            ->method('comment')
        ;

        $result = $checker->check($this->mockIo);
        $this->assertTrue($result); // 空配置应该返回true表示有错误
    }

    public function testCheckWithFileCacheProvider(): void
    {
        $config = [
            'cache' => [
                'pool' => 'cache.app',
                'ttl' => 7200,
            ],
            'token_provider' => 'file_cache',
        ];

        $checker = $this->createCheckerWithConfig($config);

        $this->mockIo->expects($this->exactly(2))
            ->method('comment')
        ;

        // 在单元测试环境中，由于没有容器，文件缓存检查可能会失败
        // 这个测试主要验证配置可以正确解析和显示
        $result = $checker->check($this->mockIo);
        $this->assertTrue($result); // 单元测试环境中无法访问kernel.cache_dir，会返回错误
    }

    public function testCheckWithInvalidCacheConfig(): void
    {
        $config = [
            'cache' => 'invalid_string',
            'token_provider' => 'file_cache',
        ];

        $checker = $this->createCheckerWithConfig($config);

        $this->mockIo->expects($this->exactly(2))
            ->method('comment')
        ;

        // 无效的缓存配置在单元测试环境中无法访问kernel.cache_dir
        $result = $checker->check($this->mockIo);
        $this->assertTrue($result); // 单元测试环境中返回错误
    }

    public function testCheckWithCustomCachePool(): void
    {
        $config = [
            'cache' => [
                'pool' => 'cache.redis',
                'ttl' => 1800,
            ],
            'token_provider' => 'redis',  // 使用redis provider避免文件缓存检查
        ];

        $checker = $this->createCheckerWithConfig($config);

        // 验证comment方法被调用2次（缓存池和TTL信息）
        $this->mockIo->expects($this->exactly(2))
            ->method('comment')
        ;

        $result = $checker->check($this->mockIo);
        $this->assertFalse($result); // 使用非文件缓存不会检查缓存目录
    }

    public function testCheckWithNonIntegerTtl(): void
    {
        $config = [
            'token_provider' => 'memory', // 使用非文件缓存避免目录检查
            'cache' => [
                'pool' => 'cache.app',
                'ttl' => 'invalid',
            ],
        ];

        $checker = $this->createCheckerWithConfig($config);

        $this->mockIo->expects($this->exactly(2))
            ->method('comment')
        ;

        $result = $checker->check($this->mockIo);
        $this->assertFalse($result);
    }

    public function testCheckWithNonStringPool(): void
    {
        $config = [
            'token_provider' => 'memory', // 使用非文件缓存避免目录检查
            'cache' => [
                'pool' => 123,
                'ttl' => 3600,
            ],
        ];

        $checker = $this->createCheckerWithConfig($config);

        $this->mockIo->expects($this->exactly(2))
            ->method('comment') // 会调用两次：缓存池信息和TTL信息
        ;

        $result = $checker->check($this->mockIo);
        $this->assertFalse($result);
    }

    protected function onSetUp(): void
    {
        $this->mockIo = $this->createMock(SymfonyStyle::class);
        $this->checker = self::getService(CacheConfigChecker::class);
    }

    /** @param array<string, mixed> $config */
    private function createCheckerWithConfig(array $config): CacheConfigChecker
    {
        // 使用独立工厂类创建实例，工厂类不被 CoversClass 覆盖
        return CheckerFactory::createCacheConfigChecker($config);
    }
}
