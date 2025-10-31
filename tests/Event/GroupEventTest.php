<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Event\GroupEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(GroupEvent::class)]
final class GroupEventTest extends AbstractEventTestCase
{
    public function testGroupEventCreation(): void
    {
        $eventData = [
            'chat_id' => 'chat_123',
            'operator_id' => 'user_123',
            'external_label' => 'external_label_123',
            'i18n_names' => [
                'en' => 'English Name',
                'zh' => '中文名称']];

        $context = [
            'event_id' => 'event_123',
            'tenant_key' => 'tenant_123'];

        $event = new GroupEvent('im.chat.updated_v1', $eventData, $context);

        $this->assertSame('im.chat.updated_v1', $event->getEventType());
        $this->assertSame($eventData, $event->getData());
        $this->assertSame($context, $event->getContext());
    }

    public function testGetChatId(): void
    {
        $eventData = ['chat_id' => 'chat_123'];
        $event = new GroupEvent('test', $eventData);

        $this->assertSame('chat_123', $event->getChatId());
    }

    public function testGetChatIdWithMissingData(): void
    {
        $event = new GroupEvent('test', []);

        $this->assertSame('', $event->getChatId());
    }

    public function testGetOperatorId(): void
    {
        $eventData = ['operator_id' => 'user_123'];
        $event = new GroupEvent('test', $eventData);

        $this->assertSame('user_123', $event->getOperatorId());
    }

    public function testGetOperatorIdWithMissingData(): void
    {
        $event = new GroupEvent('test', []);

        $this->assertSame('', $event->getOperatorId());
    }

    public function testGetExternalLabel(): void
    {
        $eventData = ['external_label' => 'external_label_123'];
        $event = new GroupEvent('test', $eventData);

        $this->assertSame('external_label_123', $event->getExternalLabel());
    }

    public function testGetExternalLabelWithMissingData(): void
    {
        $event = new GroupEvent('test', []);

        $this->assertSame('', $event->getExternalLabel());
    }

    public function testGetI18nNames(): void
    {
        $i18nNames = [
            'en' => 'English Name',
            'zh' => '中文名称'];
        $eventData = ['i18n_names' => $i18nNames];
        $event = new GroupEvent('test', $eventData);

        $this->assertSame($i18nNames, $event->getI18nNames());
    }

    public function testGetI18nNamesWithMissingData(): void
    {
        $event = new GroupEvent('test', []);

        $this->assertSame([], $event->getI18nNames());
    }

    public function testIsDisbanded(): void
    {
        $disbandedEvent = new GroupEvent('im.chat.disbanded_v1', []);
        $this->assertTrue($disbandedEvent->isDisbanded());

        $otherEvent = new GroupEvent('im.chat.updated_v1', []);
        $this->assertFalse($otherEvent->isDisbanded());
    }

    public function testIsUpdated(): void
    {
        $updatedEvent = new GroupEvent('im.chat.updated_v1', []);
        $this->assertTrue($updatedEvent->isUpdated());

        $otherEvent = new GroupEvent('im.chat.disbanded_v1', []);
        $this->assertFalse($otherEvent->isUpdated());
    }
}
