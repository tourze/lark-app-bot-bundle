<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\LarkAppBotBundle\Exception\GenericApiException;
use Tourze\LarkAppBotBundle\Service\User\UserDataBuilder;
use Tourze\LarkAppBotBundle\Service\User\UserPermissionExtractor;
use Tourze\LarkAppBotBundle\Service\User\UserServiceInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserDataBuilder::class)]
#[RunTestsInSeparateProcesses]
final class UserDataBuilderTest extends AbstractIntegrationTestCase
{
    private UserDataBuilder $builder;

    private UserServiceInterface $userService;

    private UserPermissionExtractor $permissionExtractor;

    private LoggerInterface $logger;

    public function testBuildUserDataWithCompleteInfo(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';

        $basicInfo = [
            'user_id' => $userId,
            'name' => 'John Doe',
            'is_tenant_manager' => false];

        $departments = [
            ['department_id' => 'dept1', 'name' => 'IT'],
            ['department_id' => 'dept2', 'name' => 'Dev'],
        ];

        $permissions = ['read', 'write'];
        $leader = ['user_id' => 'leader123', 'name' => 'Manager'];
        $subordinates = [];

        $this->userService->expects($this->once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn($basicInfo)
        ;

        $this->userService->expects($this->once())
            ->method('getUserDepartments')
            ->with($userId, $userIdType)
            ->willReturn(['items' => $departments])
        ;

        $this->userService->expects($this->once())
            ->method('getUserLeader')
            ->with($userId, $userIdType)
            ->willReturn($leader)
        ;

        $this->userService->expects($this->never())
            ->method('getUserSubordinates')
        ;

        $this->permissionExtractor->expects($this->once())
            ->method('extractPermissions')
            ->with($basicInfo)
            ->willReturn($permissions)
        ;

        $result = $this->builder->buildUserData($userId, $userIdType);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('basic_info', $result);
        $this->assertArrayHasKey('departments', $result);
        $this->assertArrayHasKey('permissions', $result);
        $this->assertArrayHasKey('leader', $result);
        $this->assertArrayHasKey('subordinates', $result);
        $this->assertSame($basicInfo, $result['basic_info']);
        $this->assertSame($departments, $result['departments']);
        $this->assertSame($permissions, $result['permissions']);
        $this->assertSame($leader, $result['leader']);
        $this->assertSame($subordinates, $result['subordinates']);
        $this->assertSame([], $result['custom_data']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertSame('1.0', $result['metadata']['version']);
        $this->assertSame('synced', $result['metadata']['sync_status']);
    }

    public function testBuildUserDataWithTenantManager(): void
    {
        $userId = 'manager123';
        $userIdType = 'user_id';

        $basicInfo = [
            'user_id' => $userId,
            'name' => 'Manager',
            'is_tenant_manager' => true];

        $subordinates = [
            ['user_id' => 'user1', 'name' => 'User 1'],
            ['user_id' => 'user2', 'name' => 'User 2'],
        ];

        $this->userService->expects($this->once())
            ->method('getUser')
            ->with($userId, $userIdType)
            ->willReturn($basicInfo)
        ;

        $this->userService->expects($this->once())
            ->method('getUserDepartments')
            ->with($userId, $userIdType)
            ->willReturn(['items' => []])
        ;

        $this->userService->expects($this->once())
            ->method('getUserLeader')
            ->with($userId, $userIdType)
            ->willReturn(null)
        ;

        $this->userService->expects($this->once())
            ->method('getUserSubordinates')
            ->with($userId, $userIdType)
            ->willReturn($subordinates)
        ;

        $this->permissionExtractor->expects($this->once())
            ->method('extractPermissions')
            ->with($basicInfo)
            ->willReturn(['tenant_admin'])
        ;

        $result = $this->builder->buildUserData($userId, $userIdType);

        $this->assertSame($subordinates, $result['subordinates']);
    }

    public function testBuildUserDataWithSubordinatesException(): void
    {
        $userId = 'manager123';
        $userIdType = 'user_id';

        $basicInfo = [
            'user_id' => $userId,
            'name' => 'Manager',
            'is_tenant_manager' => true];

        $this->userService->expects($this->once())
            ->method('getUser')
            ->willReturn($basicInfo)
        ;

        $this->userService->expects($this->once())
            ->method('getUserDepartments')
            ->willReturn(['items' => []])
        ;

        $this->userService->expects($this->once())
            ->method('getUserLeader')
            ->willReturn(null)
        ;

        $this->userService->expects($this->once())
            ->method('getUserSubordinates')
            ->willThrowException(new \Exception('API error'))
        ;

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('获取用户下属信息失败', [
                'user_id' => $userId,
                'error' => 'API error',
            ])
        ;

        $this->permissionExtractor->expects($this->once())
            ->method('extractPermissions')
            ->willReturn(['tenant_admin'])
        ;

        $result = $this->builder->buildUserData($userId, $userIdType);

        $this->assertSame([], $result['subordinates']);
    }

    public function testBatchBuildUserDataSuccess(): void
    {
        $userIds = ['user1', 'user2'];
        $userIdType = 'user_id';

        $basicInfoMap = [
            'user1' => ['user_id' => 'user1', 'name' => 'User 1'],
            'user2' => ['user_id' => 'user2', 'name' => 'User 2']];

        $this->userService->expects($this->once())
            ->method('batchGetUsers')
            ->with($userIds, $userIdType)
            ->willReturn($basicInfoMap)
        ;

        $this->userService->expects($this->exactly(2))
            ->method('getUserDepartments')
            ->willReturn(['items' => []])
        ;

        $this->permissionExtractor->expects($this->exactly(2))
            ->method('extractPermissions')
            ->willReturn([])
        ;

        $result = $this->builder->batchBuildUserData($userIds, $userIdType);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('user1', $result);
        $this->assertArrayHasKey('user2', $result);
        $this->assertSame($basicInfoMap['user1'], $result['user1']['basic_info']);
        $this->assertSame($basicInfoMap['user2'], $result['user2']['basic_info']);
    }

    public function testBatchBuildUserDataWithException(): void
    {
        $userIds = ['user1', 'user2'];
        $userIdType = 'user_id';

        $basicInfoMap = [
            'user1' => ['user_id' => 'user1', 'name' => 'User 1'],
            'user2' => ['user_id' => 'user2', 'name' => 'User 2']];

        $this->userService->expects($this->once())
            ->method('batchGetUsers')
            ->willReturn($basicInfoMap)
        ;

        $this->userService->expects($this->exactly(2))
            ->method('getUserDepartments')
            ->willReturnCallback(function ($userId) {
                if ('user2' === $userId) {
                    throw new GenericApiException('Department error');
                }

                return ['items' => []];
            })
        ;

        $this->permissionExtractor->expects($this->once())
            ->method('extractPermissions')
            ->willReturn([])
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('构建用户数据失败', [
                'user_id' => 'user2',
                'error' => 'Department error',
            ])
        ;

        $result = $this->builder->batchBuildUserData($userIds, $userIdType);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('user1', $result);
        $this->assertArrayNotHasKey('user2', $result);
    }

    public function testBuildUserDataFromBasicInfoWithLeader(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $basicInfo = [
            'user_id' => $userId,
            'name' => 'John Doe',
            'leader_user_id' => 'leader123'];

        $leader = ['user_id' => 'leader123', 'name' => 'Manager'];

        $this->userService->expects($this->once())
            ->method('getUserDepartments')
            ->with($userId, $userIdType)
            ->willReturn(['items' => []])
        ;

        $this->userService->expects($this->once())
            ->method('getUser')
            ->with('leader123', 'user_id')
            ->willReturn($leader)
        ;

        $this->permissionExtractor->expects($this->once())
            ->method('extractPermissions')
            ->with($basicInfo)
            ->willReturn([])
        ;

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildUserDataFromBasicInfo');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, $userId, $userIdType, $basicInfo);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('leader', $result);
        $this->assertSame($leader, $result['leader']);
    }

    public function testBuildUserDataFromBasicInfoWithLeaderException(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $basicInfo = [
            'user_id' => $userId,
            'name' => 'John Doe',
            'leader_user_id' => 'leader123'];

        $this->userService->expects($this->once())
            ->method('getUserDepartments')
            ->willReturn(['items' => []])
        ;

        $this->userService->expects($this->once())
            ->method('getUser')
            ->with('leader123', 'user_id')
            ->willThrowException(new \Exception('Leader not found'))
        ;

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('获取用户上级信息失败', [
                'leader_id' => 'leader123',
                'error' => 'Leader not found',
            ])
        ;

        $this->permissionExtractor->expects($this->once())
            ->method('extractPermissions')
            ->willReturn([])
        ;

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildUserDataFromBasicInfo');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, $userId, $userIdType, $basicInfo);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('leader', $result);
        $this->assertNull($result['leader']);
    }

    public function testBuildUserDataFromBasicInfoWithoutLeader(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $basicInfo = [
            'user_id' => $userId,
            'name' => 'John Doe'];

        $this->userService->expects($this->once())
            ->method('getUserDepartments')
            ->willReturn(['items' => []])
        ;

        $this->userService->expects($this->never())
            ->method('getUser')
        ;

        $this->permissionExtractor->expects($this->once())
            ->method('extractPermissions')
            ->willReturn([])
        ;

        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildUserDataFromBasicInfo');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, $userId, $userIdType, $basicInfo);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('leader', $result);
        $this->assertNull($result['leader']);
    }

    public function testMetadataStructure(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';

        $this->userService->expects($this->once())
            ->method('getUser')
            ->willReturn(['user_id' => $userId])
        ;

        $this->userService->expects($this->once())
            ->method('getUserDepartments')
            ->willReturn(['items' => []])
        ;

        $this->userService->expects($this->once())
            ->method('getUserLeader')
            ->willReturn(null)
        ;

        $this->permissionExtractor->expects($this->once())
            ->method('extractPermissions')
            ->willReturn([])
        ;

        $result = $this->builder->buildUserData($userId, $userIdType);

        $metadata = $result['metadata'];
        $this->assertIsArray($metadata);
        $this->assertSame('1.0', $metadata['version']);
        $this->assertSame('synced', $metadata['sync_status']);
        $this->assertSame('api', $metadata['data_source']);
        $this->assertArrayHasKey('last_sync', $metadata);
        $this->assertGreaterThan(0, $metadata['last_sync']);
    }

    protected function prepareMockServices(): void
    {
        $this->userService = self::createMock(UserServiceInterface::class);
        $this->permissionExtractor = self::createMock(UserPermissionExtractor::class);
        $this->logger = self::createMock(LoggerInterface::class);
    }

    protected function onSetUp(): void
    {
        // 获取服务实例，不再设置 mock
        $this->builder = self::getService(UserDataBuilder::class);
        // 创建 mock 对象
        $this->userService = self::createMock(UserServiceInterface::class);
        $this->permissionExtractor = self::createMock(UserPermissionExtractor::class);
        $this->logger = self::createMock(LoggerInterface::class);
        $this->builder = self::getService(UserDataBuilder::class);
    }
}
