<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\TestCase;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * 测试用 EntityManager 模拟对象工厂
 *
 * @internal
 */
class TestEntityManagerFactory
{
    /**
     * @return MockObject&EntityManagerInterface
     */
    public static function create(): MockObject&EntityManagerInterface
    {
        // 使用非匿名类方式创建 mock
        $reflection = new \ReflectionClass(TestCase::class);
        $testCase = $reflection->newInstanceWithoutConstructor();

        // 使用反射调用 createMock 方法
        $method = $reflection->getMethod('createMock');
        $method->setAccessible(true);

        $mock = $method->invoke($testCase, EntityManagerInterface::class);

        // 确保 mock 实现了正确的接口
        if (!$mock instanceof EntityManagerInterface) {
            throw new \RuntimeException('Failed to create proper EntityManager mock');
        }

        if (!$mock instanceof MockObject) {
            throw new \RuntimeException('Created mock does not implement MockObject');
        }

        return $mock;
    }
}
