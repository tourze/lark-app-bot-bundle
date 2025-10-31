<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Event\GenericEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(GenericEvent::class)]
final class GenericEventTest extends AbstractEventTestCase
{
    public function testGenericEventCreation(): void
    {
        $eventData = [
            'field1' => 'value1',
            'field2' => 'value2',
            'nested' => [
                'field' => 'nested_value']];

        $context = [
            'event_id' => 'event_123',
            'tenant_key' => 'tenant_123'];

        $event = new GenericEvent('generic_event', $eventData, $context);

        $this->assertSame('generic_event', $event->getEventType());
        $this->assertSame($eventData, $event->getData());
        $this->assertSame($context, $event->getContext());
    }

    public function testGet(): void
    {
        $eventData = [
            'field1' => 'value1',
            'field2' => 123,
            'field3' => ['nested' => 'value']];

        $event = new GenericEvent('test', $eventData);

        $this->assertSame('value1', $event->get('field1'));
        $this->assertSame(123, $event->get('field2'));
        $this->assertSame(['nested' => 'value'], $event->get('field3'));
    }

    public function testGetWithDefault(): void
    {
        $event = new GenericEvent('test', []);

        $this->assertNull($event->get('nonexistent'));
        $this->assertSame('default', $event->get('nonexistent', 'default'));
        $this->assertSame(0, $event->get('nonexistent', 0));
        $this->assertSame([], $event->get('nonexistent', []));
    }

    public function testGetWithExistingKey(): void
    {
        $eventData = ['field1' => 'value1'];
        $event = new GenericEvent('test', $eventData);

        // Should return actual value, not default
        $this->assertSame('value1', $event->get('field1', 'default'));
    }

    public function testGetWithNullValue(): void
    {
        $eventData = ['field1' => null];
        $event = new GenericEvent('test', $eventData);

        // Should return default value when field is null due to ?? operator
        $this->assertSame('default', $event->get('field1', 'default'));
    }

    public function testHas(): void
    {
        $eventData = [
            'field1' => 'value1',
            'field2' => null,
            'field3' => '',
            'field4' => 0,
            'field5' => false];

        $event = new GenericEvent('test', $eventData);

        $this->assertTrue($event->has('field1'));
        $this->assertFalse($event->has('field2')); // isset() returns false for null
        $this->assertTrue($event->has('field3'));
        $this->assertTrue($event->has('field4'));
        $this->assertTrue($event->has('field5'));
        $this->assertFalse($event->has('nonexistent'));
    }

    public function testHasWithEmptyData(): void
    {
        $event = new GenericEvent('test', []);

        $this->assertFalse($event->has('field1'));
        $this->assertFalse($event->has('nonexistent'));
    }
}
