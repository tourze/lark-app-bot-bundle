<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\ConfigurationException;
use Tourze\LarkAppBotBundle\Exception\LarkException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ConfigurationException::class)]
final class ConfigurationExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstance(): void
    {
        $exception = new ConfigurationException('Configuration error');

        $this->assertInstanceOf(LarkException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Configuration error', $exception->getMessage());
    }

    public function testExceptionForMissingConfig(): void
    {
        $exception = new ConfigurationException('Redis DSN is required when cache type is "redis"');

        $this->assertSame('Redis DSN is required when cache type is "redis"', $exception->getMessage());
    }
}
