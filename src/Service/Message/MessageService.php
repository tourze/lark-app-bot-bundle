<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\ValidationException;
use Tourze\LarkAppBotBundle\Service\Client\LarkClientInterface;
use Tourze\LarkAppBotBundle\Service\Message\Builder\MessageBuilderInterface;

/**
 * 消息服务核心类
 * 负责处理飞书消息的发送、回复、编辑等操作.
 */
#[Autoconfigure(public: true)]
final class MessageService implements MessageServiceInterface
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

    private LarkClientInterface $client;

    private LoggerInterface $logger;

    /**
     * @var array<string, string>
     */
    private array $supportedMsgTypes = [
        self::MSG_TYPE_TEXT => '文本消息',
        self::MSG_TYPE_RICH_TEXT => '富文本消息',
        self::MSG_TYPE_IMAGE => '图片消息',
        self::MSG_TYPE_FILE => '文件消息',
        self::MSG_TYPE_AUDIO => '音频消息',
        self::MSG_TYPE_MEDIA => '视频消息',
        self::MSG_TYPE_STICKER => '表情包消息',
        self::MSG_TYPE_INTERACTIVE => '卡片消息',
        self::MSG_TYPE_SHARE_CHAT => '分享群名片',
        self::MSG_TYPE_SHARE_USER => '分享个人名片',
    ];

    public function __construct(
        LarkClientInterface $client,
        LoggerInterface $logger,
    ) {
        $this->client = $client;
        $this->logger = $logger;
    }

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
     * @throws ApiException
     * @throws ValidationException
     */
    public function send(
        string $receiveId,
        string $msgType,
        array|string $content,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array {
        $this->validateMsgType($msgType);
        $this->validateReceiveIdType($receiveIdType);

        // 如果内容是数组，转换为JSON字符串
        if (\is_array($content)) {
            $content = json_encode($content, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }

        $payload = array_merge([
            'receive_id' => $receiveId,
            'msg_type' => $msgType,
            'content' => $content,
        ], $options);

        $this->logger->debug('Sending message', [
            'receive_id' => $receiveId,
            'msg_type' => $msgType,
            'receive_id_type' => $receiveIdType,
        ]);

        $response = $this->client->request(
            'POST',
            '/open-apis/im/v1/messages',
            [
                'query' => ['receive_id_type' => $receiveIdType],
                'json' => $payload,
            ]
        );

        $data = json_decode($response->getContent(), true);
        \assert(\is_array($data));

        $responseData = $data['data'] ?? [];
        \assert(\is_array($responseData));

        $this->logger->info('Message sent successfully', [
            'message_id' => $responseData['message_id'] ?? null,
            'receive_id' => $receiveId,
            'msg_type' => $msgType,
        ]);

        return $responseData;
    }

    /**
     * 使用消息构建器发送消息.
     *
     * @param string                  $receiveId     接收者的ID
     * @param MessageBuilderInterface $builder       消息构建器
     * @param string                  $receiveIdType 接收者ID类型
     * @param array<string, mixed>    $options       额外选项
     *
     * @return array<string, mixed> 发送结果
     * @throws ApiException
     * @throws ValidationException
     */
    public function sendWithBuilder(
        string $receiveId,
        MessageBuilderInterface $builder,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array {
        return $this->send(
            $receiveId,
            $builder->getMsgType(),
            $builder->build(),
            $receiveIdType,
            $options
        );
    }

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
     * @throws ApiException
     * @throws ValidationException
     */
    public function sendText(
        string $receiveId,
        string $text,
        ?string $rootId = null,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array {
        if (null !== $rootId) {
            $options['root_id'] = $rootId;
        }

        return $this->send(
            $receiveId,
            self::MSG_TYPE_TEXT,
            ['text' => $text],
            $receiveIdType,
            $options
        );
    }

    /**
     * 发送富文本消息.
     *
     * @param string               $receiveId     接收者的ID
     * @param array<string, mixed> $post          富文本内容
     * @param string|null          $rootId        根消息ID（用于回复）
     * @param string               $receiveIdType 接收者ID类型
     * @param array<string, mixed> $options       额外选项
     *
     * @return array<string, mixed> 发送结果
     * @throws ApiException
     * @throws ValidationException
     */
    public function sendRichText(
        string $receiveId,
        array $post,
        ?string $rootId = null,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array {
        if (null !== $rootId) {
            $options['root_id'] = $rootId;
        }

        return $this->send(
            $receiveId,
            self::MSG_TYPE_RICH_TEXT,
            $post,
            $receiveIdType,
            $options
        );
    }

    /**
     * 发送图片消息.
     *
     * @param string               $receiveId     接收者的ID
     * @param string               $imageKey      图片的key
     * @param string               $receiveIdType 接收者ID类型
     * @param array<string, mixed> $options       额外选项
     *
     * @return array<string, mixed> 发送结果
     * @throws ApiException
     * @throws ValidationException
     */
    public function sendImage(
        string $receiveId,
        string $imageKey,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array {
        return $this->send(
            $receiveId,
            self::MSG_TYPE_IMAGE,
            ['image_key' => $imageKey],
            $receiveIdType,
            $options
        );
    }

    /**
     * 发送文件消息.
     *
     * @param string               $receiveId     接收者的ID
     * @param string               $fileKey       文件的key
     * @param string               $receiveIdType 接收者ID类型
     * @param array<string, mixed> $options       额外选项
     *
     * @return array<string, mixed> 发送结果
     * @throws ApiException
     * @throws ValidationException
     */
    public function sendFile(
        string $receiveId,
        string $fileKey,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array {
        return $this->send(
            $receiveId,
            self::MSG_TYPE_FILE,
            ['file_key' => $fileKey],
            $receiveIdType,
            $options
        );
    }

    /**
     * 发送卡片消息.
     *
     * @param string               $receiveId     接收者的ID
     * @param array<string, mixed> $card          卡片内容
     * @param string|null          $rootId        根消息ID（用于回复）
     * @param string               $receiveIdType 接收者ID类型
     * @param array<string, mixed> $options       额外选项
     *
     * @return array<string, mixed> 发送结果
     * @throws ApiException
     * @throws ValidationException
     */
    public function sendCard(
        string $receiveId,
        array $card,
        ?string $rootId = null,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array {
        if (null !== $rootId) {
            $options['root_id'] = $rootId;
        }

        return $this->send(
            $receiveId,
            self::MSG_TYPE_INTERACTIVE,
            $card,
            $receiveIdType,
            $options
        );
    }

    /**
     * 回复消息.
     *
     * @param string                      $messageId 要回复的消息ID
     * @param string                      $msgType   消息类型
     * @param array<string, mixed>|string $content   消息内容
     * @param array<string, mixed>        $options   额外选项
     *
     * @return array<string, mixed> 发送结果
     * @throws ApiException
     * @throws ValidationException
     */
    public function reply(
        string $messageId,
        string $msgType,
        array|string $content,
        array $options = [],
    ): array {
        $this->validateMsgType($msgType);

        // 如果内容是数组，转换为JSON字符串
        if (\is_array($content)) {
            $content = json_encode($content, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }

        $payload = array_merge([
            'msg_type' => $msgType,
            'content' => $content,
        ], $options);

        $this->logger->debug('Replying to message', [
            'message_id' => $messageId,
            'msg_type' => $msgType,
        ]);

        $response = $this->client->request(
            'POST',
            \sprintf('/open-apis/im/v1/messages/%s/reply', $messageId),
            [
                'json' => $payload,
            ]
        );

        $data = json_decode($response->getContent(), true);
        \assert(\is_array($data));

        $replyData = $data['data'] ?? [];
        \assert(\is_array($replyData));

        $this->logger->info('Reply sent successfully', [
            'reply_message_id' => $replyData['message_id'] ?? null,
            'original_message_id' => $messageId,
            'msg_type' => $msgType,
        ]);

        return $replyData;
    }

    /**
     * 编辑消息.
     *
     * @param string                      $messageId 要编辑的消息ID
     * @param string                      $msgType   消息类型
     * @param array<string, mixed>|string $content   新的消息内容
     *
     * @return array<string, mixed> 编辑结果
     * @throws ApiException
     * @throws ValidationException
     */
    public function edit(
        string $messageId,
        string $msgType,
        array|string $content,
    ): array {
        $this->validateMsgType($msgType);

        // 如果内容是数组，转换为JSON字符串
        if (\is_array($content)) {
            $content = json_encode($content, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }

        $payload = [
            'msg_type' => $msgType,
            'content' => $content,
        ];

        $this->logger->debug('Editing message', [
            'message_id' => $messageId,
            'msg_type' => $msgType,
        ]);

        $response = $this->client->request(
            'PUT',
            \sprintf('/open-apis/im/v1/messages/%s', $messageId),
            [
                'json' => $payload,
            ]
        );

        $data = json_decode($response->getContent(), true);
        \assert(\is_array($data));

        $editData = $data['data'] ?? [];
        \assert(\is_array($editData));

        $this->logger->info('Message edited successfully', [
            'message_id' => $messageId,
            'msg_type' => $msgType,
        ]);

        /** @var array<string, mixed> $editData */
        return $editData;
    }

    /**
     * 撤回消息.
     *
     * @param string $messageId 要撤回的消息ID
     *
     * @return array<string, mixed> 撤回结果
     * @throws ApiException
     */
    public function recall(string $messageId): array
    {
        $this->logger->debug('Recalling message', [
            'message_id' => $messageId,
        ]);

        $response = $this->client->request(
            'DELETE',
            \sprintf('/open-apis/im/v1/messages/%s', $messageId)
        );

        $data = json_decode($response->getContent(), true);
        \assert(\is_array($data));

        $recallData = $data['data'] ?? [];
        \assert(\is_array($recallData));

        $this->logger->info('Message recalled successfully', [
            'message_id' => $messageId,
        ]);

        /** @var array<string, mixed> $recallData */
        return $recallData;
    }

    /**
     * 获取消息.
     *
     * @param string $messageId 消息ID
     *
     * @return array<string, mixed> 消息详情
     * @throws ApiException
     */
    public function get(string $messageId): array
    {
        $this->logger->debug('Getting message', [
            'message_id' => $messageId,
        ]);

        $response = $this->client->request(
            'GET',
            \sprintf('/open-apis/im/v1/messages/%s', $messageId)
        );

        $data = json_decode($response->getContent(), true);
        \assert(\is_array($data));

        $messageData = $data['data'] ?? [];
        \assert(\is_array($messageData));

        /** @var array<string, mixed> $messageData */
        return $messageData;
    }

    /**
     * 批量发送消息.
     *
     * @param array<string>               $receiveIds    接收者ID列表
     * @param string                      $msgType       消息类型
     * @param array<string, mixed>|string $content       消息内容
     * @param string                      $receiveIdType 接收者ID类型
     *
     * @return array<string, mixed> 批量发送结果
     * @throws ApiException
     * @throws ValidationException
     */
    public function sendBatch(
        array $receiveIds,
        string $msgType,
        array|string $content,
        string $receiveIdType = self::RECEIVE_ID_TYPE_OPEN_ID,
    ): array {
        $this->validateMsgType($msgType);
        $this->validateReceiveIdType($receiveIdType);

        if ([] === $receiveIds) {
            throw new ValidationException('接收者ID列表不能为空');
        }

        if (\count($receiveIds) > 200) {
            throw new ValidationException('批量发送消息接收者数量不能超过200');
        }

        // 如果内容是数组，转换为JSON字符串
        if (\is_array($content)) {
            $content = json_encode($content, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }

        $payload = [
            'receive_ids' => $receiveIds,
            'msg_type' => $msgType,
            'content' => $content,
        ];

        $this->logger->debug('Sending batch message', [
            'receive_ids_count' => \count($receiveIds),
            'msg_type' => $msgType,
            'receive_id_type' => $receiveIdType,
        ]);

        $response = $this->client->request(
            'POST',
            '/open-apis/message/v4/batch_send',
            [
                'query' => ['receive_id_type' => $receiveIdType],
                'json' => $payload,
            ]
        );

        $data = json_decode($response->getContent(), true);
        \assert(\is_array($data));

        $batchData = $data['data'] ?? [];
        \assert(\is_array($batchData));

        $this->logger->info('Batch message sent', [
            'message_id' => $batchData['message_id'] ?? null,
            'invalid_receive_ids' => $batchData['invalid_receive_ids'] ?? [],
        ]);

        return $batchData;
    }

    /**
     * 获取支持的消息类型.
     *
     * @return array<string, string>
     */
    public function getSupportedMsgTypes(): array
    {
        return $this->supportedMsgTypes;
    }

    /**
     * 验证消息类型.
     *
     * @param string $msgType 消息类型
     *
     * @throws ValidationException
     */
    private function validateMsgType(string $msgType): void
    {
        if (!isset($this->supportedMsgTypes[$msgType])) {
            throw ValidationException::withErrors(\sprintf('不支持的消息类型: %s', $msgType), ['supported_types' => array_keys($this->supportedMsgTypes)]);
        }
    }

    /**
     * 验证接收者ID类型.
     *
     * @param string $receiveIdType 接收者ID类型
     *
     * @throws ValidationException
     */
    private function validateReceiveIdType(string $receiveIdType): void
    {
        $supportedTypes = [
            self::RECEIVE_ID_TYPE_OPEN_ID,
            self::RECEIVE_ID_TYPE_USER_ID,
            self::RECEIVE_ID_TYPE_UNION_ID,
            self::RECEIVE_ID_TYPE_EMAIL,
            self::RECEIVE_ID_TYPE_CHAT_ID,
        ];

        if (!\in_array($receiveIdType, $supportedTypes, true)) {
            throw ValidationException::withErrors(\sprintf('不支持的接收者ID类型: %s', $receiveIdType), ['supported_types' => $supportedTypes]);
        }
    }
}
