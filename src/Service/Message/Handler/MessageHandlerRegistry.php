<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Handler;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Event\MessageEvent;

/**
 * 消息处理器注册表.
 *
 * 管理和调度消息处理器
 */
#[Autoconfigure(public: true)]
class MessageHandlerRegistry
{
    /**
     * @var MessageHandlerInterface[]
     */
    private array $handlers = [];

    private bool $sorted = false;

    /**
     * @param iterable<MessageHandlerInterface> $handlers
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        iterable $handlers = [],
    ) {
        foreach ($handlers as $handler) {
            $this->addHandler($handler);
        }
    }

    /**
     * 注册消息处理器.
     */
    public function addHandler(MessageHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
        $this->sorted = false;

        $this->logger->debug('注册消息处理器', [
            'handler' => $handler->getName(),
            'priority' => $handler->getPriority(),
        ]);
    }

    /**
     * 移除消息处理器.
     */
    public function removeHandler(MessageHandlerInterface $handler): void
    {
        $this->handlers = array_filter(
            $this->handlers,
            fn (MessageHandlerInterface $h) => $h !== $handler
        );
    }

    /**
     * 处理消息事件.
     */
    public function handleMessage(MessageEvent $event): void
    {
        $this->sortHandlers();

        $handled = false;
        foreach ($this->handlers as $handler) {
            if ($handler->supports($event)) {
                try {
                    $this->logger->debug('执行消息处理器', [
                        'handler' => $handler->getName(),
                        'message_id' => $event->getMessageId(),
                    ]);

                    $handler->handle($event);
                    $handled = true;

                    // 如果处理器返回了结果，停止处理链
                    if ($event->isPropagationStopped()) {
                        break;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('消息处理器执行失败', [
                        'handler' => $handler->getName(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }

        if (!$handled) {
            $this->logger->warning('没有处理器处理该消息', [
                'message_id' => $event->getMessageId(),
                'message_type' => $event->getMessageType(),
            ]);
        }
    }

    /**
     * 获取所有处理器.
     *
     * @return MessageHandlerInterface[]
     */
    public function getHandlers(): array
    {
        $this->sortHandlers();

        return $this->handlers;
    }

    /**
     * 清空所有处理器.
     */
    public function clear(): void
    {
        $this->handlers = [];
        $this->sorted = false;
    }

    /**
     * 获取处理器数量.
     */
    public function count(): int
    {
        return \count($this->handlers);
    }

    /**
     * 按优先级排序处理器.
     */
    private function sortHandlers(): void
    {
        if ($this->sorted) {
            return;
        }

        usort($this->handlers, function (MessageHandlerInterface $a, MessageHandlerInterface $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        $this->sorted = true;
    }
}
