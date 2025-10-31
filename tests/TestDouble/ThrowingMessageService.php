<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\TestDouble;

use Tourze\LarkAppBotBundle\Service\Message\MessageServiceInterface;

/**
 * 总是抛出异常的 MessageService，用于测试错误处理.
 *
 * @internal 仅用于测试
 */
final class ThrowingMessageService implements MessageServiceInterface
{
    /**
     * 无参构造函数，避免需要真实依赖.
     */
    public function __construct()
    {
        // 无需调用父类构造函数，因为现在实现的是接口
    }

    public function send(
        string $receiveId,
        string $msgType,
        array|string $content,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array {
        throw new \Exception('Send failed');
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function sendText(
        string $receiveId,
        string $text,
        ?string $rootId = null,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array {
        throw new \Exception('Send failed');
    }

    public function getSupportedMsgTypes(): array
    {
        return [
            self::MSG_TYPE_TEXT => '文本消息',
            self::MSG_TYPE_RICH_TEXT => '富文本消息',
            self::MSG_TYPE_IMAGE => '图片消息',
        ];
    }
}
