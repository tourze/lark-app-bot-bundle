<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\RateLimit;

/**
 * 限流器接口.
 */
interface RateLimiterInterface
{
    /**
     * 检查是否可以发起请求
     */
    public function canMakeRequest(): bool;

    /**
     * 记录一次请求
     */
    public function recordRequest(): void;

    /**
     * 获取需要等待的时间（秒）.
     */
    public function getWaitTime(): int;

    /**
     * 获取剩余请求次数.
     */
    public function getRemainingRequests(): int;

    /**
     * 获取时间窗口（秒）.
     */
    public function getTimeWindow(): int;
}
