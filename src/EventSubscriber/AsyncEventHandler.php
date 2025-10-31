<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\LarkAppBotBundle\Event\AsyncEventMessage;

/**
 * 异步事件处理器.
 *
 * 使用Symfony Messenger组件处理异步事件
 */
#[Autoconfigure(public: true)]
class AsyncEventHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?MessageBusInterface $messageBus = null,
    ) {
    }

    /**
     * 异步分发事件.
     *
     * @param array<string, mixed> $eventData
     * @param array<string, mixed> $context
     */
    public function dispatchAsync(string $eventType, array $eventData, array $context = []): void
    {
        if (null === $this->messageBus) {
            $this->logger->warning('Messenger组件未安装，无法异步处理事件', [
                'event_type' => $eventType,
            ]);

            return;
        }

        try {
            $message = new AsyncEventMessage($eventType, $eventData, $context);
            $this->messageBus->dispatch($message);

            $this->logger->info('事件已加入异步队列', [
                'event_type' => $eventType,
                'event_id' => $context['event_id'] ?? '',
            ]);
        } catch (\Exception $e) {
            $this->logger->error('异步分发事件失败', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 检查是否支持异步处理.
     */
    public function isAsyncSupported(): bool
    {
        return null !== $this->messageBus;
    }
}
