<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\InvalidAuditFormatException;
use Tourze\LarkAppBotBundle\Exception\LarkException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidAuditFormatException::class)]
final class InvalidAuditFormatExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstance(): void
    {
        $exception = new InvalidAuditFormatException('Invalid audit format');

        $this->assertInstanceOf(LarkException::class, $exception);
        $this->assertSame('Invalid audit format', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new InvalidAuditFormatException('Invalid audit format', 400);

        $this->assertSame(400, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidAuditFormatException('Invalid audit format', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
