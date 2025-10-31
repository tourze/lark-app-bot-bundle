<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\Message\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\LarkAppBotBundle\Service\Message\Handler\MessageHandlerInterface;
use Tourze\LarkAppBotBundle\Service\Message\Handler\MessageHandlerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 消息处理器注册表测试.
 *
 * @internal
 */
#[CoversClass(MessageHandlerRegistry::class)]
#[RunTestsInSeparateProcesses]
final class MessageHandlerRegistryTest extends AbstractIntegrationTestCase
{
    private MessageHandlerRegistry $registry;

    private LoggerInterface $logger;

    public function testAddHandler(): void
    {
        $handler = $this->createMock(MessageHandlerInterface::class);
        $handler->method('getName')->willReturn('test_handler');
        $handler->method('getPriority')->willReturn(100);

        $this->registry->addHandler($handler);

        $this->assertIsArray($this);
        $this->assertCount(1, $this->registry->getHandlers());
        $this->assertContains($handler, $this->registry->getHandlers());
    }

    public function testHandlersAreSortedByPriority(): void
    {
        $handler1 = $this->createMock(MessageHandlerInterface::class);
        $handler1->method('getPriority')->willReturn(50);

        $handler2 = $this->createMock(MessageHandlerInterface::class);
        $handler2->method('getPriority')->willReturn(100);

        $handler3 = $this->createMock(MessageHandlerInterface::class);
        $handler3->method('getPriority')->willReturn(75);

        $this->registry->addHandler($handler1);
        $this->registry->addHandler($handler2);
        $this->registry->addHandler($handler3);

        $handlers = $this->registry->getHandlers();

        $this->assertSame($handler2, $handlers[0]); // Priority 100
        $this->assertSame($handler3, $handlers[1]); // Priority 75
        $this->assertSame($handler1, $handlers[2]); // Priority 50
    }

    public function testHandleMessage(): void
    {
        $event = $this->createTestMessageEvent([
            'message_id' => 'test-message-id',
            'message_type' => 'text',
        ]);

        $handler1 = $this->createMock(MessageHandlerInterface::class);
        $handler1->method('getName')->willReturn('handler1');
        $handler1->method('getPriority')->willReturn(100);
        $handler1->method('supports')->with($event)->willReturn(false);

        $handler2 = $this->createMock(MessageHandlerInterface::class);
        $handler2->method('getName')->willReturn('handler2');
        $handler2->method('getPriority')->willReturn(50);
        $handler2->method('supports')->with($event)->willReturn(true);
        $handler2->expects($this->once())->method('handle')->with($event);

        $this->registry->addHandler($handler1);
        $this->registry->addHandler($handler2);

        $this->registry->handleMessage($event);
    }

    public function testHandleMessageStopsPropagation(): void
    {
        $event = $this->createStoppedMessageEvent();

        $handler1 = $this->createMock(MessageHandlerInterface::class);
        $handler1->method('getName')->willReturn('handler1');
        $handler1->method('getPriority')->willReturn(100);
        $handler1->method('supports')->willReturn(true);
        $handler1->expects($this->once())->method('handle');

        $handler2 = $this->createMock(MessageHandlerInterface::class);
        $handler2->method('getName')->willReturn('handler2');
        $handler2->method('getPriority')->willReturn(50);
        $handler2->method('supports')->willReturn(true);
        $handler2->expects($this->never())->method('handle');

        $this->registry->addHandler($handler1);
        $this->registry->addHandler($handler2);

        $this->registry->handleMessage($event);
    }

    public function testRemoveHandler(): void
    {
        $handler1 = $this->createMock(MessageHandlerInterface::class);
        $handler2 = $this->createMock(MessageHandlerInterface::class);

        $this->registry->addHandler($handler1);
        $this->registry->addHandler($handler2);

        $this->assertIsArray($this);
        $this->assertCount(2, $this->registry->getHandlers());

        $this->registry->removeHandler($handler1);

        $this->assertCount(1, $this->registry->getHandlers());
        $this->assertNotContains($handler1, $this->registry->getHandlers());
        $this->assertContains($handler2, $this->registry->getHandlers());
    }

    public function testClear(): void
    {
        $handler = $this->createMock(MessageHandlerInterface::class);
        $this->registry->addHandler($handler);

        $this->assertIsArray($this);
        $this->assertCount(1, $this->registry->getHandlers());

        $this->registry->clear();

        $this->assertCount(0, $this->registry->getHandlers());
    }

    public function testCount(): void
    {
        $handler = $this->createMock(MessageHandlerInterface::class);
        $this->registry->addHandler($handler);
        $this->assertSame(1, $this->registry->count());
    }

    public function testHandlerExceptionDoesNotStopOthers(): void
    {
        $event = $this->createTestMessageEvent([
            'message_id' => 'test-message-id',
        ]);

        $handler1 = $this->createMock(MessageHandlerInterface::class);
        $handler1->method('getName')->willReturn('handler1');
        $handler1->method('getPriority')->willReturn(100);
        $handler1->method('supports')->willReturn(true);
        $handler1->method('handle')->willThrowException(new \RuntimeException('Handler error'));

        $handler2 = $this->createMock(MessageHandlerInterface::class);
        $handler2->method('getName')->willReturn('handler2');
        $handler2->method('getPriority')->willReturn(50);
        $handler2->method('supports')->willReturn(true);
        $handler2->expects($this->once())->method('handle');

        $this->registry->addHandler($handler1);
        $this->registry->addHandler($handler2);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('消息处理器执行失败', self::anything())
        ;

        $this->registry->handleMessage($event);
    }

    protected function onSetUp(): void
    {
        // 从容器获取服务实例，符合集成测试的架构模式
        $this->registry = self::getService(MessageHandlerRegistry::class);

        // 创建 mock 对象用于测试隔离
        $this->prepareMockServices();
    }

    protected function prepareMockServices(): void
    {
        // 创建 mock 对象
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * 创建测试用的 MessageEvent 实例.
     *
     * @param array<string, mixed> $data
     */
    private function createTestMessageEvent(array $data = []): MessageEvent
    {
        $defaultData = [
            'message_id' => 'test-message-id',
            'message_type' => 'text',
            'chat_id' => 'test-chat-id',
            'chat_type' => 'p2p',
            'content' => '',
            'sender' => [
                'sender_id' => [
                    'open_id' => 'test-open-id',
                    'user_id' => 'test-user-id',
                    'union_id' => 'test-union-id',
                ],
                'sender_type' => 'user',
                'tenant_key' => 'test-tenant',
            ],
        ];

        return new MessageEvent(
            'im.message.receive_v1',
            array_merge($defaultData, $data),
            [
                'event_id' => 'test-event-id',
                'tenant_key' => 'test-tenant',
                'app_id' => 'test-app-id',
            ]
        );
    }

    /**
     * 创建一个已停止传播的 MessageEvent 实例.
     */
    private function createStoppedMessageEvent(): MessageEvent
    {
        $event = $this->createTestMessageEvent(['message_id' => 'test-message-id']);
        $event->stopPropagation();

        return $event;
    }
}
