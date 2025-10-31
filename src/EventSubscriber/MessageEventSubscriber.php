<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\LarkAppBotBundle\Service\Message\Handler\MessageHandlerRegistry;

/**
 * 消息事件订阅器.
 *
 * 将消息事件转发给消息处理器
 */
#[Autoconfigure(public: true)]
class MessageEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageHandlerRegistry $handlerRegistry,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 获取订阅的事件.
     *
     * @return array<string, array{string, int}|string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => ['onMessage', 100],
        ];
    }

    /**
     * 处理消息事件.
     */
    public function onMessage(MessageEvent $event): void
    {
        $this->logger->info('收到消息事件', [
            'message_id' => $event->getMessageId(),
            'chat_id' => $event->getChatId(),
            'sender_id' => $event->getSenderId(),
            'message_type' => $event->getMessageType(),
        ]);

        // 转发给处理器注册表
        $this->handlerRegistry->handleMessage($event);
    }
}
