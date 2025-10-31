<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Service\User\UserPermissionExtractor;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserPermissionExtractor::class)]
#[RunTestsInSeparateProcesses]
final class UserPermissionExtractorTest extends AbstractIntegrationTestCase
{
    private UserPermissionExtractor $extractor;

    public function testExtractPermissionsWithTenantManager(): void
    {
        $basicInfo = [
            'is_tenant_manager' => true];

        $result = $this->extractor->extractPermissions($basicInfo);

        $this->assertContains(['name' => 'tenant_admin', 'scope' => 'global'], $result);
        $this->assertContains(['name' => '*', 'scope' => 'global'], $result);
    }

    public function testExtractPermissionsWithCustomPermissions(): void
    {
        $basicInfo = [
            'custom_attrs' => [
                [
                    'key' => 'permissions',
                    'value' => ['read', 'write', 'delete']],
                [
                    'key' => 'other',
                    'value' => 'value']]];

        $result = $this->extractor->extractPermissions($basicInfo);

        $this->assertContains(['name' => 'read', 'scope' => 'custom'], $result);
        $this->assertContains(['name' => 'write', 'scope' => 'custom'], $result);
        $this->assertContains(['name' => 'delete', 'scope' => 'custom'], $result);
    }

    public function testExtractPermissionsWithPositions(): void
    {
        $basicInfo = [
            'positions' => [
                ['position_code' => 'manager'], ['position_code' => 'developer'],
                ['name' => 'Position without code']]];

        $result = $this->extractor->extractPermissions($basicInfo);

        $this->assertContains(['name' => 'position:manager', 'scope' => 'position'], $result);
        $this->assertContains(['name' => 'position:developer', 'scope' => 'position'], $result);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testExtractPermissionsWithAllTypes(): void
    {
        $basicInfo = [
            'is_tenant_manager' => true,
            'custom_attrs' => [
                [
                    'key' => 'permissions',
                    'value' => ['custom_permission']]],
            'positions' => [
                ['position_code' => 'manager']]];

        $result = $this->extractor->extractPermissions($basicInfo);

        $this->assertContains(['name' => 'tenant_admin', 'scope' => 'global'], $result);
        $this->assertContains(['name' => '*', 'scope' => 'global'], $result);
        $this->assertContains(['name' => 'custom_permission', 'scope' => 'custom'], $result);
        $this->assertContains(['name' => 'position:manager', 'scope' => 'position'], $result);
    }

    public function testExtractPermissionsWithDuplicates(): void
    {
        $basicInfo = [
            'custom_attrs' => [
                [
                    'key' => 'permissions',
                    'value' => ['read', 'write', 'read'], // Duplicate 'read'
                ]]];

        $result = $this->extractor->extractPermissions($basicInfo);

        $this->assertContains(['name' => 'read', 'scope' => 'custom'], $result);
        $this->assertContains(['name' => 'write', 'scope' => 'custom'], $result);
        $this->assertIsArray($result);
        $this->assertCount(3, $result); // Now returns all entries including duplicates
    }

    public function testExtractPermissionsWithEmptyData(): void
    {
        $basicInfo = [];

        $result = $this->extractor->extractPermissions($basicInfo);

        $this->assertEmpty($result);
    }

    public function testExtractPermissionsWithInvalidCustomAttrs(): void
    {
        $basicInfo = [
            'custom_attrs' => [
                [
                    'key' => 'permissions',
                    'value' => 'not_an_array', // Invalid value type
                ]]];

        $result = $this->extractor->extractPermissions($basicInfo);

        $this->assertEmpty($result);
    }

    public function testExtractPermissionsWithFalseIsTenantManager(): void
    {
        $basicInfo = [
            'is_tenant_manager' => false];

        $result = $this->extractor->extractPermissions($basicInfo);

        $this->assertEmpty($result);
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象

        $this->extractor = self::getService(UserPermissionExtractor::class);
    }
}
