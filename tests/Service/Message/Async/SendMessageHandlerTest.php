<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Async;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Exception\RateLimitException;
use Tourze\LarkAppBotBundle\Service\Message\Async\SendMessageCommand;
use Tourze\LarkAppBotBundle\Service\Message\Async\SendMessageHandler;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;
use Tourze\LarkAppBotBundle\Service\Performance\PerformanceMonitor;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(SendMessageHandler::class)]
#[RunTestsInSeparateProcesses]
final class SendMessageHandlerTest extends AbstractIntegrationTestCase
{
    private SendMessageHandler $handler;

    private MockObject&MessageService $mockMessageService;

    private MockObject&LoggerInterface $mockLogger;

    private MockObject&PerformanceMonitor $mockPerformanceMonitor;

    public function testHandlerDropsStaleMessage(): void
    {
        // 创建一个6分钟前的消息
        $oldTimestamp = microtime(true) - 360;
        $command = new SendMessageCommand(
            'user123',
            'text',
            'Old message',
            'open_id',
            [],
            'corr_old',
            0,
            $oldTimestamp
        );

        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                'Dropping stale message',
                self::callback(function ($context) {
                    return isset($context['correlation_id'])
                        && isset($context['age'], $context['receive_id'])
                        && 'user123' === $context['receive_id'];
                })
            )
        ;

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Message too old to process');

        $this->handler->__invoke($command);
    }

    public function testHandlerSendsMessageSuccessfully(): void
    {
        $command = new SendMessageCommand(
            'user456',
            'text',
            ['text' => 'Hello World'],
            'open_id',
            ['uuid' => 'test-uuid'],
            'corr_success'
        );

        $expectedResult = ['message_id' => 'msg_789', 'status' => 'success'];

        // 先设置 MessageService 的期望
        $this->mockMessageService->expects($this->once())
            ->method('send')
            ->with(
                'user456',
                'text',
                ['text' => 'Hello World'],
                'open_id',
                ['uuid' => 'test-uuid']
            )
            ->willReturn($expectedResult)
        ;

        $this->mockPerformanceMonitor->expects($this->once())
            ->method('monitorMessageSend')
            ->with(
                'text',
                self::isCallable()
            )
            ->willReturnCallback(function ($msgType, $callback) {
                // @phpstan-ignore symplify.noDynamicName (Mock callback必须动态调用)
                return $callback();
            })
        ;

        $this->mockLogger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context): void {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $this->assertSame('Processing async message', $message);
                    $this->assertSame('text', $context['msg_type']);
                    $this->assertSame('user456', $context['receive_id']);
                    $this->assertSame(0, $context['retry_count']);
                } elseif (2 === $callCount) {
                    $this->assertSame('Async message sent successfully', $message);
                    $this->assertSame('msg_789', $context['message_id']);
                }
            })
        ;

        $this->handler->__invoke($command);
    }

    public function testHandlerHandlesRateLimitWithRetry(): void
    {
        $command = new SendMessageCommand(
            'user_rate',
            'card',
            ['card' => ['header' => ['title' => 'Test']]],
            'user_id',
            [],
            'corr_rate',
            2 // 还没达到最大重试次数
        );

        $this->mockPerformanceMonitor->expects($this->once())
            ->method('monitorMessageSend')
            ->willThrowException(new RateLimitException('Rate limit exceeded'))
        ;

        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                'Rate limit encountered',
                self::callback(function ($context) {
                    return 2 === $context['retry_count'];
                }))
        ;

        $this->expectException(RecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Rate limited, will retry');

        $this->handler->__invoke($command);
    }

    public function testHandlerHandlesRateLimitMaxRetryExceeded(): void
    {
        $command = new SendMessageCommand(
            'user_rate_max',
            'text',
            'Test',
            'open_id',
            [],
            'corr_rate_max',
            3 // 已达到最大重试次数
        );

        $this->mockPerformanceMonitor->expects($this->once())
            ->method('monitorMessageSend')
            ->willThrowException(new RateLimitException('Rate limit exceeded'))
        ;

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Max retry count exceeded due to rate limiting');

        $this->handler->__invoke($command);
    }

    public function testHandlerHandlesServerError(): void
    {
        $command = new SendMessageCommand(
            'user_server',
            'text',
            'Test',
            'open_id',
            [],
            'corr_server',
            1
        );

        $apiException = new GenericApiException('Server error', 500);

        $this->mockPerformanceMonitor->expects($this->once())
            ->method('monitorMessageSend')
            ->willThrowException($apiException)
        ;

        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Server error sending async message',
                self::callback(function ($context) {
                    return 500 === $context['status_code']
                        && 1 === $context['retry_count'];
                }))
        ;

        $this->expectException(RecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Server error, will retry');

        $this->handler->__invoke($command);
    }

    public function testHandlerHandlesClientError(): void
    {
        $command = new SendMessageCommand(
            'user_client',
            'text',
            'Test',
            'open_id',
            [],
            'corr_client',
            0
        );

        $apiException = new GenericApiException('Bad request', 400);

        $this->mockPerformanceMonitor->expects($this->once())
            ->method('monitorMessageSend')
            ->willThrowException($apiException)
        ;

        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Client error sending async message',
                self::callback(function ($context) {
                    return 400 === $context['status_code'];
                }))
        ;

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Client error, message cannot be sent');

        $this->handler->__invoke($command);
    }

    public function testHandlerHandlesUnexpectedError(): void
    {
        $command = new SendMessageCommand(
            'user_unexpected',
            'text',
            'Test',
            'open_id'
        );

        $this->mockPerformanceMonitor->expects($this->once())
            ->method('monitorMessageSend')
            ->willThrowException(new \RuntimeException('Something went wrong'))
        ;

        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected error sending async message',
                self::callback(function ($context) {
                    return isset($context['error'])
                        && isset($context['trace'])
                        && 'Something went wrong' === $context['error'];
                }))
        ;

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Unexpected error occurred');

        $this->handler->__invoke($command);
    }

    public function testHandlerWithoutPerformanceMonitor(): void
    {
        // 注册 Mock 到容器（不包含性能监控器）
        self::getContainer()->set(MessageService::class, $this->mockMessageService);
        self::getContainer()->set(LoggerInterface::class, $this->mockLogger);
        self::getContainer()->set('Tourze\LarkAppBotBundle\Service\Performance\PerformanceMonitor', null);

        // 从容器获取 Handler（没有性能监控器）
        /** @var SendMessageHandler $handler */
        $handler = self::getService(SendMessageHandler::class);

        $command = new SendMessageCommand(
            'user_no_monitor',
            'text',
            'Hello',
            'open_id'
        );

        $expectedResult = ['message_id' => 'msg_no_monitor'];

        $this->mockMessageService->expects($this->once())
            ->method('send')
            ->with(
                'user_no_monitor',
                'text',
                'Hello',
                'open_id',
                []
            )
            ->willReturn($expectedResult)
        ;

        $handler->__invoke($command);
    }

    public function testHandlerPassesOptionsToMessageService(): void
    {
        $command = new SendMessageCommand(
            'user_options',
            'interactive',
            ['card' => ['content' => 'test']],
            'user_id',
            ['uuid' => 'unique-123', 'priority' => 'high']
        );

        $expectedResult = ['message_id' => 'msg_options'];

        $this->mockMessageService->expects($this->once())
            ->method('send')
            ->with(
                'user_options',
                'interactive',
                ['card' => ['content' => 'test']],
                'user_id',
                ['uuid' => 'unique-123', 'priority' => 'high']
            )
            ->willReturn($expectedResult)
        ;

        $this->mockPerformanceMonitor->expects($this->once())
            ->method('monitorMessageSend')
            ->willReturnCallback(function ($msgType, $callback) {
                $this->assertSame('interactive', $msgType);

                // @phpstan-ignore symplify.noDynamicName (Mock callback必须动态调用)
                return $callback();
            })
        ;

        $this->handler->__invoke($command);
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象用于测试隔离
        $this->mockMessageService = $this->createMock(MessageService::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockPerformanceMonitor = $this->createMock(PerformanceMonitor::class);

        // 注册 Mock 依赖到容器
        self::getContainer()->set(MessageService::class, $this->mockMessageService);
        self::getContainer()->set(LoggerInterface::class, $this->mockLogger);
        self::getContainer()->set('Tourze\LarkAppBotBundle\Service\Performance\PerformanceMonitor', $this->mockPerformanceMonitor);

        // 从容器获取 handler，确保测试能够控制所有依赖
        /** @var SendMessageHandler $handler */
        $handler = self::getService(SendMessageHandler::class);
        $this->handler = $handler;
    }
}
