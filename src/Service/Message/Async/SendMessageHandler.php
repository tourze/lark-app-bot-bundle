<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Service\Message\Async;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Tourze\LarkAppBotBundle\Exception\ApiException;
use Tourze\LarkAppBotBundle\Exception\RateLimitException;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;
use Tourze\LarkAppBotBundle\Service\Performance\PerformanceMonitor;

/**
 * 异步消息发送处理器
 * 处理通过消息队列发送的飞书消息.
 */
#[AsMessageHandler]
#[Autoconfigure(public: true)]
final class SendMessageHandler
{
    private const MAX_RETRY_COUNT = 3;
    private const MAX_MESSAGE_AGE = 300; // 5分钟

    public function __construct(
        private readonly MessageService $messageService,
        private readonly LoggerInterface $logger,
        private readonly ?PerformanceMonitor $performanceMonitor = null,
    ) {
    }

    public function __invoke(SendMessageCommand $command): void
    {
        $this->validateMessageAge($command);
        $this->logProcessingStart($command);

        try {
            $result = $this->executeMessageSend($command);
            $this->logSuccess($command, $result);
        } catch (RateLimitException $e) {
            $this->handleRateLimitException($command, $e);
        } catch (ApiException $e) {
            $this->handleApiException($command, $e);
        } catch (\Exception $e) {
            $this->handleUnexpectedException($command, $e);
        }
    }

    /**
     * 验证消息年龄.
     *
     * @throws UnrecoverableMessageHandlingException
     */
    private function validateMessageAge(SendMessageCommand $command): void
    {
        if ($command->getAge() <= self::MAX_MESSAGE_AGE) {
            return;
        }

        $this->logger->warning('Dropping stale message', [
            'correlation_id' => $command->getCorrelationId(),
            'age' => $command->getAge(),
            'receive_id' => $command->getReceiveId(),
        ]);

        throw new UnrecoverableMessageHandlingException('Message too old to process');
    }

    /**
     * 记录处理开始.
     */
    private function logProcessingStart(SendMessageCommand $command): void
    {
        $this->logger->info('Processing async message', [
            'correlation_id' => $command->getCorrelationId(),
            'msg_type' => $command->getMsgType(),
            'receive_id' => $command->getReceiveId(),
            'retry_count' => $command->getRetryCount(),
        ]);
    }

    /**
     * 执行消息发送.
     *
     * @return array<string, mixed>
     */
    private function executeMessageSend(SendMessageCommand $command): array
    {
        if (null === $this->performanceMonitor) {
            return $this->sendMessage($command);
        }

        $result = $this->performanceMonitor->monitorMessageSend(
            $command->getMsgType(),
            fn () => $this->sendMessage($command)
        );

        \assert(\is_array($result));

        /** @var array<string, mixed> $result */
        return $result;
    }

    /**
     * 记录成功.
     *
     * @param array<string, mixed> $result
     */
    private function logSuccess(SendMessageCommand $command, array $result): void
    {
        $this->logger->info('Async message sent successfully', [
            'correlation_id' => $command->getCorrelationId(),
            'message_id' => $result['message_id'] ?? null,
        ]);
    }

    /**
     * 处理速率限制异常.
     *
     * @throws RecoverableMessageHandlingException|UnrecoverableMessageHandlingException
     */
    private function handleRateLimitException(SendMessageCommand $command, RateLimitException $e): void
    {
        $this->logger->warning('Rate limit encountered', [
            'correlation_id' => $command->getCorrelationId(),
            'retry_count' => $command->getRetryCount(),
            'error' => $e->getMessage(),
        ]);

        if ($command->getRetryCount() >= self::MAX_RETRY_COUNT) {
            throw new UnrecoverableMessageHandlingException('Max retry count exceeded due to rate limiting', 0, $e);
        }

        throw new RecoverableMessageHandlingException('Rate limited, will retry', 0, $e);
    }

    /**
     * 处理API异常.
     *
     * @throws RecoverableMessageHandlingException|UnrecoverableMessageHandlingException
     */
    private function handleApiException(SendMessageCommand $command, ApiException $e): void
    {
        $statusCode = $e->getCode();

        if ($this->isRecoverableError($statusCode)) {
            $this->handleRecoverableApiError($command, $e, $statusCode);

            return;
        }

        $this->handleUnrecoverableApiError($command, $e, $statusCode);
    }

    /**
     * 判断是否为可恢复错误.
     */
    private function isRecoverableError(int $statusCode): bool
    {
        return $statusCode >= 500 || 0 === $statusCode;
    }

    /**
     * 处理可恢复的API错误.
     *
     * @throws RecoverableMessageHandlingException|UnrecoverableMessageHandlingException
     */
    private function handleRecoverableApiError(SendMessageCommand $command, ApiException $e, int $statusCode): void
    {
        $this->logger->error('Server error sending async message', [
            'correlation_id' => $command->getCorrelationId(),
            'retry_count' => $command->getRetryCount(),
            'error' => $e->getMessage(),
            'status_code' => $statusCode,
        ]);

        if ($command->getRetryCount() >= self::MAX_RETRY_COUNT) {
            throw new UnrecoverableMessageHandlingException('Max retry count exceeded due to server errors', 0, $e);
        }

        throw new RecoverableMessageHandlingException('Server error, will retry', 0, $e);
    }

    /**
     * 处理不可恢复的API错误.
     *
     * @throws UnrecoverableMessageHandlingException
     */
    private function handleUnrecoverableApiError(SendMessageCommand $command, ApiException $e, int $statusCode): void
    {
        $this->logger->error('Client error sending async message', [
            'correlation_id' => $command->getCorrelationId(),
            'error' => $e->getMessage(),
            'status_code' => $statusCode,
        ]);

        throw new UnrecoverableMessageHandlingException('Client error, message cannot be sent', 0, $e);
    }

    /**
     * 处理未预期的异常.
     *
     * @throws UnrecoverableMessageHandlingException
     */
    private function handleUnexpectedException(SendMessageCommand $command, \Exception $e): void
    {
        $this->logger->error('Unexpected error sending async message', [
            'correlation_id' => $command->getCorrelationId(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        throw new UnrecoverableMessageHandlingException('Unexpected error occurred', 0, $e);
    }

    /**
     * 发送消息.
     *
     * @return array<string, mixed>
     */
    private function sendMessage(SendMessageCommand $command): array
    {
        return $this->messageService->send(
            $command->getReceiveId(),
            $command->getMsgType(),
            $command->getContent(),
            $command->getReceiveIdType(),
            $command->getOptions()
        );
    }
}
