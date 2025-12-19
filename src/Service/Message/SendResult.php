<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message;

/**
 * 消息发送结果.
 */
final class SendResult
{
    public function __construct(
        private readonly bool $success,
        private readonly ?string $messageId,
        private readonly ?string $error,
        private readonly int $errorCode = 0,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}
