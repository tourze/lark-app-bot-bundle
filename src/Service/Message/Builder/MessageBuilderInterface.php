<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Builder;

use Tourze\LarkAppBotBundle\Exception\ValidationException;

/**
 * 消息构建器接口
 * 定义了所有消息构建器必须实现的方法.
 */
interface MessageBuilderInterface
{
    /**
     * 获取消息类型.
     */
    public function getMsgType(): string;

    /**
     * 构建消息内容.
     *
     * @return array<string, mixed> 消息内容数组
     * @throws ValidationException 当消息内容无效时抛出
     */
    public function build(): array;

    /**
     * 验证消息内容是否有效.
     */
    public function isValid(): bool;

    /**
     * 重置构建器状态
     */
    public function reset(): self;

    /**
     * 转换为JSON字符串.
     */
    public function toJson(): string;
}
