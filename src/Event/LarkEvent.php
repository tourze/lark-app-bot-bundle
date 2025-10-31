<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * 飞书事件基类.
 */
abstract class LarkEvent extends Event
{
    /**
     * @param array<string, mixed> $data    事件数据
     * @param array<string, mixed> $context 上下文信息
     */
    public function __construct(
        protected readonly string $eventType,
        protected readonly array $data,
        protected readonly array $context = [],
    ) {
    }

    /**
     * 获取事件类型.
     */
    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * 获取事件数据.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 获取上下文信息.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 获取事件ID.
     */
    public function getEventId(): string
    {
        $eventId = $this->context['event_id'] ?? '';
        \assert(\is_string($eventId));

        return $eventId;
    }

    /**
     * 获取租户key.
     */
    public function getTenantKey(): string
    {
        $tenantKey = $this->context['tenant_key'] ?? '';
        \assert(\is_string($tenantKey));

        return $tenantKey;
    }

    /**
     * 获取应用ID.
     */
    public function getAppId(): string
    {
        $appId = $this->context['app_id'] ?? '';
        \assert(\is_string($appId));

        return $appId;
    }

    /**
     * 获取时间戳.
     */
    public function getTimestamp(): int
    {
        $createTime = $this->data['create_time'] ?? time();
        \assert(is_numeric($createTime));

        return (int) $createTime;
    }

    /**
     * 是否是重试事件.
     */
    public function isRetry(): bool
    {
        return (bool) ($this->context['is_retry'] ?? false);
    }
}
