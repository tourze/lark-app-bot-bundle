<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\LarkAppBotBundle\Command\Handler\ActionHandlerInterface;
use Tourze\LarkAppBotBundle\Command\Handler\ActionHandlerRegistry;
use Tourze\LarkAppBotBundle\Exception\UnsupportedTypeException;

/**
 * @internal
 */
#[CoversClass(ActionHandlerRegistry::class)]
#[RunTestsInSeparateProcesses]
final class ActionHandlerRegistryTest extends AbstractIntegrationTestCase
{
    public function testRegisterHandler(): void
    {
        $handler = $this->createMock(ActionHandlerInterface::class);
        $handler->method('getActionName')->willReturn('test-action');

        $registry = self::getService(ActionHandlerRegistry::class);
        $registry->registerHandler($handler);

        $this->assertSame($handler, $registry->getHandler('test-action'));
    }

    public function testGetHandlerThrowsExceptionForUnsupportedAction(): void
    {
        $registry = self::getService(ActionHandlerRegistry::class);

        $this->expectException(UnsupportedTypeException::class);
        $registry->getHandler('non-existent-' . uniqid('', true));
    }

    public function testGetSupportedActions(): void
    {
        $registry = self::getService(ActionHandlerRegistry::class);
        $actions = $registry->getSupportedActions();
        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);
    }

    protected function onSetUp(): void
    {
    }
}
