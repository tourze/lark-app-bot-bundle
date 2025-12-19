<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\LarkAppBotBundle\Service\User\UserCacheService;
use Tourze\LarkAppBotBundle\Service\User\UserDataService;
use Tourze\LarkAppBotBundle\Service\User\UserPermissionService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserPermissionService::class)]
#[RunTestsInSeparateProcesses]
final class UserPermissionServiceTest extends AbstractIntegrationTestCase
{
    private UserPermissionService $service;

    private MockObject $userDataService;

    private MockObject $cacheService;

    public function testHasPermissionForTenantManager(): void
    {
        $userId = 'u_123';
        $permission = 'admin';
        $userIdType = 'user_id';
        $user = ['user_id' => $userId, 'is_tenant_manager' => true];

        $this->cacheService->expects($this->once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn($user)
        ;

        $result = $this->service->hasPermission($userId, $permission, $userIdType);

        $this->assertTrue($result);
    }

    public function testHasPermissionForNormalUser(): void
    {
        $userId = 'u_123';
        $permission = 'read';
        $userIdType = 'user_id';
        $user = [
            'user_id' => $userId,
            'is_tenant_manager' => false,
            'positions' => [
                ['name' => 'developer']]];

        $this->cacheService->expects($this->once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn($user)
        ;

        $result = $this->service->hasPermission($userId, $permission, $userIdType);

        $this->assertIsBool($result);
    }

    public function testGetUserPermissionsForTenantManager(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $user = ['user_id' => $userId, 'is_tenant_manager' => true];

        $this->cacheService->expects($this->once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn($user)
        ;

        $result = $this->service->getUserPermissions($userId, $userIdType);

        $this->assertSame(['*'], $result);
    }

    public function testGetUserPermissionsForNormalUser(): void
    {
        $userId = 'u_123';
        $userIdType = 'user_id';
        $user = [
            'user_id' => $userId,
            'is_tenant_manager' => false,
            'positions' => [
                ['name' => 'developer']]];

        $this->cacheService->expects($this->once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn($user)
        ;

        $result = $this->service->getUserPermissions($userId, $userIdType);

        $this->assertIsArray($result);
    }

    protected function onSetUp(): void
    {
        // 获取服务实例，不再设置 mock
        $this->service = self::getService(UserPermissionService::class);
        // 创建 mock 对象
        $this->userDataService = self::createMock(UserDataService::class);
        self::getContainer()->set(UserDataService::class, $this->userDataService);
        $this->cacheService = self::createMock(UserCacheService::class);
        self::getContainer()->set(UserCacheService::class, $this->cacheService);
        $this->service = self::getService(UserPermissionService::class);
    }
}
