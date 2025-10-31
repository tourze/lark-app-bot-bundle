<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\InvalidRegexPatternException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * 无效正则表达式模式异常测试.
 *
 * @internal
 */
#[CoversClass(InvalidRegexPatternException::class)]
class InvalidRegexPatternExceptionTest extends AbstractExceptionTestCase
{
    public function testForPatternCreatesExceptionWithCorrectMessage(): void
    {
        $pattern = '[invalid';

        $exception = InvalidRegexPatternException::forPattern($pattern);

        $this->assertInstanceOf(InvalidRegexPatternException::class, $exception);
        $this->assertSame('Invalid regex pattern: [invalid', $exception->getMessage());
    }

    public function testForPatternHandlesEmptyPattern(): void
    {
        $pattern = '';

        $exception = InvalidRegexPatternException::forPattern($pattern);

        $this->assertSame('Invalid regex pattern: ', $exception->getMessage());
    }

    public function testForPatternHandlesSpecialCharacters(): void
    {
        $pattern = '(abc*+?{]';

        $exception = InvalidRegexPatternException::forPattern($pattern);

        $this->assertSame('Invalid regex pattern: (abc*+?{]', $exception->getMessage());
    }

    public function testForPatternHandlesUnicodePattern(): void
    {
        $pattern = '中文正则';

        $exception = InvalidRegexPatternException::forPattern($pattern);

        $this->assertSame('Invalid regex pattern: 中文正则', $exception->getMessage());
    }
}
