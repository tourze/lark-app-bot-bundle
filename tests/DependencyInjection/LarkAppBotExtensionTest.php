<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\DependencyInjection\LarkAppBotExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * 依赖注入扩展测试.
 *
 * @internal
 */
#[CoversClass(LarkAppBotExtension::class)]
final class LarkAppBotExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    protected function setUp(): void
    {
        // 无需特殊初始化
    }

    public function testExtensionInstantiation(): void
    {
        $extension = new LarkAppBotExtension();
        $this->assertInstanceOf(LarkAppBotExtension::class, $extension);
    }
}
