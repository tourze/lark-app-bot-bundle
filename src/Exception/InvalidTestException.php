<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

/**
 * 无效测试异常
 * 当请求执行不存在的安全测试时抛出此异常.
 */
final class InvalidTestException extends \RuntimeException
{
    public static function unknownTest(string $testName): self
    {
        return new self("Unknown test: {$testName}");
    }

    public static function disabledTest(string $testName): self
    {
        return new self("Test '{$testName}' is disabled");
    }
}
