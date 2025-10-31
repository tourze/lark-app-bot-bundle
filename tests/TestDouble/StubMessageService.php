<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\TestDouble;

use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface;
use Tourze\LarkAppBotBundle\Service\Message\MessageServiceInterface;

/**
 * MessageService 的测试桩实现.
 *
 * @internal 仅用于测试
 */
final class StubMessageService implements MessageServiceInterface
{
    public function __construct(
        private readonly LarkClientInterface $client,
        private readonly LoggerInterface $logger,
    ) {
        // 不再调用父类构造函数，因为现在实现的是接口
    }

    /**
     * 获取客户端实例（用于测试）
     */
    public function getClient(): LarkClientInterface
    {
        return $this->client;
    }

    /**
     * 获取日志记录器（用于测试）
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param array<string, mixed>|string $content
     * @param array<string, mixed>        $options
     *
     * @return array<string, mixed>
     */
    public function send(
        string $receiveId,
        string $msgType,
        array|string $content,
        string $receiveIdType = 'open_id',
        array $options = [],
    ): array {
        // 模拟无效接收者错误
        if ('invalid-receiver' === $receiveId) {
            throw new GenericApiException('Invalid receiver ID');
        }

        return [
            'message_id' => 'mock_message_id_123',
            'create_time' => '1640995200',
            'update_time' => '1640995200',
            'msg_type' => $msgType,
        ];
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
        string $receiveIdType = 'open_id',
        array $options = [],
    ): array {
        return $this->send($receiveId, 'text', ['text' => $text], $receiveIdType, $options);
    }

    /**
     * @return array<string, string>
     */
    public function getSupportedMsgTypes(): array
    {
        return [
            'text' => '文本消息',
            'post' => '富文本消息',
            'image' => '图片消息',
        ];
    }
}
