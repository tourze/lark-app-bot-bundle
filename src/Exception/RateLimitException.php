<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

/**
 * 请求频率限制异常.
 *
 * 当API请求频率超限时抛出
 */
final class RateLimitException extends ApiException
{
    /**
     * 重试时间（秒）.
     */
    private ?int $retryAfter = null;

    /**
     * 速率限制信息.
     *
     * @var array<string, mixed>
     */
    private array $rateLimitInfo = [];

    /**
     * 获取重试时间.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * 设置重试时间.
     */
    public function setRetryAfter(?int $retryAfter): void
    {
        $this->retryAfter = $retryAfter;
    }

    /**
     * 获取速率限制信息.
     *
     * @return array<string, mixed>
     */
    public function getRateLimitInfo(): array
    {
        return $this->rateLimitInfo;
    }

    /**
     * 设置速率限制信息.
     *
     * @param array<string, mixed> $rateLimitInfo
     */
    public function setRateLimitInfo(array $rateLimitInfo): void
    {
        $this->rateLimitInfo = $rateLimitInfo;
    }

    /**
     * 创建带重试信息的异常.
     */
    public static function withRetryAfter(string $message, int $code, int $retryAfter, ?\Throwable $previous = null): self
    {
        $exception = new self($message, $code, $previous);
        $exception->setRetryAfter($retryAfter);

        return $exception;
    }

    /**
     * 判断是否可以重试.
     */
    public function canRetry(): bool
    {
        return null !== $this->retryAfter && $this->retryAfter > 0;
    }

    /**
     * 获取建议的重试时间戳.
     */
    public function getRetryTimestamp(): ?int
    {
        if (!$this->canRetry() || null === $this->retryAfter) {
            return null;
        }

        return time() + $this->retryAfter;
    }
}
