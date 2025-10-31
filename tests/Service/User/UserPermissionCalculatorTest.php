<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Service\User\UserPermissionCalculator;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserPermissionCalculator::class)]
#[RunTestsInSeparateProcesses]
final class UserPermissionCalculatorTest extends AbstractIntegrationTestCase
{
    private UserPermissionCalculator $calculator;

    public function testCalculateUserPermissions(): void
    {
        $user = [
            'department_ids' => ['dept_1', 'dept_2'],
            'leader_user_id' => 'leader_123',
            'employee_type' => 1];

        $result = $this->calculator->calculateUserPermissions($user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('department_permissions', $result);
        $this->assertArrayHasKey('employee_permissions', $result);
        $this->assertArrayHasKey('leadership_permissions', $result);
    }

    public function testHasPermission(): void
    {
        $permissions = ['read', 'write'];

        $this->assertTrue($this->calculator->hasPermission($permissions, 'read'));
        $this->assertTrue($this->calculator->hasPermission($permissions, 'write'));
        $this->assertFalse($this->calculator->hasPermission($permissions, 'delete'));
    }

    public function testHasPermissionWithWildcard(): void
    {
        $permissions = ['*'];

        $this->assertTrue($this->calculator->hasPermission($permissions, 'any_permission'));
        $this->assertTrue($this->calculator->hasPermission($permissions, 'another_permission'));
    }

    public function testHasPermissionEmpty(): void
    {
        $permissions = [];

        $this->assertFalse($this->calculator->hasPermission($permissions, 'read'));
    }

    public function testParsePermissions(): void
    {
        $user = [
            'is_tenant_manager' => true,
            'employee_type' => 2,
            'positions' => [
                ['position_code' => 'DEV001', 'position_name' => 'Senior Developer']],
            'custom_attrs' => [
                ['key' => 'permissions', 'value' => ['custom_permission_1', 'custom_permission_2']]]];

        $result = $this->calculator->parsePermissions($user);

        $this->assertIsArray($result);
        $this->assertContains('tenant_admin', $result);
        $this->assertContains('*', $result);
        $this->assertContains('employee_type:2', $result);
        $this->assertContains('position:DEV001', $result);
        $this->assertContains('position_name:Senior Developer', $result);
        $this->assertContains('custom_permission_1', $result);
        $this->assertContains('custom_permission_2', $result);
    }

    public function testParsePermissionsWithoutTenantManager(): void
    {
        $user = [
            'employee_type' => 1,
            'positions' => [],
            'custom_attrs' => []];

        $result = $this->calculator->parsePermissions($user);

        $this->assertIsArray($result);
        $this->assertContains('employee_type:1', $result);
        $this->assertNotContains('tenant_admin', $result);
        $this->assertNotContains('*', $result);
    }

    public function testCalculateOrgLevel(): void
    {
        $tenantManagerUser = ['is_tenant_manager' => true];
        $this->assertSame(0, $this->calculator->calculateOrgLevel($tenantManagerUser));

        $ceoUser = ['job_level_name' => 'CEO'];
        $this->assertSame(1, $this->calculator->calculateOrgLevel($ceoUser));

        $vpUser = ['job_level_name' => 'VP of Engineering'];
        $this->assertSame(2, $this->calculator->calculateOrgLevel($vpUser));

        $directorUser = ['job_level_name' => 'Director'];
        $this->assertSame(3, $this->calculator->calculateOrgLevel($directorUser));

        $managerUser = ['job_level_name' => 'Manager'];
        $this->assertSame(4, $this->calculator->calculateOrgLevel($managerUser));

        $employeeWithLeader = ['leader_user_id' => 'leader_123'];
        $this->assertSame(5, $this->calculator->calculateOrgLevel($employeeWithLeader));

        $employeeWithoutLeader = [];
        $this->assertSame(6, $this->calculator->calculateOrgLevel($employeeWithoutLeader));
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象

        $this->calculator = self::getService(UserPermissionCalculator::class);
    }
}
