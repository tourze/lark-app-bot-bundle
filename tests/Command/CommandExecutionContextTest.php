<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Command\CommandExecutionContext;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CommandExecutionContext::class)]
#[RunTestsInSeparateProcesses]
final class CommandExecutionContextTest extends AbstractIntegrationTestCase
{
    public function testInstantiateFromService(): void
    {
        // 使用测试环境服务容器中预置的 Mock 服务
        $ctx = self::getContainer()->get('MockCommandExecutionContext');
        $this->assertInstanceOf(CommandExecutionContext::class, $ctx);

        $this->assertSame('id_1', $ctx->identifier);
        $this->assertSame('open_id', $ctx->type);
        $this->assertTrue($ctx->isBatch);
        $this->assertFalse($ctx->showDepartment);
        $this->assertTrue($ctx->showGroups);
        $this->assertSame('json', $ctx->format);
        $this->assertSame(['name','email'], $ctx->fields);
    }

    public function testReadonlyPropertiesDefinition(): void
    {
        $ref = new \ReflectionClass(CommandExecutionContext::class);
        $props = array_map(fn($p) => $p->getName(), $ref->getProperties());

        foreach (['identifier','type','isBatch','showDepartment','showGroups','format','fields'] as $expected) {
            $this->assertContains($expected, $props);
        }
    }

    protected function onSetUp(): void
    {
    }
}
