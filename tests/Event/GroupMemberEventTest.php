<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Event\GroupMemberEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(GroupMemberEvent::class)]
final class GroupMemberEventTest extends AbstractEventTestCase
{
    public function testGroupMemberEventCreation(): void
    {
        $eventData = [
            'chat_id' => 'chat_123',
            'operator_id' => 'user_123',
            'users' => [
                ['tenant_key' => 'tenant_123', 'user_id' => 'user_456'],
                ['tenant_key' => 'tenant_123', 'user_id' => 'user_789']]];

        $context = [
            'event_id' => 'event_123',
            'tenant_key' => 'tenant_123'];

        $event = new GroupMemberEvent('im.chat.member.user.added_v1', $eventData, $context);

        $this->assertSame('im.chat.member.user.added_v1', $event->getEventType());
        $this->assertSame($eventData, $event->getData());
        $this->assertSame($context, $event->getContext());
    }

    public function testGetChatId(): void
    {
        $eventData = ['chat_id' => 'chat_123'];
        $event = new GroupMemberEvent('test', $eventData);

        $this->assertSame('chat_123', $event->getChatId());
    }

    public function testGetChatIdWithMissingData(): void
    {
        $event = new GroupMemberEvent('test', []);

        $this->assertSame('', $event->getChatId());
    }

    public function testGetOperatorId(): void
    {
        $eventData = ['operator_id' => 'user_123'];
        $event = new GroupMemberEvent('test', $eventData);

        $this->assertSame('user_123', $event->getOperatorId());
    }

    public function testGetOperatorIdWithMissingData(): void
    {
        $event = new GroupMemberEvent('test', []);

        $this->assertSame('', $event->getOperatorId());
    }

    public function testGetUsers(): void
    {
        $users = [
            ['tenant_key' => 'tenant_123', 'user_id' => 'user_456'],
            ['tenant_key' => 'tenant_123', 'user_id' => 'user_789']];
        $eventData = ['users' => $users];
        $event = new GroupMemberEvent('test', $eventData);

        $this->assertSame($users, $event->getUsers());
    }

    public function testGetUsersWithMissingData(): void
    {
        $event = new GroupMemberEvent('test', []);

        $this->assertSame([], $event->getUsers());
    }

    public function testIsBotAdded(): void
    {
        $botAddedEvent = new GroupMemberEvent('im.chat.member.bot.added_v1', []);
        $this->assertTrue($botAddedEvent->isBotAdded());

        $otherEvent = new GroupMemberEvent('im.chat.member.user.added_v1', []);
        $this->assertFalse($otherEvent->isBotAdded());
    }

    public function testIsBotDeleted(): void
    {
        $botDeletedEvent = new GroupMemberEvent('im.chat.member.bot.deleted_v1', []);
        $this->assertTrue($botDeletedEvent->isBotDeleted());

        $otherEvent = new GroupMemberEvent('im.chat.member.user.added_v1', []);
        $this->assertFalse($otherEvent->isBotDeleted());
    }

    public function testIsUserAdded(): void
    {
        $userAddedEvent = new GroupMemberEvent('im.chat.member.user.added_v1', []);
        $this->assertTrue($userAddedEvent->isUserAdded());

        $otherEvent = new GroupMemberEvent('im.chat.member.bot.added_v1', []);
        $this->assertFalse($otherEvent->isUserAdded());
    }

    public function testIsUserWithdrawn(): void
    {
        $userWithdrawnEvent = new GroupMemberEvent('im.chat.member.user.withdrawn_v1', []);
        $this->assertTrue($userWithdrawnEvent->isUserWithdrawn());

        $otherEvent = new GroupMemberEvent('im.chat.member.user.added_v1', []);
        $this->assertFalse($otherEvent->isUserWithdrawn());
    }

    public function testIsUserDeleted(): void
    {
        $userDeletedEvent = new GroupMemberEvent('im.chat.member.user.deleted_v1', []);
        $this->assertTrue($userDeletedEvent->isUserDeleted());

        $otherEvent = new GroupMemberEvent('im.chat.member.user.added_v1', []);
        $this->assertFalse($otherEvent->isUserDeleted());
    }
}
