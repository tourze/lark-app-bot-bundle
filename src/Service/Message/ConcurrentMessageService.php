<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Tourze\LarkAppBotBundle\Service\Message\Async\BatchSendMessageCommand;
use Tourze\LarkAppBotBundle\Service\Message\Async\SendMessageCommand;
use Tourze\LarkAppBotBundle\Service\Message\Builder\MessageBuilderInterface;

/**
 * 并发消息服务
 * 提供同步和异步消息发送的统一接口，支持批量发送优化.
 */
#[Autoconfigure(public: true)]
class ConcurrentMessageService
{
    /**
     * 发送模式.
     */
    public const MODE_SYNC = 'sync';
    public const MODE_ASYNC = 'async';
    public const MODE_BATCH = 'batch';

    /**
     * 批量发送的默认批次大小.
     */
    private const DEFAULT_BATCH_SIZE = 50;
    /**
     * 批量发送的时间窗口（秒）.
     */
    private const BATCH_WINDOW = 1.0;

    /**
     * 批量发送的缓冲区.
     *
     * @var array<string, array{receiveIds: array<string>, msgType: string, content: array<string, mixed>|string, receiveIdType: string, options: array<string, mixed>}>
     */
    private array $batchBuffer = [];

    /**
     * 批量发送的计时器.
     *
     * @var array<string, float>
     */
    private array $batchTimers = [];

    public function __construct(
        private readonly MessageService $messageService,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly string $defaultMode = self::MODE_SYNC,
    ) {
    }

    /**
     * 发送消息使用构建器.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|string
     */
    public function sendWithBuilder(
        string $receiveId,
        MessageBuilderInterface $builder,
        string $receiveIdType = MessageService::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
        ?string $mode = null,
    ): array|string {
        return $this->send(
            $receiveId,
            $builder->getMsgType(),
            $builder->build(),
            $receiveIdType,
            $options,
            $mode
        );
    }

    /**
     * 发送消息（自动选择最优方式）.
     *
     * @param array<string, mixed>|string $content
     * @param array<string, mixed>        $options
     *
     * @return array<string, mixed>|string
     */
    public function send(
        string $receiveId,
        string $msgType,
        array|string $content,
        string $receiveIdType = MessageService::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
        ?string $mode = null,
    ): array|string {
        $mode ??= $this->defaultMode;

        return match ($mode) {
            self::MODE_ASYNC => $this->sendAsync($receiveId, $msgType, $content, $receiveIdType, $options),
            self::MODE_BATCH => $this->addToBatch($receiveId, $msgType, $content, $receiveIdType, $options),
            default => $this->sendSync($receiveId, $msgType, $content, $receiveIdType, $options),
        };
    }

    /**
     * 异步发送消息.
     *
     * @param array<string, mixed>|string $content
     * @param array<string, mixed>        $options
     *
     * @return string 返回关联ID
     */
    public function sendAsync(
        string $receiveId,
        string $msgType,
        array|string $content,
        string $receiveIdType = MessageService::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
        ?int $delaySeconds = null,
    ): string {
        $correlationId = uniqid('async_', true);

        $command = new SendMessageCommand(
            $receiveId,
            $msgType,
            $content,
            $receiveIdType,
            $options,
            $correlationId,
            0,
            microtime(true)
        );

        $stamps = [];
        if (null !== $delaySeconds && $delaySeconds > 0) {
            $stamps[] = new DelayStamp($delaySeconds * 1000);
        }

        $this->messageBus->dispatch($command, $stamps);

        $this->logger->info('Message queued for async send', [
            'correlation_id' => $correlationId,
            'receive_id' => $receiveId,
            'msg_type' => $msgType,
            'delay' => $delaySeconds,
        ]);

        return $correlationId;
    }

    /**
     * 添加到批量发送缓冲区.
     *
     * @param array<string, mixed>|string $content
     * @param array<string, mixed>        $options
     *
     * @return string 返回批次ID
     */
    public function addToBatch(
        string $receiveId,
        string $msgType,
        array|string $content,
        string $receiveIdType = MessageService::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): string {
        $batchKey = $this->generateBatchKey($msgType, $content, $receiveIdType);

        if (!isset($this->batchBuffer[$batchKey])) {
            $this->batchBuffer[$batchKey] = [
                'receiveIds' => [],
                'msgType' => $msgType,
                'content' => $content,
                'receiveIdType' => $receiveIdType,
                'options' => $options,
            ];
            $this->batchTimers[$batchKey] = microtime(true);
        }

        $this->batchBuffer[$batchKey]['receiveIds'][] = $receiveId;

        // 检查是否需要立即发送
        if ($this->shouldFlushBatch($batchKey)) {
            $this->flushBatch($batchKey);
        }

        return $batchKey;
    }

    /**
     * 批量发送消息到多个接收者.
     *
     * @param array<string>               $receiveIds
     * @param array<string, mixed>|string $content
     * @param array<string, mixed>        $options
     *
     * @return string 返回关联ID
     */
    public function sendBatchAsync(
        array $receiveIds,
        string $msgType,
        array|string $content,
        string $receiveIdType = MessageService::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): string {
        $correlationId = uniqid('batch_', true);

        $command = new BatchSendMessageCommand(
            $receiveIds,
            $msgType,
            $content,
            $receiveIdType,
            $options,
            $correlationId,
            0,
            microtime(true)
        );

        $this->messageBus->dispatch($command);

        $this->logger->info('Batch message queued', [
            'correlation_id' => $correlationId,
            'recipient_count' => \count($receiveIds),
            'msg_type' => $msgType,
        ]);

        return $correlationId;
    }

    /**
     * 同步发送消息.
     *
     * @param array<string, mixed>|string $content
     * @param array<string, mixed>        $options
     *
     * @return array<string, mixed>
     */
    public function sendSync(
        string $receiveId,
        string $msgType,
        array|string $content,
        string $receiveIdType = MessageService::RECEIVE_ID_TYPE_OPEN_ID,
        array $options = [],
    ): array {
        return $this->messageService->send($receiveId, $msgType, $content, $receiveIdType, $options);
    }

    /**
     * 刷新所有批次缓冲区.
     */
    public function flushAllBatches(): void
    {
        foreach (array_keys($this->batchBuffer) as $batchKey) {
            $this->flushBatch($batchKey);
        }
    }

    /**
     * 获取批次统计信息.
     *
     * @return array<string, mixed>
     */
    public function getBatchStats(): array
    {
        $stats = [];

        foreach ($this->batchBuffer as $batchKey => $batch) {
            $stats[$batchKey] = [
                'recipient_count' => \count($batch['receiveIds']),
                'msg_type' => $batch['msgType'],
                'age' => microtime(true) - $this->batchTimers[$batchKey],
            ];
        }

        return $stats;
    }

    /**
     * 生成批次键.
     *
     * @param array<string, mixed>|string $content
     */
    private function generateBatchKey(string $msgType, array|string $content, string $receiveIdType): string
    {
        if (\is_array($content)) {
            $jsonString = json_encode($content);
            $contentString = false !== $jsonString ? $jsonString : serialize($content);
        } else {
            $contentString = $content;
        }
        $contentHash = md5($contentString);

        return \sprintf('%s_%s_%s', $msgType, $receiveIdType, $contentHash);
    }

    /**
     * 检查是否应该刷新批次
     */
    private function shouldFlushBatch(string $batchKey): bool
    {
        $batch = $this->batchBuffer[$batchKey];

        // 达到批次大小限制
        if (\count($batch['receiveIds']) >= self::DEFAULT_BATCH_SIZE) {
            return true;
        }

        // 超过时间窗口
        $elapsed = microtime(true) - $this->batchTimers[$batchKey];
        if ($elapsed >= self::BATCH_WINDOW) {
            return true;
        }

        return false;
    }

    /**
     * 刷新特定批次
     */
    private function flushBatch(string $batchKey): void
    {
        if (!isset($this->batchBuffer[$batchKey])) {
            return;
        }

        $batch = $this->batchBuffer[$batchKey];

        if ([] === $batch['receiveIds']) {
            unset($this->batchBuffer[$batchKey], $this->batchTimers[$batchKey]);

            return;
        }

        // 发送批量消息
        $this->sendBatchAsync(
            $batch['receiveIds'],
            $batch['msgType'],
            $batch['content'],
            $batch['receiveIdType'],
            $batch['options']
        );

        // 清理缓冲区
        unset($this->batchBuffer[$batchKey], $this->batchTimers[$batchKey]);
    }
}
