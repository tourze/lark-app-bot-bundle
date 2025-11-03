<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Event\MessageReactionEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(MessageReactionEvent::class)]
final class MessageReactionEventTest extends AbstractEventTestCase
{
    public function testMessageReactionEventCreation(): void
    {
        $eventData = [
            'message_id' => 'msg_123',
            'reaction_type' => ['emoji_type' => 'SMILE'],
            'operator_id' => 'user_123',
            'action_time' => '1609073151'];

        $context = [
            'event_id' => 'event_123',
            'tenant_key' => 'tenant_123'];

        $event = new MessageReactionEvent('im.message.reaction.created_v1', $eventData, $context);

        $this->assertSame('im.message.reaction.created_v1', $event->getEventType());
        $this->assertSame($eventData, $event->getData());
        $this->assertSame($context, $event->getContext());
    }

    public function testGetMessageId(): void
    {
        $eventData = ['message_id' => 'msg_123'];
        $event = new MessageReactionEvent('test', $eventData);

        $this->assertSame('msg_123', $event->getMessageId());
    }

    public function testGetMessageIdWithMissingData(): void
    {
        $event = new MessageReactionEvent('test', []);

        $this->assertSame('', $event->getMessageId());
    }

    public function testGetReactionType(): void
    {
        $reactionType = ['emoji_type' => 'SMILE'];
        $eventData = ['reaction_type' => $reactionType];
        $event = new MessageReactionEvent('test', $eventData);

        $this->assertSame($reactionType, $event->getReactionType());
    }

    public function testGetReactionTypeWithMissingData(): void
    {
        $event = new MessageReactionEvent('test', []);

        $this->assertSame(['emoji_type' => ''], $event->getReactionType());
    }

    public function testGetOperatorId(): void
    {
        $eventData = ['operator_id' => 'user_123'];
        $event = new MessageReactionEvent('test', $eventData);

        $this->assertSame('user_123', $event->getOperatorId());
    }

    public function testGetOperatorIdWithMissingData(): void
    {
        $event = new MessageReactionEvent('test', []);

        $this->assertSame('', $event->getOperatorId());
    }

    public function testGetActionTime(): void
    {
        $eventData = ['action_time' => '1609073151'];
        $event = new MessageReactionEvent('test', $eventData);

        $this->assertSame('1609073151', $event->getActionTime());
    }

    public function testGetActionTimeWithMissingData(): void
    {
        $event = new MessageReactionEvent('test', []);

        $this->assertSame('', $event->getActionTime());
    }

    public function testIsCreated(): void
    {
        $createdEvent = new MessageReactionEvent('im.message.reaction.created_v1', []);
        $this->assertTrue($createdEvent->isCreated());

        $otherEvent = new MessageReactionEvent('im.message.reaction.deleted_v1', []);
        $this->assertFalse($otherEvent->isCreated());
    }

    public function testIsDeleted(): void
    {
        $deletedEvent = new MessageReactionEvent('im.message.reaction.deleted_v1', []);
        $this->assertTrue($deletedEvent->isDeleted());

        $otherEvent = new MessageReactionEvent('im.message.reaction.created_v1', []);
        $this->assertFalse($otherEvent->isDeleted());
    }

    public function testGetEmojiType(): void
    {
        $eventData = ['reaction_type' => ['emoji_type' => 'SMILE']];
        $event = new MessageReactionEvent('test', $eventData);

        $this->assertSame('SMILE', $event->getEmojiType());
    }

    public function testGetEmojiTypeWithMissingData(): void
    {
        $event = new MessageReactionEvent('test', []);

        $this->assertSame('', $event->getEmojiType());
    }

    public function testGetEmojiTypeWithMissingEmojiType(): void
    {
        $eventData = ['reaction_type' => ['other' => 'value']];
        $event = new MessageReactionEvent('test', $eventData);

        $this->assertSame('', $event->getEmojiType());
    }
}
