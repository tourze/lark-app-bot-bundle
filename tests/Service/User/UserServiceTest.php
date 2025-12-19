<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
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

    public function testBatchGetUsersWithEmptyInput(): void
    {
        $result = $this->userService->batchGetUsers([]);

        self::assertSame([], $result);
    }

    public function testClearAllUserCache(): void
    {
        // 测试方法存在且可调用，不验证内部逻辑
        $this->expectNotToPerformAssertions();
        $this->userService->clearAllUserCache();
    }

    public function testClearUserCache(): void
    {
        // 测试清除用户缓存方法
        $this->expectNotToPerformAssertions();
        $this->userService->clearUserCache('test_user_id', 'open_id');
    }

    public function testSearchUsers(): void
    {
        // 测试搜索用户方法
        $result = $this->userService->searchUsers(['query' => 'test']);
        $this->assertIsArray($result);
    }

    protected function onSetUp(): void
    {
        // 使用真实的Symfony服务容器获取服务
        $this->userService = self::getService(UserService::class);
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要Mock服务，全部使用真实实现
    }
}
