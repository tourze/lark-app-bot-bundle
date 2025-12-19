<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\LarkAppBotBundle\Event\GenericEvent;
use Tourze\LarkAppBotBundle\Event\MessageEvent;
use Tourze\LarkAppBotBundle\EventSubscriber\EventDispatcher;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * 事件调度器测试.
 *
 * @internal
 */
#[CoversClass(EventDispatcher::class)]
#[RunTestsInSeparateProcesses]
final class EventDispatcherTest extends AbstractIntegrationTestCase
{
    private EventDispatcher $dispatcher;

    private LoggerInterface $logger;

    protected function onSetUp(): void
    {
        // 使用真实的Symfony EventDispatcher服务
        $this->dispatcher = self::getService(EventDispatcher::class);
        $this->logger = self::getService(LoggerInterface::class);
    }

    public function testDispatchKnownEvent(): void
    {
        $eventType = 'im.message.receive_v1';
        $eventData = [
            'message_id' => 'test-message-id',
            'content' => '{"text":"Hello"}',
            'chat_id' => 'test-chat-id'];
        $context = ['event_id' => 'test-event-id'];

        // 添加临时监听器来验证事件类型
        $dispatchedEvent = null;
        $listener = function ($event) use (&$dispatchedEvent) {
            $dispatchedEvent = $event;
        };

        $this->dispatcher->addListener('im.message.receive_v1', $listener);

        $this->dispatcher->dispatch($eventType, $eventData, $context);

        $this->assertInstanceOf(MessageEvent::class, $dispatchedEvent);
    }

    public function testDispatchUnknownEvent(): void
    {
        $eventType = 'unknown.event.type';
        $eventData = ['data' => 'test'];
        $context = [];

        // 添加临时监听器来验证事件类型
        $dispatchedEvent = null;
        $listener = function ($event) use (&$dispatchedEvent) {
            $dispatchedEvent = $event;
        };

        $this->dispatcher->addListener('unknown.event.type', $listener);

        $this->dispatcher->dispatch($eventType, $eventData, $context);

        $this->assertInstanceOf(GenericEvent::class, $dispatchedEvent);
    }

    public function testAddListener(): void
    {
        $eventType = 'test.event';
        $listener = function (): void {};
        $priority = 10;

        $this->dispatcher->addListener($eventType, $listener, $priority);
        $listeners = $this->dispatcher->getListeners($eventType);

        $this->assertContains($listener, $listeners);
    }

    public function testRemoveListener(): void
    {
        $eventType = 'test.event';
        $listener1 = function (): void {};
        $listener2 = function (): void {};

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
