<?php

declare(strict_types=1);

namespace Tourze\LarkAppBotBundle\Tests\Service\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\LarkAppBotBundle\Service\User\UserStatusChecker;

/**
 * @internal
 */
#[CoversClass(UserStatusChecker::class)]
final class UserStatusCheckerTest extends TestCase
{
    private UserStatusChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new UserStatusChecker();
    }

    public function testCheckUserStatusActive(): void
    {
        $user = [
            'status' => [
                'is_activated' => true,
                'is_resigned' => false],
            'is_frozen' => false];

        $result = $this->checker->checkUserStatus($user);

        $this->assertTrue($result['is_active']);
        $this->assertFalse($result['is_frozen']);
        $this->assertFalse($result['is_resigned']);
        $this->assertSame('正常', $result['status_text']);
    }

    public function testCheckUserStatusFrozen(): void
    {
        $user = [
            'status' => [
                'is_activated' => true,
                'is_resigned' => false],
            'is_frozen' => true];

        $result = $this->checker->checkUserStatus($user);

        $this->assertFalse($result['is_active']);
        $this->assertTrue($result['is_frozen']);
        $this->assertFalse($result['is_resigned']);
        $this->assertSame('已冻结', $result['status_text']);
    }

    public function testCheckUserStatusResigned(): void
    {
        $user = [
            'status' => [
                'is_activated' => true,
                'is_resigned' => true],
            'is_frozen' => false];

        $result = $this->checker->checkUserStatus($user);

        $this->assertFalse($result['is_active']);
        $this->assertFalse($result['is_frozen']);
        $this->assertTrue($result['is_resigned']);
        $this->assertSame('已离职', $result['status_text']);
    }

    public function testCheckUserStatusExited(): void
    {
        $user = [
            'status' => [
                'is_activated' => true,
                'is_resigned' => false,
                'is_exited' => true],
            'is_frozen' => false];

        $result = $this->checker->checkUserStatus($user);

        $this->assertFalse($result['is_active']);
        $this->assertSame('已退出', $result['status_text']);
    }

    public function testCheckUserStatusUnjoin(): void
    {
        $user = [
            'status' => [
                'is_activated' => true,
                'is_resigned' => false,
                'is_unjoin' => true],
            'is_frozen' => false];

        $result = $this->checker->checkUserStatus($user);

        $this->assertFalse($result['is_active']);
        $this->assertSame('未加入', $result['status_text']);
    }

    public function testCheckUserStatusNotActivated(): void
    {
        $user = [
            'status' => [
                'is_activated' => false,
                'is_resigned' => false],
            'is_frozen' => false];

        $result = $this->checker->checkUserStatus($user);

        $this->assertFalse($result['is_active']);
        $this->assertSame('未激活', $result['status_text']);
    }

    public function testCheckUserStatusEmptyData(): void
    {
        $user = [];

        $result = $this->checker->checkUserStatus($user);

        $this->assertTrue($result['is_active']);
        $this->assertFalse($result['is_frozen']);
        $this->assertFalse($result['is_resigned']);
        $this->assertSame('正常', $result['status_text']);
    }

    public function testCheckUserStatusPriorityOrder(): void
    {
        // 测试状态优先级: 冻结 > 离职 > 退出 > 未加入 > 未激活
        $user = [
            'status' => [
                'is_activated' => false,
                'is_resigned' => true,
                'is_exited' => true,
                'is_unjoin' => true],
            'is_frozen' => true];

        $result = $this->checker->checkUserStatus($user);

        $this->assertSame('已冻结', $result['status_text']);
    }
}
