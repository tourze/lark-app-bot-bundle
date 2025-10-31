<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Command\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\LarkAppBotBundle\Command\Output\UserInfoFormatter;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserInfoFormatter::class)]
#[RunTestsInSeparateProcesses]
final class UserInfoFormatterTest extends AbstractIntegrationTestCase
{
    private UserInfoFormatter $formatter;

    private SymfonyStyle&MockObject $mockIo;

    /** @var array<string, mixed> */
    private array $sampleUserInfo;

    public function testShowBasicUserInfo(): void
    {
        $this->mockIo->expects($this->once())
            ->method('definitionList')
            ->with(
                ['Open ID' => 'ou_123456'],
                ['用户ID' => 'abc123'],
                ['姓名' => 'Zhang San'],
                ['英文名' => 'John Zhang'],
                ['昵称' => 'Johnny'],
                ['邮箱' => 'zhangsan@example.com'],
                ['手机' => '13800138000'],
                ['员工类型' => 'full_time'],
                ['状态' => '已激活'],
                self::callback(function ($arg) {
                    return \is_array($arg) && isset($arg['入职时间']);
                }),
                ['城市' => 'Beijing'],
                ['国家' => 'CN']
            )
        ;

        $this->formatter->showBasicUserInfo($this->mockIo, $this->sampleUserInfo);
    }

    public function testShowBasicUserInfoWithMissingFields(): void
    {
        $minimalUserInfo = [
            'open_id' => 'ou_123',
        ];

        $this->mockIo->expects($this->once())
            ->method('definitionList')
            ->with(
                ['Open ID' => 'ou_123'],
                ['用户ID' => 'N/A'],
                ['姓名' => 'N/A'],
                ['英文名' => 'N/A'],
                ['昵称' => 'N/A'],
                ['邮箱' => 'N/A'],
                ['手机' => 'N/A'],
                ['员工类型' => 'N/A'],
                ['状态' => 'N/A'],
                ['入职时间' => 'N/A'],
                ['城市' => 'N/A'],
                ['国家' => 'N/A']
            )
        ;

        $this->formatter->showBasicUserInfo($this->mockIo, $minimalUserInfo);
    }

    public function testShowBasicUserInfoFormatsStatus(): void
    {
        $userWithStatus2 = array_merge($this->sampleUserInfo, ['status' => 2]);

        $this->mockIo->expects($this->once())
            ->method('definitionList')
            ->with(
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                self::anything(),
                ['状态' => '已停用'],
                self::anything(),
                self::anything(),
                self::anything()
            )
        ;

        $this->formatter->showBasicUserInfo($this->mockIo, $userWithStatus2);
    }

    public function testShowUserAvatar(): void
    {
        $this->mockIo->expects($this->once())
            ->method('note')
            ->with(self::stringContains('https://example.com/avatar_72.jpg'))
        ;

        $this->formatter->showUserAvatar($this->mockIo, $this->sampleUserInfo);
    }

    public function testShowUserAvatarWhenMissing(): void
    {
        $userWithoutAvatar = [
            'open_id' => 'ou_123',
        ];

        $this->mockIo->expects($this->never())
            ->method('note')
        ;

        $this->formatter->showUserAvatar($this->mockIo, $userWithoutAvatar);
    }

    public function testShowUserAvatarWhenEmpty(): void
    {
        $userWithEmptyAvatar = [
            'open_id' => 'ou_123',
            'avatar' => [
                'avatar_72' => '',
            ],
        ];

        $this->mockIo->expects($this->never())
            ->method('note')
        ;

        $this->formatter->showUserAvatar($this->mockIo, $userWithEmptyAvatar);
    }

    public function testShowUserDepartments(): void
    {
        $this->mockIo->expects($this->once())
            ->method('section')
            ->with('所在部门')
        ;

        $this->mockIo->expects($this->once())
            ->method('table')
            ->with(
                ['部门ID', '部门名称', '部门路径'],
                self::callback(function ($rows): bool {
                    if (!\is_array($rows) || 2 !== \count($rows)) {
                        return false;
                    }
                    $firstRow = $rows[0] ?? null;
                    if (!\is_array($firstRow)) {
                        return false;
                    }
                    $path = $firstRow[2] ?? null;

                    return ($firstRow[0] ?? null) === 'dept_001'
                        && ($firstRow[1] ?? null) === 'Engineering'
                        && \is_string($path) && str_contains($path, 'Company > Engineering');
                })
            )
        ;

        $this->formatter->showUserDepartments($this->mockIo, $this->sampleUserInfo);
    }

    public function testShowUserDepartmentsWhenEmpty(): void
    {
        $userWithoutDepartments = [
            'open_id' => 'ou_123',
            'departments' => [],
        ];

        $this->mockIo->expects($this->never())
            ->method('section')
        ;

        $this->formatter->showUserDepartments($this->mockIo, $userWithoutDepartments);
    }

    public function testShowUserDepartmentsWhenMissing(): void
    {
        $userWithoutDepartments = [
            'open_id' => 'ou_123',
        ];

        $this->mockIo->expects($this->never())
            ->method('section')
        ;

        $this->formatter->showUserDepartments($this->mockIo, $userWithoutDepartments);
    }

    public function testShowUserGroups(): void
    {
        $this->mockIo->expects($this->once())
            ->method('section')
            ->with('所在群组')
        ;

        $this->mockIo->expects($this->once())
            ->method('table')
            ->with(
                ['群组ID', '群组名称', '成员数'],
                self::callback(function ($rows): bool {
                    if (!\is_array($rows) || 1 !== \count($rows)) {
                        return false;
                    }
                    $firstRow = $rows[0] ?? null;
                    if (!\is_array($firstRow)) {
                        return false;
                    }

                    return 'oc_abc123' === ($firstRow[0] ?? null)
                        && 'Team Chat' === ($firstRow[1] ?? null)
                        && 50 === ($firstRow[2] ?? null);
                })
            )
        ;

        $this->formatter->showUserGroups($this->mockIo, $this->sampleUserInfo);
    }

    public function testShowUserGroupsWhenEmpty(): void
    {
        $userWithoutGroups = [
            'open_id' => 'ou_123',
            'groups' => [],
        ];

        $this->mockIo->expects($this->never())
            ->method('section')
        ;

        $this->formatter->showUserGroups($this->mockIo, $userWithoutGroups);
    }

    public function testShowUserGroupsWhenMissing(): void
    {
        $userWithoutGroups = [
            'open_id' => 'ou_123',
        ];

        $this->mockIo->expects($this->never())
            ->method('section')
        ;

        $this->formatter->showUserGroups($this->mockIo, $userWithoutGroups);
    }

    public function testOutputTable(): void
    {
        $this->mockIo->expects($this->once())
            ->method('title')
            ->with('用户信息')
        ;

        $this->mockIo->expects($this->once())
            ->method('definitionList')
        ;

        $this->mockIo->expects($this->once())
            ->method('note')
        ;

        $this->mockIo->expects($this->exactly(2))
            ->method('section')
        ;

        $this->mockIo->expects($this->exactly(2))
            ->method('table')
        ;

        $this->formatter->outputTable($this->mockIo, $this->sampleUserInfo);
    }

    public function testOutputTableWithMinimalData(): void
    {
        $minimalUser = [
            'open_id' => 'ou_minimal',
        ];

        $this->mockIo->expects($this->once())
            ->method('title')
            ->with('用户信息')
        ;

        $this->mockIo->expects($this->once())
            ->method('definitionList')
        ;

        // Should not show sections/tables for missing data
        $this->mockIo->expects($this->never())
            ->method('section')
        ;

        $this->formatter->outputTable($this->mockIo, $minimalUser);
    }

    public function testStatusFormatting(): void
    {
        $statusTests = [
            ['status' => 1, 'expected' => '已激活'],
            ['status' => 2, 'expected' => '已停用'],
            ['status' => 4, 'expected' => '未激活'],
            ['status' => 5, 'expected' => '已退出'],
            ['status' => 99, 'expected' => '未知(99)'],
        ];

        foreach ($statusTests as $test) {
            $userInfo = array_merge($this->sampleUserInfo, ['status' => $test['status']]);

            $this->mockIo->expects($this->once())
                ->method('definitionList')
                ->with(
                    self::anything(),
                    self::anything(),
                    self::anything(),
                    self::anything(),
                    self::anything(),
                    self::anything(),
                    self::anything(),
                    self::anything(),
                    ['状态' => $test['expected']],
                    self::anything(),
                    self::anything(),
                    self::anything()
                )
            ;

            $this->formatter->showBasicUserInfo($this->mockIo, $userInfo);

            // Reset mock for next iteration
            $this->mockIo = $this->createMock(SymfonyStyle::class);
            $this->formatter = self::getService(UserInfoFormatter::class);
        }
    }

    protected function onSetUp(): void
    {
        $this->formatter = self::getService(UserInfoFormatter::class);
        $this->mockIo = $this->createMock(SymfonyStyle::class);

        $this->sampleUserInfo = [
            'open_id' => 'ou_123456',
            'user_id' => 'abc123',
            'name' => 'Zhang San',
            'en_name' => 'John Zhang',
            'nickname' => 'Johnny',
            'email' => 'zhangsan@example.com',
            'mobile' => '13800138000',
            'employee_type' => 'full_time',
            'status' => 1,
            'join_time' => 1640000000,
            'city' => 'Beijing',
            'country' => 'CN',
            'avatar' => [
                'avatar_72' => 'https://example.com/avatar_72.jpg',
            ],
            'departments' => [
                [
                    'id' => 'dept_001',
                    'name' => 'Engineering',
                    'path' => ['Company', 'Engineering'],
                ],
                [
                    'id' => 'dept_002',
                    'name' => 'R&D',
                    'path' => ['Company', 'R&D'],
                ],
            ],
            'groups' => [
                [
                    'chat_id' => 'oc_abc123',
                    'name' => 'Team Chat',
                    'member_count' => 50,
                ],
            ],
        ];
    }
}
