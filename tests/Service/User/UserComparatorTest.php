<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\LarkAppBotBundle\Service\User\UserComparator;
use Tourze\LarkAppBotBundle\Service\User\UserFormatter;
use Tourze\LarkAppBotBundle\Service\User\UserStatusChecker;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(UserComparator::class)]
#[RunTestsInSeparateProcesses]
final class UserComparatorTest extends AbstractIntegrationTestCase
{
    private UserComparator $comparator;

    private UserStatusChecker $statusChecker;

    private UserFormatter $formatter;

    public function testCompareUserInfoWithNoChanges(): void
    {
        $oldUser = [
            'user_id' => 'user123',
            'name' => 'John Doe',
            'email' => 'john@example.com'];
        $newUser = [
            'user_id' => 'user123',
            'name' => 'John Doe',
            'email' => 'john@example.com'];

        $result = $this->comparator->compareUserInfo($oldUser, $newUser);

        $this->assertSame([], $result['added']);
        $this->assertSame([], $result['removed']);
        $this->assertSame([], $result['changed']);
    }

    public function testCompareUserInfoWithAddedFields(): void
    {
        $oldUser = [
            'user_id' => 'user123',
            'name' => 'John Doe'];
        $newUser = [
            'user_id' => 'user123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'mobile' => '1234567890'];

        $result = $this->comparator->compareUserInfo($oldUser, $newUser);

        $this->assertSame(['email' => 'john@example.com', 'mobile' => '1234567890'], $result['added']);
        $this->assertSame([], $result['removed']);
        $this->assertSame([], $result['changed']);
    }

    public function testCompareUserInfoWithRemovedFields(): void
    {
        $oldUser = [
            'user_id' => 'user123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'mobile' => '1234567890'];
        $newUser = [
            'user_id' => 'user123',
            'name' => 'John Doe'];

        $result = $this->comparator->compareUserInfo($oldUser, $newUser);

        $this->assertSame([], $result['added']);
        $this->assertSame(['email' => 'john@example.com', 'mobile' => '1234567890'], $result['removed']);
        $this->assertSame([], $result['changed']);
    }

    public function testCompareUserInfoWithChangedFields(): void
    {
        $oldUser = [
            'user_id' => 'user123',
            'name' => 'John Doe',
            'email' => 'john@example.com'];
        $newUser = [
            'user_id' => 'user123',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'];

        $result = $this->comparator->compareUserInfo($oldUser, $newUser);

        $this->assertSame([], $result['added']);
        $this->assertSame([], $result['removed']);
        $expected = [
            'name' => ['old' => 'John Doe', 'new' => 'Jane Doe'],
            'email' => ['old' => 'john@example.com', 'new' => 'jane@example.com']];
        $this->assertSame($expected, $result['changed']);
    }

    public function testCompareUserInfoWithMixedChanges(): void
    {
        $oldUser = [
            'user_id' => 'user123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'department' => 'IT'];
        $newUser = [
            'user_id' => 'user123',
            'name' => 'Jane Doe',
            'mobile' => '1234567890',
            'department' => 'HR'];

        $result = $this->comparator->compareUserInfo($oldUser, $newUser);

        $this->assertSame(['mobile' => '1234567890'], $result['added']);
        $this->assertSame(['email' => 'john@example.com'], $result['removed']);
        $expected = [
            'name' => ['old' => 'John Doe', 'new' => 'Jane Doe'],
            'department' => ['old' => 'IT', 'new' => 'HR']];
        $this->assertSame($expected, $result['changed']);
    }

    public function testCompareUserInfoWithEmptyArrays(): void
    {
        $result = $this->comparator->compareUserInfo([], []);

        $this->assertSame([], $result['added']);
        $this->assertSame([], $result['removed']);
        $this->assertSame([], $result['changed']);
    }

    public function testGenerateUserSummaryWithCompleteInfo(): void
    {
        $user = [
            'user_id' => 'user123',
            'open_id' => 'open123',
            'email' => 'john@example.com',
            'enterprise_email' => 'john@company.com',
            'mobile' => '1234567890',
            'mobile_visible' => true,
            'department_ids' => ['dept1', 'dept2'],
            'job_title' => 'Developer'];

        $expectedStatus = [
            'status_text' => '正常',
            'is_active' => true];

        $this->statusChecker->expects($this->once())
            ->method('checkUserStatus')
            ->with($user)
            ->willReturn($expectedStatus)
        ;

        $this->formatter->expects($this->once())
            ->method('formatDisplayName')
            ->with($user)
            ->willReturn('John Doe')
        ;

        $this->formatter->expects($this->once())
            ->method('getAvatarUrl')
            ->with($user, '72')
            ->willReturn('https://example.com/avatar.jpg')
        ;

        $result = $this->comparator->generateUserSummary($user);

        $expected = [
            'id' => 'user123',
            'name' => 'John Doe',
            'avatar' => 'https://example.com/avatar.jpg',
            'email' => 'john@company.com',
            'mobile' => '1234567890',
            'department' => 'dept1',
            'position' => 'Developer',
            'status' => '正常',
            'is_active' => true];

        $this->assertSame($expected, $result);
    }

    public function testGenerateUserSummaryWithMinimalInfo(): void
    {
        $user = ['open_id' => 'open123'];

        $expectedStatus = [
            'status_text' => '未知',
            'is_active' => false];

        $this->statusChecker->expects($this->once())
            ->method('checkUserStatus')
            ->with($user)
            ->willReturn($expectedStatus)
        ;

        $this->formatter->expects($this->once())
            ->method('formatDisplayName')
            ->with($user)
            ->willReturn('未知用户')
        ;

        $this->formatter->expects($this->once())
            ->method('getAvatarUrl')
            ->with($user, '72')
            ->willReturn(null)
        ;

        $result = $this->comparator->generateUserSummary($user);

        $expected = [
            'id' => 'open123',
            'name' => '未知用户',
            'avatar' => null,
            'email' => '',
            'mobile' => '',
            'department' => '',
            'position' => '',
            'status' => '未知',
            'is_active' => false];

        $this->assertSame($expected, $result);
    }

    public function testGenerateUserSummaryWithMobileNotVisible(): void
    {
        $user = [
            'user_id' => 'user123',
            'mobile' => '1234567890',
            'mobile_visible' => false];

        $expectedStatus = [
            'status_text' => '正常',
            'is_active' => true];

        $this->statusChecker->expects($this->once())
            ->method('checkUserStatus')
            ->with($user)
            ->willReturn($expectedStatus)
        ;

        $this->formatter->expects($this->once())
            ->method('formatDisplayName')
            ->with($user)
            ->willReturn('John Doe')
        ;

        $this->formatter->expects($this->once())
            ->method('getAvatarUrl')
            ->with($user, '72')
            ->willReturn(null)
        ;

        $result = $this->comparator->generateUserSummary($user);

        $this->assertSame('', $result['mobile']);
    }

    public function testGenerateUserSummaryPrefersEnterpriseEmail(): void
    {
        $user = [
            'user_id' => 'user123',
            'email' => 'john@personal.com',
            'enterprise_email' => 'john@company.com'];

        $expectedStatus = [
            'status_text' => '正常',
            'is_active' => true];

        $this->statusChecker->expects($this->once())
            ->method('checkUserStatus')
            ->with($user)
            ->willReturn($expectedStatus)
        ;

        $this->formatter->expects($this->once())
            ->method('formatDisplayName')
            ->with($user)
            ->willReturn('John Doe')
        ;

        $this->formatter->expects($this->once())
            ->method('getAvatarUrl')
            ->with($user, '72')
            ->willReturn(null)
        ;

        $result = $this->comparator->generateUserSummary($user);

        $this->assertSame('john@company.com', $result['email']);
    }

    public function testGenerateUserSummaryUsesFirstDepartment(): void
    {
        $user = [
            'user_id' => 'user123',
            'department_ids' => ['dept1', 'dept2', 'dept3']];

        $expectedStatus = [
            'status_text' => '正常',
            'is_active' => true];

        $this->statusChecker->expects($this->once())
            ->method('checkUserStatus')
            ->with($user)
            ->willReturn($expectedStatus)
        ;

        $this->formatter->expects($this->once())
            ->method('formatDisplayName')
            ->with($user)
            ->willReturn('John Doe')
        ;

        $this->formatter->expects($this->once())
            ->method('getAvatarUrl')
            ->with($user, '72')
            ->willReturn(null)
        ;

        $result = $this->comparator->generateUserSummary($user);

        $this->assertSame('dept1', $result['department']);
    }

    protected function prepareMockServices(): void
    {
        $this->statusChecker = self::createMock(UserStatusChecker::class);
        $this->formatter = self::createMock(UserFormatter::class);
        self::getContainer()->set(UserStatusChecker::class, $this->statusChecker);
        self::getContainer()->set(UserFormatter::class, $this->formatter);
    }

    protected function onSetUp(): void
    {
        // 获取服务实例，不再设置 mock
        $this->comparator = self::getService(UserComparator::class);
        // 创建 mock 对象
        $this->statusChecker = self::createMock(UserStatusChecker::class);
        self::getContainer()->set(UserStatusChecker::class, $this->statusChecker);
        $this->formatter = self::createMock(UserFormatter::class);
        self::getContainer()->set(UserFormatter::class, $this->formatter);
        $this->comparator = self::getService(UserComparator::class);
    }
}
