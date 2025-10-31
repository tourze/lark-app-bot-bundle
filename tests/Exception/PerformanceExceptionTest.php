<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\PerformanceException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * 性能异常测试.
 *
 * @internal
 */
#[CoversClass(PerformanceException::class)]
final class PerformanceExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstance(): void
    {
        $exception = new PerformanceException('Test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Test message', $exception->getMessage());
    }

    public function testSendFailed(): void
    {
        $exception = PerformanceException::sendFailed();

        $this->assertInstanceOf(PerformanceException::class, $exception);
        $this->assertSame('Send failed', $exception->getMessage());
    }

    public function testSendFailedWithCustomMessage(): void
    {
        $exception = PerformanceException::sendFailed('Custom send error');

        $this->assertSame('Custom send error', $exception->getMessage());
    }

    public function testProcessingFailed(): void
    {
        $exception = PerformanceException::processingFailed();

        $this->assertInstanceOf(PerformanceException::class, $exception);
        $this->assertSame('Processing failed', $exception->getMessage());
    }

    public function testProcessingFailedWithCustomMessage(): void
    {
        $exception = PerformanceException::processingFailed('Custom processing error');

        $this->assertSame('Custom processing error', $exception->getMessage());
    }

    public function testWebhookFailed(): void
    {
        $exception = PerformanceException::webhookFailed();

        $this->assertInstanceOf(PerformanceException::class, $exception);
        $this->assertSame('Webhook failed', $exception->getMessage());
    }

    public function testWebhookFailedWithCustomMessage(): void
    {
        $exception = PerformanceException::webhookFailed('Custom webhook error');

        $this->assertSame('Custom webhook error', $exception->getMessage());
    }

    public function testCacheError(): void
    {
        $exception = PerformanceException::cacheError();

        $this->assertInstanceOf(PerformanceException::class, $exception);
        $this->assertSame('Cache error', $exception->getMessage());
    }

    public function testCacheErrorWithCustomMessage(): void
    {
        $exception = PerformanceException::cacheError('Custom cache error');

        $this->assertSame('Custom cache error', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new PerformanceException('Test message', 123);

        $this->assertSame(123, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new PerformanceException('Test message', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
