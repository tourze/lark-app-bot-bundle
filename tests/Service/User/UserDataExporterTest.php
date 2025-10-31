<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Service\User\UserDataExporter;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserDataExporter::class)]
#[RunTestsInSeparateProcesses]
final class UserDataExporterTest extends AbstractIntegrationTestCase
{
    private UserDataExporter $exporter;

    public function testExportWithDefaultOptions(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'basic_info' => ['name' => 'John Doe', 'email' => 'john@example.com'],
            'departments' => [['id' => 'dept1', 'name' => 'IT']],
            'permissions' => ['read', 'write'],
            'leader' => ['user_id' => 'leader1', 'name' => 'Manager'],
            'subordinates' => [
                ['user_id' => 'sub1', 'name' => 'Subordinate 1', 'email' => 'sub1@example.com'],
            ],
            'custom_data' => ['key1' => 'value1'],
            'metadata' => ['version' => '1.0']];

        $result = $this->exporter->export($userId, $userIdType, $userData);

        $this->assertIsArray($result);
        $this->assertSame($userId, $result['user_id']);
        $this->assertSame($userIdType, $result['user_id_type']);
        $this->assertSame('1.0', $result['export_version']);
        $this->assertArrayHasKey('export_time', $result);
        $this->assertIsInt($result['export_time']);

        // Default options should include most data except metadata
        $this->assertSame($userData['basic_info'], $result['basic_info']);
        $this->assertSame($userData['departments'], $result['departments']);
        $this->assertSame($userData['permissions'], $result['permissions']);
        $this->assertSame($userData['leader'], $result['leader']);
        $this->assertSame($userData['custom_data'], $result['custom_data']);
        $this->assertArrayNotHasKey('metadata', $result);

        // Check subordinates are filtered
        $expectedSubordinates = [
            ['user_id' => 'sub1', 'name' => 'Subordinate 1', 'email' => 'sub1@example.com'],
        ];
        $this->assertSame($expectedSubordinates, $result['subordinates']);
    }

    public function testExportWithCustomOptions(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'basic_info' => ['name' => 'John Doe'],
            'departments' => [['id' => 'dept1']],
            'permissions' => ['read'],
            'leader' => ['user_id' => 'leader1'],
            'subordinates' => [],
            'custom_data' => ['key1' => 'value1'],
            'metadata' => ['version' => '1.0'],
        ];

        $options = [
            'include_basic_info' => true,
            'include_departments' => false,
            'include_permissions' => false,
            'include_relations' => false,
            'include_custom_data' => false,
            'include_metadata' => true,
        ];

        $result = $this->exporter->export($userId, $userIdType, $userData, $options);

        $this->assertSame($userData['basic_info'], $result['basic_info']);
        $this->assertSame($userData['metadata'], $result['metadata']);
        $this->assertArrayNotHasKey('departments', $result);
        $this->assertArrayNotHasKey('permissions', $result);
        $this->assertArrayNotHasKey('leader', $result);
        $this->assertArrayNotHasKey('subordinates', $result);
        $this->assertArrayNotHasKey('custom_data', $result);
    }

    public function testExportExcludeBasicInfo(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'basic_info' => ['name' => 'John Doe'],
            'departments' => [],
            'permissions' => [],
            'leader' => null,
            'subordinates' => [],
            'custom_data' => [],
            'metadata' => []];

        $options = ['include_basic_info' => false];

        $result = $this->exporter->export($userId, $userIdType, $userData, $options);

        $this->assertArrayNotHasKey('basic_info', $result);
    }

    public function testExportExcludeDepartments(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'basic_info' => [],
            'departments' => [['id' => 'dept1']],
            'permissions' => [],
            'leader' => null,
            'subordinates' => [],
            'custom_data' => [],
            'metadata' => []];

        $options = ['include_departments' => false];

        $result = $this->exporter->export($userId, $userIdType, $userData, $options);

        $this->assertArrayNotHasKey('departments', $result);
    }

    public function testExportExcludePermissions(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'basic_info' => [],
            'departments' => [],
            'permissions' => ['read', 'write'],
            'leader' => null,
            'subordinates' => [],
            'custom_data' => [],
            'metadata' => []];

        $options = ['include_permissions' => false];

        $result = $this->exporter->export($userId, $userIdType, $userData, $options);

        $this->assertArrayNotHasKey('permissions', $result);
    }

    public function testExportExcludeRelations(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'basic_info' => [],
            'departments' => [],
            'permissions' => [],
            'leader' => ['user_id' => 'leader1'],
            'subordinates' => [['user_id' => 'sub1']],
            'custom_data' => [],
            'metadata' => []];

        $options = ['include_relations' => false];

        $result = $this->exporter->export($userId, $userIdType, $userData, $options);

        $this->assertArrayNotHasKey('leader', $result);
        $this->assertArrayNotHasKey('subordinates', $result);
    }

    public function testExportExcludeCustomData(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'basic_info' => [],
            'departments' => [],
            'permissions' => [],
            'leader' => null,
            'subordinates' => [],
            'custom_data' => ['key1' => 'value1'],
            'metadata' => []];

        $options = ['include_custom_data' => false];

        $result = $this->exporter->export($userId, $userIdType, $userData, $options);

        $this->assertArrayNotHasKey('custom_data', $result);
    }

    public function testExportIncludeMetadata(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'basic_info' => [],
            'departments' => [],
            'permissions' => [],
            'leader' => null,
            'subordinates' => [],
            'custom_data' => [],
            'metadata' => ['version' => '1.0', 'last_sync' => 1234567890]];

        $options = ['include_metadata' => true];

        $result = $this->exporter->export($userId, $userIdType, $userData, $options);

        $this->assertSame($userData['metadata'], $result['metadata']);
    }

    public function testExportSubordinatesFiltering(): void
    {
        $userId = 'user123';
        $userIdType = 'user_id';
        $userData = [
            'basic_info' => [],
            'departments' => [],
            'permissions' => [],
            'leader' => null,
            'subordinates' => [
                [
                    'user_id' => 'sub1',
                    'name' => 'Subordinate 1',
                    'email' => 'sub1@example.com',
                    'department' => 'IT',
                    'title' => 'Developer',
                ],
                [
                    'user_id' => 'sub2',
                    'name' => 'Subordinate 2',
                    'extra_field' => 'should not be included',
                ],
                [
                    'name' => 'No User ID',
                    'email' => 'no-id@example.com',
                ],
            ],
            'custom_data' => [],
            'metadata' => [],
        ];

        $result = $this->exporter->export($userId, $userIdType, $userData);

        $expectedSubordinates = [
            ['user_id' => 'sub1', 'name' => 'Subordinate 1', 'email' => 'sub1@example.com'],
            ['user_id' => 'sub2', 'name' => 'Subordinate 2', 'email' => ''],
            ['user_id' => '', 'name' => 'No User ID', 'email' => 'no-id@example.com'],
        ];

        $this->assertSame($expectedSubordinates, $result['subordinates']);
    }

    public function testExportTimestamp(): void
    {
        $beforeExport = time();

        $result = $this->exporter->export('user123', 'user_id', [
            'basic_info' => [],
            'departments' => [],
            'permissions' => [],
            'leader' => null,
            'subordinates' => [],
            'custom_data' => [],
            'metadata' => [],
        ]);

        $afterExport = time();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('export_time', $result);
        $this->assertGreaterThanOrEqual($beforeExport, $result['export_time']);
        $this->assertLessThanOrEqual($afterExport, $result['export_time']);
    }

    public function testExportVersion(): void
    {
        $result = $this->exporter->export('user123', 'user_id', [
            'basic_info' => [],
            'departments' => [],
            'permissions' => [],
            'leader' => null,
            'subordinates' => [],
            'custom_data' => [],
            'metadata' => [],
        ]);

        $this->assertSame('1.0', $result['export_version']);
    }

    protected function onSetUp(): void
    {
        // 从容器获取服务实例
        $this->exporter = self::getService(UserDataExporter::class);
    }
}
