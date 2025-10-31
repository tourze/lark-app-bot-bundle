<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\LarkException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * 飞书异常测试.
 *
 * @internal
 */
#[CoversClass(LarkException::class)]
final class LarkExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstance(): void
    {
        $exception = $this->createTestException('Test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(LarkException::class, $exception);
        $this->assertSame('Test message', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = $this->createTestException('Test message', 123);

        $this->assertSame(123, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = $this->createTestException('Test message', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * 创建具体的测试异常实例.
     *
     * 由于 LarkException 是抽象类，使用匿名类实现用于测试
     */
    private function createTestException(string $message = '', int $code = 0, ?\Throwable $previous = null): LarkException
    {
        return new class($message, $code, $previous) extends LarkException {
        };
    }
}
