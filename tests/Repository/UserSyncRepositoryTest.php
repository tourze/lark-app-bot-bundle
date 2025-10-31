<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Entity\UserSync;
use Tourze\LarkAppBotBundle\Repository\UserSyncRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(UserSyncRepository::class)]
#[RunTestsInSeparateProcesses]
final class UserSyncRepositoryTest extends AbstractRepositoryTestCase
{
    public function testSaveAndFindUserSyncShouldWorkCorrectly(): void
    {
        $userSync = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $userSync);

        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $em->persist($userSync);
        $em->flush();

        $foundUser = $repository->findByUserId($userSync->getUserId());
        self::assertNotNull($foundUser);
        self::assertSame($userSync->getName(), $foundUser->getName());
    }

    public function testFindByUserIdShouldReturnCorrectUser(): void
    {
        $userId = 'user_test_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $user = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $user);
        $user->setUserId($userId);

        $em->persist($user);
        $em->flush();

        $foundUser = $repository->findByUserId($userId);
        self::assertNotNull($foundUser);
        self::assertSame($userId, $foundUser->getUserId());
    }

    public function testFindByOpenIdShouldReturnCorrectUser(): void
    {
        $openId = 'open_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $user = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $user);
        $user->setOpenId($openId);

        $em->persist($user);
        $em->flush();

        $foundUser = $repository->findByOpenId($openId);
        self::assertNotNull($foundUser);
        self::assertSame($openId, $foundUser->getOpenId());
    }

    public function testFindByUnionIdShouldReturnCorrectUser(): void
    {
        $unionId = 'union_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $user = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $user);
        $user->setUnionId($unionId);

        $em->persist($user);
        $em->flush();

        $foundUser = $repository->findByUnionId($unionId);
        self::assertNotNull($foundUser);
        self::assertSame($unionId, $foundUser->getUnionId());
    }

    public function testFindByEmailShouldReturnCorrectUser(): void
    {
        $email = 'test_' . uniqid() . '@example.com';
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $user = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $user);
        $user->setEmail($email);

        $em->persist($user);
        $em->flush();

        $foundUser = $repository->findByEmail($email);
        self::assertNotNull($foundUser);
        self::assertSame($email, $foundUser->getEmail());
    }

    public function testFindBySyncStatusShouldReturnCorrectUsers(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        // Create user with success status
        $successUser = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $successUser);
        $successUser->setSyncStatus('success');

        // Create user with failed status
        $failedUser = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $failedUser);
        $failedUser->setSyncStatus('failed');

        $em->persist($successUser);
        $em->persist($failedUser);
        $em->flush();

        $successUsers = $repository->findBySyncStatus('success');
        self::assertNotEmpty($successUsers);
        foreach ($successUsers as $user) {
            self::assertSame('success', $user->getSyncStatus());
        }

        $failedUsers = $repository->findBySyncStatus('failed');
        self::assertNotEmpty($failedUsers);
        foreach ($failedUsers as $user) {
            self::assertSame('failed', $user->getSyncStatus());
        }
    }

    public function testFindPendingSyncShouldReturnPendingUsers(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $pendingUser = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $pendingUser);
        $pendingUser->setSyncStatus('pending');

        $em->persist($pendingUser);
        $em->flush();

        $pendingUsers = $repository->findPendingSync();
        self::assertNotEmpty($pendingUsers);
        foreach ($pendingUsers as $user) {
            self::assertSame('pending', $user->getSyncStatus());
        }
    }

    public function testFindFailedSyncShouldReturnFailedUsers(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $failedUser = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $failedUser);
        $failedUser->setSyncStatus('failed');

        $em->persist($failedUser);
        $em->flush();

        $failedUsers = $repository->findFailedSync();
        self::assertNotEmpty($failedUsers);
        foreach ($failedUsers as $user) {
            self::assertSame('failed', $user->getSyncStatus());
        }
    }

    public function testFindSuccessfulSyncShouldReturnSuccessfulUsers(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $successUser = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $successUser);
        $successUser->setSyncStatus('success');

        $em->persist($successUser);
        $em->flush();

        $successUsers = $repository->findSuccessfulSync();
        self::assertNotEmpty($successUsers);
        foreach ($successUsers as $user) {
            self::assertSame('success', $user->getSyncStatus());
        }
    }

    public function testGetStatusCountsShouldReturnCorrectCounts(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $pendingUser = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $pendingUser);
        $pendingUser->setSyncStatus('pending');

        $successUser = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $successUser);
        $successUser->setSyncStatus('success');

        $failedUser = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $failedUser);
        $failedUser->setSyncStatus('failed');

        $em->persist($pendingUser);
        $em->persist($successUser);
        $em->persist($failedUser);
        $em->flush();

        $counts = $repository->getStatusCounts();
        $this->assertIsArray($counts);
        self::assertArrayHasKey('pending', $counts);
        self::assertArrayHasKey('success', $counts);
        self::assertArrayHasKey('failed', $counts);
        self::assertGreaterThanOrEqual(1, $counts['pending']);
        self::assertGreaterThanOrEqual(1, $counts['success']);
        self::assertGreaterThanOrEqual(1, $counts['failed']);
    }

    public function testFindBySyncDateRangeShouldReturnUsersInRange(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $user = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $user);
        $user->setSyncAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        $startDate = new \DateTime('-1 day');
        $endDate = new \DateTime('+1 day');

        $users = $repository->findBySyncDateRange($startDate, $endDate);
        self::assertGreaterThanOrEqual(1, \count($users));
        foreach ($users as $foundUser) {
            $syncAt = $foundUser->getSyncAt();
            self::assertNotNull($syncAt);
            self::assertGreaterThanOrEqual($startDate->getTimestamp(), $syncAt->getTimestamp());
            self::assertLessThanOrEqual($endDate->getTimestamp(), $syncAt->getTimestamp());
        }
    }

    public function testFindRecentSyncedShouldReturnRecentlySyncedUsers(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $user1 = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $user1);
        $user1->setSyncAt(new \DateTimeImmutable('-1 hour'));

        $user2 = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $user2);
        $user2->setSyncAt(new \DateTimeImmutable('-2 hours'));

        $em->persist($user1);
        $em->persist($user2);
        $em->flush();

        $recentUsers = $repository->findRecentSynced();
        self::assertNotEmpty($recentUsers);
        foreach ($recentUsers as $user) {
            self::assertNotNull($user->getSyncAt());
        }

        // Should be ordered by syncAt DESC, so most recent first
        if (\count($recentUsers) >= 2) {
            $syncAt0 = $recentUsers[0]->getSyncAt();
            $syncAt1 = $recentUsers[1]->getSyncAt();
            self::assertNotNull($syncAt0);
            self::assertNotNull($syncAt1);
            self::assertGreaterThanOrEqual(
                $syncAt1->getTimestamp(),
                $syncAt0->getTimestamp()
            );
        }
    }

    public function testSyncStatusValidationShouldOnlyAllowValidStatuses(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $user = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $user);
        // 先声明预期异常，再设置非法状态（实体当前实现会在 setter 抛出）
        $this->expectException(\Exception::class);
        $user->setSyncStatus('invalid_status');

        // Flush 时也应当保持抛错行为
        $em->persist($user);
        $em->flush();
    }

    public function testUniqueUserIdConstraintShouldPreventDuplicates(): void
    {
        $userId = 'unique_user_' . uniqid();
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $user1 = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $user1);
        $user1->setUserId($userId);

        $user2 = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $user2);
        $user2->setUserId($userId);

        $em->persist($user1);
        $em->flush();

        // Second user with same user_id should cause constraint violation
        $this->expectException(\Exception::class);
        $em->persist($user2);
        $em->flush();
    }

    public function testDepartmentIdsJsonFieldShouldHandleArrays(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $departmentIds = ['dept1' => 'Department 1', 'dept2' => 'Department 2', 'dept3' => 'Department 3'];
        $user = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $user);
        $user->setDepartmentIds($departmentIds);

        $em->persist($user);
        $em->flush();

        $foundUser = $repository->findByUserId($user->getUserId());
        self::assertNotNull($foundUser);
        self::assertSame($departmentIds, $foundUser->getDepartmentIds());
    }

    public function testErrorMessageFieldShouldStoreFailureReasons(): void
    {
        $repository = $this->getRepository();
        $em = self::getEntityManager();

        $errorMessage = 'API rate limit exceeded';
        $user = $this->createNewEntity();
        self::assertInstanceOf(UserSync::class, $user);
        $user->setSyncStatus('failed');
        $user->setErrorMessage($errorMessage);

        $em->persist($user);
        $em->flush();

        $foundUser = $repository->findByUserId($user->getUserId());
        self::assertNotNull($foundUser);
        self::assertSame($errorMessage, $foundUser->getErrorMessage());
    }

    protected function onSetUp(): void
    {
        // No setup required - using self::getService() directly in tests
    }

    protected function createNewEntity(): object
    {
        $user = new UserSync();
        $user->setUserId('user_' . uniqid());
        $user->setOpenId('open_' . uniqid());
        $user->setUnionId('union_' . uniqid());
        $user->setName('Test User ' . uniqid());
        $user->setEmail('test_' . uniqid() . '@example.com');
        $user->setMobile('+86' . rand(13000000000, 18999999999));
        $user->setDepartmentIds(['dept1' => 'Department 1', 'dept2' => 'Department 2']);
        $user->setSyncStatus('pending');

        return $user;
    }

    protected function getRepository(): UserSyncRepository
    {
        return self::getService(UserSyncRepository::class);
    }
}
