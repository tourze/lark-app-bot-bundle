<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Event\UserEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(UserEvent::class)]
final class UserEventTest extends AbstractEventTestCase
{
    public function testUserEventCreation(): void
    {
        $userData = [
            'user_id' => 'user_123',
            'open_id' => 'ou_123',
            'union_id' => 'on_123',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'mobile' => '13800138000',
            'department_ids' => ['dept_1', 'dept_2']];

        $context = [
            'event_id' => 'event_123',
            'tenant_key' => 'tenant_123'];

        $event = new UserEvent('contact.user.created_v3', $userData, $context);

        $this->assertSame('contact.user.created_v3', $event->getType());
        $this->assertSame($userData, $event->getUser());
        $this->assertSame($userData, $event->getData());
        $this->assertSame($context, $event->getContext());
    }

    public function testGetUserId(): void
    {
        $userData = ['user_id' => 'user_123'];
        $event = new UserEvent('test', $userData);

        $this->assertSame('user_123', $event->getUserId());
    }

    public function testGetUserIdWithMissingData(): void
    {
        $event = new UserEvent('test', []);

        $this->assertSame('', $event->getUserId());
    }

    public function testGetOpenId(): void
    {
        $userData = ['open_id' => 'ou_123'];
        $event = new UserEvent('test', $userData);

        $this->assertSame('ou_123', $event->getOpenId());
    }

    public function testGetOpenIdWithMissingData(): void
    {
        $event = new UserEvent('test', []);

        $this->assertSame('', $event->getOpenId());
    }

    public function testGetUnionId(): void
    {
        $userData = ['union_id' => 'on_123'];
        $event = new UserEvent('test', $userData);

        $this->assertSame('on_123', $event->getUnionId());
    }

    public function testGetUnionIdWithMissingData(): void
    {
        $event = new UserEvent('test', []);

        $this->assertSame('', $event->getUnionId());
    }

    public function testGetName(): void
    {
        $userData = ['name' => 'Test User'];
        $event = new UserEvent('test', $userData);

        $this->assertSame('Test User', $event->getName());
    }

    public function testGetNameWithMissingData(): void
    {
        $event = new UserEvent('test', []);

        $this->assertSame('', $event->getName());
    }

    public function testGetEmail(): void
    {
        $userData = ['email' => 'test@example.com'];
        $event = new UserEvent('test', $userData);

        $this->assertSame('test@example.com', $event->getEmail());
    }

    public function testGetEmailWithMissingData(): void
    {
        $event = new UserEvent('test', []);

        $this->assertSame('', $event->getEmail());
    }

    public function testGetMobile(): void
    {
        $userData = ['mobile' => '13800138000'];
        $event = new UserEvent('test', $userData);

        $this->assertSame('13800138000', $event->getMobile());
    }

    public function testGetMobileWithMissingData(): void
    {
        $event = new UserEvent('test', []);

        $this->assertSame('', $event->getMobile());
    }

    public function testGetDepartmentIds(): void
    {
        $departmentIds = ['dept_1', 'dept_2'];
        $userData = ['department_ids' => $departmentIds];
        $event = new UserEvent('test', $userData);

        $this->assertSame($departmentIds, $event->getDepartmentIds());
    }

    public function testGetDepartmentIdsWithMissingData(): void
    {
        $event = new UserEvent('test', []);

        $this->assertSame([], $event->getDepartmentIds());
    }

    public function testIsCreated(): void
    {
        $createdEvent = new UserEvent('contact.user.created_v3', []);
        $this->assertTrue($createdEvent->isCreated());

        $otherEvent = new UserEvent('contact.user.updated_v3', []);
        $this->assertFalse($otherEvent->isCreated());
    }

    public function testIsUpdated(): void
    {
        $updatedEvent = new UserEvent('contact.user.updated_v3', []);
        $this->assertTrue($updatedEvent->isUpdated());

        $otherEvent = new UserEvent('contact.user.created_v3', []);
        $this->assertFalse($otherEvent->isUpdated());
    }

    public function testIsDeleted(): void
    {
        $deletedEvent = new UserEvent('contact.user.deleted_v3', []);
        $this->assertTrue($deletedEvent->isDeleted());

        $otherEvent = new UserEvent('contact.user.created_v3', []);
        $this->assertFalse($otherEvent->isDeleted());
    }

    public function testConstants(): void
    {
        $this->assertSame('lark.user.created', UserEvent::USER_CREATED);
        $this->assertSame('lark.user.updated', UserEvent::USER_UPDATED);
        $this->assertSame('lark.user.deleted', UserEvent::USER_DELETED);
        $this->assertSame('lark.user.activity', UserEvent::USER_ACTIVITY);
        $this->assertSame('lark.user.data_loaded', UserEvent::USER_DATA_LOADED);
        $this->assertSame('lark.user.data_updated', UserEvent::USER_DATA_UPDATED);
        $this->assertSame('lark.user.data_deleted', UserEvent::USER_DATA_DELETED);
        $this->assertSame('lark.user.data_imported', UserEvent::USER_DATA_IMPORTED);
    }
}
