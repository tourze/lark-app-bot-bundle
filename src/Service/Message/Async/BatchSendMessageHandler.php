<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Async;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;
use Tourze\LarkAppBotBundle\Service\Performance\PerformanceMonitor;

/**
 * 批量消息发送处理器
 * 处理批量消息发送，支持并发优化.
 */
#[AsMessageHandler]
#[Autoconfigure(public: true)]
class BatchSendMessageHandler
{
    private const BATCH_SIZE = 50; // 飞书API批量发送限制
    private const MAX_MESSAGE_AGE = 600; // 10分钟

    public function __construct(
        private readonly MessageService $messageService,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly ?PerformanceMonitor $performanceMonitor = null,
    ) {
    }

    public function __invoke(BatchSendMessageCommand $command): void
    {
        // 检查消息年龄
        if ($command->getAge() > self::MAX_MESSAGE_AGE) {
            $this->logger->warning('Dropping stale batch message', [
                'correlation_id' => $command->getCorrelationId(),
                'age' => $command->getAge(),
                'receive_ids_count' => \count($command->getReceiveIds()),
            ]);

            throw new UnrecoverableMessageHandlingException('Batch message too old to process');
        }

        $receiveIds = $command->getReceiveIds();
        $totalCount = \count($receiveIds);

        $this->logger->info('Processing batch message', [
            'correlation_id' => $command->getCorrelationId(),
            'total_recipients' => $totalCount,
            'msg_type' => $command->getMsgType(),
        ]);

        // 如果接收者数量超过批量限制，分割成多个批次
        if ($totalCount > self::BATCH_SIZE) {
            $this->splitAndDispatch($command);

            return;
        }

        // 执行批量发送
        try {
            $startTime = microtime(true);

            $result = $this->messageService->sendBatch(
                $receiveIds,
                $command->getMsgType(),
                $command->getContent(),
                $command->getReceiveIdType()
            );

            $duration = microtime(true) - $startTime;

            // 记录性能指标
            if (null !== $this->performanceMonitor) {
                $this->performanceMonitor->recordCustomMetric(
                    'batch_send_duration',
                    $duration,
                    [
                        'batch_size' => (string) $totalCount,
                        'msg_type' => $command->getMsgType(),
                    ]
                );
            }

            // 处理失败的接收者
            $invalidIds = $result['invalid_receive_ids'] ?? [];
            \assert(\is_array($invalidIds));
            if ([] !== $invalidIds) {
                $this->logger->warning('Some recipients failed in batch send', [
                    'correlation_id' => $command->getCorrelationId(),
                    'invalid_count' => \count($invalidIds),
                    'invalid_ids' => $invalidIds,
                ]);

                // 可以选择为失败的接收者创建单独的重试任务
                /** @var array<string> $invalidIds */
                $this->retryFailedRecipients($command, $invalidIds);
            }

            $this->logger->info('Batch message processed', [
                'correlation_id' => $command->getCorrelationId(),
                'message_id' => $result['message_id'] ?? null,
                'success_count' => $totalCount - \count($invalidIds),
                'failed_count' => \count($invalidIds),
                'duration' => $duration,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send batch message', [
                'correlation_id' => $command->getCorrelationId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 降级到单个发送
            $this->fallbackToIndividualSend($command);
        }
    }

    /**
     * 分割大批量为多个小批次
     */
    private function splitAndDispatch(BatchSendMessageCommand $command): void
    {
        $this->logger->info('Splitting large batch into smaller batches', [
            'correlation_id' => $command->getCorrelationId(),
            'total_recipients' => \count($command->getReceiveIds()),
            'batch_size' => self::BATCH_SIZE,
        ]);

        $subCommands = $command->split(self::BATCH_SIZE);

        foreach ($subCommands as $subCommand) {
            $this->messageBus->dispatch($subCommand);
        }
    }

    /**
     * 重试失败的接收者.
     *
     * @param array<string> $invalidIds
     */
    private function retryFailedRecipients(BatchSendMessageCommand $command, array $invalidIds): void
    {
        /** @var array<string> $invalidIds */
        $invalidIds = array_filter($invalidIds, 'is_string');

        foreach ($invalidIds as $receiveId) {
            $individualCommand = new SendMessageCommand(
                $receiveId,
                $command->getMsgType(),
                $command->getContent(),
                $command->getReceiveIdType(),
                $command->getOptions(),
                $command->getCorrelationId() . '_retry_' . $receiveId,
                0,
                microtime(true)
            );

            $this->messageBus->dispatch($individualCommand);
        }
    }

    /**
     * 降级到单个发送
     */
    private function fallbackToIndividualSend(BatchSendMessageCommand $command): void
    {
        $this->logger->warning('Falling back to individual send', [
            'correlation_id' => $command->getCorrelationId(),
            'recipient_count' => \count($command->getReceiveIds()),
        ]);

        foreach ($command->getReceiveIds() as $receiveId) {
            $individualCommand = new SendMessageCommand(
                $receiveId,
                $command->getMsgType(),
                $command->getContent(),
                $command->getReceiveIdType(),
                $command->getOptions(),
                $command->getCorrelationId() . '_fallback_' . $receiveId,
                0,
                microtime(true)
            );

            $this->messageBus->dispatch($individualCommand);
        }
    }
}
