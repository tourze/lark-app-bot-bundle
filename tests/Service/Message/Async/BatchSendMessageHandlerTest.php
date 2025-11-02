<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Async;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\LarkAppBotBundle\Service\Message\Async\BatchSendMessageCommand;
use Tourze\LarkAppBotBundle\Service\Message\Async\BatchSendMessageHandler;
use Tourze\LarkAppBotBundle\Service\Message\Async\SendMessageCommand;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;
use Tourze\LarkAppBotBundle\Service\Performance\PerformanceMonitor;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(BatchSendMessageHandler::class)]
#[RunTestsInSeparateProcesses]
final class BatchSendMessageHandlerTest extends AbstractIntegrationTestCase
{
    private BatchSendMessageHandler $handler;

    private MockObject&MessageService $mockMessageService;

    private MockObject&MessageBusInterface $mockMessageBus;

    private MockObject&LoggerInterface $mockLogger;

    private MockObject&PerformanceMonitor $mockPerformanceMonitor;

    public function testHandlerDropsStaleMessage(): void
    {
        // 创建一个11分钟前的消息
        $oldTimestamp = microtime(true) - 660;
        $command = new BatchSendMessageCommand(
            ['user1', 'user2'],
            'text',
            'Hello',
            'open_id',
            [],
            'corr_123',
            0,
            $oldTimestamp
        );

        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                'Dropping stale batch message',
                self::callback(function ($context) {
                    return isset($context['correlation_id'])
                        && isset($context['age'], $context['receive_ids_count'])
                        && 2 === $context['receive_ids_count'];
                })
            )
        ;

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('Batch message too old to process');

        $this->handler->__invoke($command);
    }

    public function testHandlerProcessesSmallBatchSuccessfully(): void
    {
        $receiveIds = ['user1', 'user2', 'user3'];
        $command = new BatchSendMessageCommand(
            $receiveIds,
            'text',
            ['text' => 'Hello World'],
            'open_id',
            [],
            'corr_123'
        );

        $expectedResult = [
            'message_id' => 'msg_123',
            'invalid_receive_ids' => [],
        ];

        $this->mockMessageService->expects($this->once())
            ->method('sendBatch')
            ->with($receiveIds, 'text', ['text' => 'Hello World'], 'open_id')
            ->willReturn($expectedResult)
        ;

        $this->mockPerformanceMonitor->expects($this->once())
            ->method('recordCustomMetric')
            ->with(
                'batch_send_duration',
                self::anything(),
                [
                    'batch_size' => 3,
                    'msg_type' => 'text',
                ]
            )
        ;

        $this->mockLogger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context): void {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $this->assertSame('Processing batch message', $message);
                    $this->assertSame(3, $context['total_recipients']);
                    $this->assertSame('text', $context['msg_type']);
                } elseif (2 === $callCount) {
                    $this->assertSame('Batch message processed', $message);
                    $this->assertSame('msg_123', $context['message_id']);
                    $this->assertSame(3, $context['success_count']);
                    $this->assertSame(0, $context['failed_count']);
                }
            })
        ;

        $this->handler->__invoke($command);
    }

    public function testHandlerSplitsLargeBatch(): void
    {
        // 创建包含120个接收者的命令（超过批量限制50）
        $receiveIds = array_map(fn ($i) => "user{$i}", range(1, 120));
        $command = new BatchSendMessageCommand(
            $receiveIds,
            'text',
            'Hello',
            'open_id',
            [],
            'corr_123'
        );

        $this->mockLogger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context): void {
                static $callCount = 0;
                ++$callCount;

                if (1 === $callCount) {
                    $this->assertSame('Processing batch message', $message);
                    $this->assertSame(120, $context['total_recipients']);
                } elseif (2 === $callCount) {
                    $this->assertSame('Splitting large batch into smaller batches', $message);
                    $this->assertSame(120, $context['total_recipients']);
                    $this->assertSame(50, $context['batch_size']);
                }
            })
        ;

        // 期望分派3个子命令（120/50 = 3批）
        $this->mockMessageBus->expects($this->exactly(3))
            ->method('dispatch')
            ->with(self::isInstanceOf(BatchSendMessageCommand::class))
            ->willReturnCallback(function ($command) {
                return new Envelope($command);
            })
        ;

        $this->handler->__invoke($command);
    }

    public function testHandlerHandlesPartialFailure(): void
    {
        $receiveIds = ['user1', 'user2', 'user3', 'user4'];
        $command = new BatchSendMessageCommand(
            $receiveIds,
            'card',
            ['card' => ['header' => ['title' => 'Test']]],
            'user_id',
            ['priority' => 'high'],
            'corr_456'
        );

        $expectedResult = [
            'message_id' => 'msg_456',
            'invalid_receive_ids' => ['user2', 'user4'],
        ];

        $this->mockMessageService->expects($this->once())
            ->method('sendBatch')
            ->willReturn($expectedResult)
        ;

        // 期望记录 processing, warning 和 processed 日志
        $matcher = $this->exactly(3);
        $this->mockLogger->expects($matcher)
            ->method(self::logicalOr('info', 'warning'))
            ->willReturnCallback(function ($message, $context) use ($matcher): void {
                if (1 === $matcher->numberOfInvocations()) {
                    $this->assertSame('Processing batch message', $message);
                } elseif (2 === $matcher->numberOfInvocations()) {
                    $this->assertSame('Some recipients failed in batch send', $message);
                    $this->assertSame(2, $context['invalid_count']);
                    $this->assertSame(['user2', 'user4'], $context['invalid_ids']);
                } elseif (3 === $matcher->numberOfInvocations()) {
                    $this->assertSame('Batch message processed', $message);
                    $this->assertSame(2, $context['success_count']);
                    $this->assertSame(2, $context['failed_count']);
                }
            })
        ;

        // 期望为2个失败的接收者分派重试命令
        $this->mockMessageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->with(self::isInstanceOf(SendMessageCommand::class))
            ->willReturnCallback(function ($command) {
                return new Envelope($command);
            })
        ;

        $this->handler->__invoke($command);
    }

    public function testHandlerWithoutPerformanceMonitor(): void
    {
        // 直接构造 Handler（没有性能监控器），避免替换已初始化的框架 logger 服务
        /** @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass */
        $handler = new BatchSendMessageHandler(
            $this->mockMessageService,
            $this->mockMessageBus,
            $this->mockLogger,
            null
        );

        $command = new BatchSendMessageCommand(
            ['user1'],
            'text',
            'Hello',
            'open_id'
        );

        $this->mockMessageService->expects($this->once())
            ->method('sendBatch')
            ->willReturn(['message_id' => 'msg_123', 'invalid_receive_ids' => []])
        ;

        $handler->__invoke($command);
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象用于测试隔离（直接构造被测实例，避免容器中已初始化服务无法替换的问题）
        $this->mockMessageService = $this->createMock(MessageService::class);
        $this->mockMessageBus = $this->createMock(MessageBusInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockPerformanceMonitor = $this->createMock(PerformanceMonitor::class);

        // 直接构造被测实例，确保测试完全控制依赖
        /** @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass */
        $this->handler = new BatchSendMessageHandler(
            $this->mockMessageService,
            $this->mockMessageBus,
            $this->mockLogger,
            $this->mockPerformanceMonitor
        );
    }
}

