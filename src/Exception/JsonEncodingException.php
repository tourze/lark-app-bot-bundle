<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Exception;

/**
 * JSON编码异常.
 */
final class JsonEncodingException extends LarkException
{
    public static function fromError(?string $error = null): self
    {
        $message = 'JSON编码失败';
        if (null !== $error) {
            $message .= ': ' . $error;
        }

        return new self($message);
    }
}
