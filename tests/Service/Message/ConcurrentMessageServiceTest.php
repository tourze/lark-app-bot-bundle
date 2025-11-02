<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Tourze\LarkAppBotBundle\Service\Message\Async\BatchSendMessageCommand;
use Tourze\LarkAppBotBundle\Service\Message\Async\SendMessageCommand;
use Tourze\LarkAppBotBundle\Service\Message\Builder\MessageBuilderInterface;
use Tourze\LarkAppBotBundle\Service\Message\ConcurrentMessageService;
use Tourze\LarkAppBotBundle\Service\Message\MessageService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(ConcurrentMessageService::class)]
#[RunTestsInSeparateProcesses]
final class ConcurrentMessageServiceTest extends AbstractIntegrationTestCase
{
    private ConcurrentMessageService $service;

    private MockObject&MessageService $mockMessageService;

    private MockObject&MessageBusInterface $mockMessageBus;

    private MockObject&LoggerInterface $mockLogger;

    public function testSendWithBuilderSyncMode(): void
    {
        $mockBuilder = $this->createMock(MessageBuilderInterface::class);
        $mockBuilder->expects($this->once())
            ->method('getMsgType')
            ->willReturn('text')
        ;
        $mockBuilder->expects($this->once())
            ->method('build')
            ->willReturn(['text' => 'Hello World'])
        ;

        $expectedResult = ['message_id' => 'msg_123', 'status' => 'success'];

        $this->mockMessageService->expects($this->once())
            ->method('send')
            ->with(
                'user123',
                'text',
                ['text' => 'Hello World'],
                'open_id',
                ['option' => 'value'])
            ->willReturn($expectedResult)
        ;

        $result = $this->service->sendWithBuilder(
            'user123',
            $mockBuilder,
            'open_id',
            ['option' => 'value'],
            ConcurrentMessageService::MODE_SYNC
        );

        $this->assertSame($expectedResult, $result);
    }

    public function testSendSyncMode(): void
    {
        $expectedResult = ['message_id' => 'sync_msg', 'status' => 'sent'];

        $this->mockMessageService->expects($this->once())
            ->method('send')
            ->with('user_sync', 'text', 'Sync message', 'open_id', [])
            ->willReturn($expectedResult)
        ;

        $result = $this->service->send(
            'user_sync',
            'text',
            'Sync message',
            'open_id',
            [],
            ConcurrentMessageService::MODE_SYNC
        );

        $this->assertSame($expectedResult, $result);
    }

    public function testSendAsyncMode(): void
    {
        $this->mockMessageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                self::callback(function ($command) {
                    return $command instanceof SendMessageCommand
                        && 'user_async' === $command->getReceiveId()
                        && 'card' === $command->getMsgType()
                        && $command->getContent() === ['card' => 'content'];
                }),
                [])
            ->willReturnCallback(function ($command) {
                return new Envelope($command);
            })
        ;

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with(
                'Message queued for async send',
                self::callback(function ($context) {
                    return isset($context['correlation_id'])
                        && 'user_async' === $context['receive_id']
                        && 'card' === $context['msg_type']
                        && null === $context['delay'];
                })
            )
        ;

        $correlationId = $this->service->send(
            'user_async',
            'card',
            ['card' => 'content'],
            'user_id',
            [],
            ConcurrentMessageService::MODE_ASYNC
        );

        $this->assertIsString($correlationId);
        $this->assertStringStartsWith('async_', $correlationId);
    }

    public function testSendAsyncWithDelay(): void
    {
        $delaySeconds = 30;

        $this->mockMessageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                self::isInstanceOf(SendMessageCommand::class),
                self::callback(function ($stamps) use ($delaySeconds) {
                    $this->assertIsArray($stamps);
                    $this->assertCount(1, $stamps);
                    $this->assertInstanceOf(DelayStamp::class, $stamps[0]);
                    $this->assertSame($delaySeconds * 1000, $stamps[0]->getDelay());

                    return true;
                })
            )
            ->willReturnCallback(function ($command) {
                return new Envelope($command);
            })
        ;

        $correlationId = $this->service->sendAsync(
            'user_delayed',
            'text',
            'Delayed message',
            'open_id',
            [],
            $delaySeconds
        );

        $this->assertStringStartsWith('async_', $correlationId);
    }

    public function testSendBatchMode(): void
    {
        // 添加第一个消息到批次
        $batchKey1 = $this->service->send(
            'user1',
            'text',
            'Same message',
            'open_id',
            [],
            ConcurrentMessageService::MODE_BATCH
        );

        // 添加第二个消息到同一批次（相同内容）
        $batchKey2 = $this->service->send(
            'user2',
            'text',
            'Same message',
            'open_id',
            [],
            ConcurrentMessageService::MODE_BATCH
        );

        $this->assertSame($batchKey1, $batchKey2);

        // 验证批次统计
        $stats = $this->service->getBatchStats();
        $this->assertIsArray($stats);
        $this->assertCount(1, $stats);
        $this->assertIsString($batchKey1);
        $this->assertArrayHasKey($batchKey1, $stats);
        $this->assertSame(2, $stats[$batchKey1]['recipient_count']);
        $this->assertSame('text', $stats[$batchKey1]['msg_type']);
    }

    public function testBatchFlushOnSizeLimit(): void
    {
        // Mock 期望批量发送
        $this->mockMessageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                self::callback(function ($command) {
                    return $command instanceof BatchSendMessageCommand
                        && 50 === \count($command->getReceiveIds());
                })
            )
            ->willReturnCallback(function ($command) {
                return new Envelope($command);
            })
        ;

        // 创建新的服务实例以测试批量大小限制
        $service = self::getService(ConcurrentMessageService::class);

        // 添加50个消息到批次（达到默认批量大小）
        for ($i = 1; $i <= 50; ++$i) {
            $service->addToBatch(
                "user{$i}",
                'text',
                'Batch message',
                'open_id'
            );
        }
    }

    public function testSendBatchAsync(): void
    {
        $receiveIds = ['user1', 'user2', 'user3', 'user4', 'user5'];

        $this->mockMessageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                self::callback(function ($command) use ($receiveIds) {
                    return $command instanceof BatchSendMessageCommand
                        && $command->getReceiveIds() === $receiveIds
                        && 'interactive' === $command->getMsgType()
                        && $command->getContent() === ['card' => 'batch content'];
                })
            )
            ->willReturnCallback(function ($command) {
                return new Envelope($command);
            })
        ;

        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with(
                'Batch message queued',
                self::callback(function ($context) {
                    return isset($context['correlation_id'])
                        && 5 === $context['recipient_count']
                        && 'interactive' === $context['msg_type'];
                })
            )
        ;

        $correlationId = $this->service->sendBatchAsync(
            $receiveIds,
            'interactive',
            ['card' => 'batch content'],
            'user_id',
            ['priority' => 'high']);

        $this->assertStringStartsWith('batch_', $correlationId);
    }

    public function testFlushAllBatches(): void
    {
        // 添加不同批次的消息
        $this->service->addToBatch('user1', 'text', 'Message 1', 'open_id');
        $this->service->addToBatch('user2', 'text', 'Message 2', 'open_id');
        $this->service->addToBatch('user3', 'card', ['card' => 'content'], 'user_id');

        // 期望发送3个批次命令
        $this->mockMessageBus->expects($this->exactly(3))
            ->method('dispatch')
            ->with(self::isInstanceOf(BatchSendMessageCommand::class))
            ->willReturnCallback(function ($command) {
                return new Envelope($command);
            })
        ;

        $this->service->flushAllBatches();

        // 验证批次已清空
        $stats = $this->service->getBatchStats();
        $this->assertEmpty($stats);
    }

    public function testDefaultModeConfiguration(): void
    {
        // 创建默认为异步模式的服务
        $asyncService = self::getService(ConcurrentMessageService::class);

        $this->mockMessageBus->expects($this->once())
            ->method('dispatch')
            ->with(self::isInstanceOf(SendMessageCommand::class))
            ->willReturnCallback(function ($command) {
                return new Envelope($command);
            })
        ;

        // 不指定模式时应使用默认的异步模式
        $result = $asyncService->send('user_default', 'text', 'Default mode test', 'open_id');

        $this->assertIsString($result);
        $this->assertStringStartsWith('async_', $result);
    }

    public function testBatchKeyGeneration(): void
    {
        // 相同内容应生成相同的批次键
        $key1 = $this->service->addToBatch('user1', 'text', 'Same content', 'open_id');
        $key2 = $this->service->addToBatch('user2', 'text', 'Same content', 'open_id');
        $this->assertSame($key1, $key2);

        // 不同内容应生成不同的批次键
        $key3 = $this->service->addToBatch('user3', 'text', 'Different content', 'open_id');
        $this->assertNotSame($key1, $key3);

        // 不同消息类型应生成不同的批次键
        $key4 = $this->service->addToBatch('user4', 'card', 'Same content', 'open_id');
        $this->assertNotSame($key1, $key4);

        // 不同接收ID类型应生成不同的批次键
        $key5 = $this->service->addToBatch('user5', 'text', 'Same content', 'user_id');
        $this->assertNotSame($key1, $key5);
    }

    public function testAddToBatch(): void
    {
        $receiveId = 'test_user';
        /** @var string $msgType */
        $msgType = 'text';
        $content = ['content' => 'test message'];

        $this->service->addToBatch($receiveId, $msgType, $content);

        // 验证消息已添加到批次中
        $stats = $this->service->getBatchStats();
        $this->assertNotEmpty($stats, '批次中应该有消息');
        $batchKeys = array_keys($stats);
        $this->assertStringContainsString('text_open_id_', $batchKeys[0], '批次键应该包含消息类型和接收者类型信息');
    }

    public function testAddToBatchWithAutoFlush(): void
    {
        $receiveId = 'test_user';
        /** @var string $msgType */
        $msgType = 'text';

        // 添加多条消息达到批次大小限制
        for ($i = 0; $i < 100; ++$i) {
            $this->service->addToBatch($receiveId . $i, $msgType, ['content' => "message {$i}"]);
        }

        // 第101条消息应该触发自动刷新
        /** @var string $finalMsgType */
        $finalMsgType = $msgType;
        $this->service->addToBatch($receiveId . '_final', $finalMsgType, ['content' => 'trigger message']);

        // 验证批次已自动刷新
        $this->expectNotToPerformAssertions();
    }

    public function testSendWithoutPerformanceMonitor(): void
    {
        $service = self::getService(ConcurrentMessageService::class);

        $this->mockMessageService->expects($this->once())
            ->method('send')
            ->willReturn(['message_id' => 'test'])
        ;

        $result = $service->sendSync('user', 'text', 'message', 'open_id');
        $this->assertSame(['message_id' => 'test'], $result);
    }

    protected function prepareMockServices(): void
    {
        $this->mockMessageService = $this->createMock(MessageService::class);
        $this->mockMessageBus = $this->createMock(MessageBusInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
    }

    protected function onSetUp(): void
    {
        // 先创建依赖的 mock，并将被测服务以异步为默认模式注入容器
        $this->mockMessageService = $this->createMock(MessageService::class);
        $this->mockMessageBus = $this->createMock(MessageBusInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);

        /** @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass */
        $instance = new ConcurrentMessageService(
            $this->mockMessageService,
            $this->mockMessageBus,
            $this->mockLogger,
            ConcurrentMessageService::MODE_ASYNC,
        );

        self::getContainer()->set(ConcurrentMessageService::class, $instance);
        $this->service = self::getService(ConcurrentMessageService::class);
    }
}
