<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Event;

/**
 * 群成员事件.
 */
class GroupMemberEvent extends LarkEvent
{
    /**
     * 获取群组ID.
     */
    public function getChatId(): string
    {
        $chatId = $this->data['chat_id'] ?? '';
        \assert(\is_string($chatId));

        return $chatId;
    }

    /**
     * 获取操作者ID.
     */
    public function getOperatorId(): string
    {
        $operatorId = $this->data['operator_id'] ?? '';
        \assert(\is_string($operatorId));

        return $operatorId;
    }

    /**
     * 获取用户列表.
     *
     * @return array<array{tenant_key: string, user_id: string}>
     */
    public function getUsers(): array
    {
        $users = $this->data['users'] ?? [];
        \assert(\is_array($users));

        // 类型断言确保每个用户都有正确的结构
        foreach ($users as $user) {
            \assert(\is_array($user));
            \assert(\is_string($user['tenant_key'] ?? ''));
            \assert(\is_string($user['user_id'] ?? ''));
        }

        /** @var array<array{tenant_key: string, user_id: string}> $users */
        return $users;
    }

    /**
     * 是否是机器人被添加.
     */
    public function isBotAdded(): bool
    {
        return 'im.chat.member.bot.added_v1' === $this->eventType;
    }

    /**
     * 是否是机器人被删除.
     */
    public function isBotDeleted(): bool
    {
        return 'im.chat.member.bot.deleted_v1' === $this->eventType;
    }

    /**
     * 是否是用户被添加.
     */
    public function isUserAdded(): bool
    {
        return 'im.chat.member.user.added_v1' === $this->eventType;
    }

    /**
     * 是否是用户退出.
     */
    public function isUserWithdrawn(): bool
    {
        return 'im.chat.member.user.withdrawn_v1' === $this->eventType;
    }

    /**
     * 是否是用户被删除.
     */
    public function isUserDeleted(): bool
    {
        return 'im.chat.member.user.deleted_v1' === $this->eventType;
    }
}
