<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

/**
 * 通用API异常.
 *
 * 当无法归类到特定异常类型时使用
 */
final class GenericApiException extends ApiException
{
    /**
     * 创建带详情的异常.
     *
     * @param array<string, mixed> $errorData
     */
    public static function withDetails(string $message, int $code, array $errorData, ?\Throwable $previous = null): self
    {
        $exception = new self($message, $code, $previous);
        $exception->setErrorData($errorData);

        return $exception;
    }
}
