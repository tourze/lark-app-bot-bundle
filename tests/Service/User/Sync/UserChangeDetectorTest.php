<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Service\User\Sync\UserChangeDetector;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserChangeDetector::class)]
#[RunTestsInSeparateProcesses]
final class UserChangeDetectorTest extends AbstractIntegrationTestCase
{
    private UserChangeDetector $detector;

    public function testDetectChangesWithNoChanges(): void
    {
        $oldData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 1,
        ];
        $newData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 1,
        ];

        $changes = $this->detector->detectChanges($oldData, $newData);

        $this->assertEmpty($changes);
    }

    public function testDetectChangesWithStringChanges(): void
    {
        $oldData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];
        $newData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ];

        $changes = $this->detector->detectChanges($oldData, $newData);

        $this->assertIsArray($changes);
        $this->assertCount(2, $changes);
        $this->assertSame(['old' => 'John Doe', 'new' => 'Jane Doe'], $changes['name']);
        $this->assertSame(['old' => 'john@example.com', 'new' => 'jane@example.com'], $changes['email']);
    }

    public function testDetectChangesWithArrayChanges(): void
    {
        $oldData = [
            'department_ids' => ['dept1', 'dept2'],
        ];
        $newData = [
            'department_ids' => ['dept1', 'dept3'],
        ];

        $changes = $this->detector->detectChanges($oldData, $newData);

        $this->assertIsArray($changes);
        $this->assertCount(1, $changes);
        $this->assertSame(['old' => ['dept1', 'dept2'], 'new' => ['dept1', 'dept3']], $changes['department_ids']);
    }

    public function testDetectChangesWithNullValues(): void
    {
        $oldData = [
            'mobile' => null,
        ];
        $newData = [
            'mobile' => '13800138000',
        ];

        $changes = $this->detector->detectChanges($oldData, $newData);

        $this->assertIsArray($changes);
        $this->assertCount(1, $changes);
        $this->assertSame(['old' => null, 'new' => '13800138000'], $changes['mobile']);
    }

    public function testDetectChangesWithMissingOldField(): void
    {
        $oldData = [];
        $newData = [
            'name' => 'John Doe',
        ];

        $changes = $this->detector->detectChanges($oldData, $newData);

        $this->assertIsArray($changes);
        $this->assertCount(1, $changes);
        $this->assertSame(['old' => null, 'new' => 'John Doe'], $changes['name']);
    }

    public function testShouldDispatchUpdateEventWithNoOldData(): void
    {
        $newData = ['name' => 'John Doe'];

        $result = $this->detector->shouldDispatchUpdateEvent(null, $newData);

        $this->assertFalse($result);
    }

    public function testShouldDispatchUpdateEventWithNoChanges(): void
    {
        $oldData = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $newData = ['name' => 'John Doe', 'email' => 'john@example.com'];

        $result = $this->detector->shouldDispatchUpdateEvent($oldData, $newData);

        $this->assertFalse($result);
    }

    public function testShouldDispatchUpdateEventWithChanges(): void
    {
        $oldData = ['name' => 'John Doe'];
        $newData = ['name' => 'Jane Doe'];

        $result = $this->detector->shouldDispatchUpdateEvent($oldData, $newData);

        $this->assertTrue($result);
    }

    public function testHasChangesWithNullOldData(): void
    {
        $newData = ['name' => 'John Doe'];

        $result = $this->detector->hasChanges(null, $newData);

        $this->assertFalse($result);
    }

    public function testHasChangesWithNoChanges(): void
    {
        $oldData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 1,
        ];
        $newData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 1,
        ];

        $result = $this->detector->hasChanges($oldData, $newData);

        $this->assertFalse($result);
    }

    public function testHasChangesWithKeyFieldChanges(): void
    {
        $oldData = ['name' => 'John Doe'];
        $newData = ['name' => 'Jane Doe'];

        $result = $this->detector->hasChanges($oldData, $newData);

        $this->assertTrue($result);
    }

    public function testHasChangesWithNonKeyFieldChanges(): void
    {
        $oldData = ['non_key_field' => 'value1'];
        $newData = ['non_key_field' => 'value2'];

        $result = $this->detector->hasChanges($oldData, $newData);

        $this->assertFalse($result);
    }

    public function testGetChangeSummaryWithNullOldData(): void
    {
        $newData = ['name' => 'John Doe'];

        $summary = $this->detector->getChangeSummary(null, $newData);

        $this->assertFalse($summary['has_changes']);
        $this->assertEmpty($summary['changed_fields']);
        $this->assertEmpty($summary['critical_changes']);
    }

    public function testGetChangeSummaryWithNoChanges(): void
    {
        $oldData = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $newData = ['name' => 'John Doe', 'email' => 'john@example.com'];

        $summary = $this->detector->getChangeSummary($oldData, $newData);

        $this->assertFalse($summary['has_changes']);
        $this->assertEmpty($summary['changed_fields']);
        $this->assertEmpty($summary['critical_changes']);
    }

    public function testGetChangeSummaryWithRegularChanges(): void
    {
        $oldData = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $newData = ['name' => 'Jane Doe', 'email' => 'jane@example.com'];

        $summary = $this->detector->getChangeSummary($oldData, $newData);

        $this->assertTrue($summary['has_changes']);
        $this->assertSame(['name', 'email'], $summary['changed_fields']);
        $this->assertEmpty($summary['critical_changes']);
    }

    public function testGetChangeSummaryWithCriticalChanges(): void
    {
        $oldData = [
            'name' => 'John Doe',
            'status' => 1,
            'department_ids' => ['dept1'],
            'is_tenant_manager' => false,
        ];
        $newData = [
            'name' => 'Jane Doe',
            'status' => 2,
            'department_ids' => ['dept2'],
            'is_tenant_manager' => true,
        ];

        $summary = $this->detector->getChangeSummary($oldData, $newData);

        $this->assertTrue($summary['has_changes']);
        $this->assertSame(['name', 'status', 'department_ids', 'is_tenant_manager'], $summary['changed_fields']);
        $this->assertSame(['status', 'department_ids', 'is_tenant_manager'], $summary['critical_changes']);
    }

    public function testGetChangeSummaryWithMixedChanges(): void
    {
        $oldData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 1,
        ];
        $newData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'status' => 2,
        ];

        $summary = $this->detector->getChangeSummary($oldData, $newData);

        $this->assertTrue($summary['has_changes']);
        $this->assertSame(['name', 'email', 'status'], $summary['changed_fields']);
        $this->assertSame(['status'], $summary['critical_changes']);
    }

    public function testAllKeyFieldsAreChecked(): void
    {
        $oldData = [
            'name' => 'John',
            'en_name' => 'John',
            'email' => 'john@example.com',
            'mobile' => '13800138000',
            'status' => 1,
            'department_ids' => ['dept1'],
            'leader_user_id' => 'leader1',
            'is_tenant_manager' => false,
        ];
        $newData = [
            'name' => 'Jane',
            'en_name' => 'Jane',
            'email' => 'jane@example.com',
            'mobile' => '13900139000',
            'status' => 2,
            'department_ids' => ['dept2'],
            'leader_user_id' => 'leader2',
            'is_tenant_manager' => true,
        ];

        $summary = $this->detector->getChangeSummary($oldData, $newData);

        $this->assertTrue($summary['has_changes']);
        $this->assertIsArray($summary);
        $this->assertCount(8, $summary['changed_fields']);
        $this->assertSame(['status', 'department_ids', 'is_tenant_manager'], $summary['critical_changes']);
    }

    protected function prepareMockServices(): void
    {
        // 此测试不需要 Mock 服务
    }

    protected function onSetUp(): void
    {
        // 创建 mock 对象

        $this->detector = self::getService(UserChangeDetector::class);
    }
}
