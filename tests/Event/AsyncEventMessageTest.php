<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Event\AsyncEventMessage;

/**
 * @internal
 */
#[CoversClass(AsyncEventMessage::class)]
final class AsyncEventMessageTest extends TestCase
{
    public function testAsyncEventMessageCreation(): void
    {
        $eventType = 'test_event';
        $eventData = [
            'field1' => 'value1',
            'field2' => 'value2'];
        $context = [
            'event_id' => 'event_123',
            'tenant_key' => 'tenant_123'];

        $message = new AsyncEventMessage($eventType, $eventData, $context);

        $this->assertSame($eventType, $message->getEventType());
        $this->assertSame($eventData, $message->getEventData());
        $this->assertSame($context, $message->getContext());
    }

    public function testAsyncEventMessageCreationWithoutContext(): void
    {
        $eventType = 'test_event';
        $eventData = ['field1' => 'value1'];

        $message = new AsyncEventMessage($eventType, $eventData);

        $this->assertSame($eventType, $message->getEventType());
        $this->assertSame($eventData, $message->getEventData());
        $this->assertSame([], $message->getContext());
    }

    public function testGetEventType(): void
    {
        $message = new AsyncEventMessage('custom_event', []);

        $this->assertSame('custom_event', $message->getEventType());
    }

    public function testGetEventData(): void
    {
        $eventData = [
            'user_id' => 'user_123',
            'action' => 'create',
            'data' => ['key' => 'value']];
        $message = new AsyncEventMessage('test', $eventData);

        $this->assertSame($eventData, $message->getEventData());
    }

    public function testGetEventDataWithEmptyData(): void
    {
        $message = new AsyncEventMessage('test', []);

        $this->assertSame([], $message->getEventData());
    }

    public function testGetContext(): void
    {
        $context = [
            'event_id' => 'event_123',
            'tenant_key' => 'tenant_123',
            'app_id' => 'app_123'];
        $message = new AsyncEventMessage('test', [], $context);

        $this->assertSame($context, $message->getContext());
    }

    public function testGetContextWithEmptyContext(): void
    {
        $message = new AsyncEventMessage('test', []);

        $this->assertSame([], $message->getContext());
    }
}
