<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\TestDouble;

use Psr\Log\LoggerInterface;

/**
 * 内存日志记录器，用于测试中记录和验证日志消息.
 *
 * @internal 仅用于测试
 */
final class InMemoryLogger implements LoggerInterface
{
    /**
     * @var string[]
     */
    private array $loggedMessages = [];

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->loggedMessages[] = (string) $message;
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->loggedMessages[] = (string) $message;
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->loggedMessages[] = (string) $message;
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->loggedMessages[] = (string) $message;
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->loggedMessages[] = (string) $message;
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->loggedMessages[] = (string) $message;
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->loggedMessages[] = (string) $message;
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->loggedMessages[] = (string) $message;
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->loggedMessages[] = (string) $message;
    }

    /**
     * 获取所有已记录的消息.
     *
     * @return string[]
     */
    public function getLoggedMessages(): array
    {
        return $this->loggedMessages;
    }

    /**
     * 清空所有已记录的消息.
     */
    public function clear(): void
    {
        $this->loggedMessages = [];
    }
}
