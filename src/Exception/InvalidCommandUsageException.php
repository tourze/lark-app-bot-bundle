<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

/**
 * 无效的命令使用异常.
 *
 * 当命令行参数或选项无效时抛出
 */
class InvalidCommandUsageException extends \InvalidArgumentException
{
    /**
     * 创建无效参数值异常.
     */
    public static function invalidValue(string $paramName, mixed $value, string $reason = ''): self
    {
        $message = \sprintf(
            '参数 "%s" 的值 "%s" 无效%s',
            $paramName,
            \is_scalar($value) ? $value : \gettype($value),
            '' !== $reason ? ': ' . $reason : ''
        );

        return new self($message);
    }

    /**
     * 创建缺少必需参数异常.
     */
    public static function missingRequired(string $paramName): self
    {
        return new self(\sprintf('缺少必需的参数: %s', $paramName));
    }

    /**
     * 创建无效选项异常.
     *
     * @param array<string> $validValues
     */
    public static function invalidOption(string $optionName, mixed $value, array $validValues = []): self
    {
        $stringValue = \is_scalar($value) ? (string) $value : \gettype($value);
        $message = \sprintf('选项 "--%s" 的值 "%s" 无效', $optionName, $stringValue);

        if ([] !== $validValues) {
            $message .= \sprintf('，有效值为: %s', implode(', ', $validValues));
        }

        return new self($message);
    }

    /**
     * 创建类型错误异常.
     */
    public static function invalidType(string $paramName, string $expected, string $actual): self
    {
        return new self(\sprintf(
            '参数 "%s" 类型错误，期望 %s，实际为 %s',
            $paramName,
            $expected,
            $actual
        ));
    }
}
