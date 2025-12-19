<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Handler;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Event\MessageEvent;

/**
 * 默认消息处理器.
 *
 * 处理未被其他处理器处理的消息
 */
#[Autoconfigure(public: true)]
final class DefaultMessageHandler extends AbstractMessageHandler
{
    /**
     * 支持所有消息类型.
     */
    public function supports(MessageEvent $event): bool
    {
        return true;
    }

    /**
     * 处理消息.
     */
    public function handle(MessageEvent $event): void
    {
        $this->log('收到消息', [
            'message_id' => $event->getMessageId(),
            'chat_id' => $event->getChatId(),
            'sender_id' => $event->getSenderId(),
            'message_type' => $event->getMessageType(),
            'content' => $event->getContent(),
        ]);

        // 默认回复
        if ($event->isPrivateMessage()) {
            $this->handlePrivateMessage($event);
        } elseif ($event->isGroupMessage() && $event->isMentionedBot()) {
            $this->handleGroupMessage($event);
        }
    }

    /**
     * 获取处理器优先级（最低）.
     */
    public function getPriority(): int
    {
        return -1000;
    }

    /**
     * 获取处理器名称.
     */
    public function getName(): string
    {
        return 'default';
    }

    /**
     * 处理私聊消息.
     */
    protected function handlePrivateMessage(MessageEvent $event): void
    {
        $this->replyText(
            $event,
            '您好！我是飞书机器人，有什么可以帮助您的吗？'
        );
    }

    /**
     * 处理群聊消息.
     */
    protected function handleGroupMessage(MessageEvent $event): void
    {
        $this->replyText(
            $event,
            '您好！我在群里为大家服务，有什么需要帮助的吗？'
        );
    }
}
