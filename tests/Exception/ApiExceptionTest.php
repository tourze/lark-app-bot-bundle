<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ApiException::class)]
final class ApiExceptionTest extends AbstractExceptionTestCase
{
    public function testBasicException(): void
    {
        $exception = new GenericApiException('Test error', 100);

        $this->assertSame('Test error', $exception->getMessage());
        $this->assertSame(100, $exception->getCode());
        $this->assertSame(100, $exception->getErrorCode());
    }

    public function testWithDetails(): void
    {
        $errorData = [
            'field' => 'email',
            'error' => 'invalid format'];

        $exception = GenericApiException::withDetails('Validation failed', 400, $errorData);

        $this->assertSame('Validation failed', $exception->getMessage());
        $this->assertSame(400, $exception->getCode());
        $this->assertSame($errorData, $exception->getErrorData());
    }

    public function testSettersAndGetters(): void
    {
        $exception = new GenericApiException();

        $exception->setErrorCode(99991663);
        $this->assertSame(99991663, $exception->getErrorCode());

        $errorData = ['key' => 'value'];
        $exception->setErrorData($errorData);
        $this->assertSame($errorData, $exception->getErrorData());
    }

    public function testWithPreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new GenericApiException('New error', 500, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testApiExceptionWithDetails(): void
    {
        $exception = GenericApiException::withDetails(
            'Validation failed',
            400,
            ['field' => 'email', 'error' => 'invalid']);

        $this->assertSame('Validation failed', $exception->getMessage());
        $this->assertSame(400, $exception->getCode());
        $this->assertSame(['field' => 'email', 'error' => 'invalid'], $exception->getErrorData());
    }
}
