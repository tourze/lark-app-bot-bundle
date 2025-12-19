<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * URL验证事件.
 */
final class UrlVerificationEvent extends Event
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly string $challenge,
        private readonly array $data,
    ) {
    }

    /**
     * 获取challenge.
     */
    public function getChallenge(): string
    {
        return $this->challenge;
    }

    /**
     * 获取原始数据.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
