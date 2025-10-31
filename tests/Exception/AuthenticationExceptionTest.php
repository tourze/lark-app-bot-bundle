<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\AuthenticationException;
use Tourze\LarkAppBotBundle\Exception\LarkException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * 认证异常测试.
 *
 * @internal
 */
#[CoversClass(AuthenticationException::class)]
final class AuthenticationExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstance(): void
    {
        $exception = new AuthenticationException('Authentication failed');

        $this->assertInstanceOf(LarkException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Authentication failed', $exception->getMessage());
    }

    public function testExceptionWithDetails(): void
    {
        $exception = new AuthenticationException('Invalid app_id or app_secret', 401);

        $this->assertSame('Invalid app_id or app_secret', $exception->getMessage());
        $this->assertSame(401, $exception->getCode());
    }
}
