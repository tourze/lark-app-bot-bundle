<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\LarkAppBotBundle\Service\User\UserCacheService;
use Tourze\LarkAppBotBundle\Service\User\UserDataService;
use Tourze\LarkAppBotBundle\Service\User\UserRelationService;
use Tourze\LarkAppBotBundle\Service\User\UserSearchService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserRelationService::class)]
#[RunTestsInSeparateProcesses]
final class UserRelationServiceTest extends AbstractIntegrationTestCase
{
    private UserRelationService $service;

    private UserDataService&MockObject $userDataService;

    private UserCacheService&MockObject $cacheService;

    private UserSearchService&MockObject $searchService;

    public function testGetUserDepartments(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $expectedDepartments = [
            'items' => [
                ['department_id' => 'dept_1', 'user_order' => 1], ['department_id' => 'dept_2', 'user_order' => 2]],
            'has_more' => false];

        $this->userDataService->expects($this->once())
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->userDataService->expects($this->once())
            ->method('fetchUserDepartments')
            ->with($userId, $userIdType)
            ->willReturn($expectedDepartments)
        ;

        $result = $this->service->getUserDepartments($userId, $userIdType);

        $this->assertSame($expectedDepartments, $result);
    }

    public function testGetUserLeader(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $user = ['user_id' => $userId, 'leader_user_id' => 'u_456'];
        $leaderData = ['user_id' => 'u_456', 'name' => 'Leader'];

        $this->cacheService->expects($this->exactly(2))
            ->method('getUser')
            ->willReturnCallback(function ($id, $type) use ($userId, $userIdType, $user, $leaderData) {
                if ($id === $userId && $type === $userIdType) {
                    return $user;
                }
                if ('u_456' === $id && 'user_id' === $type) {
                    return $leaderData;
                }

                return null;
            })
        ;

        $this->userDataService->expects($this->exactly(2))
            ->method('filterFields')
            ->willReturnCallback(function ($userData, $fields) use ($user, $leaderData) {
                if ($userData === $user && $fields === ['leader_user_id']) {
                    return ['leader_user_id' => 'u_456'];
                }
                if ($userData === $leaderData && [] === $fields) {
                    return $leaderData;
                }

                return $userData;
            })
        ;

        $result = $this->service->getUserLeader($userId, $userIdType);

        $this->assertSame($leaderData, $result);
    }

    public function testGetUserLeaderReturnsNullWhenNoLeader(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $user = ['user_id' => $userId, 'leader_user_id' => null];

        $this->cacheService->expects($this->once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn($user)
        ;

        $result = $this->service->getUserLeader($userId, $userIdType);

        $this->assertNull($result);
    }

    public function testGetUserSubordinates(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $subordinates = [
            ['user_id' => 'u_456', 'name' => 'Subordinate 1'], ['user_id' => 'u_789', 'name' => 'Subordinate 2']];

        $this->searchService->expects($this->once())
            ->method('findSubordinates')
            ->with($userId)
            ->willReturn($subordinates)
        ;

        $result = $this->service->getUserSubordinates($userId, $userIdType);

        $this->assertSame($subordinates, $result);
    }

    public function testAreInSameDepartmentTrue(): void
    {
        $userId1 = 'u_123';
        $userId2 = 'u_456';
        $userIdType = 'user_id';

        $departments1 = [
            'items' => [
                ['department_id' => 'dept_1', 'user_order' => 1], ['department_id' => 'dept_2', 'user_order' => 2]],
            'has_more' => false];

        $departments2 = [
            'items' => [
                ['department_id' => 'dept_2', 'user_order' => 1], ['department_id' => 'dept_3', 'user_order' => 2]],
            'has_more' => false];

        $this->userDataService->expects($this->exactly(2))
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->userDataService->expects($this->exactly(2))
            ->method('fetchUserDepartments')
            ->willReturnCallback(function ($userId, $userIdType) use ($userId1, $userId2, $departments1, $departments2) {
                if ($userId === $userId1) {
                    return $departments1;
                }
                if ($userId === $userId2) {
                    return $departments2;
                }

                return ['items' => [], 'has_more' => false];
            })
        ;

        $result = $this->service->areInSameDepartment($userId1, $userId2, $userIdType);

        $this->assertTrue($result);
    }

    public function testAreInSameDepartmentFalse(): void
    {
        $userId1 = 'u_123';
        $userId2 = 'u_456';
        $userIdType = 'user_id';

        $departments1 = [
            'items' => [
                ['department_id' => 'dept_1', 'user_order' => 1], ['department_id' => 'dept_2', 'user_order' => 2]],
            'has_more' => false];

        $departments2 = [
            'items' => [
                ['department_id' => 'dept_3', 'user_order' => 1], ['department_id' => 'dept_4', 'user_order' => 2]],
            'has_more' => false];

        $this->userDataService->expects($this->exactly(2))
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->userDataService->expects($this->exactly(2))
            ->method('fetchUserDepartments')
            ->willReturnCallback(function ($userId, $userIdType) use ($userId1, $userId2, $departments1, $departments2) {
                if ($userId === $userId1) {
                    return $departments1;
                }
                if ($userId === $userId2) {
                    return $departments2;
                }

                return ['items' => [], 'has_more' => false];
            })
        ;

        $result = $this->service->areInSameDepartment($userId1, $userId2, $userIdType);

        $this->assertFalse($result);
    }

    public function testAreInSameDepartmentEmptyDepartments(): void
    {
        $userId1 = 'u_123';
        $userId2 = 'u_456';
        $userIdType = 'user_id';

        $departments1 = ['items' => [], 'has_more' => false];
        $departments2 = ['items' => [], 'has_more' => false];

        $this->userDataService->expects($this->exactly(2))
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->userDataService->expects($this->exactly(2))
            ->method('fetchUserDepartments')
            ->willReturnCallback(function ($userId, $userIdType) use ($userId1, $userId2, $departments1, $departments2) {
                if ($userId === $userId1) {
                    return $departments1;
                }
                if ($userId === $userId2) {
                    return $departments2;
                }

                return ['items' => [], 'has_more' => false];
            })
        ;

        $result = $this->service->areInSameDepartment($userId1, $userId2, $userIdType);

        $this->assertFalse($result);
    }

    public function testAreInSameDepartmentOneUserNoDepartments(): void
    {
        $userId1 = 'u_123';
        $userId2 = 'u_456';
        $userIdType = 'user_id';

        $departments1 = [
            'items' => [
                ['department_id' => 'dept_1', 'user_order' => 1]],
            'has_more' => false];
        $departments2 = ['items' => [], 'has_more' => false];

        $this->userDataService->expects($this->exactly(2))
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->userDataService->expects($this->exactly(2))
            ->method('fetchUserDepartments')
            ->willReturnCallback(function ($userId, $userIdType) use ($userId1, $userId2, $departments1, $departments2) {
                if ($userId === $userId1) {
                    return $departments1;
                }
                if ($userId === $userId2) {
                    return $departments2;
                }

                return ['items' => [], 'has_more' => false];
            })
        ;

        $result = $this->service->areInSameDepartment($userId1, $userId2, $userIdType);

        $this->assertFalse($result);
    }

    public function testAreInSameDepartmentMultipleCommonDepartments(): void
    {
        $userId1 = 'u_123';
        $userId2 = 'u_456';
        $userIdType = 'user_id';

        $departments1 = [
            'items' => [
                ['department_id' => 'dept_1', 'user_order' => 1], ['department_id' => 'dept_2', 'user_order' => 2],
                ['department_id' => 'dept_3', 'user_order' => 3]],
            'has_more' => false];

        $departments2 = [
            'items' => [
                ['department_id' => 'dept_2', 'user_order' => 1], ['department_id' => 'dept_3', 'user_order' => 2],
                ['department_id' => 'dept_4', 'user_order' => 3]],
            'has_more' => false];

        $this->userDataService->expects($this->exactly(2))
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->userDataService->expects($this->exactly(2))
            ->method('fetchUserDepartments')
            ->willReturnCallback(function ($userId, $userIdType) use ($userId1, $userId2, $departments1, $departments2) {
                if ($userId === $userId1) {
                    return $departments1;
                }
                if ($userId === $userId2) {
                    return $departments2;
                }

                return ['items' => [], 'has_more' => false];
            })
        ;

        $result = $this->service->areInSameDepartment($userId1, $userId2, $userIdType);

        $this->assertTrue($result);
    }

    protected function prepareMockServices(): void
    {
        $this->userDataService = self::createMock(UserDataService::class);
        $this->cacheService = self::createMock(UserCacheService::class);
        $this->searchService = self::createMock(UserSearchService::class);
        self::getContainer()->set(UserDataService::class, $this->userDataService);
        self::getContainer()->set(UserCacheService::class, $this->cacheService);
        self::getContainer()->set(UserSearchService::class, $this->searchService);
    }

    protected function onSetUp(): void
    {
        // 获取服务实例，不再设置 mock
        $this->service = self::getService(UserRelationService::class);
        // 创建 mock 对象
        $this->userDataService = self::createMock(UserDataService::class);
        self::getContainer()->set(UserDataService::class, $this->userDataService);
        $this->cacheService = self::createMock(UserCacheService::class);
        self::getContainer()->set(UserCacheService::class, $this->cacheService);
        $this->searchService = self::createMock(UserSearchService::class);
        self::getContainer()->set(UserSearchService::class, $this->searchService);
        $this->service = self::getService(UserRelationService::class);
    }
}
