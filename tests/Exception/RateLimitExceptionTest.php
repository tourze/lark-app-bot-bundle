<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\LarkException;
use Tourze\LarkAppBotBundle\Exception\RateLimitException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(RateLimitException::class)]
final class RateLimitExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstance(): void
    {
        $exception = new RateLimitException('Rate limit exceeded');

        $this->assertInstanceOf(LarkException::class, $exception);
        $this->assertSame('Rate limit exceeded', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new RateLimitException('Rate limit exceeded', 429);

        $this->assertSame(429, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new RateLimitException('Rate limit exceeded', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
