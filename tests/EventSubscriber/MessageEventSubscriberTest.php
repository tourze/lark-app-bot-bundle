<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\LarkAppBotBundle\EventSubscriber\MessageEventSubscriber;
use Tourze\LarkAppBotBundle\Service\Message\Handler\MessageHandlerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(MessageEventSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class MessageEventSubscriberTest extends AbstractEventSubscriberTestCase
{
    private MessageEventSubscriber $listener;

    private MessageHandlerRegistry $handlerRegistry;

    public function testOnMessage(): void
    {
        $event = $this->createTestMessageEvent([
            'message_id' => 'msg_123',
            'message_type' => 'text',
            'chat_id' => 'chat_123',
            'sender' => [
                'sender_id' => [
                    'open_id' => 'user_123',
                    'user_id' => 'user_123',
                    'union_id' => 'user_123',
                ],
                'sender_type' => 'user',
                'tenant_key' => 'test-tenant',
            ],
        ]);

        $this->handlerRegistry
            ->expects($this->once())
            ->method('handleMessage')
            ->with($event)
        ;

        // Logger会执行真实的日志记录，我们主要测试业务逻辑

        $this->listener->onMessage($event);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = MessageEventSubscriber::getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertArrayHasKey(MessageEvent::class, $events);
        $this->assertSame(['onMessage', 100], $events[MessageEvent::class]);
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象
        $this->handlerRegistry = $this->createMock(MessageHandlerRegistry::class);

        // 设置依赖到容器中
        self::getContainer()->set(MessageHandlerRegistry::class, $this->handlerRegistry);

        // 从容器中获取服务实例（使用真实的Logger）
        $this->listener = self::getService(MessageEventSubscriber::class);
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
}
