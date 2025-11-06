<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

#[CoversClass(GenericApiException::class)]
final class GenericApiExceptionTest extends AbstractExceptionTestCase
{
    public function testConstruct(): void
    {
        $e = new GenericApiException('msg', 1);
        $this->assertSame('msg', $e->getMessage());
        $this->assertSame(1, $e->getCode());
    }
}
