<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Event;

/**
 * 机器人菜单事件
 * 当用户点击机器人菜单时触发.
 */
final class MenuEvent extends LarkEvent
{
    /**
     * 事件类型常量.
     */
    public const EVENT_TYPE = 'application.bot.menu_v6';

    /**
     * 获取操作者信息.
     *
     * @return array{
     *     operator_id: array{
     *         union_id?: string,
     *         user_id?: string,
     *         open_id?: string
     *     },
     *     operator_type: string
     * }
     */
    public function getOperator(): array
    {
        $operator = $this->data['operator'] ?? [];
        \assert(\is_array($operator));
        $operator['operator_id'] = \is_array($operator['operator_id'] ?? null) ? $operator['operator_id'] : [];
        $operator['operator_type'] = \is_string($operator['operator_type'] ?? null) ? $operator['operator_type'] : 'user';

        /** @var array{operator_id: array{union_id?: string, user_id?: string, open_id?: string}, operator_type: string} $operator */
        return $operator;
    }

    /**
     * 获取操作者的 Open ID.
     */
    public function getOperatorOpenId(): string
    {
        $operator = $this->getOperator();
        $openId = $operator['operator_id']['open_id'] ?? '';
        \assert(\is_string($openId));

        return $openId;
    }

    /**
     * 获取操作者的 User ID.
     */
    public function getOperatorUserId(): string
    {
        $operator = $this->getOperator();
        $userId = $operator['operator_id']['user_id'] ?? '';
        \assert(\is_string($userId));

        return $userId;
    }

    /**
     * 获取操作者的 Union ID.
     */
    public function getOperatorUnionId(): string
    {
        $operator = $this->getOperator();
        $unionId = $operator['operator_id']['union_id'] ?? '';
        \assert(\is_string($unionId));

        return $unionId;
    }

    /**
     * 获取操作者类型.
     */
    public function getOperatorType(): string
    {
        $operator = $this->getOperator();
        $operatorType = $operator['operator_type'] ?? 'user';
        \assert(\is_string($operatorType));

        return $operatorType;
    }

    /**
     * 获取菜单事件键值（菜单项的 value）.
     */
    public function getEventKey(): string
    {
        $eventKey = $this->data['event_key'] ?? '';
        \assert(\is_string($eventKey));

        return $eventKey;
    }

    /**
     * 获取事件时间戳.
     */
    public function getEventTimestamp(): int
    {
        $timestamp = $this->data['timestamp'] ?? time();
        \assert(is_numeric($timestamp));

        return (int) $timestamp;
    }

    /**
     * 是否是用户操作.
     */
    public function isUserOperation(): bool
    {
        return 'user' === $this->getOperatorType();
    }
}
