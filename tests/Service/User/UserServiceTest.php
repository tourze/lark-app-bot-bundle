<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\LarkAppBotBundle\Service\User\UserCacheService;
use Tourze\LarkAppBotBundle\Service\User\UserDataService;
use Tourze\LarkAppBotBundle\Service\User\UserPermissionService;
use Tourze\LarkAppBotBundle\Service\User\UserRelationService;
use Tourze\LarkAppBotBundle\Service\User\UserSearchService;
use Tourze\LarkAppBotBundle\Service\User\UserService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 *
 * 测试 UserService 的用户数据处理逻辑
 */
#[CoversClass(UserService::class)]
#[RunTestsInSeparateProcesses]
final class UserServiceTest extends AbstractIntegrationTestCase
{
    private UserService $userService;

    private UserDataService&MockObject $dataService;

    private UserCacheService&MockObject $cacheService;

    private UserSearchService&MockObject $searchService;

    private UserPermissionService&MockObject $permissionService;

    private UserRelationService&MockObject $relationService;

    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(UserService::class, $this->userService);
    }

    public function testServiceHasRequiredMethods(): void
    {
        $reflection = new \ReflectionClass($this->userService);

        $this->assertTrue($reflection->hasMethod('getUser'));
        $this->assertTrue($reflection->hasMethod('batchGetUsers'));
        $this->assertTrue($reflection->hasMethod('searchUsers'));
        $this->assertTrue($reflection->hasMethod('getUserDepartments'));
        $this->assertTrue($reflection->hasMethod('hasPermission'));
        $this->assertTrue($reflection->hasMethod('clearUserCache'));
        $this->assertTrue($reflection->hasMethod('clearAllUserCache'));
        $this->assertTrue($reflection->hasMethod('getUserInfo'));
    }

    public function testGetUser(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $userData = ['user_id' => $userId, 'name' => 'Test User'];

        $this->dataService->expects(self::once())
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->cacheService->expects(self::once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn(null)
        ;

        $this->dataService->expects(self::once())
            ->method('fetchUser')
            ->with($userId, $userIdType)
            ->willReturn($userData)
        ;

        $this->cacheService->expects(self::once())
            ->method('cacheUser')
            ->with($userId, $userIdType, $userData)
        ;

        $this->dataService->expects(self::once())
            ->method('filterFields')
            ->with($userData, [])
            ->willReturn($userData)
        ;

        $result = $this->userService->getUser($userId, $userIdType);

        self::assertSame($userData, $result);
    }

    public function testGetUserWithCache(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $userData = ['user_id' => $userId, 'name' => 'Test User'];

        $this->dataService->expects(self::once())
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->cacheService->expects(self::once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn($userData)
        ;

        $this->dataService->expects(self::once())
            ->method('filterFields')
            ->with($userData, [])
            ->willReturn($userData)
        ;

        $result = $this->userService->getUser($userId, $userIdType);

        self::assertSame($userData, $result);
    }

    public function testBatchGetUsersWithEmptyInput(): void
    {
        $result = $this->userService->batchGetUsers([]);

        self::assertSame([], $result);
    }

    public function testBatchGetUsers(): void
    {
        $userIds = ['u_123', 'u_456'];
        $userIdType = 'user_id';
        $fields = ['name', 'email'];

        $cachedUsers = ['u_123' => ['user_id' => 'u_123', 'name' => 'User 1']];
        $uncachedUserIds = ['u_456'];
        $newUsers = ['u_456' => ['user_id' => 'u_456', 'name' => 'User 2']];

        $this->dataService->expects(self::once())
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->cacheService->expects(self::once())
            ->method('batchGetUsers')
            ->with($userIds, $userIdType)
            ->willReturn(['cached' => $cachedUsers, 'uncached' => $uncachedUserIds])
        ;

        $this->dataService->expects(self::once())
            ->method('batchFetchUsers')
            ->with($uncachedUserIds, $userIdType)
            ->willReturn($newUsers)
        ;

        $this->cacheService->expects(self::once())
            ->method('batchCacheUsers')
            ->with($newUsers, $userIdType)
        ;

        $this->dataService->expects(self::exactly(2))
            ->method('filterFields')
            ->willReturnCallback(fn ($user, $fields) => $user)
        ;

        $result = $this->userService->batchGetUsers($userIds, $userIdType, $fields);

        $this->assertIsArray($result);
        self::assertCount(2, $result);
        self::assertArrayHasKey('u_123', $result);
        self::assertArrayHasKey('u_456', $result);
    }

    public function testBatchGetUsersAllCached(): void
    {
        $userIds = ['u_123', 'u_456'];
        $userIdType = 'user_id';

        $cachedUsers = [
            'u_123' => ['user_id' => 'u_123', 'name' => 'User 1'],
            'u_456' => ['user_id' => 'u_456', 'name' => 'User 2'],
        ];
        $uncachedUserIds = [];

        $this->dataService->expects(self::once())
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->cacheService->expects(self::once())
            ->method('batchGetUsers')
            ->with($userIds, $userIdType)
            ->willReturn(['cached' => $cachedUsers, 'uncached' => $uncachedUserIds])
        ;

        $this->dataService->expects(self::exactly(2))
            ->method('filterFields')
            ->willReturnCallback(fn ($user, $fields) => $user)
        ;

        $result = $this->userService->batchGetUsers($userIds, $userIdType);

        $this->assertIsArray($result);
        self::assertCount(2, $result);
        self::assertArrayHasKey('u_123', $result);
        self::assertArrayHasKey('u_456', $result);
    }

    public function testSearchUsers(): void
    {
        $params = ['query' => 'test'];
        $searchResult = ['items' => [], 'has_more' => false];

        $this->searchService->expects(self::once())
            ->method('searchUsers')
            ->with($params)
            ->willReturn($searchResult)
        ;

        $result = $this->userService->searchUsers($params);

        self::assertSame($searchResult, $result);
    }

    public function testSearchUsersWithEmptyParams(): void
    {
        $searchResult = ['items' => [], 'has_more' => false];

        $this->searchService->expects(self::once())
            ->method('searchUsers')
            ->with([])
            ->willReturn($searchResult)
        ;

        $result = $this->userService->searchUsers();

        self::assertSame($searchResult, $result);
    }

    public function testGetUserDepartments(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $departments = ['items' => [], 'has_more' => false];

        $this->relationService->expects(self::once())
            ->method('getUserDepartments')
            ->with($userId, $userIdType)
            ->willReturn($departments)
        ;

        $result = $this->userService->getUserDepartments($userId, $userIdType);

        self::assertSame($departments, $result);
    }

    public function testGetUserLeader(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $leader = ['user_id' => 'u_999', 'name' => 'Leader'];

        $this->relationService->expects(self::once())
            ->method('getUserLeader')
            ->with($userId, $userIdType)
            ->willReturn($leader)
        ;

        $result = $this->userService->getUserLeader($userId, $userIdType);

        self::assertSame($leader, $result);
    }

    public function testGetUserSubordinates(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $subordinates = [['user_id' => 'u_456', 'name' => 'Subordinate']];

        $this->relationService->expects(self::once())
            ->method('getUserSubordinates')
            ->with($userId, $userIdType)
            ->willReturn($subordinates)
        ;

        $result = $this->userService->getUserSubordinates($userId, $userIdType);

        self::assertSame($subordinates, $result);
    }

    public function testHasPermission(): void
    {
        $userId = 'u_123';
        $permission = 'admin';
        $userIdType = 'user_id';

        $this->permissionService->expects(self::once())
            ->method('hasPermission')
            ->with($userId, $permission, $userIdType)
            ->willReturn(true)
        ;

        $result = $this->userService->hasPermission($userId, $permission, $userIdType);

        self::assertTrue($result);
    }

    public function testClearUserCache(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';

        $this->cacheService->expects(self::once())
            ->method('clearUserCache')
            ->with($userId, $userIdType)
        ;

        $this->expectNotToPerformAssertions();
        $this->userService->clearUserCache($userId, $userIdType);
    }

    public function testClearAllUserCache(): void
    {
        $this->cacheService->expects(self::once())
            ->method('clearAllCache')
        ;

        $this->expectNotToPerformAssertions();
        $this->userService->clearAllUserCache();
    }

    public function testGetUserInfo(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $fields = ['name'];
        $userData = ['user_id' => $userId, 'name' => 'Test User'];

        $this->dataService->expects(self::once())
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->cacheService->expects(self::once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn($userData)
        ;

        $this->dataService->expects(self::once())
            ->method('filterFields')
            ->with($userData, $fields)
            ->willReturn(['name' => 'Test User'])
        ;

        $result = $this->userService->getUserInfo($userId, $userIdType, $fields);

        self::assertSame(['name' => 'Test User'], $result);
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象
        $this->dataService = self::createMock(UserDataService::class);
        self::getContainer()->set(UserDataService::class, $this->dataService);
        $this->cacheService = self::createMock(UserCacheService::class);
        self::getContainer()->set(UserCacheService::class, $this->cacheService);
        $this->searchService = self::createMock(UserSearchService::class);
        self::getContainer()->set(UserSearchService::class, $this->searchService);
        $this->permissionService = self::createMock(UserPermissionService::class);
        self::getContainer()->set(UserPermissionService::class, $this->permissionService);
        $this->relationService = self::createMock(UserRelationService::class);
        self::getContainer()->set(UserRelationService::class, $this->relationService);

        // 获取服务实例
        $this->userService = self::getService(UserService::class);
    }
}
