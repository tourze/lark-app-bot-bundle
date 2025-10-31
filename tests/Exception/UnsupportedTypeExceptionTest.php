<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\UnsupportedTypeException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(UnsupportedTypeException::class)]
final class UnsupportedTypeExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstance(): void
    {
        $exception = new UnsupportedTypeException('Unsupported type');

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertSame('Unsupported type', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new UnsupportedTypeException('Unsupported type', 400);

        $this->assertSame(400, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new UnsupportedTypeException('Unsupported type', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testCreate(): void
    {
        $exception = UnsupportedTypeException::create('invalid_type', ['valid1', 'valid2'], 'test');

        $this->assertInstanceOf(UnsupportedTypeException::class, $exception);
        $this->assertStringContainsString('invalid_type', $exception->getMessage());
        $this->assertStringContainsString('valid1, valid2', $exception->getMessage());
        $this->assertStringContainsString('test', $exception->getMessage());
    }

    public function testForUserIdType(): void
    {
        $exception = UnsupportedTypeException::forUserIdType('invalid_id');

        $this->assertInstanceOf(UnsupportedTypeException::class, $exception);
        $this->assertStringContainsString('invalid_id', $exception->getMessage());
        $this->assertStringContainsString('用户ID', $exception->getMessage());
        $this->assertStringContainsString('open_id', $exception->getMessage());
    }

    public function testForMemberType(): void
    {
        $exception = UnsupportedTypeException::forMemberType('invalid_member');

        $this->assertInstanceOf(UnsupportedTypeException::class, $exception);
        $this->assertStringContainsString('invalid_member', $exception->getMessage());
        $this->assertStringContainsString('成员', $exception->getMessage());
    }

    public function testForOutputFormat(): void
    {
        $exception = UnsupportedTypeException::forOutputFormat('invalid_format');

        $this->assertInstanceOf(UnsupportedTypeException::class, $exception);
        $this->assertStringContainsString('invalid_format', $exception->getMessage());
        $this->assertStringContainsString('输出格式', $exception->getMessage());
    }

    public function testForFileFormat(): void
    {
        $exception = UnsupportedTypeException::forFileFormat('invalid_format');

        $this->assertInstanceOf(UnsupportedTypeException::class, $exception);
        $this->assertStringContainsString('invalid_format', $exception->getMessage());
        $this->assertStringContainsString('文件格式', $exception->getMessage());
    }
}
