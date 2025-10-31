<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\InvalidCacheStrategyException;
use Tourze\LarkAppBotBundle\Exception\LarkException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidCacheStrategyException::class)]
final class InvalidCacheStrategyExceptionTest extends AbstractExceptionTestCase
{
    public function testException(): void
    {
        $message = 'Invalid cache strategy: unknown';
        $exception = new InvalidCacheStrategyException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertInstanceOf(LarkException::class, $exception);
    }
}
