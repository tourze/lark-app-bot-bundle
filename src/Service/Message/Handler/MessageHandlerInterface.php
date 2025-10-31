<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Handler;

use Tourze\LarkAppBotBundle\Event\MessageEvent;

/**
 * 消息处理器接口.
 */
interface MessageHandlerInterface
{
    /**
     * 是否支持处理该消息.
     */
    public function supports(MessageEvent $event): bool;

    /**
     * 处理消息.
     */
    public function handle(MessageEvent $event): void;

    /**
     * 获取处理器优先级.
     *
     * 数字越大优先级越高
     */
    public function getPriority(): int;

    /**
     * 获取处理器名称.
     */
    public function getName(): string;
}
