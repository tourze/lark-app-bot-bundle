<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Service\User\UserEvent;

/**
 * @internal
 */
#[CoversClass(UserEvent::class)]
final class UserEventTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $eventType = UserEvent::USER_UPDATED;
        $userData = [
            'user_id' => 'u_123',
            'user_id_type' => 'user_id',
            'name' => 'Test User'];
        $metadata = [
            'timestamp' => '2023-01-01 00:00:00',
            'source' => 'api'];

        $event = new UserEvent($eventType, $userData, $metadata);

        $this->assertSame($eventType, $event->getEventType());
        $this->assertSame($userData, $event->getUserData());
        $this->assertSame($metadata, $event->getMetadata());
    }

    public function testGetUserId(): void
    {
        $userData = ['user_id' => 'u_123'];
        $event = new UserEvent(UserEvent::USER_CREATED, $userData);

        $this->assertSame('u_123', $event->getUserId());
    }

    public function testGetUserIdWithNull(): void
    {
        $userData = ['name' => 'Test User'];
        $event = new UserEvent(UserEvent::USER_CREATED, $userData);

        $this->assertNull($event->getUserId());
    }

    public function testGetUserIdType(): void
    {
        $userData = ['user_id_type' => 'open_id'];
        $event = new UserEvent(UserEvent::USER_CREATED, $userData);

        $this->assertSame('open_id', $event->getUserIdType());
    }

    public function testGetUserIdTypeWithNull(): void
    {
        $userData = ['name' => 'Test User'];
        $event = new UserEvent(UserEvent::USER_CREATED, $userData);

        $this->assertNull($event->getUserIdType());
    }

    public function testDefaultMetadata(): void
    {
        $event = new UserEvent(UserEvent::USER_CREATED, []);

        $this->assertSame([], $event->getMetadata());
    }

    public function testEventConstants(): void
    {
        $this->assertSame('user.updated', UserEvent::USER_UPDATED);
        $this->assertSame('user.deleted', UserEvent::USER_DELETED);
        $this->assertSame('user.created', UserEvent::USER_CREATED);
        $this->assertSame('user.batch_sync_completed', UserEvent::BATCH_SYNC_COMPLETED);
    }
}
