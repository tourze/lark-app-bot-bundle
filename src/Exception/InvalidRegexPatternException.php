<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

/**
 * 无效正则表达式模式异常.
 */
final class InvalidRegexPatternException extends LarkException
{
    public static function forPattern(string $pattern): self
    {
        return new self(\sprintf('Invalid regex pattern: %s', $pattern));
    }
}
