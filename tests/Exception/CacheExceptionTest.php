<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\CacheException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(CacheException::class)]
final class CacheExceptionTest extends AbstractExceptionTestCase
{
    public function testException(): void
    {
        $exception = new CacheException('Test cache error');

        $this->assertSame('Test cache error', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf('Tourze\LarkAppBotBundle\Exception\LarkException', $exception);
    }

    public function testExceptionWithCode(): void
    {
        $exception = new CacheException('Test cache error', 1001);

        $this->assertSame('Test cache error', $exception->getMessage());
        $this->assertSame(1001, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new CacheException('Test cache error', 1001, $previous);

        $this->assertSame('Test cache error', $exception->getMessage());
        $this->assertSame(1001, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
