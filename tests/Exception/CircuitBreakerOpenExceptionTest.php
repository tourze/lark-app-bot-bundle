<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\CircuitBreakerOpenException;
use Tourze\LarkAppBotBundle\Exception\LarkException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(CircuitBreakerOpenException::class)]
final class CircuitBreakerOpenExceptionTest extends AbstractExceptionTestCase
{
    public function testException(): void
    {
        $message = 'Circuit breaker is open';
        $exception = new CircuitBreakerOpenException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertInstanceOf(LarkException::class, $exception);
    }
}
