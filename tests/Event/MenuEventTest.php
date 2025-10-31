<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Event\MenuEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * 菜单事件测试.
 *
 * @internal
 */
#[CoversClass(MenuEvent::class)]
final class MenuEventTest extends AbstractEventTestCase
{
    public function testMenuEventCreation(): void
    {
        $eventData = [
            'operator' => [
                'operator_id' => [
                    'union_id' => 'on_123',
                    'user_id' => 'user_123',
                    'open_id' => 'ou_123'],
                'operator_type' => 'user'],
            'event_key' => 'query_sales',
            'timestamp' => '1609073151'];

        $context = [
            'event_id' => 'event_123',
            'tenant_key' => 'tenant_123',
            'app_id' => 'app_123'];

        $event = new MenuEvent(MenuEvent::EVENT_TYPE, $eventData, $context);

        $this->assertSame(MenuEvent::EVENT_TYPE, $event->getEventType());
        $this->assertSame($eventData, $event->getData());
        $this->assertSame($context, $event->getContext());
    }

    public function testGetOperator(): void
    {
        $eventData = [
            'operator' => [
                'operator_id' => [
                    'union_id' => 'on_123',
                    'user_id' => 'user_123',
                    'open_id' => 'ou_123'],
                'operator_type' => 'user'],
            'event_key' => 'test_menu',
            'timestamp' => '1609073151'];

        $event = new MenuEvent(MenuEvent::EVENT_TYPE, $eventData);
        $operator = $event->getOperator();

        $this->assertSame('on_123', $operator['operator_id']['union_id'] ?? null);
        $this->assertSame('user_123', $operator['operator_id']['user_id'] ?? null);
        $this->assertSame('ou_123', $operator['operator_id']['open_id'] ?? null);
        $this->assertSame('user', $operator['operator_type']);
    }

    public function testGetOperatorIds(): void
    {
        $eventData = [
            'operator' => [
                'operator_id' => [
                    'union_id' => 'on_123',
                    'user_id' => 'user_123',
                    'open_id' => 'ou_123'],
                'operator_type' => 'user'],
            'event_key' => 'test_menu'];

        $event = new MenuEvent(MenuEvent::EVENT_TYPE, $eventData);

        $this->assertSame('ou_123', $event->getOperatorOpenId());
        $this->assertSame('user_123', $event->getOperatorUserId());
        $this->assertSame('on_123', $event->getOperatorUnionId());
    }

    public function testGetOperatorIdsWithMissingData(): void
    {
        $eventData = [
            'operator' => [
                'operator_id' => [],
                'operator_type' => 'bot'],
            'event_key' => 'test_menu'];

        $event = new MenuEvent(MenuEvent::EVENT_TYPE, $eventData);

        $this->assertSame('', $event->getOperatorOpenId());
        $this->assertSame('', $event->getOperatorUserId());
        $this->assertSame('', $event->getOperatorUnionId());
    }

    public function testGetEventKey(): void
    {
        $eventData = [
            'event_key' => 'query_sales',
            'timestamp' => '1609073151'];

        $event = new MenuEvent(MenuEvent::EVENT_TYPE, $eventData);
        $this->assertSame('query_sales', $event->getEventKey());
    }

    public function testGetEventKeyWithMissingData(): void
    {
        $event = new MenuEvent(MenuEvent::EVENT_TYPE, []);
        $this->assertSame('', $event->getEventKey());
    }

    public function testGetEventTimestamp(): void
    {
        $eventData = [
            'timestamp' => '1609073151'];

        $event = new MenuEvent(MenuEvent::EVENT_TYPE, $eventData);
        $this->assertSame(1609073151, $event->getEventTimestamp());
    }

    public function testGetEventTimestampDefault(): void
    {
        $event = new MenuEvent(MenuEvent::EVENT_TYPE, []);
        $timestamp = $event->getEventTimestamp();

        // 应该返回当前时间戳
        $this->assertGreaterThan(0, $timestamp);
    }

    public function testIsUserOperation(): void
    {
        $userEvent = new MenuEvent(MenuEvent::EVENT_TYPE, [
            'operator' => [
                'operator_type' => 'user']]);
        $this->assertTrue($userEvent->isUserOperation());

        $botEvent = new MenuEvent(MenuEvent::EVENT_TYPE, [
            'operator' => [
                'operator_type' => 'bot']]);
        $this->assertFalse($botEvent->isUserOperation());

        // 默认情况
        $defaultEvent = new MenuEvent(MenuEvent::EVENT_TYPE, []);
        $this->assertTrue($defaultEvent->isUserOperation());
    }
}
