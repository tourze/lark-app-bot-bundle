<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\InvalidDebugActionException;
use Tourze\LarkAppBotBundle\Exception\LarkException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidDebugActionException::class)]
final class InvalidDebugActionExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstance(): void
    {
        $exception = new InvalidDebugActionException('Invalid debug action');

        $this->assertInstanceOf(LarkException::class, $exception);
        $this->assertSame('不支持的调试操作: Invalid debug action', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new InvalidDebugActionException('Invalid debug action');

        // 默认代码为0
        $this->assertSame(0, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $exception = new InvalidDebugActionException('Invalid debug action');

        // 由于构造函数只接受一个参数，previous 总是 null
        $this->assertNull($exception->getPrevious());
    }
}
