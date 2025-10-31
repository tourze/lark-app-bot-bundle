<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\JsonEncodingException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * JSON编码异常测试.
 *
 * @internal
 */
#[CoversClass(JsonEncodingException::class)]
class JsonEncodingExceptionTest extends AbstractExceptionTestCase
{
    public function testFromErrorCreatesExceptionWithGenericMessageWhenNoError(): void
    {
        $exception = JsonEncodingException::fromError();

        $this->assertInstanceOf(JsonEncodingException::class, $exception);
        $this->assertSame('JSON编码失败', $exception->getMessage());
    }

    public function testFromErrorCreatesExceptionWithGenericMessageWhenNullError(): void
    {
        $exception = JsonEncodingException::fromError(null);

        $this->assertSame('JSON编码失败', $exception->getMessage());
    }

    public function testFromErrorIncludesErrorMessageWhenProvided(): void
    {
        $error = 'Malformed UTF-8 characters';

        $exception = JsonEncodingException::fromError($error);

        $this->assertSame('JSON编码失败: Malformed UTF-8 characters', $exception->getMessage());
    }

    public function testFromErrorHandlesEmptyErrorString(): void
    {
        $error = '';

        $exception = JsonEncodingException::fromError($error);

        $this->assertSame('JSON编码失败: ', $exception->getMessage());
    }

    public function testFromErrorHandlesUnicodeErrorMessage(): void
    {
        $error = '无法编码中文字符';

        $exception = JsonEncodingException::fromError($error);

        $this->assertSame('JSON编码失败: 无法编码中文字符', $exception->getMessage());
    }
}
