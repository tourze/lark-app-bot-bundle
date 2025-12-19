<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Handler;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;

/**
 * 消息处理器抽象类.
 */
#[WithMonologChannel(channel: 'lark_app_bot')]
abstract class AbstractMessageHandler implements MessageHandlerInterface
{
    public function __construct(
        protected readonly MessageService $messageService,
        protected readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 获取处理器优先级.
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * 获取处理器名称.
     */
    public function getName(): string
    {
        return $this::class;
    }

    /**
     * 记录处理日志.
     *
     * @param array<string, mixed> $context
     */
    protected function log(string $message, array $context = []): void
    {
        $this->logger->info(\sprintf('[%s] %s', $this->getName(), $message), $context);
    }

    /**
     * 记录错误日志.
     *
     * @param array<string, mixed> $context
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->logger->error(\sprintf('[%s] %s', $this->getName(), $message), $context);
    }

    /**
     * 回复文本消息.
     */
    protected function replyText(MessageEvent $event, string $text): void
    {
        try {
            $this->messageService->sendText(
                $event->getChatId(),
                $text,
                $event->getMessageId()
            );
        } catch (\Exception $e) {
            $this->logError('回复消息失败', [
                'error' => $e->getMessage(),
                'chat_id' => $event->getChatId(),
                'message_id' => $event->getMessageId(),
            ]);
        }
    }

    /**
     * 回复富文本消息.
     *
     * @param array<string, mixed> $content
     */
    protected function replyRichText(MessageEvent $event, array $content): void
    {
        try {
            $this->messageService->sendRichText(
                $event->getChatId(),
                $content,
                $event->getMessageId()
            );
        } catch (\Exception $e) {
            $this->logError('回复富文本消息失败', [
                'error' => $e->getMessage(),
                'chat_id' => $event->getChatId(),
                'message_id' => $event->getMessageId(),
            ]);
        }
    }

    /**
     * 回复卡片消息.
     *
     * @param array<string, mixed> $card
     */
    protected function replyCard(MessageEvent $event, array $card): void
    {
        try {
            $this->messageService->sendCard(
                $event->getChatId(),
                $card,
                $event->getMessageId()
            );
        } catch (\Exception $e) {
            $this->logError('回复卡片消息失败', [
                'error' => $e->getMessage(),
                'chat_id' => $event->getChatId(),
                'message_id' => $event->getMessageId(),
            ]);
        }
    }
}
