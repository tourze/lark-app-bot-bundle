<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\InvalidCommandUsageException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidCommandUsageException::class)]
final class InvalidCommandUsageExceptionTest extends AbstractExceptionTestCase
{
    public function testInvalidValue(): void
    {
        $exception = InvalidCommandUsageException::invalidValue('test_param', 'invalid_value', 'test reason');

        $this->assertStringContainsString('参数 "test_param" 的值 "invalid_value" 无效: test reason', $exception->getMessage());
    }

    public function testInvalidValueWithoutReason(): void
    {
        $exception = InvalidCommandUsageException::invalidValue('test_param', 'invalid_value');

        $this->assertStringContainsString('参数 "test_param" 的值 "invalid_value" 无效', $exception->getMessage());
        $this->assertStringNotContainsString(':', $exception->getMessage());
    }

    public function testInvalidValueWithArrayValue(): void
    {
        $exception = InvalidCommandUsageException::invalidValue('test_param', ['invalid'], 'must be string');

        $this->assertStringContainsString('参数 "test_param" 的值 "array" 无效: must be string', $exception->getMessage());
    }

    public function testMissingRequired(): void
    {
        $exception = InvalidCommandUsageException::missingRequired('required_param');

        $this->assertSame('缺少必需的参数: required_param', $exception->getMessage());
    }

    public function testInvalidOption(): void
    {
        $exception = InvalidCommandUsageException::invalidOption('format', 'invalid', ['json', 'xml']);

        $expectedMessage = '选项 "--format" 的值 "invalid" 无效，有效值为: json, xml';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function testInvalidOptionWithoutValidValues(): void
    {
        $exception = InvalidCommandUsageException::invalidOption('test', 'value');

        $this->assertSame('选项 "--test" 的值 "value" 无效', $exception->getMessage());
    }
}
