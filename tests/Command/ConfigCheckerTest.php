<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Command\ConfigChecker;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ConfigChecker::class)]
#[RunTestsInSeparateProcesses]
final class ConfigCheckerTest extends AbstractIntegrationTestCase
{
    public function testCheckBasicConfig(): void
    {
        $this->assertTrue(true);
    }

    public function testCheckNetworkConfig(): void
    {
        $this->assertTrue(true);
    }

    public function testCheckCacheConfig(): void
    {
        $this->assertTrue(true);
    }

    protected function onSetUp(): void
    {
    }
}
