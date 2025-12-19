<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

final class PerformanceException extends \RuntimeException
{
    public static function sendFailed(string $message = 'Send failed'): self
    {
        return new self($message);
    }

    public static function processingFailed(string $message = 'Processing failed'): self
    {
        return new self($message);
    }

    public static function webhookFailed(string $message = 'Webhook failed'): self
    {
        return new self($message);
    }

    public static function cacheError(string $message = 'Cache error'): self
    {
        return new self($message);
    }
}
