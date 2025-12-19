<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Async;

/**
 * 批量发送消息命令
 * 用于高效地批量发送消息.
 */
final class BatchSendMessageCommand
{
    /**
     * @param array<string>               $receiveIds
     * @param array<string, mixed>|string $content
     * @param array<string, mixed>        $options
     */
    public function __construct(
        private readonly array $receiveIds,
        private readonly string $msgType,
        private readonly array|string $content,
        private readonly string $receiveIdType,
        private readonly array $options = [],
        private readonly ?string $correlationId = null,
        private readonly int $retryCount = 0,
        private readonly ?float $timestamp = null,
    ) {
    }

    /**
     * @return array<string>
     */
    public function getReceiveIds(): array
    {
        return $this->receiveIds;
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
            $this->receiveIds,
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

    /**
     * 分割成多个批次
     *
     * @return array<self>
     */
    public function split(int $batchSize = 50): array
    {
        if ($batchSize < 1) {
            $batchSize = 1;
        }
        $batches = array_chunk($this->receiveIds, $batchSize);
        $commands = [];

        foreach ($batches as $index => $batchReceiveIds) {
            $commands[] = new self(
                $batchReceiveIds,
                $this->msgType,
                $this->content,
                $this->receiveIdType,
                $this->options,
                ($this->correlationId ?? '') . '_batch_' . $index,
                $this->retryCount,
                $this->timestamp
            );
        }

        return $commands;
    }
}
