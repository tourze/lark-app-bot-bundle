<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\EventSubscriber\AsyncEventHandler;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AsyncEventHandler::class)]
#[RunTestsInSeparateProcesses]
final class AsyncEventHandlerTest extends AbstractIntegrationTestCase
{
    public function testCanBeCreatedFromContainer(): void
    {
        $handler = self::getContainer()->get(AsyncEventHandler::class);

        $this->assertInstanceOf(AsyncEventHandler::class, $handler);
    }

    public function testIsAsyncSupportedFromContainer(): void
    {
        /** @var AsyncEventHandler $handler */
        $handler = self::getContainer()->get(AsyncEventHandler::class);

        // 测试从容器获取的实例的基本功能
        $this->assertIsBool($handler->isAsyncSupported());
    }

    public function testDispatchAsync(): void
    {
        /** @var AsyncEventHandler $handler */
        $handler = self::getContainer()->get(AsyncEventHandler::class);

        // 测试基本的异步调度不抛出异常
        $this->expectNotToPerformAssertions();
        $handler->dispatchAsync('test.event', ['data' => 'test']);
    }

    protected function onSetUp(): void
    {
        // 无需特殊设置
    }
}
