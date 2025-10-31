<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Service\User\Sync\UserDataProcessor;
use Tourze\LarkAppBotBundle\Service\User\UserCacheManagerInterface;
use Tourze\LarkAppBotBundle\Service\User\UserServiceInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserDataProcessor::class)]
#[RunTestsInSeparateProcesses]
final class UserDataProcessorTest extends AbstractIntegrationTestCase
{
    private UserServiceInterface $userService;

    private UserCacheManagerInterface $cacheManager;

    private LoggerInterface $logger;

    private UserDataProcessor $processor;

    public function testProcessUserDataBasic(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com'];

        $this->cacheManager->expects($this->once())
            ->method('cacheUser')
            ->with($userId, $userIdType, $userData)
        ;

        $result = $this->processor->processUserData($userId, $userIdType, $userData);

        $this->assertSame($userData, $result);
    }

    public function testProcessUserDataWithDepartments(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'name' => 'John Doe',
            'department_ids' => ['dept1', 'dept2'],
        ];

        $departments = [
            'dept1' => ['name' => 'Department 1'],
            'dept2' => ['name' => 'Department 2'],
        ];

        $this->cacheManager->expects($this->exactly(2))
            ->method('cacheUser')
        ;

        $this->userService->expects($this->once())
            ->method('getUserDepartments')
            ->with($userId, $userIdType)
            ->willReturn($departments)
        ;

        $result = $this->processor->processUserData($userId, $userIdType, $userData);

        $this->assertSame($userData, $result);
    }

    public function testProcessUserDataWithTenantManager(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'name' => 'John Doe',
            'is_tenant_manager' => true];

        $expectedPermissions = [
            'is_tenant_manager' => true,
            'permissions' => ['*'],
            'roles' => ['tenant_manager'],
        ];

        $this->cacheManager->expects($this->exactly(2))
            ->method('cacheUser')
        ;

        $result = $this->processor->processUserData($userId, $userIdType, $userData);

        $this->assertSame($userData, $result);
    }

    public function testProcessUserDataWithCustomAttributes(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'name' => 'John Doe',
            'custom_attrs' => [
                [
                    'key' => 'permissions',
                    'value' => ['read', 'write'],
                ],
                [
                    'key' => 'roles',
                    'value' => ['editor', 'reviewer'],
                ],
            ],
        ];

        $expectedPermissions = [
            'is_tenant_manager' => false,
            'permissions' => ['read', 'write'],
            'roles' => ['editor', 'reviewer'],
        ];

        $this->cacheManager->expects($this->exactly(2))
            ->method('cacheUser')
        ;

        $result = $this->processor->processUserData($userId, $userIdType, $userData);

        $this->assertSame($userData, $result);
    }

    public function testProcessUserDataWithMixedPermissions(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'name' => 'John Doe',
            'is_tenant_manager' => true,
            'custom_attrs' => [
                [
                    'key' => 'permissions',
                    'value' => ['read', 'write'],
                ],
                [
                    'key' => 'roles',
                    'value' => ['editor'],
                ],
            ],
        ];

        // 权限应该去重，租户管理员权限包含所有权限
        $expectedPermissions = [
            'is_tenant_manager' => true,
            'permissions' => ['*', 'read', 'write'],
            'roles' => ['tenant_manager', 'editor'],
        ];

        $this->cacheManager->expects($this->exactly(2))
            ->method('cacheUser')
        ;

        $result = $this->processor->processUserData($userId, $userIdType, $userData);

        $this->assertSame($userData, $result);
    }

    public function testProcessUserDataWithSyncError(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'name' => 'John Doe',
            'department_ids' => ['dept1'],
        ];

        $this->cacheManager->expects($this->once())
            ->method('cacheUser')
            ->with($userId, $userIdType, $userData)
        ;

        $exception = new \RuntimeException('Department sync failed');
        $this->userService->expects($this->once())
            ->method('getUserDepartments')
            ->willThrowException($exception)
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                '同步用户相关数据失败',
                [
                    'user_id' => $userId,
                    'error' => 'Department sync failed'])
        ;

        $result = $this->processor->processUserData($userId, $userIdType, $userData);

        $this->assertSame($userData, $result);
    }

    public function testGetCachedUserData(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $cachedData = ['name' => 'John Doe'];

        $this->cacheManager->expects($this->once())
            ->method('getCachedUser')
            ->with($userId, $userIdType)
            ->willReturn($cachedData)
        ;

        $result = $this->processor->getCachedUserData($userId, $userIdType);

        $this->assertSame($cachedData, $result);
    }

    public function testGetCachedUserDataNotFound(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';

        $this->cacheManager->expects($this->once())
            ->method('getCachedUser')
            ->with($userId, $userIdType)
            ->willReturn(null)
        ;

        $result = $this->processor->getCachedUserData($userId, $userIdType);

        $this->assertNull($result);
    }

    public function testHandleDeletedUser(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                '用户可能已被删除',
                [
                    'user_id' => $userId,
                    'user_id_type' => $userIdType]
            )
        ;

        $this->cacheManager->expects($this->once())
            ->method('invalidateUser')
            ->with($userId, $userIdType)
        ;

        $this->processor->handleDeletedUser($userId, $userIdType);
    }

    public function testProcessUserDataWithEmptyDepartmentIds(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'name' => 'John Doe',
            'department_ids' => [],
        ];

        $this->cacheManager->expects($this->once())
            ->method('cacheUser')
            ->with($userId, $userIdType, $userData)
        ;

        // 不应该调用 getUserDepartments
        $this->userService->expects($this->never())
            ->method('getUserDepartments')
        ;

        $result = $this->processor->processUserData($userId, $userIdType, $userData);

        $this->assertSame($userData, $result);
    }

    public function testProcessUserDataWithInvalidDepartmentIds(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'name' => 'John Doe',
            'department_ids' => 'invalid_format'];

        $this->cacheManager->expects($this->once())
            ->method('cacheUser')
            ->with($userId, $userIdType, $userData)
        ;

        // 不应该调用 getUserDepartments
        $this->userService->expects($this->never())
            ->method('getUserDepartments')
        ;

        $result = $this->processor->processUserData($userId, $userIdType, $userData);

        $this->assertSame($userData, $result);
    }

    public function testProcessUserDataWithInvalidCustomAttrs(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'name' => 'John Doe',
            'custom_attrs' => 'invalid_format'];

        $this->cacheManager->expects($this->exactly(2))
            ->method('cacheUser')
        ;

        $result = $this->processor->processUserData($userId, $userIdType, $userData);

        $this->assertSame($userData, $result);
    }

    public function testProcessUserDataWithNonStringPermissions(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'name' => 'John Doe',
            'custom_attrs' => [
                [
                    'key' => 'permissions',
                    'value' => ['read', 123, 'write'], // 包含非字符串
                ],
            ],
        ];

        $this->cacheManager->expects($this->exactly(2))
            ->method('cacheUser')
        ;

        $result = $this->processor->processUserData($userId, $userIdType, $userData);

        $this->assertSame($userData, $result);
    }

    public function testProcessUserDataWithNonArrayCustomAttribute(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'name' => 'John Doe',
            'custom_attrs' => [
                'invalid_attr', // 非数组
                [
                    'key' => 'permissions',
                    'value' => ['read', 'write'],
                ],
            ],
        ];

        $expectedPermissions = [
            'is_tenant_manager' => false,
            'permissions' => ['read', 'write'],
            'roles' => [],
        ];

        $this->cacheManager->expects($this->exactly(2))
            ->method('cacheUser')
        ;

        $result = $this->processor->processUserData($userId, $userIdType, $userData);

        $this->assertSame($userData, $result);
    }

    public function testProcessUserDataWithDuplicatePermissions(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'name' => 'John Doe',
            'custom_attrs' => [
                [
                    'key' => 'permissions',
                    'value' => ['read', 'write', 'read'], // 重复权限
                ],
                [
                    'key' => 'roles',
                    'value' => ['editor', 'reviewer', 'editor'], // 重复角色
                ],
            ],
        ];

        $expectedPermissions = [
            'is_tenant_manager' => false,
            'permissions' => ['read', 'write'], // 应该去重
            'roles' => ['editor', 'reviewer'], // 应该去重
        ];

        $this->cacheManager->expects($this->exactly(2))
            ->method('cacheUser')
        ;

        $result = $this->processor->processUserData($userId, $userIdType, $userData);

        $this->assertSame($userData, $result);
    }

    protected function onSetUp(): void
    {
        // 获取服务实例，不再设置 mock
        $this->processor = self::getService(UserDataProcessor::class);
        // 创建 mock 对象
        $this->userService = self::createMock(UserServiceInterface::class);
        self::getContainer()->set(UserServiceInterface::class, $this->userService);
        $this->cacheManager = self::createMock(UserCacheManagerInterface::class);
        self::getContainer()->set(UserCacheManagerInterface::class, $this->cacheManager);
        $this->logger = self::createMock(LoggerInterface::class);
        self::getContainer()->set(LoggerInterface::class, $this->logger);
        $this->processor = self::getService(UserDataProcessor::class);
    }
}
