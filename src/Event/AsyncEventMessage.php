<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Event;

/**
 * 异步事件消息.
 *
 * 用于在消息队列中传递事件信息
 */
class AsyncEventMessage
{
    /**
     * @param array<string, mixed> $eventData
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly string $eventType,
        private readonly array $eventData,
        private readonly array $context = [],
    ) {
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * @return array<string, mixed>
     */
    public function getEventData(): array
    {
        return $this->eventData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
