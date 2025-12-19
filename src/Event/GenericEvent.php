<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Event;

/**
 * 通用事件.
 *
 * 用于处理未定义具体类型的事件
 */
final class GenericEvent extends LarkEvent
{
    /**
     * 获取数据字段.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * 检查是否存在字段.
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }
}
