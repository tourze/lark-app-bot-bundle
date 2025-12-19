<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Event;

/**
 * 群组事件.
 */
final class GroupEvent extends LarkEvent
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
     * 获取操作者用户ID.
     */
    public function getOperatorId(): string
    {
        $operatorId = $this->data['operator_id'] ?? '';
        \assert(\is_string($operatorId));

        return $operatorId;
    }

    /**
     * 获取外部标签（仅对外部群有效）.
     */
    public function getExternalLabel(): string
    {
        $externalLabel = $this->data['external_label'] ?? '';
        \assert(\is_string($externalLabel));

        return $externalLabel;
    }

    /**
     * 获取i18n名称.
     *
     * @return array<string, string>
     */
    public function getI18nNames(): array
    {
        $i18nNames = $this->data['i18n_names'] ?? [];
        \assert(\is_array($i18nNames));

        // 类型断言确保键值都是字符串
        foreach ($i18nNames as $key => $value) {
            \assert(\is_string($key));
            \assert(\is_string($value));
        }

        /** @var array<string, string> $i18nNames */
        return $i18nNames;
    }

    /**
     * 是否是解散事件.
     */
    public function isDisbanded(): bool
    {
        return 'im.chat.disbanded_v1' === $this->eventType;
    }

    /**
     * 是否是更新事件.
     */
    public function isUpdated(): bool
    {
        return 'im.chat.updated_v1' === $this->eventType;
    }
}
