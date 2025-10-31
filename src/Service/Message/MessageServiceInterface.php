<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message;

/**
 * 消息服务接口.
 */
interface MessageServiceInterface
{
    /**
     * 支持的消息类型.
     */
    public const MSG_TYPE_TEXT = 'text';
    public const MSG_TYPE_RICH_TEXT = 'post';
    public const MSG_TYPE_IMAGE = 'image';
    public const MSG_TYPE_FILE = 'file';
    public const MSG_TYPE_AUDIO = 'audio';
    public const MSG_TYPE_MEDIA = 'media';
    public const MSG_TYPE_STICKER = 'sticker';
    public const MSG_TYPE_INTERACTIVE = 'interactive';
    public const MSG_TYPE_SHARE_CHAT = 'share_chat';
    public const MSG_TYPE_SHARE_USER = 'share_user';

    /**
     * 接收者ID类型.
     */
    public const RECEIVE_ID_TYPE_OPEN_ID = 'open_id';
    public const RECEIVE_ID_TYPE_USER_ID = 'user_id';
    public const RECEIVE_ID_TYPE_UNION_ID = 'union_id';
    public const RECEIVE_ID_TYPE_EMAIL = 'email';
    public const RECEIVE_ID_TYPE_CHAT_ID = 'chat_id';

    /**
     * 发送消息.
     *
     * @param string                      $receiveId     接收者的ID
     * @param string                      $msgType       消息类型
     * @param array<string, mixed>|string $content       消息内容
     * @param string                      $receiveIdType 接收者ID类型
     * @param array<string, mixed>        $options       额外选项
     *
     * @return array<string, mixed> 发送结果
     */
    public function send(
        string $receiveId,
        string $msgType,
        array|string $content,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array;

    /**
     * 发送文本消息.
     *
     * @param string               $receiveId     接收者的ID
     * @param string               $text          文本内容
     * @param string|null          $rootId        根消息ID（用于回复）
     * @param string               $receiveIdType 接收者ID类型
     * @param array<string, mixed> $options       额外选项
     *
     * @return array<string, mixed> 发送结果
     */
    public function sendText(
        string $receiveId,
        string $text,
        ?string $rootId = null,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array;

    /**
     * 获取支持的消息类型.
     *
     * @return array<string, string>
     */
    public function getSupportedMsgTypes(): array;
}
