<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\LarkAppBotBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(LarkAppBotBundle::class)]
#[RunTestsInSeparateProcesses]
final class LarkAppBotBundleTest extends AbstractBundleTestCase
{
}
