<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\InvalidTestException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidTestException::class)]
final class InvalidTestExceptionTest extends AbstractExceptionTestCase
{
    public function testConstructor(): void
    {
        $exception = new InvalidTestException('Test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Test message', $exception->getMessage());
    }

    public function testUnknownTest(): void
    {
        $testName = 'sql_injection_test';
        $exception = InvalidTestException::unknownTest($testName);

        $this->assertInstanceOf(InvalidTestException::class, $exception);
        $this->assertSame("Unknown test: {$testName}", $exception->getMessage());
    }

    public function testDisabledTest(): void
    {
        $testName = 'xss_test';
        $exception = InvalidTestException::disabledTest($testName);

        $this->assertInstanceOf(InvalidTestException::class, $exception);
        $this->assertSame("Test '{$testName}' is disabled", $exception->getMessage());
    }

    public function testUnknownTestWithEmptyName(): void
    {
        $exception = InvalidTestException::unknownTest('');

        $this->assertSame('Unknown test: ', $exception->getMessage());
    }

    public function testDisabledTestWithEmptyName(): void
    {
        $exception = InvalidTestException::disabledTest('');

        $this->assertSame("Test '' is disabled", $exception->getMessage());
    }

    public function testUnknownTestWithSpecialCharacters(): void
    {
        $testName = 'test@#$%^&*()';
        $exception = InvalidTestException::unknownTest($testName);

        $this->assertSame("Unknown test: {$testName}", $exception->getMessage());
    }

    public function testDisabledTestWithSpecialCharacters(): void
    {
        $testName = 'test-with-dashes_and_underscores';
        $exception = InvalidTestException::disabledTest($testName);

        $this->assertSame("Test '{$testName}' is disabled", $exception->getMessage());
    }

    public function testExceptionInheritance(): void
    {
        $exception = new InvalidTestException();

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testWithPreviousException(): void
    {
        $previous = new \InvalidArgumentException('Previous error');
        $exception = new InvalidTestException('Test error', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame('Test error', $exception->getMessage());
    }
}
