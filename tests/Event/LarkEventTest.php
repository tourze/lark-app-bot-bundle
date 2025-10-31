<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Event\LarkEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(LarkEvent::class)]
final class LarkEventTest extends AbstractEventTestCase
{
    public function testLarkEventCreation(): void
    {
        $eventData = [
            'create_time' => '1609073151',
            'data' => 'test_data'];

        $context = [
            'event_id' => 'event_123',
            'tenant_key' => 'tenant_123',
            'app_id' => 'app_123',
            'is_retry' => true];

        $event = new TestLarkEvent('test_event', $eventData, $context);

        $this->assertSame('test_event', $event->getEventType());
        $this->assertSame($eventData, $event->getData());
        $this->assertSame($context, $event->getContext());
    }

    public function testGetEventId(): void
    {
        $context = ['event_id' => 'event_123'];
        $event = new TestLarkEvent('test', [], $context);

        $this->assertSame('event_123', $event->getEventId());
    }

    public function testGetEventIdWithMissingData(): void
    {
        $event = new TestLarkEvent('test', []);

        $this->assertSame('', $event->getEventId());
    }

    public function testGetTenantKey(): void
    {
        $context = ['tenant_key' => 'tenant_123'];
        $event = new TestLarkEvent('test', [], $context);

        $this->assertSame('tenant_123', $event->getTenantKey());
    }

    public function testGetTenantKeyWithMissingData(): void
    {
        $event = new TestLarkEvent('test', []);

        $this->assertSame('', $event->getTenantKey());
    }

    public function testGetAppId(): void
    {
        $context = ['app_id' => 'app_123'];
        $event = new TestLarkEvent('test', [], $context);

        $this->assertSame('app_123', $event->getAppId());
    }

    public function testGetAppIdWithMissingData(): void
    {
        $event = new TestLarkEvent('test', []);

        $this->assertSame('', $event->getAppId());
    }

    public function testGetTimestamp(): void
    {
        $eventData = ['create_time' => '1609073151'];
        $event = new TestLarkEvent('test', $eventData);

        $this->assertSame(1609073151, $event->getTimestamp());
    }

    public function testGetTimestampWithMissingData(): void
    {
        $event = new TestLarkEvent('test', []);
        $timestamp = $event->getTimestamp();

        // Should return current timestamp
        $this->assertGreaterThan(0, $timestamp);
        $this->assertLessThanOrEqual(time(), $timestamp);
    }

    public function testIsRetry(): void
    {
        $retryEvent = new TestLarkEvent('test', [], ['is_retry' => true]);
        $this->assertTrue($retryEvent->isRetry());

        $nonRetryEvent = new TestLarkEvent('test', [], ['is_retry' => false]);
        $this->assertFalse($nonRetryEvent->isRetry());

        $defaultEvent = new TestLarkEvent('test', []);
        $this->assertFalse($defaultEvent->isRetry());
    }
}
