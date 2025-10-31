<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Template;

use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 抽象消息模板类
 * 提供模板的基础实现.
 */
abstract class AbstractMessageTemplate implements MessageTemplateInterface
{
    /**
     * @param array<string, mixed> $variables
     */
    public function validateVariables(array $variables): bool
    {
        $requiredVariables = $this->getRequiredVariables();

        foreach (array_keys($requiredVariables) as $variableName) {
            if (!isset($variables[$variableName]) || '' === $variables[$variableName]) {
                return false;
            }
        }

        return true;
    }

    /**
     * 验证并获取变量值
     *
     * @param array<string, mixed> $variables
     *
     * @throws ValidationException
     */
    protected function getVariable(array $variables, string $key, mixed $default = null): mixed
    {
        if (!isset($variables[$key]) && null === $default) {
            $requiredVariables = $this->getRequiredVariables();
            if (isset($requiredVariables[$key])) {
                throw new ValidationException(\sprintf('缺少必需的模板变量: %s (%s)', $key, $requiredVariables[$key]));
            }
        }

        return $variables[$key] ?? $default;
    }

    /**
     * 格式化时间.
     *
     * @param \DateTimeInterface|string|int $time
     */
    protected function formatTime($time, string $format = 'Y-m-d H:i:s'): string
    {
        if ($time instanceof \DateTimeInterface) {
            return $time->format($format);
        }

        if (\is_string($time)) {
            $timestamp = strtotime($time);
            if (false === $timestamp) {
                return $time;
            }

            return date($format, $timestamp);
        }

        if (\is_int($time)) {
            return date($format, $time);
        }

        return '';
    }

    /**
     * 截断文本.
     */
    protected function truncateText(string $text, int $length, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
    }

    /**
     * 转义HTML特殊字符.
     */
    protected function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }
}
