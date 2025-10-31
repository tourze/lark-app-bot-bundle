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
use Tourze\LarkAppBotBundle\Service\User\UserServiceRefactored;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserServiceRefactored::class)]
#[RunTestsInSeparateProcesses]
final class UserServiceRefactoredTest extends AbstractIntegrationTestCase
{
    private UserServiceRefactored $service;

    private UserDataService&MockObject $dataService;

    private UserCacheService&MockObject $cacheService;

    private UserSearchService&MockObject $searchService;

    private UserPermissionService&MockObject $permissionService;

    private UserRelationService&MockObject $relationService;

    public function testGetUser(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $userData = ['user_id' => $userId, 'name' => 'Test User'];

        $this->dataService->expects($this->once())
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->cacheService->expects($this->once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn(null)
        ;

        $this->dataService->expects($this->once())
            ->method('fetchUser')
            ->with($userId, $userIdType)
            ->willReturn($userData)
        ;

        $this->cacheService->expects($this->once())
            ->method('cacheUser')
            ->with($userId, $userIdType, $userData)
        ;

        $this->dataService->expects($this->once())
            ->method('filterFields')
            ->with($userData, [])
            ->willReturn($userData)
        ;

        $result = $this->service->getUser($userId, $userIdType);

        $this->assertSame($userData, $result);
    }

    public function testGetUserWithCache(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $userData = ['user_id' => $userId, 'name' => 'Test User'];

        $this->dataService->expects($this->once())
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->cacheService->expects($this->once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn($userData)
        ;

        $this->dataService->expects($this->once())
            ->method('filterFields')
            ->with($userData, [])
            ->willReturn($userData)
        ;

        $result = $this->service->getUser($userId, $userIdType);

        $this->assertSame($userData, $result);
    }

    public function testBatchGetUsers(): void
    {
        $userIds = ['u_123', 'u_456'];
        $userIdType = 'user_id';
        $fields = ['name', 'email'];

        $cachedUsers = ['u_123' => ['user_id' => 'u_123', 'name' => 'User 1']];
        $uncachedUserIds = ['u_456'];
        $newUsers = ['u_456' => ['user_id' => 'u_456', 'name' => 'User 2']];

        $this->dataService->expects($this->once())
            ->method('validateUserIdType')
            ->with($userIdType)
        ;

        $this->cacheService->expects($this->once())
            ->method('batchGetUsers')
            ->with($userIds, $userIdType)
            ->willReturn(['cached' => $cachedUsers, 'uncached' => $uncachedUserIds])
        ;

        $this->dataService->expects($this->once())
            ->method('batchFetchUsers')
            ->with($uncachedUserIds, $userIdType)
            ->willReturn($newUsers)
        ;

        $this->cacheService->expects($this->once())
            ->method('batchCacheUsers')
            ->with($newUsers, $userIdType)
        ;

        $this->dataService->expects($this->exactly(2))
            ->method('filterFields')
            ->willReturnCallback(fn ($user, $fields) => $user)
        ;

        $result = $this->service->batchGetUsers($userIds, $userIdType, $fields);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('u_123', $result);
        $this->assertArrayHasKey('u_456', $result);
    }

    public function testSearchUsers(): void
    {
        $params = ['query' => 'test'];
        $searchResult = ['items' => [], 'has_more' => false];

        $this->searchService->expects($this->once())
            ->method('searchUsers')
            ->with($params)
            ->willReturn($searchResult)
        ;

        $result = $this->service->searchUsers($params);

        $this->assertSame($searchResult, $result);
    }

    public function testGetUserDepartments(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $departments = ['items' => [], 'has_more' => false];

        $this->relationService->expects($this->once())
            ->method('getUserDepartments')
            ->with($userId, $userIdType)
            ->willReturn($departments)
        ;

        $result = $this->service->getUserDepartments($userId, $userIdType);

        $this->assertSame($departments, $result);
    }

    public function testHasPermission(): void
    {
        $userId = 'u_123';
        $permission = 'admin';
        $userIdType = 'user_id';

        $this->permissionService->expects($this->once())
            ->method('hasPermission')
            ->with($userId, $permission, $userIdType)
            ->willReturn(true)
        ;

        $result = $this->service->hasPermission($userId, $permission, $userIdType);

        $this->assertTrue($result);
    }

    public function testClearUserCache(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';

        $this->cacheService->expects($this->once())
            ->method('clearUserCache')
            ->with($userId, $userIdType)
        ;

        $this->service->clearUserCache($userId, $userIdType);
    }

    public function testClearAllUserCache(): void
    {
        $this->cacheService->expects($this->once())
            ->method('clearAllCache')
        ;

        $this->service->clearAllUserCache();
    }

    protected function onSetUp(): void
    {
        // 获取服务实例，不再设置 mock
        $this->service = self::getService(UserServiceRefactored::class);
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
        $this->service = self::getService(UserServiceRefactored::class);
    }
}
