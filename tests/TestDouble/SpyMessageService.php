<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\TestDouble;

use Tourze\LarkAppBotBundle\Service\Message\MessageServiceInterface;

/**
 * MessageService 的间谍实现，用于测试中记录方法调用.
 *
 * @internal 仅用于测试
 */
final class SpyMessageService implements MessageServiceInterface
{
    /**
     * @var string[]
     */
    private array $calledMethods = [];

    /**
     * 无参构造函数，避免需要真实依赖.
     */
    public function __construct()
    {
        // 无需调用父类构造函数，因为现在实现的是接口
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function send(
        string $receiveId,
        string $msgType,
        array|string $content,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array {
        $this->calledMethods[] = 'send';

        return [];
    }

    public function sendText(
        string $receiveId,
        string $text,
        ?string $rootId = null,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array {
        $this->calledMethods[] = 'sendText';

        return [];
    }

    /**
     * 获取所有已调用的方法名称.
     *
     * @return string[]
     */
    public function getCalledMethods(): array
    {
        return $this->calledMethods;
    }

    /**
     * 清空已调用的方法记录.
     */
    public function clear(): void
    {
        $this->calledMethods = [];
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
