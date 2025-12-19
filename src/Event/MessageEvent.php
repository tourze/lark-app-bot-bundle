<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Event;

/**
 * 消息事件.
 */
final class MessageEvent extends LarkEvent
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
     * 获取根消息ID（用于回复）.
     */
    public function getRootId(): string
    {
        $rootId = $this->data['root_id'] ?? '';
        \assert(\is_string($rootId));

        return $rootId;
    }

    /**
     * 获取父消息ID（用于回复）.
     */
    public function getParentId(): string
    {
        $parentId = $this->data['parent_id'] ?? '';
        \assert(\is_string($parentId));

        return $parentId;
    }

    /**
     * 获取聊天ID.
     */
    public function getChatId(): string
    {
        $chatId = $this->data['chat_id'] ?? '';
        \assert(\is_string($chatId));

        return $chatId;
    }

    /**
     * 获取聊天类型.
     */
    public function getChatType(): string
    {
        $chatType = $this->data['chat_type'] ?? '';
        \assert(\is_string($chatType));

        return $chatType;
    }

    /**
     * 获取消息类型.
     */
    public function getMessageType(): string
    {
        $messageType = $this->data['message_type'] ?? '';
        \assert(\is_string($messageType));

        return $messageType;
    }

    /**
     * 获取消息内容.
     */
    public function getContent(): string
    {
        $content = $this->data['content'] ?? '';
        \assert(\is_string($content));

        return $content;
    }

    /**
     * 获取@的用户列表.
     *
     * @return array<array{id: array{open_id: string, union_id?: string}, name: string, tenant_key: string}>
     */
    public function getMentions(): array
    {
        $mentions = $this->data['mentions'] ?? [];
        \assert(\is_array($mentions));

        // 类型断言确保每个mention都有正确的结构
        foreach ($mentions as $mention) {
            \assert(\is_array($mention));
            \assert(\is_array($mention['id'] ?? []));
            \assert(\is_string($mention['id']['open_id'] ?? ''));
            \assert(\is_string($mention['name'] ?? ''));
            \assert(\is_string($mention['tenant_key'] ?? ''));
        }

        /** @var array<array{id: array{open_id: string, union_id?: string}, name: string, tenant_key: string}> $mentions */
        return $mentions;
    }

    /**
     * 获取发送者信息.
     *
     * @return array{sender_id: array{union_id: string, user_id: string, open_id: string}, sender_type: string, tenant_key: string}
     */
    public function getSender(): array
    {
        $sender = $this->data['sender'] ?? [];
        \assert(\is_array($sender));
        $sender['sender_id'] = \is_array($sender['sender_id'] ?? null) ? $sender['sender_id'] : [
            'union_id' => '',
            'user_id' => '',
            'open_id' => '',
        ];
        $sender['sender_type'] = \is_string($sender['sender_type'] ?? null) ? $sender['sender_type'] : 'user';
        $sender['tenant_key'] = \is_string($sender['tenant_key'] ?? null) ? $sender['tenant_key'] : '';
        \assert(\is_string($sender['sender_id']['union_id'] ?? ''));
        \assert(\is_string($sender['sender_id']['user_id'] ?? ''));
        \assert(\is_string($sender['sender_id']['open_id'] ?? ''));

        /** @var array{sender_id: array{union_id: string, user_id: string, open_id: string}, sender_type: string, tenant_key: string} $sender */
        return $sender;
    }

    /**
     * 获取发送者ID.
     */
    public function getSenderId(): string
    {
        $sender = $this->getSender();
        $senderId = $sender['sender_id']['open_id'] ?? '';
        \assert(\is_string($senderId));

        return $senderId;
    }

    /**
     * 获取发送者类型.
     */
    public function getSenderType(): string
    {
        $sender = $this->getSender();
        $senderType = $sender['sender_type'] ?? 'user';
        \assert(\is_string($senderType));

        return $senderType;
    }

    /**
     * 是否是群聊消息.
     */
    public function isGroupMessage(): bool
    {
        return \in_array($this->getChatType(), ['group', 'supergroup'], true);
    }

    /**
     * 是否是私聊消息.
     */
    public function isPrivateMessage(): bool
    {
        return 'p2p' === $this->getChatType();
    }

    /**
     * 是否@了机器人.
     */
    public function isMentionedBot(): bool
    {
        $mentions = $this->getMentions();
        foreach ($mentions as $mention) {
            $id = $mention['id'] ?? [];
            \assert(\is_array($id));
            $openId = $id['open_id'] ?? '';
            \assert(\is_string($openId));

            if ('@_all' === $openId) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取纯文本内容（去除@等特殊标记）.
     */
    public function getPlainText(): string
    {
        $content = $this->getContent();
        if ('text' !== $this->getMessageType()) {
            return '';
        }

        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            \assert(\is_array($data));
            $text = $data['text'] ?? '';
            \assert(\is_string($text));

            return $text;
        } catch (\JsonException) {
            return $content;
        }
    }
}
