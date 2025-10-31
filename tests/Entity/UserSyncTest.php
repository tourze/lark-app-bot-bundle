<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\LarkAppBotBundle\Entity\UserSync;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(UserSync::class)]
final class UserSyncTest extends AbstractEntityTestCase
{
    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            ['userId', 'user_123456'],
            ['openId', 'open_id_abc'],
            ['unionId', 'union_id_xyz'],
            ['name', 'John Doe'],
            ['email', 'john@example.com'],
            ['mobile', '+1234567890'],
            ['departmentIds', ['dept_1', 'dept_2']],
            ['syncStatus', 'success'],
            ['syncAt', new \DateTimeImmutable('2024-01-01 12:00:00')],
            ['errorMessage', 'Test error'],
        ];
    }

    public function testUserSyncCanBeCreatedWithDefaultValues(): void
    {
        $userSync = new UserSync();

        self::assertNull($userSync->getId());
        self::assertSame('', $userSync->getUserId());
        self::assertNull($userSync->getOpenId());
        self::assertNull($userSync->getUnionId());
        self::assertSame('', $userSync->getName());
        self::assertNull($userSync->getEmail());
        self::assertNull($userSync->getMobile());
        self::assertNull($userSync->getDepartmentIds());
        self::assertSame('pending', $userSync->getSyncStatus());
        self::assertNull($userSync->getSyncAt());
        self::assertNull($userSync->getErrorMessage());
    }

    public function testUserSyncSettersAndGetters(): void
    {
        $userSync = new UserSync();
        $departmentIds = ['dept_a', 'dept_b', 'dept_c'];
        $syncAt = new \DateTimeImmutable('2024-06-01 10:30:00');

        $userSync->setUserId('user_test123');
        $userSync->setOpenId('open_test456');
        $userSync->setUnionId('union_test789');
        $userSync->setName('Jane Smith');
        $userSync->setEmail('jane@example.com');
        $userSync->setMobile('+9876543210');
        $userSync->setDepartmentIds($departmentIds);
        $userSync->setSyncStatus('failed');
        $userSync->setSyncAt($syncAt);
        $userSync->setErrorMessage('Sync failed due to network error');

        self::assertSame('user_test123', $userSync->getUserId());
        self::assertSame('open_test456', $userSync->getOpenId());
        self::assertSame('union_test789', $userSync->getUnionId());
        self::assertSame('Jane Smith', $userSync->getName());
        self::assertSame('jane@example.com', $userSync->getEmail());
        self::assertSame('+9876543210', $userSync->getMobile());
        self::assertSame($departmentIds, $userSync->getDepartmentIds());
        self::assertSame('failed', $userSync->getSyncStatus());
        self::assertSame($syncAt, $userSync->getSyncAt());
        self::assertSame('Sync failed due to network error', $userSync->getErrorMessage());
    }

    public function testToStringMethod(): void
    {
        $userSync = new UserSync();
        $userSync->setName('Test User');
        $userSync->setUserId('user_12345');

        self::assertSame('用户同步 Test User (user_12345)', (string) $userSync);
    }

    public function testOpenIdCanBeNull(): void
    {
        $userSync = new UserSync();
        $userSync->setOpenId(null);

        self::assertNull($userSync->getOpenId());
    }

    public function testUnionIdCanBeNull(): void
    {
        $userSync = new UserSync();
        $userSync->setUnionId(null);

        self::assertNull($userSync->getUnionId());
    }

    public function testEmailCanBeNull(): void
    {
        $userSync = new UserSync();
        $userSync->setEmail(null);

        self::assertNull($userSync->getEmail());
    }

    public function testMobileCanBeNull(): void
    {
        $userSync = new UserSync();
        $userSync->setMobile(null);

        self::assertNull($userSync->getMobile());
    }

    public function testDepartmentIdsCanBeNull(): void
    {
        $userSync = new UserSync();
        $userSync->setDepartmentIds(null);

        self::assertNull($userSync->getDepartmentIds());
    }

    public function testSyncAtCanBeNull(): void
    {
        $userSync = new UserSync();
        $userSync->setSyncAt(null);

        self::assertNull($userSync->getSyncAt());
    }

    public function testErrorMessageCanBeNull(): void
    {
        $userSync = new UserSync();
        $userSync->setErrorMessage(null);

        self::assertNull($userSync->getErrorMessage());
    }

    public function testSyncStatusValues(): void
    {
        $userSync = new UserSync();

        // Test all valid sync status values
        $userSync->setSyncStatus('pending');
        self::assertSame('pending', $userSync->getSyncStatus());

        $userSync->setSyncStatus('success');
        self::assertSame('success', $userSync->getSyncStatus());

        $userSync->setSyncStatus('failed');
        self::assertSame('failed', $userSync->getSyncStatus());
    }

    public function testDepartmentIdsArray(): void
    {
        $userSync = new UserSync();
        $departments = ['engineering', 'product', 'design'];

        $userSync->setDepartmentIds($departments);

        self::assertSame($departments, $userSync->getDepartmentIds());
        $this->assertIsArray($userSync->getDepartmentIds());
        self::assertCount(3, $userSync->getDepartmentIds());
    }

    protected function createEntity(): object
    {
        return new UserSync();
    }
}
