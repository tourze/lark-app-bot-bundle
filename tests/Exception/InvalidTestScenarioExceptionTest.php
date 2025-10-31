<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Exception\InvalidTestScenarioException;
use Tourze\LarkAppBotBundle\Exception\LarkException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidTestScenarioException::class)]
final class InvalidTestScenarioExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstance(): void
    {
        $exception = new InvalidTestScenarioException('Invalid test scenario');

        $this->assertInstanceOf(LarkException::class, $exception);
        $this->assertSame('Invalid test scenario', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new InvalidTestScenarioException('Invalid test scenario', 400);

        $this->assertSame(400, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidTestScenarioException('Invalid test scenario', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
