<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\LarkException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ValidationException::class)]
final class ValidationExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstance(): void
    {
        $exception = new ValidationException('Validation failed');

        $this->assertInstanceOf(LarkException::class, $exception);
        $this->assertSame('Validation failed', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new ValidationException('Validation failed', 422);

        $this->assertSame(422, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new ValidationException('Validation failed', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
