<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Async;

/**
 * 异步发送消息命令
 * 用于通过消息队列异步发送飞书消息.
 */
final class SendMessageCommand
{
    /**
     * @param array<string, mixed>|string $content
     * @param array<string, mixed>        $options 额外选项
     */
    public function __construct(
        private readonly string $receiveId,
        private readonly string $msgType,
        private readonly array|string $content,
        private readonly string $receiveIdType,
        private readonly array $options = [],
        private readonly ?string $correlationId = null,
        private readonly int $retryCount = 0,
        private readonly ?float $timestamp = null,
    ) {
    }

    public function getReceiveId(): string
    {
        return $this->receiveId;
    }

    public function getMsgType(): string
    {
        return $this->msgType;
    }

    /**
     * @return array<string, mixed>|string
     */
    public function getContent(): array|string
    {
        return $this->content;
    }

    public function getReceiveIdType(): string
    {
        return $this->receiveIdType;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function incrementRetryCount(): self
    {
        return new self(
            $this->receiveId,
            $this->msgType,
            $this->content,
            $this->receiveIdType,
            $this->options,
            $this->correlationId,
            $this->retryCount + 1,
            $this->timestamp
        );
    }

    public function getAge(): float
    {
        return microtime(true) - $this->getTimestamp();
    }

    public function getTimestamp(): float
    {
        return $this->timestamp ?? microtime(true);
    }
}
