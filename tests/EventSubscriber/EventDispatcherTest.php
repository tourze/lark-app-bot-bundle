<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\LarkAppBotBundle\Event\GenericEvent;
use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\LarkAppBotBundle\EventSubscriber\EventDispatcher;

/**
 * 事件调度器测试.
 *
 * @internal
 */
#[CoversClass(EventDispatcher::class)]
final class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    private EventDispatcherInterface $symfonyEventDispatcher;

    protected function setUp(): void
    {
        $this->symfonyEventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $mockLogger = $this->createMock(LoggerInterface::class);

        // 直接创建EventDispatcher实例
        $this->dispatcher = new EventDispatcher($this->symfonyEventDispatcher, $mockLogger);
    }

    public function testDispatchKnownEvent(): void
    {
        $eventType = 'im.message.receive_v1';
        $eventData = [
            'message_id' => 'test-message-id',
            'content' => '{"text":"Hello"}',
            'chat_id' => 'test-chat-id'];
        $context = ['event_id' => 'test-event-id'];

        $this->symfonyEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                $this->assertInstanceOf(MessageEvent::class, $event);

                return $event;
            })
        ;

        $this->dispatcher->dispatch($eventType, $eventData, $context);
    }

    public function testDispatchUnknownEvent(): void
    {
        $eventType = 'unknown.event.type';
        $eventData = ['data' => 'test'];
        $context = [];

        $this->symfonyEventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                $this->assertInstanceOf(GenericEvent::class, $event);

                return $event;
            })
        ;

        $this->dispatcher->dispatch($eventType, $eventData, $context);
    }

    public function testAddListener(): void
    {
        $eventType = 'test.event';
        $listener = function (): void {};
        $priority = 10;

        $this->symfonyEventDispatcher->expects($this->once())
            ->method('addListener')
            ->with($eventType, $listener, $priority)
        ;

        $this->dispatcher->addListener($eventType, $listener, $priority);
        $listeners = $this->dispatcher->getListeners($eventType);

        $this->assertContains($listener, $listeners);
    }

    public function testRemoveListener(): void
    {
        $eventType = 'test.event';
        $listener1 = function (): void {};
        $listener2 = function (): void {};

        $this->symfonyEventDispatcher->expects($this->exactly(2))
            ->method('addListener')
        ;
        $this->symfonyEventDispatcher->expects($this->once())
            ->method('removeListener')
            ->with($eventType, $listener1)
        ;

        $this->dispatcher->addListener($eventType, $listener1);
        $this->dispatcher->addListener($eventType, $listener2);
        $this->dispatcher->removeListener($eventType, $listener1);

        $listeners = $this->dispatcher->getListeners($eventType);

        $this->assertNotContains($listener1, $listeners);
        $this->assertContains($listener2, $listeners);
    }

    public function testHasListeners(): void
    {
        $eventType = 'test.event';
        $listener = function (): void {};

        $this->assertFalse($this->dispatcher->hasListeners($eventType));

        $this->symfonyEventDispatcher->expects($this->once())
            ->method('addListener')
        ;

        $this->dispatcher->addListener($eventType, $listener);

        $this->assertTrue($this->dispatcher->hasListeners($eventType));
    }

    public function testGetSupportedEventTypes(): void
    {
        $supportedTypes = $this->dispatcher->getSupportedEventTypes();

        $this->assertContains('im.message.receive_v1', $supportedTypes);
        $this->assertContains('im.chat.member.bot.added_v1', $supportedTypes);
        $this->assertContains('contact.user.created_v3', $supportedTypes);
    }

    public function testRegisterEventMapping(): void
    {
        $eventType = 'custom.event.type';
        $eventClass = GenericEvent::class;

        $this->dispatcher->registerEventMapping($eventType, $eventClass);

        $supportedTypes = $this->dispatcher->getSupportedEventTypes();
        $this->assertContains($eventType, $supportedTypes);
    }
}
