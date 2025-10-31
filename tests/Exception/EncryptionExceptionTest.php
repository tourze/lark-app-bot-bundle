<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\EncryptionException;
use Tourze\LarkAppBotBundle\Exception\LarkException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(EncryptionException::class)]
final class EncryptionExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstance(): void
    {
        $exception = new EncryptionException('Encryption failed');

        $this->assertInstanceOf(LarkException::class, $exception);
        $this->assertSame('Encryption failed', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new EncryptionException('Encryption failed', 500);

        $this->assertSame(500, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new EncryptionException('Encryption failed', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
