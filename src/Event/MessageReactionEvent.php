<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Event;

/**
 * 消息反应事件（表情回复）.
 */
class MessageReactionEvent extends LarkEvent
{
    /**
     * 获取消息ID.
     */
    public function getMessageId(): string
    {
        $messageId = $this->data['message_id'] ?? '';
        \assert(\is_string($messageId));

        return $messageId;
    }

    /**
     * 获取反应类型.
     *
     * @return array{emoji_type: string}
     */
    public function getReactionType(): array
    {
        $reactionType = $this->data['reaction_type'] ?? [];
        \assert(\is_array($reactionType));
        if ([] === $reactionType) {
            return ['emoji_type' => ''];
        }
        \assert(\is_string($reactionType['emoji_type'] ?? ''));

        /** @var array{emoji_type: string} $reactionType */
        return $reactionType;
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
     * 获取操作时间.
     */
    public function getActionTime(): string
    {
        $actionTime = $this->data['action_time'] ?? '';
        \assert(\is_string($actionTime));

        return $actionTime;
    }

    /**
     * 是否是添加反应.
     */
    public function isCreated(): bool
    {
        return 'im.message.reaction.created_v1' === $this->eventType;
    }

    /**
     * 是否是删除反应.
     */
    public function isDeleted(): bool
    {
        return 'im.message.reaction.deleted_v1' === $this->eventType;
    }

    /**
     * 获取表情类型.
     */
    public function getEmojiType(): string
    {
        $reactionType = $this->getReactionType();

        return $reactionType['emoji_type'] ?? '';
    }
}
